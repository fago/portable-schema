/**
 * RFC 6570 expansion, levels 1 and 2: {var}, {+var}, {#var}.
 */
const UNRESERVED = /[A-Za-z0-9\-._~]/u;
const RESERVED = /[:/?#[\]@!$&'()*+,;=]/u;

export const expand = (template: string, variables: Record<string, string>): string =>
  template.replace(/\{([+#]?)([^}]*)\}/gu, (_match, operator: string, list: string) => {
    const values = list.split(",")
      .filter((name) => variables[name] !== undefined)
      .map((name) => encode(variables[name]!, operator !== ""));
    if (values.length === 0) return "";
    return (operator === "#" ? "#" : "") + values.join(",");
  });

/** Variable names in the order they appear. */
export const variables = (template: string): string[] =>
  [...template.matchAll(/\{[+#]?([^}]*)\}/gu)].flatMap((m) => m[1]!.split(","));

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
