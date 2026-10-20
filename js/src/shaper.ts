import type { CheckRequest } from "./checks.js";
import { equals, isEmpty, isObject, key, length, typeOf, type JsonObject } from "./json.js";
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

/** Keywords that apply only where no other subschema evaluated. */
const UNEVALUATED = ["unevaluatedProperties", "unevaluatedItems"];

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

    const mistyped = new Set(shaped.errors.filter((e) => e.code === "type").map((e) => e.instanceLocation));
    const checked = (code: string, location: string): boolean => !code.startsWith("check.") || !mistyped.has(location);

    let errors = withoutReportedProperties(
      shaped.errors.filter((e) => this.reported(subject(e)) && checked(e.code, e.instanceLocation)),
    );
    errors.sort((a, b) => Pointer.compare(a.instanceLocation, b.instanceLocation) || Pointer.compare(a.keywordLocation, b.keywordLocation));
    errors = unique(errors, (e) => [e.instanceLocation, e.code, e.params]);

    let unchecked = shaped.unchecked.filter((u) => this.reported(u.instanceLocation) && checked(u.code, u.instanceLocation));
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

    const keywords = this.inDocumentOrder(node);
    const typeFailed = keywords.some((k) => k.keyword === "type" && !k.valid);
    for (const keyword of keywords) {
      if (typeFailed && keyword.keyword !== "type") continue;
      shaped.add(this.keyword(keyword, node, location));
    }
    shaped.errors = shaped.errors.filter((e) => !this.missing(subject(e)));
    shaped.unchecked = shaped.unchecked.filter((u) => !this.missing(u.instanceLocation));
    return shaped;
  }

  /** The keywords of a schema in the order of the schema document, since the base libraries evaluate in different orders. */
  private inDocumentOrder(node: SchemaNode): KeywordNode[] {
    const [, schema] = this.registry.resolve(node.uri);
    const keys = isObject(schema) ? Object.keys(schema) : [];
    const position = (k: KeywordNode): number => {
      const i = keys.indexOf(k.keyword);
      return i === -1 ? Number.MAX_SAFE_INTEGER : i;
    };
    return [...node.keywords].sort((a, b) => position(a) - position(b));
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
    const names = isObject(instance) ? Object.keys(instance) : [];
    const known = UNEVALUATED.includes(node.keyword) ? evaluated(parent, node) : new Set<string>();
    node.children.forEach((child, i) => {
      if (known.has(child.instanceLocation)) return;
      if (child.boolean && !child.valid && PROPERTIES.includes(node.keyword)) {
        const segments = Pointer.segments(child.instanceLocation);
        shaped.errors.push(error("unknownProperty", { property: segments[segments.length - 1] }));
      } else if (node.keyword === "propertyNames" && !child.valid) {
        shaped.errors.push(error("propertyNames", { property: names[i] ?? null }));
      } else {
        shaped.add(this.schema(child, this.childLocation(node, child, parent, parentLocation, location)));
      }
    });
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
        const title = this.title(property, node.uri.split("#")[0]!);
        if (title !== null) annotations.push([Pointer.append(location, name), "title", title]);
      }
    }
    return annotations;
  }

  /** The title of a property's schema, or of the schema it references, also for missing properties. */
  private title(schema: unknown, base: string): string | null {
    if (!isObject(schema)) return null;
    if (typeof schema["title"] === "string") return schema["title"];
    const ref = schema["$ref"];
    if (typeof ref !== "string" || ref.startsWith("portable:")) return null;
    const uri = ref.startsWith("#") ? base + ref : ref;
    const [, target] = this.registry.resolve(uri.includes("#") ? uri : uri + "#");
    return isObject(target) && typeof target["title"] === "string" ? target["title"] : null;
  }

  /** The keyword location of a subschema: below its keyword in the same schema, or at the keyword for references. */
  private childLocation(node: KeywordNode, child: SchemaNode, parent: SchemaNode, parentLocation: string, location: string): string {
    if (!REFERENCES.includes(node.keyword) && child.uri.startsWith(parent.uri + "/")) {
      return parentLocation + decodeURIComponent(child.uri.slice(parent.uri.length));
    }
    return location;
  }

  /** With partial and emptyAsMissing, an empty property value counts as missing. */
  private missing(location: string): boolean {
    if (!this.partial || !this.emptyAsMissing || location === "") return false;
    const [, parent] = Pointer.get(this.data, Pointer.fromSegments(Pointer.segments(location).slice(0, -1)));
    const [found, value] = Pointer.get(this.data, location);
    return isObject(parent) && found && isEmpty(value);
  }

  private reported(location: string): boolean {
    return this.locations === null || this.locations.some((base) => Pointer.isWithin(location, base));
  }
}

/** Drops `unknownProperty` errors for properties with another error at or below them: one error per problem. */
const withoutReportedProperties = (errors: ValidationError[]): ValidationError[] =>
  errors.filter((e) => {
    if (e.code !== "unknownProperty") return true;
    const property = subject(e);
    return !errors.some((other) => Pointer.isWithin(other.instanceLocation, property));
  });

/** The location an error is about: the named property for errors at its parent (`required`, `unknownProperty`, …). */
const subject = (e: ValidationError): string =>
  typeof e.params["property"] === "string" ? Pointer.append(e.instanceLocation, e.params["property"]) : e.instanceLocation;

/**
 * Instance locations evaluated by a schema other than `false`, without `skip` and the subschemas that drop their
 * annotations: failed `anyOf`/`oneOf` branches, `if` conditions and `contains` items, and `not`. For the
 * unevaluated* keywords, since the base libraries differ in what a failed subschema evaluated.
 */
const evaluated = (node: SchemaNode, skip: KeywordNode | null = null, locations = new Set<string>()): Set<string> => {
  if (!node.boolean || node.valid) locations.add(node.instanceLocation);
  for (const keyword of node.keywords) {
    if (keyword === skip) continue;
    for (const child of keyword.children) {
      if (!discarded(keyword, child)) evaluated(child, null, locations);
    }
  }
  return locations;
};

const discarded = (keyword: KeywordNode, child: SchemaNode): boolean => {
  switch (keyword.keyword) {
    case "not":
      return true;
    case "anyOf":
    case "oneOf":
    case "contains":
      return !child.valid;
    case "if":
      return !child.valid && child.uri.endsWith("/if");
    default:
      return false;
  }
};

/** The branch whose const discriminator matches, else the one with the fewest errors; ties go to the first. */
const bestBranch = (branches: unknown[], instance: JsonObject, results: Shaped[]): number => {
  for (let i = 0; i < results.length; i++) {
    const branch = branches[i];
    const properties = isObject(branch) ? branch["properties"] : undefined;
    if (!isObject(properties)) continue;
    const discriminators = Object.entries(properties).filter(([, p]) => isObject(p) && Object.hasOwn(p, "const"));
    if (discriminators.length === 0) continue;
    if (discriminators.every(([name, p]) => Object.hasOwn(instance, name) && equals(instance[name], (p as JsonObject)["const"]))) {
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
    const k = key(item);
    if (seen.has(k)) indexes.push(i);
    seen.add(k);
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
