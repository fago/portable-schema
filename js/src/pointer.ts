import { compareStrings, isObject, type Json } from "./json.js";

/**
 * JSON Pointers (RFC 6901) and the core form of Relative JSON Pointers.
 */
export const RELATIVE_PATTERN = /^(0|[1-9][0-9]*)(#|(\/([^~/]|~[01])*)*)$/u;

export const escape = (segment: string): string => segment.replaceAll("~", "~0").replaceAll("/", "~1");

export const unescape = (segment: string): string => segment.replaceAll("~1", "/").replaceAll("~0", "~");

/** A pointer as URI fragment: every byte outside RFC 3986's fragment characters percent-encoded. */
export const toFragment = (pointer: string): string =>
  [...new TextEncoder().encode(pointer)]
    .map((b) => /[A-Za-z0-9\-._~!$&'()*+,;=:@/?]/u.test(String.fromCharCode(b)) && b < 128 ? String.fromCharCode(b) : "%" + b.toString(16).toUpperCase().padStart(2, "0"))
    .join("");

export const fromSegments = (segments: (string | number)[]): string =>
  segments.map((segment) => "/" + escape(String(segment))).join("");

export const segments = (pointer: string): string[] =>
  pointer === "" ? [] : pointer.split("/").slice(1).map(unescape);

export const append = (pointer: string, segment: string | number): string => pointer + "/" + escape(String(segment));

/** Whether `pointer` equals `base` or lies below it. */
export const isWithin = (pointer: string, base: string): boolean =>
  base === "" || pointer === base || pointer.startsWith(base + "/");

/** Looks up a value. Returns [found, value]. */
export const get = (data: unknown, pointer: string): [boolean, unknown] => {
  for (const segment of segments(pointer)) {
    if (isObject(data) && Object.hasOwn(data, segment)) {
      data = data[segment];
    } else if (Array.isArray(data) && /^(0|[1-9][0-9]*)$/.test(segment) && Number(segment) < data.length) {
      data = data[Number(segment)];
    } else {
      return [false, null];
    }
  }
  return [true, data];
};

/** Resolves a Relative JSON Pointer from `location` in `root`. Returns [found, value]. */
export const resolveRelative = (relative: string, location: string, root: unknown): [boolean, unknown] => {
  const m = RELATIVE_PATTERN.exec(relative);
  if (!m) return [false, null];
  let parts = segments(location);
  const up = Number(m[1]);
  if (up > parts.length) return [false, null];
  parts = parts.slice(0, parts.length - up);
  if (m[2] === "#") {
    if (parts.length === 0) return [false, null];
    const key = parts[parts.length - 1]!;
    const [, parent] = get(root, fromSegments(parts.slice(0, -1)));
    return [true, Array.isArray(parent) ? Number(key) : key];
  }
  return get(root, fromSegments(parts) + m[2]);
};

/** Compares pointers segment by segment: array indexes numerically, other segments by code point. */
export const compare = (a: string, b: string): number => {
  const sa = segments(a);
  const sb = segments(b);
  for (let i = 0; i < Math.min(sa.length, sb.length); i++) {
    const x = sa[i]!;
    const y = sb[i]!;
    if (x === y) continue;
    if (/^[0-9]+$/.test(x) && /^[0-9]+$/.test(y)) return Math.sign(Number(x) - Number(y));
    return compareStrings(x, y);
  }
  return Math.sign(sa.length - sb.length);
};

export type { Json };
