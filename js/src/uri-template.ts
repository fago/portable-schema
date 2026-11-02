/**
 * RFC 6570 expansion of {var} and {+var}.
 */
const UNRESERVED = /[A-Za-z0-9\-._~]/u;
const RESERVED = /[:/?#[\]@!$&'()*+,;=]/u;

export const expand = (template: string, variables: Record<string, string>): string =>
  template.replace(/\{(\+?)([^}]*)\}/gu, (_match, operator: string, list: string) =>
    list.split(",")
      .filter((name) => variables[name] !== undefined)
      .map((name) => encode(variables[name]!, operator !== ""))
      .join(","));

/** A comma-separated list of variable names. */
const NAMES = "[A-Za-z0-9_]+(?:\\.[A-Za-z0-9_]+)*(?:,[A-Za-z0-9_]+(?:\\.[A-Za-z0-9_]+)*)*";

/**
 * Refuses a portable-typeBy href or fallback the prototype can't select by: href expressions beyond {var} and
 * {+var}, braces outside them, any expression in the fallback, and fragments, since targets are registry ids.
 */
export const assertTypeBy = (href: string, fallback: string | null): void => {
  if (!new RegExp(`^(?:[^{}#]|\\{\\+?${NAMES}\\})*$`, "u").test(href)) {
    throw new Error(`portable-typeBy href ${href}: only {var} and {+var} without a fragment are supported`);
  }
  if (fallback !== null && !/^[^{}#]*$/u.test(fallback)) {
    throw new Error(`portable-typeBy fallback ${fallback}: only an id without a fragment is supported`);
  }
};

const encode = (value: string, allowReserved: boolean): string => {
  let out = "";
  const chars = [...value];
  for (let i = 0; i < chars.length; i++) {
    const char = chars[i]!;
    if (allowReserved && char === "%" && /^[0-9A-Fa-f]{2}$/u.test((chars[i + 1] ?? "") + (chars[i + 2] ?? ""))) {
      out += char + chars[i + 1] + chars[i + 2];
      i += 2;
    } else if (UNRESERVED.test(char) || (allowReserved && RESERVED.test(char))) {
      out += char;
    } else {
      out += [...new TextEncoder().encode(char)].map((b) => "%" + b.toString(16).toUpperCase().padStart(2, "0")).join("");
    }
  }
  return out;
};
