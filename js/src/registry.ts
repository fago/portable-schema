import { readdirSync, readFileSync, statSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import * as Formats from "./formats.js";
import { isObject, type Json, type JsonObject } from "./json.js";
import * as Pointer from "./pointer.js";

export type Schema = JsonObject | boolean;

/**
 * Schemas and catalogs by $id. Values follow the JSON data model.
 */
export class Registry {
  static readonly TYPES_DIRECTORY = fileURLToPath(new URL("../../types/v1", import.meta.url));

  /** The meta-schemas the validators load: Hyperjump keeps them in one global store, so a registry id must not be one. */
  static readonly META_SCHEMA_IDS: readonly string[] = [
    "https://json-schema.org/draft/2020-12/schema",
    ...["core", "applicator", "validation", "meta-data", "format-annotation", "format-assertion", "content", "unevaluated"]
      .map((name) => `https://json-schema.org/draft/2020-12/meta/${name}`),
    "https://json-schema.org/v1",
    ...["core", "applicator", "validation", "meta-data", "format", "content", "unevaluated"]
      .map((name) => `https://json-schema.org/v1/meta/${name}`),
    "https://portable-schema.dev/v1/schema",
    "https://portable-schema.dev/v1/vocab",
    "https://portable-schema.dev/v1/load",
  ];

  private schemas = new Map<string, Schema>();

  /** Ids by their normalized form. */
  private normalized = new Map<string, string>();

  /** A registry with the type library. */
  static withTypes(): Registry {
    return new Registry().addDirectory(Registry.TYPES_DIRECTORY);
  }

  /**
   * Adds a schema under `id`, or under its own $id.
   *
   * The id must be an absolute URI without a fragment, dot segments or the `file:` scheme, must not be a meta-schema
   * id, and must differ from every other id after percent-decoding and with the scheme and authority in lower case: Hyperjump normalizes ids,
   * so `a%2Bb` and `a+b` would be one schema. The schema is one resource: its own `$id`, if any, is `id`, and no value
   * below its root has a `$id` or `$schema`.
   */
  add(schema: Schema, id?: string): this {
    id ??= isObject(schema) && typeof schema["$id"] === "string" ? schema["$id"] : undefined;
    if (id === undefined) throw new Error("A schema without $id needs an id.");
    const key = normalize(id);
    if (!Formats.check("uri", id) || id.includes("#") || key.startsWith("file:") || /[:/]\.{1,2}(\/|$)/u.test(normalize(id.split("?")[0]!))) {
      throw new Error(`The schema id ${id} is not an absolute URI without a fragment, dot segments or file: scheme.`);
    }
    if (Registry.META_SCHEMA_IDS.some((meta) => normalize(meta) === key)) throw new Error(`The schema id ${id} is a meta-schema id.`);
    if (isObject(schema) && schema["$id"] !== undefined && schema["$id"] !== id) throw new Error(`The schema ${id} has the $id ${JSON.stringify(schema["$id"])}.`);
    const nested = embedded(schema, "");
    if (nested !== null) throw new Error(`The schema ${id} has $id or $schema below its root, at ${nested}.`);
    const other = this.normalized.get(key) ?? id;
    if (other !== id) throw new Error(`The schema ids ${other} and ${id} are the same after normalization.`);
    this.normalized.set(key, id);
    this.schemas.set(id, schema);
    return this;
  }

  /** Adds every *.json file in `directory`, recursively, by its $id. */
  addDirectory(directory: string): this {
    const paths: string[] = [];
    const walk = (dir: string): void => {
      for (const name of readdirSync(dir)) {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) walk(path);
        else if (name.endsWith(".json")) paths.push(path);
      }
    };
    walk(directory);
    for (const path of paths.sort()) {
      this.add(JSON.parse(readFileSync(path, "utf8")) as Schema);
    }
    return this;
  }

  /** A deep copy: changes to either registry don't reach the other. */
  copy(): Registry {
    const copy = new Registry();
    copy.schemas = new Map([...this.schemas].map(([id, schema]) => [id, structuredClone(schema)]));
    copy.normalized = new Map(this.normalized);
    return copy;
  }

  has(id: string): boolean {
    return this.schemas.has(id);
  }

  get(id: string): Schema {
    const schema = this.schemas.get(id);
    if (schema === undefined) throw new Error(`Unknown schema ${id}`);
    return schema;
  }

  ids(): string[] {
    return [...this.schemas.keys()];
  }

  /** Looks up a value by an absolute URI with a JSON Pointer fragment. Returns [found, value]. */
  resolve(uri: string): [boolean, unknown] {
    const hash = uri.indexOf("#");
    const id = hash === -1 ? uri : uri.slice(0, hash);
    const fragment = hash === -1 ? "" : uri.slice(hash + 1);
    const schema = this.schemas.get(id);
    if (schema === undefined) return [false, null];
    return Pointer.get(schema, decode(fragment));
  }
}

/** `id` percent-decoded byte by byte, as PHP's rawurldecode() does, with the scheme and authority in lower case. */
const normalize = (id: string): string =>
  id.replace(/^[^:]*:(\/\/[^/?]*)?/u, (prefix) => prefix.toLowerCase())
    .replace(/%([0-9a-f]{2})/giu, (_, hex: string) => String.fromCharCode(parseInt(hex, 16)));

/**
 * The location of the first value below the root with a string `$id` or `$schema`: Hyperjump reads these in every
 * object, `default` and `const` included.
 */
const embedded = (value: unknown, location: string): string | null => {
  if (location !== "" && isObject(value) && (typeof value["$id"] === "string" || typeof value["$schema"] === "string")) return location;
  if (!isObject(value) && !Array.isArray(value)) return null;
  for (const [key, item] of Object.entries(value)) {
    const found = embedded(item, Pointer.append(location, key));
    if (found !== null) return found;
  }
  return null;
};

const decode = (fragment: string): string => {
  try {
    return decodeURIComponent(fragment);
  } catch {
    return fragment;
  }
};

export type { Json };
