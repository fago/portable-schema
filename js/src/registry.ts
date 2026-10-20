import { readdirSync, readFileSync, statSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import { isObject, type Json, type JsonObject } from "./json.js";
import * as Pointer from "./pointer.js";

export type Schema = JsonObject | boolean;

/**
 * Schemas and catalogs by $id. Values follow the JSON data model.
 */
export class Registry {
  static readonly TYPES_DIRECTORY = fileURLToPath(new URL("../../types/v1", import.meta.url));

  private schemas = new Map<string, Schema>();

  /** A registry with the type library. */
  static withTypes(): Registry {
    return new Registry().addDirectory(Registry.TYPES_DIRECTORY);
  }

  /** Adds a schema under `id`, or under its own $id. */
  add(schema: Schema, id?: string): this {
    id ??= isObject(schema) && typeof schema["$id"] === "string" ? schema["$id"] : undefined;
    if (id === undefined) throw new Error("A schema without $id needs an id.");
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

const decode = (fragment: string): string => {
  try {
    return decodeURIComponent(fragment);
  } catch {
    return fragment;
  }
};

export type { Json };
