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
    let out: Value = {};
    if (typeof schema["$ref"] === "string") {
      const ref = schema["$ref"];
      out = this.resolve(ref.startsWith("#") ? base + ref : ref, seen);
    }
    for (const [key, value] of Object.entries(schema)) {
      if (["$ref", "$schema", "$id"].includes(key) || key.startsWith("portable-") || key.startsWith("x-portable-")) continue;
      let converted: Value;
      if (["properties", "patternProperties", "$defs", "dependentSchemas"].includes(key)) {
        converted = this.map(value as JsonObject, base, seen);
      } else if (["items", "additionalProperties", "unevaluatedProperties", "not", "if", "then", "else", "contains", "propertyNames"].includes(key)) {
        converted = this.schema(value, base, seen);
      } else if (["allOf", "anyOf", "oneOf", "prefixItems"].includes(key)) {
        converted = (value as Value[]).map((s) => this.schema(s, base, seen));
      } else {
        converted = value;
      }
      out = merge(out, key, converted);
    }
    return out;
  }

  private map(schemas: JsonObject, base: string, seen: string[]): JsonObject {
    return Object.fromEntries(Object.entries(schemas).map(([name, schema]) => [name, this.schema(schema, base, seen)])) as JsonObject;
  }
}

/**
 * Adds a keyword of the using schema to the referenced one: properties merge, `required` lists join, other
 * keywords of the using schema win.
 */
const merge = (target: Value, key: string, value: Value): Value => {
  if (!isObject(target)) return target;
  const out: Record<string, Value> = { ...target };
  if (!(key in out)) {
    out[key] = value;
  } else if (key === "required") {
    out[key] = [...new Set([...(out[key] as string[]), ...(value as string[])])];
  } else if (key === "properties") {
    const merged: Record<string, Value> = { ...(out[key] as JsonObject) };
    for (const [name, schema] of Object.entries(value as JsonObject)) {
      const existing = merged[name];
      if (isObject(existing) && isObject(schema)) {
        let property: Value = existing;
        for (const [k, v] of Object.entries(schema)) property = merge(property, k, v);
        merged[name] = property;
      } else {
        merged[name] = schema;
      }
    }
    out[key] = merged;
  } else {
    out[key] = value;
  }
  return out;
};

const toDraft04 = (schema: Value): Value => {
  if (!isObject(schema)) return schema;
  const out: Record<string, Value> = {};
  for (const [key, value] of Object.entries(schema)) {
    switch (key) {
      case "const":
        out["enum"] = [value];
        break;
      case "exclusiveMinimum":
      case "exclusiveMaximum":
        out[key === "exclusiveMinimum" ? "minimum" : "maximum"] = value;
        out[key] = true;
        break;
      case "unevaluatedProperties":
        out["additionalProperties"] = toDraft04(value);
        break;
      case "dependentRequired":
      case "dependentSchemas":
        out["dependencies"] = { ...(out["dependencies"] as JsonObject | undefined), ...mapDraft04(value as JsonObject) };
        break;
      case "format":
        if (DRAFT_04_FORMATS.includes(value as string)) out["format"] = value;
        break;
      case "properties":
      case "patternProperties":
        out[key] = mapDraft04(value as JsonObject);
        break;
      case "items":
      case "additionalProperties":
      case "not":
        out[key] = toDraft04(value);
        break;
      case "allOf":
      case "anyOf":
      case "oneOf":
        out[key] = (value as Value[]).map(toDraft04);
        break;
      case "prefixItems":
      case "contains":
      case "propertyNames":
      case "if":
      case "then":
      case "else":
      case "$defs":
        break;
      default:
        out[key] = value;
    }
  }
  return out;
};

const mapDraft04 = (schemas: JsonObject): JsonObject =>
  Object.fromEntries(Object.entries(schemas).map(([name, schema]) => [name, toDraft04(schema)])) as JsonObject;
