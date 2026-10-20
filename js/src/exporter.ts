import { isObject, type JsonObject } from "./json.js";
import type { Registry } from "./registry.js";

export const DRAFT_2020_12 = "https://json-schema.org/draft/2020-12/schema";

export const DRAFT_04 = "http://json-schema.org/draft-04/schema#";

/**
 * Formats kept in draft-04 exports: the ones draft-04 defines, and uri-reference, which SDC uses and
 * justinrainbow/json-schema checks.
 */
const DRAFT_04_FORMATS = ["date-time", "email", "hostname", "ipv4", "ipv6", "uri", "uri-reference"];

type Value = unknown;

/**
 * Exports Portable Schemas as standard-only JSON Schema for tools that can't resolve the registry, e.g. agent tool
 * definitions or Drupal SDC.
 *
 * Lost in the export: the added keywords (`portable-typeBy`, `portable-check`, …) and `x-portable-*` annotations.
 */
export class Exporter {
  constructor(private readonly registry: Registry) {}

  /** A self-contained JSON Schema 2020-12: every `$ref` merged into the schema that uses it. */
  inline(id: string): JsonObject {
    return { $schema: DRAFT_2020_12, ...(this.resolve(id, []) as JsonObject) };
  }

  /** A self-contained JSON Schema draft-04, as Drupal core's SDC metadata uses. */
  draft04(id: string): JsonObject {
    return { $schema: DRAFT_04, ...(toDraft04(this.resolve(id, [])) as JsonObject) };
  }

  /** @param seen references being resolved, to refuse cycles */
  private resolve(uri: string, seen: string[]): Value {
    if (seen.includes(uri)) throw new Error(`Cannot inline the recursive reference ${uri}`);
    const [found, schema] = this.registry.resolve(uri.includes("#") ? uri : uri + "#");
    if (!found) throw new Error(`Unknown schema ${uri}`);
    const resolved = this.schema(schema, uri.split("#")[0]!, [...seen, uri]);
    if (isObject(resolved) && uri.startsWith("portable:")) {
      // The type library's own descriptions don't help consumers of a concrete schema.
      const { title: _title, description: _description, ...rest } = resolved;
      return rest;
    }
    return resolved;
  }

  private schema(schema: Value, base: string, seen: string[]): Value {
    if (!isObject(schema)) return schema;
    const use: JsonObject = {};
    for (const [key, value] of Object.entries(schema)) {
      if (["$ref", "$schema", "$id"].includes(key) || key.startsWith("portable-") || key.startsWith("x-portable-")) continue;
      if (["properties", "patternProperties", "$defs", "dependentSchemas"].includes(key)) {
        use[key] = this.map(value as JsonObject, base, seen);
      } else if (["items", "additionalProperties", "unevaluatedProperties", "unevaluatedItems", "not", "if", "then", "else", "contains", "propertyNames"].includes(key)) {
        use[key] = this.schema(value, base, seen) as JsonObject;
      } else if (["allOf", "anyOf", "oneOf", "prefixItems"].includes(key)) {
        use[key] = (value as Value[]).map((s) => this.schema(s, base, seen)) as JsonObject[];
      } else {
        use[key] = value as JsonObject;
      }
    }
    if (typeof schema["$ref"] !== "string") return use;
    const ref = schema["$ref"];
    const target = this.resolve(ref.startsWith("#") ? base + ref : ref, seen);
    if (mergeable(target, use)) return merge(target, use);
    const { allOf = [], ...rest } = use;
    return { allOf: [target, ...(allOf as Value[])], ...rest } as JsonObject;
  }

  private map(schemas: JsonObject, base: string, seen: string[]): JsonObject {
    return Object.fromEntries(Object.entries(schemas).map(([name, schema]) => [name, this.schema(schema, base, seen)])) as JsonObject;
  }
}

/** Keywords whose result depends on the keywords listed, in the same schema. */
const DEPENDENT: Record<string, string[]> = {
  additionalProperties: ["properties", "patternProperties", "additionalProperties", "unevaluatedProperties", "allOf", "anyOf", "oneOf", "if", "then", "else", "dependentSchemas"],
  unevaluatedProperties: ["properties", "patternProperties", "additionalProperties", "unevaluatedProperties", "allOf", "anyOf", "oneOf", "if", "then", "else", "dependentSchemas"],
  items: ["prefixItems", "items", "unevaluatedItems", "contains", "allOf", "anyOf", "oneOf", "if", "then", "else"],
  unevaluatedItems: ["prefixItems", "items", "unevaluatedItems", "contains", "allOf", "anyOf", "oneOf", "if", "then", "else"],
};

/** Keywords that only annotate: the using schema's value wins. */
const ANNOTATIONS = ["title", "description", "$comment", "examples", "default", "deprecated", "readOnly", "writeOnly"];

/** Bounds that merge to the stricter value. */
const BOUNDS: Record<string, (a: number, b: number) => number> = {
  minimum: Math.max, exclusiveMinimum: Math.max, minLength: Math.max, minItems: Math.max, minProperties: Math.max,
  maximum: Math.min, exclusiveMaximum: Math.min, maxLength: Math.min, maxItems: Math.min, maxProperties: Math.min,
};

/**
 * Whether a referenced schema and the keywords next to the `$ref` can be one schema with the same meaning:
 * no keyword in both, except `required`, bounds, annotations and `properties` (recursively), and no keyword
 * that depends on the properties or items the other declares. Otherwise the export uses `allOf`.
 */
const mergeable = (target: Value, use: JsonObject): boolean => {
  if (target === true) return true;
  if (!isObject(target)) return false;
  for (const [key, value] of Object.entries(use)) {
    if (!(key in target) || key === "required" || key in BOUNDS || ANNOTATIONS.includes(key) || JSON.stringify(target[key]) === JSON.stringify(value)) continue;
    if (key !== "properties") return false;
    const properties = target["properties"] as JsonObject;
    for (const [name, schema] of Object.entries(value as JsonObject)) {
      if (name in properties && !(isObject(schema) && mergeable(properties[name], schema))) return false;
    }
  }
  // The using schema's unevaluated* keywords see the referenced schema's results anyway.
  const { unevaluatedProperties: _p, unevaluatedItems: _i, ...useDependent } = DEPENDENT;
  const checks: [JsonObject, Record<string, string[]>, JsonObject][] = [[target, DEPENDENT, use], [use, useDependent, target]];
  return checks.every(([schema, dependent, other]) =>
    Object.entries(dependent).every(([keyword, keywords]) => !(keyword in schema) || !keywords.some((k) => k in other)));
};

/**
 * The keywords next to the `$ref` added to the referenced schema: `required` lists join, the stricter bound
 * wins, properties merge, annotations of the using schema win.
 */
const merge = (target: Value, use: JsonObject): Value => {
  if (target === true) return use;
  const out: Record<string, Value> = { ...(target as JsonObject) };
  for (const [key, value] of Object.entries(use)) {
    if (!(key in out)) {
      out[key] = value;
    } else if (key === "required") {
      out[key] = [...new Set([...(out[key] as string[]), ...(value as string[])])];
    } else if (key in BOUNDS) {
      out[key] = BOUNDS[key]!(out[key] as number, value as number);
    } else if (key !== "properties") {
      out[key] = value;
    } else {
      const properties: Record<string, Value> = { ...(out[key] as JsonObject) };
      for (const [name, schema] of Object.entries(value as JsonObject)) {
        properties[name] = name in properties ? merge(properties[name], schema as JsonObject) : schema;
      }
      out[key] = properties;
    }
  }
  return out;
};

/** Draft-04 keywords kept as they are; everything else not converted is dropped. */
const DRAFT_04_KEYWORDS = ["title", "description", "default", "type", "enum", "multipleOf", "maxLength", "minLength", "pattern", "maxItems", "minItems", "uniqueItems", "maxProperties", "minProperties", "required"];

/** Keywords that evaluate properties in place, which draft-04's additionalProperties doesn't see. */
const IN_PLACE = ["allOf", "anyOf", "oneOf", "if", "then", "else", "dependentSchemas", "$ref"];

/** @param booleanAllowed whether draft-04 allows a boolean here (additionalProperties, additionalItems) */
const toDraft04 = (schema: Value, booleanAllowed = false): Value => {
  if (typeof schema === "boolean") return booleanAllowed ? schema : schema ? {} : { not: {} };
  if (!isObject(schema)) return schema;
  const inPlace = IN_PLACE.filter((k) => k in schema);
  if ("unevaluatedProperties" in schema && inPlace.length > 0) {
    throw new Error("Draft-04 has no unevaluatedProperties: additionalProperties would not see the properties of " + inPlace.join(", "));
  }
  const out: Record<string, Value> = {};
  for (const [key, value] of Object.entries(schema)) {
    switch (key) {
      case "const":
        out["enum"] = [value];
        break;
      case "minimum":
      case "maximum":
      case "exclusiveMinimum":
      case "exclusiveMaximum":
        bound(out, schema, key.endsWith("inimum") ? "minimum" : "maximum");
        break;
      case "unevaluatedProperties":
      case "additionalProperties":
        out["additionalProperties"] = toDraft04(value, true);
        break;
      case "dependentRequired":
      case "dependentSchemas": {
        const dependencies = key === "dependentRequired" ? value as JsonObject : mapDraft04(value as JsonObject);
        for (const [name, dependency] of Object.entries(dependencies)) {
          const existing = (out["dependencies"] ??= {}) as Record<string, Value>;
          if (name in existing) throw new Error(`Draft-04 dependencies can't hold both dependentRequired and dependentSchemas for ${name}`);
          existing[name] = dependency;
        }
        break;
      }
      case "format":
        if (DRAFT_04_FORMATS.includes(value as string)) out["format"] = value;
        break;
      case "properties":
      case "patternProperties":
        out[key] = mapDraft04(value as JsonObject);
        break;
      case "prefixItems":
        out["items"] = (value as Value[]).map((s) => toDraft04(s));
        if ("items" in schema) out["additionalItems"] = toDraft04(schema["items"], true);
        break;
      case "items":
        if (!("prefixItems" in schema)) out["items"] = toDraft04(value);
        break;
      case "not":
        out["not"] = toDraft04(value);
        break;
      case "allOf":
      case "anyOf":
      case "oneOf":
        out[key] = (value as Value[]).map((s) => toDraft04(s));
        break;
      default:
        if (DRAFT_04_KEYWORDS.includes(key)) out[key] = value;
    }
  }
  return out;
};

/** The stricter of `minimum` and `exclusiveMinimum` (or the maximums), as draft-04's boolean exclusive form. */
const bound = (out: Record<string, Value>, schema: JsonObject, keyword: "minimum" | "maximum"): void => {
  if (keyword in out) return;
  const exclusive = keyword === "minimum" ? "exclusiveMinimum" : "exclusiveMaximum";
  const inclusive = schema[keyword] as number | undefined;
  const strict = schema[exclusive] as number | undefined;
  if (strict !== undefined && (inclusive === undefined || (keyword === "minimum" ? strict >= inclusive : strict <= inclusive))) {
    out[keyword] = strict;
    out[exclusive] = true;
  } else {
    out[keyword] = inclusive;
  }
};

const mapDraft04 = (schemas: JsonObject): JsonObject =>
  Object.fromEntries(Object.entries(schemas).map(([name, schema]) => [name, toDraft04(schema)])) as JsonObject;
