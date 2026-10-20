import type { CheckRequest } from "./checks.js";
import { isEmpty, isObject, length, typeOf, type JsonObject } from "./json.js";
import * as Pointer from "./pointer.js";
import type { Registry } from "./registry.js";
import { Annotations, Result, Unchecked, ValidationError } from "./result.js";
import type { KeywordNode, SchemaNode } from "./trace.js";
import type { TypeBy } from "./type-by.js";

/** Keywords whose subschema is located at the keyword itself. */
const REFERENCES = ["$ref", "$dynamicRef", "portable-typeBy"];

/** Keywords whose subschema results are reported as the keyword's own error. */
const OWN_ERROR = ["not", "contains"];

/** Keywords that report a `false` subschema as an unknown property. */
const PROPERTIES = ["properties", "patternProperties", "additionalProperties", "unevaluatedProperties"];

const ANNOTATIONS_ONLY = ["title", "description", "$comment", "examples", "default", "deprecated", "readOnly", "writeOnly"];

type Annotation = [location: string, name: "title" | "messages" | "allowedTitles", value: unknown];

/** Intermediate shaping result of one subtree. */
class Shaped {
  constructor(
    public errors: ValidationError[] = [],
    public unchecked: Unchecked[] = [],
    public annotations: Annotation[] = [],
  ) {}

  add(other: Shaped): this {
    this.errors.push(...other.errors);
    this.unchecked.push(...other.unchecked);
    this.annotations.push(...other.annotations);
    return this;
  }
}

type MakeError = (code: string, params?: Record<string, unknown>, at?: string, message?: string | null) => ValidationError;

/**
 * Turns the evaluation trace into one error per problem (spec/errors.md, "Shaping"),
 * then applies the reporting options.
 */
export class Shaper {
  constructor(
    private readonly registry: Registry,
    private readonly data: unknown,
    private readonly partial = false,
    private readonly emptyAsMissing = false,
    private readonly locations: string[] | null = null,
  ) {}

  shape(root: SchemaNode): Result {
    const shaped = this.schema(root, "");

    let errors = withoutKnownProperties(shaped.errors).filter((e) => this.reported(e.instanceLocation));
    errors.sort((a, b) => Pointer.compare(a.instanceLocation, b.instanceLocation) || Pointer.compare(a.keywordLocation, b.keywordLocation));
    errors = unique(errors, (e) => [e.instanceLocation, e.code, e.params]);

    let unchecked = shaped.unchecked.filter((u) => this.reported(u.instanceLocation));
    unchecked.sort((a, b) => Pointer.compare(a.instanceLocation, b.instanceLocation));
    unchecked = unique(unchecked, (u) => [u.instanceLocation, u.code, u.reason]);

    const annotations = new Map<string, Annotations>();
    for (const [location, name, value] of shaped.annotations) {
      let target = annotations.get(location);
      if (!target) {
        target = new Annotations();
        annotations.set(location, target);
      }
      if (name === "messages") target.messages = { ...(value as Record<string, string>), ...target.messages };
      else if (name === "title") target.title ??= value as string;
      else target.allowedTitles ??= value as string[];
    }

    return new Result(errors, unchecked, annotations);
  }

  private schema(node: SchemaNode, location: string): Shaped {
    const shaped = new Shaped();
    if (node.boolean) {
      if (!node.valid) shaped.errors.push(new ValidationError(node.instanceLocation, location, node.uri, "false"));
      return shaped;
    }
    shaped.annotations = this.annotations(node);

    const typeFailed = node.keywords.some((k) => k.keyword === "type" && !k.valid);
    for (const keyword of node.keywords) {
      if (typeFailed && keyword.keyword !== "type") continue;
      shaped.add(this.keyword(keyword, node, location));
    }
    shaped.errors = shaped.errors.filter((e) => !this.missing(e.instanceLocation));
    shaped.unchecked = shaped.unchecked.filter((u) => !this.missing(u.instanceLocation));
    return shaped;
  }

  private keyword(node: KeywordNode, parent: SchemaNode, parentLocation: string): Shaped {
    const location = Pointer.append(parentLocation, node.keyword);
    const [, value] = this.registry.resolve(node.absoluteKeywordLocation);
    const [, instance] = Pointer.get(this.data, node.instanceLocation);
    const error: MakeError = (code, params = {}, at, message = null) =>
      new ValidationError(at ?? node.instanceLocation, location, node.absoluteKeywordLocation, code, params, message);

    switch (node.keyword) {
      case "oneOf":
      case "anyOf":
        return this.branches(node, parent, parentLocation, location, value, instance, error);

      case "if": {
        const shaped = new Shaped();
        for (const child of node.children) {
          if (!child.uri.endsWith("/if")) {
            shaped.add(this.schema(child, this.childLocation(node, child, parent, parentLocation, location)));
          }
        }
        return shaped;
      }

      case "portable-typeBy":
        return this.typeBy(node, location, error);

      case "portable-check":
        return this.check(node, error);
    }

    if (OWN_ERROR.includes(node.keyword)) {
      return new Shaped(node.valid ? [] : [error(node.keyword)]);
    }

    if (node.children.length === 0) {
      return new Shaped(node.valid ? [] : this.leaf(node.keyword, value, instance, error));
    }

    const shaped = new Shaped();
    for (const child of node.children) {
      if (child.boolean && !child.valid && PROPERTIES.includes(node.keyword)) {
        const segments = Pointer.segments(child.instanceLocation);
        shaped.errors.push(error("unknownProperty", { property: segments[segments.length - 1] }));
        continue;
      }
      if (node.keyword === "propertyNames" && !child.valid) {
        shaped.errors.push(error("propertyNames", { property: this.propertyName(child, node) }));
        continue;
      }
      shaped.add(this.schema(child, this.childLocation(node, child, parent, parentLocation, location)));
    }
    return shaped;
  }

  /** Errors of a keyword that applies no subschema. */
  private leaf(keyword: string, value: unknown, instance: unknown, error: MakeError): ValidationError[] {
    switch (keyword) {
      case "type":
        return [error("type", { expected: Array.isArray(value) ? value : [value], actual: typeOf(instance) })];
      case "required":
        if (this.partial || !isObject(instance)) return [];
        return (value as string[])
          .filter((property) => !Object.hasOwn(instance, property))
          .map((property) => error("required", { property }));
      case "dependentRequired": {
        if (this.partial || !isObject(instance)) return [];
        const errors: ValidationError[] = [];
        for (const [dependency, properties] of Object.entries(value as Record<string, string[]>)) {
          if (!Object.hasOwn(instance, dependency)) continue;
          for (const property of properties) {
            if (!Object.hasOwn(instance, property)) errors.push(error("dependentRequired", { property, dependency }));
          }
        }
        return errors;
      }
      case "const":
        return [error("const", { expected: value })];
      case "enum":
        return [error("enum", { value: instance, allowed: value })];
      case "minLength":
      case "maxLength":
        return [error(keyword, { limit: value, actual: length(instance as string) })];
      case "minItems":
      case "maxItems":
        return [error(keyword, { limit: value, actual: (instance as unknown[]).length })];
      case "minProperties":
      case "maxProperties":
        return [error(keyword, { limit: value, actual: Object.keys(instance as object).length })];
      case "pattern":
        return [error("pattern", { pattern: value })];
      case "format":
        return [error("format." + String(value))];
      case "minimum":
      case "maximum":
      case "exclusiveMinimum":
      case "exclusiveMaximum":
        return [error(keyword, { limit: value })];
      case "multipleOf":
        return [error("multipleOf", { divisor: value })];
      case "uniqueItems":
        return [error("uniqueItems", { indexes: duplicates(instance as unknown[]) })];
      default:
        return [error(keyword)];
    }
  }

  private branches(node: KeywordNode, parent: SchemaNode, parentLocation: string, location: string, value: unknown, instance: unknown, error: MakeError): Shaped {
    const consts = this.consts(value);
    if (consts !== null) {
      if (node.valid) return new Shaped();
      const titles = (value as JsonObject[]).map((branch) => typeof branch["title"] === "string" ? branch["title"] : null);
      const annotations: Annotation[] = titles.includes(null) ? [] : [[node.instanceLocation, "allowedTitles", titles]];
      return new Shaped([error("enum", { value: instance, allowed: consts })], [], annotations);
    }

    const results = node.children.map((child) => this.schema(child, this.childLocation(node, child, parent, parentLocation, location)));
    const matched = node.children.filter((child) => child.valid).length;

    if (node.valid) {
      const shaped = new Shaped();
      node.children.forEach((child, i) => {
        if (child.valid) shaped.add(results[i]!);
      });
      return shaped;
    }
    if (matched > 0 || !isObject(instance) || results.length === 0) {
      return new Shaped([error(node.keyword, { matched })]);
    }
    return results[bestBranch(value as unknown[], instance, results)]!;
  }

  /** The values of a oneOf whose branches are all consts, else null. */
  private consts(branches: unknown): unknown[] | null {
    if (!Array.isArray(branches) || branches.length === 0) return null;
    const consts: unknown[] = [];
    for (const branch of branches) {
      if (!isObject(branch) || !Object.hasOwn(branch, "const")) return null;
      for (const key of Object.keys(branch)) {
        if (key !== "const" && !ANNOTATIONS_ONLY.includes(key) && !key.startsWith("x-")) return null;
      }
      consts.push(branch["const"]);
    }
    return consts;
  }

  private typeBy(node: KeywordNode, location: string, error: MakeError): Shaped {
    const selection = node.details as TypeBy;
    if (selection.target !== null) {
      const shaped = new Shaped();
      for (const child of node.children) shaped.add(this.schema(child, location));
      return shaped;
    }
    const values = Object.values(selection.values);
    if (selection.missing.length > 0 || (this.partial && this.emptyAsMissing && values.some(isEmpty))) {
      if (this.partial) return new Shaped();
      return new Shaped([error("typeBy.unresolved", { value: null, resolved: null })]);
    }
    return new Shaped([error("typeBy.unresolved", { value: values.length === 1 ? values[0] : selection.values, resolved: selection.resolved })]);
  }

  private check(node: KeywordNode, error: MakeError): Shaped {
    const shaped = new Shaped();
    for (const request of node.details as CheckRequest[]) {
      const result = request.result;
      if (result === null) continue;
      if (result.unchecked !== null) {
        shaped.unchecked.push(new Unchecked(request.location, "check." + request.name, result.unchecked));
      }
      for (const e of result.errors) {
        shaped.errors.push(error(e.code, e.params, request.location, e.message ?? null));
      }
    }
    return shaped;
  }

  private annotations(node: SchemaNode): Annotation[] {
    const [, schema] = this.registry.resolve(node.uri);
    if (!isObject(schema)) return [];
    const location = node.instanceLocation;
    const annotations: Annotation[] = [];
    if (typeof schema["title"] === "string" && !node.uri.startsWith("portable:")) {
      annotations.push([location, "title", schema["title"]]);
    }
    if (isObject(schema["x-portable-messages"])) {
      annotations.push([location, "messages", schema["x-portable-messages"]]);
    }
    if (isObject(schema["properties"])) {
      for (const [name, property] of Object.entries(schema["properties"])) {
        if (isObject(property) && typeof property["title"] === "string") {
          annotations.push([Pointer.append(location, name), "title", property["title"]]);
        }
      }
    }
    return annotations;
  }

  /** The keyword location of a subschema: below its keyword in the same schema, or at the keyword for references. */
  private childLocation(node: KeywordNode, child: SchemaNode, parent: SchemaNode, parentLocation: string, location: string): string {
    if (!REFERENCES.includes(node.keyword) && child.uri.startsWith(parent.uri + "/")) {
      return parentLocation + child.uri.slice(parent.uri.length);
    }
    return location;
  }

  private propertyName(child: SchemaNode, node: KeywordNode): unknown {
    if (child.instanceLocation !== node.instanceLocation) {
      const segments = Pointer.segments(child.instanceLocation);
      return segments[segments.length - 1];
    }
    return null;
  }

  /** With partial and emptyAsMissing, an empty value counts as missing. */
  private missing(location: string): boolean {
    if (!this.partial || !this.emptyAsMissing || location === "") return false;
    const [found, value] = Pointer.get(this.data, location);
    return found && isEmpty(value);
  }

  private reported(location: string): boolean {
    return this.locations === null || this.locations.some((base) => Pointer.isWithin(location, base));
  }
}

/**
 * Drops `unknownProperty` errors for properties that have errors of their own: their schema exists, so the
 * value is the problem.
 */
const withoutKnownProperties = (errors: ValidationError[]): ValidationError[] =>
  errors.filter((error) => {
    if (error.code !== "unknownProperty") return true;
    const property = Pointer.append(error.instanceLocation, String(error.params["property"]));
    return !errors.some((other) => other.code !== "unknownProperty" && Pointer.isWithin(other.instanceLocation, property));
  });

/** The branch whose const discriminator matches, else the one with the fewest errors; ties go to the first. */
const bestBranch = (branches: unknown[], instance: JsonObject, results: Shaped[]): number => {
  for (let i = 0; i < results.length; i++) {
    const branch = branches[i];
    const properties = isObject(branch) ? branch["properties"] : undefined;
    if (!isObject(properties)) continue;
    const discriminators = Object.entries(properties).filter(([, p]) => isObject(p) && Object.hasOwn(p, "const"));
    if (discriminators.length === 0) continue;
    if (discriminators.every(([name, p]) => Object.hasOwn(instance, name) && instance[name] === (p as JsonObject)["const"])) {
      return i;
    }
  }
  let best = 0;
  results.forEach((result, i) => {
    if (result.errors.length < results[best]!.errors.length) best = i;
  });
  return best;
};

/** Indexes of items that equal an earlier item. */
const duplicates = (items: unknown[]): number[] => {
  const seen = new Set<string>();
  const indexes: number[] = [];
  items.forEach((item, i) => {
    const key = JSON.stringify(item);
    if (seen.has(key)) indexes.push(i);
    seen.add(key);
  });
  return indexes;
};

const unique = <T>(items: T[], key: (item: T) => unknown): T[] => {
  const seen = new Set<string>();
  return items.filter((item) => {
    const k = JSON.stringify(key(item));
    if (seen.has(k)) return false;
    seen.add(k);
    return true;
  });
};
