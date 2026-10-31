import * as Pointer from "./pointer.js";

/** Input turned into the JSON data model (spec/normalization.md). */
export type Normalized = {
  data: unknown;
  /** The host type name per location of a value the model can't hold; the value itself is null in `data`. */
  unrepresentable: Map<string, string>;
};

/**
 * Turns JS values into the JSON data model, as `JSON.parse` would produce them: `undefined` properties are absent,
 * `undefined` items are null, and anything `JSON.parse` cannot produce (Date, Map, NaN, BigInt, functions, …) is a
 * `type` error.
 */
export const normalize = (value: unknown): Normalized => {
  const unrepresentable = new Map<string, string>();
  return { data: convert(value, "", unrepresentable), unrepresentable };
};

const convert = (value: unknown, location: string, unrepresentable: Map<string, string>): unknown => {
  const name = unrepresentableName(value);
  if (name !== null) {
    unrepresentable.set(location, name);
    return null;
  }
  if (Array.isArray(value)) {
    return value.map((item, i) => item === undefined ? null : convert(item, Pointer.append(location, i), unrepresentable));
  }
  if (typeof value === "object" && value !== null) {
    const object: Record<string, unknown> = {};
    for (const [key, property] of Object.entries(value)) {
      if (property !== undefined) object[key] = convert(property, Pointer.append(location, key), unrepresentable);
    }
    return object;
  }
  return value;
};

/** The name of a value's host type when the JSON data model can't hold it, else null. */
const unrepresentableName = (value: unknown): string | null => {
  switch (typeof value) {
    case "number": return Number.isNaN(value) ? "nan" : Number.isFinite(value) ? null : "infinity";
    case "object": {
      if (value === null || Array.isArray(value)) return null;
      const prototype = Object.getPrototypeOf(value) as object | null;
      if (prototype === null || prototype === Object.prototype) return null;
      return ((value as object).constructor?.name ?? "object").toLowerCase();
    }
    case "string":
    case "boolean":
      return null;
    default:
      return typeof value;
  }
};
