import { isObject, key } from "./json.js";

const ANNOTATIONS_ONLY = ["title", "description", "$comment", "examples", "default", "deprecated", "readOnly", "writeOnly"];

/**
 * The allowed values of a catalog as a hash set (spec/profile.md, "Catalogs"): a scalar `enum`, or a `oneOf` whose
 * branches are distinct `const`s.
 */
export class CatalogSet {
  private constructor(private readonly keys: Set<string>) {}

  static fromEnum(values: unknown): CatalogSet | null {
    if (!Array.isArray(values) || values.some((value) => typeof value === "object" && value !== null)) return null;
    return new CatalogSet(new Set(values.map(key)));
  }

  /** Null unless the branches are distinct consts. */
  static fromOneOf(branches: unknown): CatalogSet | null {
    const values = consts(branches);
    if (values === null) return null;
    const keys = new Set(values.map(key));
    return keys.size === values.length ? new CatalogSet(keys) : null;
  }

  has(value: unknown): boolean {
    return this.keys.has(key(value));
  }
}

/** The values of `oneOf` or `anyOf` branches that are each a `const` with annotations only, else null. */
export const consts = (branches: unknown): unknown[] | null => {
  if (!Array.isArray(branches) || branches.length === 0) return null;
  const values: unknown[] = [];
  for (const branch of branches) {
    if (!isObject(branch) || !Object.hasOwn(branch, "const")) return null;
    for (const name of Object.keys(branch)) {
      if (name !== "const" && !ANNOTATIONS_ONLY.includes(name) && !name.startsWith("x-")) return null;
    }
    values.push(branch["const"]);
  }
  return values;
};
