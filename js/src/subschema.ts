import { isObject, type JsonObject } from "./json.js";
import * as Pointer from "./pointer.js";
import type { Registry } from "./registry.js";
import type { SchemaNode } from "./trace.js";
import { TypeBy } from "./type-by.js";

/** The schemas that apply at an instance location, as keywordLocations, and the keywords skipped for a missing value. */
export type SubschemaResult = { schemaLocations: string[]; unresolved: string[] };

/** One schema of the set: a registry document, a pointer into it, and its keywordLocation from the root schema. */
type Member = { id: string; pointer: string; location: string };

const ANNOTATIONS_ONLY = ["title", "description", "$comment", "examples", "default", "deprecated", "readOnly", "writeOnly"];

/**
 * Walks the schemas that apply at a location of the instance (spec/validation.md, "Subschema at a location").
 * Branches of anyOf, oneOf and if are decided by the trace of a full evaluation of the instance.
 */
export class Subschema {
  /** Verdict per schema URI and instance location, from the trace. */
  private readonly verdicts = new Map<string, boolean>();
  private unresolved: string[] = [];

  constructor(private readonly registry: Registry, private readonly root: unknown, trace: SchemaNode) {
    this.index(trace);
  }

  /** @param location a JSON Pointer, or a dotted path */
  at(id: string, location: string): SubschemaResult {
    this.unresolved = [];
    let instanceLocation = "";
    let found = true;
    let value = this.root;
    let members = this.expand({ id, pointer: "", location: "" }, instanceLocation, found, value);
    for (const token of tokens(location)) {
      const anyMatch = members.some((m) => {
        const schema = this.schema(m);
        return isObject(schema) && this.modes(schema, found, value).object && isObject(schema["properties"]) && Object.hasOwn(schema["properties"], token);
      });
      instanceLocation = Pointer.append(instanceLocation, token);
      const [childFound, childValue] = child(found, value, token);
      const next: Member[] = [];
      const push = (m: Member, ...keys: (string | number)[]): void => {
        next.push(...this.expand(below(m, ...keys), instanceLocation, childFound, childValue));
      };
      for (const m of members) {
        const schema = this.schema(m);
        if (!isObject(schema)) continue;
        const modes = this.modes(schema, found, value);
        if (modes.object) {
          if (isObject(schema["properties"]) && Object.hasOwn(schema["properties"], token)) push(m, "properties", token);
          else if (Object.hasOwn(schema, "additionalProperties")) push(m, "additionalProperties");
          if (!anyMatch && Object.hasOwn(schema, "unevaluatedProperties")) push(m, "unevaluatedProperties");
        }
        if (modes.array && isIndex(token)) {
          if (Array.isArray(schema["prefixItems"]) && Number(token) < schema["prefixItems"].length) push(m, "prefixItems", token);
          else if (Object.hasOwn(schema, "items")) push(m, "items");
        }
      }
      members = next;
      [found, value] = [childFound, childValue];
    }
    return { schemaLocations: members.map((m) => m.location), unresolved: this.unresolved };
  }

  /** The member and the schemas its keywords apply, each expanded in place. */
  private expand(m: Member, instanceLocation: string, found: boolean, value: unknown): Member[] {
    const out = [m];
    const schema = this.schema(m);
    if (!isObject(schema)) return out;
    const ref = schema["$ref"];
    if (typeof ref === "string") {
      const target = this.ref(ref, m.id);
      if (target !== null) out.push(...this.expand({ ...target, location: m.location + "/$ref" }, instanceLocation, found, value));
    }
    if (Array.isArray(schema["allOf"])) {
      schema["allOf"].forEach((_, i) => out.push(...this.expand(below(m, "allOf", i), instanceLocation, found, value)));
    }
    for (const keyword of ["anyOf", "oneOf"]) {
      const branches = schema[keyword];
      if (!Array.isArray(branches) || (keyword === "oneOf" && isCatalog(branches))) continue;
      if (!found) {
        this.unresolved.push(m.location + "/" + keyword);
        continue;
      }
      branches.forEach((_, i) => {
        if (this.valid(m.id, Pointer.fromSegments([...Pointer.segments(m.pointer), keyword, i]), instanceLocation)) {
          out.push(...this.expand(below(m, keyword, i), instanceLocation, found, value));
        }
      });
    }
    if (Object.hasOwn(schema, "if")) {
      if (!found) {
        this.unresolved.push(m.location + "/if");
      } else {
        const branch = this.valid(m.id, Pointer.append(m.pointer, "if"), instanceLocation) ? "then" : "else";
        if (Object.hasOwn(schema, branch)) out.push(...this.expand(below(m, branch), instanceLocation, found, value));
      }
    }
    const typeBy = schema["portable-typeBy"];
    if (isObject(typeBy)) {
      const selection = TypeBy.select(
        typeBy["href"] as string, typeBy["source"] as Record<string, string>, (typeBy["fallback"] as string | undefined) ?? null,
        m.id, instanceLocation, this.root, (uri) => this.registry.has(uri),
      );
      if (selection.missing.length > 0) {
        this.unresolved.push(m.location + "/portable-typeBy");
      } else if (selection.target !== null) {
        out.push(...this.expand({ id: selection.target, pointer: "", location: m.location + "/portable-typeBy" }, instanceLocation, found, value));
      }
    }
    return out;
  }

  /** Whether the member's children are looked up as an object's, an array's, or both: by the value, else by `type`. */
  private modes(schema: JsonObject, found: boolean, value: unknown): { object: boolean; array: boolean } {
    if (found) return { object: isObject(value), array: Array.isArray(value) };
    const type = schema["type"];
    const types = typeof type === "string" ? [type] : Array.isArray(type) ? type : null;
    return { object: types === null || types.includes("object"), array: types === null || types.includes("array") };
  }

  private schema(m: Member): unknown {
    const [found, schema] = Pointer.get(this.registry.get(m.id), m.pointer);
    return found ? schema : null;
  }

  private ref(ref: string, base: string): { id: string; pointer: string } | null {
    const hash = ref.indexOf("#");
    const id = hash === -1 ? ref : ref.slice(0, hash);
    const target = id === "" ? base : id;
    if (!this.registry.has(target)) return null;
    return { id: target, pointer: hash === -1 ? "" : decodeURIComponent(ref.slice(hash + 1)) };
  }

  private valid(id: string, pointer: string, instanceLocation: string): boolean {
    return this.verdicts.get(`${id}#${pointer}\n${instanceLocation}`) ?? false;
  }

  private index(node: SchemaNode): void {
    const hash = node.uri.indexOf("#");
    const id = hash === -1 ? node.uri : node.uri.slice(0, hash);
    const pointer = hash === -1 ? "" : decodeURIComponent(node.uri.slice(hash + 1));
    const key = `${id}#${pointer}\n${node.instanceLocation}`;
    if (!this.verdicts.has(key)) this.verdicts.set(key, node.valid);
    for (const keyword of node.keywords) keyword.children.forEach((c) => this.index(c));
  }
}

/** The reference tokens of a JSON Pointer, or of a dotted path, which is split on `.` without escaping. */
const tokens = (location: string): string[] => {
  if (location === "" || location === "/") return [];
  return location.startsWith("/") ? Pointer.segments(location) : location.split(".");
};

const child = (found: boolean, value: unknown, token: string): [boolean, unknown] => {
  if (found && isObject(value) && Object.hasOwn(value, token)) return [true, value[token]];
  if (found && Array.isArray(value) && isIndex(token) && Number(token) < value.length) return [true, value[Number(token)]];
  return [false, null];
};

const below = (m: Member, ...keys: (string | number)[]): Member => ({
  id: m.id,
  pointer: m.pointer + Pointer.fromSegments(keys),
  location: m.location + Pointer.fromSegments(keys),
});

const isIndex = (token: string): boolean => /^(0|[1-9][0-9]*)$/u.test(token);

/** A oneOf whose branches are all consts is one schema: a catalog. */
const isCatalog = (branches: unknown[]): boolean =>
  branches.length > 0 && branches.every((branch) =>
    isObject(branch) && Object.hasOwn(branch, "const")
    && Object.keys(branch).every((key) => key === "const" || ANNOTATIONS_ONLY.includes(key) || key.startsWith("x-")));
