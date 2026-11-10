import * as Pointer from "./pointer.js";

/** Input turned into the JSON data model (spec/normalization.md). */
export type Normalized = {
  data: unknown;
  /** The locations of values the model can't hold; the value itself is null in `data`. */
  unsupported: Set<string>;
};

/**
 * Turns JS values into the JSON data model, as `JSON.parse` would produce them: `undefined` properties are absent,
 * `undefined` items are null, and anything `JSON.parse` cannot produce (Date, Map, NaN, Infinity, BigInt, functions,
 * symbols, class instances) is unsupported: a `type` error.
 */
export const normalize = (value: unknown): Normalized => {
  const unsupported = new Set<string>();
  return { data: convert(value, "", unsupported), unsupported };
};

const convert = (value: unknown, location: string, unsupported: Set<string>): unknown => {
  if (!supported(value)) {
    unsupported.add(location);
    return null;
  }
  if (Array.isArray(value)) {
    return value.map((item, i) => item === undefined ? null : convert(item, Pointer.append(location, i), unsupported));
  }
  if (typeof value === "object" && value !== null) {
    const object: Record<string, unknown> = {};
    for (const [key, property] of Object.entries(value)) {
      if (property !== undefined) object[key] = convert(property, Pointer.append(location, key), unsupported);
    }
    return object;
  }
  return value;
};

/** Whether the JSON data model can hold the value: `JSON.parse` could produce it. */
const supported = (value: unknown): boolean => {
  switch (typeof value) {
    case "number": return Number.isFinite(value);
    case "object": {
      if (value === null || Array.isArray(value)) return true;
      const prototype = Object.getPrototypeOf(value) as object | null;
      return prototype === null || prototype === Object.prototype;
    }
    case "string":
    case "boolean":
      return true;
    default:
      return false;
  }
};
