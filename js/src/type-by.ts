import { typeOf } from "./json.js";
import * as Pointer from "./pointer.js";
import * as UriTemplate from "./uri-template.js";

/**
 * The type selection of portable-typeBy, shared by the keyword and the shaper.
 */
export class TypeBy {
  /**
   * @param values the source values found, by name
   * @param missing names of source values that are missing or not scalar
   */
  constructor(
    readonly values: Record<string, unknown>,
    readonly missing: string[],
    readonly resolved: string | null,
    readonly target: string | null,
  ) {}

  static select(
    href: string,
    source: Record<string, string>,
    fallback: string | null,
    base: string,
    location: string,
    root: unknown,
    exists: (uri: string) => boolean,
  ): TypeBy {
    const values: Record<string, unknown> = {};
    const missing: string[] = [];
    const strings: Record<string, string> = {};
    for (const [name, pointer] of Object.entries(source)) {
      const [found, value] = Pointer.resolveRelative(pointer, location, root);
      const string = found ? toString(value) : null;
      if (string === null) {
        missing.push(name);
        continue;
      }
      values[name] = value;
      strings[name] = string;
    }
    if (missing.length > 0) return new TypeBy(values, missing, null, null);
    const resolved = resolve(UriTemplate.expand(href, strings), base);
    if (!resolved.includes("#") && exists(resolved)) return new TypeBy(values, [], resolved, resolved);
    const resolvedFallback = fallback === null ? null : resolve(fallback, base);
    return new TypeBy(values, [], resolved, resolvedFallback !== null && exists(resolvedFallback) ? resolvedFallback : null);
  }
}

const toString = (value: unknown): string | null => {
  switch (typeOf(value)) {
    case "string": return value as string;
    case "integer": return String(value);
    case "boolean": return value ? "true" : "false";
    default: return null;
  }
};

export const resolve = (uri: string, base: string): string => {
  if (/^[A-Za-z][A-Za-z0-9+.-]*:/u.test(uri)) return uri;
  return uri.startsWith("#") ? base + uri : uri;
};
