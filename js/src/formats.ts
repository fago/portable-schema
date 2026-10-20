import { typeOf } from "./json.js";

/**
 * The pinned format checks of spec/profile.md.
 */
const URI = String.raw`(?:[A-Za-z][A-Za-z0-9+.\-]*:(?://(?:(?:[A-Za-z0-9\-._~!$&'()*+,;=:]|%[0-9A-Fa-f]{2})*@)?(?:\[(?:[0-9A-Fa-f:.]+|v[0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&'()*+,;=:]+)\]|(?:[A-Za-z0-9\-._~!$&'()*+,;=]|%[0-9A-Fa-f]{2})*)(?::[0-9]*)?(?:/(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|/(?:(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})+(?:/(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*)?|(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})+(?:/(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|)(?:\?(?:[A-Za-z0-9\-._~!$&'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?(?:#(?:[A-Za-z0-9\-._~!$&'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?)`;

const RELATIVE_REF = String.raw`(?:(?://(?:(?:[A-Za-z0-9\-._~!$&'()*+,;=:]|%[0-9A-Fa-f]{2})*@)?(?:\[(?:[0-9A-Fa-f:.]+|v[0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&'()*+,;=:]+)\]|(?:[A-Za-z0-9\-._~!$&'()*+,;=]|%[0-9A-Fa-f]{2})*)(?::[0-9]*)?(?:/(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|/(?:(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})+(?:/(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*)?|(?:[A-Za-z0-9\-._~!$&'()*+,;=@]|%[0-9A-Fa-f]{2})+(?:/(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|)(?:\?(?:[A-Za-z0-9\-._~!$&'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?(?:#(?:[A-Za-z0-9\-._~!$&'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?)`;

const PATTERNS: Record<string, string> = {
  "email": String.raw`^[a-zA-Z0-9.!#$%&'*+/=?^_${"`"}{|}~\-]+@[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$`,
  "duration": String.raw`^P(?:(?:[0-9]+D|[0-9]+M(?:[0-9]+D)?|[0-9]+Y(?:[0-9]+M(?:[0-9]+D)?)?)(?:T(?:[0-9]+H(?:[0-9]+M(?:[0-9]+S)?)?|[0-9]+M(?:[0-9]+S)?|[0-9]+S))?|T(?:[0-9]+H(?:[0-9]+M(?:[0-9]+S)?)?|[0-9]+M(?:[0-9]+S)?|[0-9]+S)|[0-9]+W)$`,
  "uuid": String.raw`^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$`,
  "json-pointer": String.raw`^(?:/(?:[^~/]|~[01])*)*$`,
  "relative-json-pointer": String.raw`^(?:0|[1-9][0-9]*)(?:#|(?:/(?:[^~/]|~[01])*)*)$`,
  "decimal": String.raw`^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$`,
  "uri-template": String.raw`^(?:[^\x00-\x20"'<>\\^${"`"}{|}\x7f]|%[0-9A-Fa-f]{2}|\{[+#./;?&=,!@|]?(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})(?:\.?(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2}))*(?::[1-9][0-9]{0,3}|\*)?(?:,(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})(?:\.?(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2}))*(?::[1-9][0-9]{0,3}|\*)?)*\})*$`,
};

/** The formats allowed by the profile. */
export const NAMES = [
  "date", "time", "date-time", "duration", "email", "hostname", "ipv4", "ipv6", "uri", "uri-reference",
  "uri-template", "uuid", "json-pointer", "relative-json-pointer", "regex", "decimal", "int32", "html", "commonmark",
];

/** Whether `value` passes the format. Values of other types pass, except for decimal. */
export const check = (format: string, value: unknown): boolean => {
  if (format === "int32") {
    return typeof value !== "number" || typeOf(value) !== "integer" || (value >= -2147483648 && value <= 2147483647);
  }
  if (format === "decimal" && typeof value === "number") return false;
  if (typeof value !== "string") return true;
  switch (format) {
    case "date": return date(value);
    case "time": return time(value);
    case "date-time": {
      const m = /^([^Tt]+)[Tt](.+)$/u.exec(value);
      return m !== null && date(m[1]!) && time(m[2]!);
    }
    case "hostname": return hostname(value);
    case "ipv6": return ipv6(value);
    case "uri": return match("^" + URI + "$", value);
    case "uri-reference": return match("^(?:" + URI + "|" + RELATIVE_REF + ")$", value);
    case "regex":
      try {
        new RegExp(value, "u");
        return true;
      } catch {
        return false;
      }
    case "html":
    case "commonmark":
      return true;
  }
  const pattern = PATTERNS[format];
  if (pattern === undefined) throw new Error(`Unknown format ${format}`);
  return match(pattern, value);
};

const match = (pattern: string, value: string): boolean => new RegExp(pattern, "u").test(value);

const date = (value: string): boolean => {
  const m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/u.exec(value);
  if (!m) return false;
  const [y, mo, d] = [Number(m[1]), Number(m[2]), Number(m[3])];
  if (mo < 1 || mo > 12 || d < 1) return false;
  const leap = (y % 4 === 0 && y % 100 !== 0) || y % 400 === 0;
  const days = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
  return d <= days[mo - 1]!;
};

const time = (value: string): boolean => {
  const m = /^([0-9]{2}):([0-9]{2}):([0-9]{2})(\.[0-9]+)?([Zz]|[+-]([0-9]{2}):([0-9]{2}))$/u.exec(value);
  if (!m) return false;
  if (Number(m[1]) > 23 || Number(m[2]) > 59 || Number(m[3]) > 59) return false;
  return m[6] === undefined || (Number(m[6]) <= 23 && Number(m[7]) <= 59);
};

const hostname = (value: string): boolean => {
  if (value === "" || new TextEncoder().encode(value).length > 253) return false;
  return value.split(".").every((label) => /^[A-Za-z0-9-]{1,63}$/u.test(label));
};

const ipv6 = (value: string): boolean => {
  const groups = 8;
  const tail = /^(.*:)((?:[0-9]{1,3}\.){3}[0-9]{1,3})$/u.exec(value);
  if (tail) {
    if (!match(PATTERNS["ipv4"]!, tail[2]!)) return false;
    value = tail[1] + "0:0";
  }
  const parts = value.split("::");
  if (parts.length > 2) return false;
  let count = 0;
  for (const part of parts) {
    if (part === "") continue;
    for (const group of part.split(":")) {
      if (!/^[0-9A-Fa-f]{1,4}$/u.test(group)) return false;
      count++;
    }
  }
  return parts.length === 2 ? count < groups : count === groups;
};
