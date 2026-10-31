import { typeOf } from "./json.js";

/**
 * The pinned format checks of spec/profile.md.
 */

/** RFC 3987 ucschar and iprivate, as PCRE writes them; JS reads `\u{…}`. Split at the BMP: PCRE2 10.46 mis-compiles a class holding every range. */
const UCSCHAR = String.raw`\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}`;

const UCSCHAR_ASTRAL = String.raw`\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}`;

const IPRIVATE = String.raw`\x{E000}-\x{F8FF}`;

const IPRIVATE_ASTRAL = String.raw`\x{F0000}-\x{FFFFD}\x{100000}-\x{10FFFD}`;

const UNRESERVED = String.raw`A-Za-z0-9\-._~`;

/** The character sets of the RFC 3986 URI grammar, or with `ucschar` and `iprivate` of the RFC 3987 IRI grammar. */
type Chars = {
  /** the unreserved characters, as a class body */
  u: string;
  /** unreserved characters above the BMP */
  astral: string;
  /** characters the query allows in addition */
  q: string;
  qAstral: string;
};

const PCT = "%[0-9A-Fa-f]{2}";

/** A class of `body`, plus the astral characters as a second class. */
const cls = (chars: Chars, body: string, query = false): string => {
  const astral = chars.astral + (query ? chars.qAstral : "");
  return astral === "" ? `[${body}]` : `(?:[${body}]|[${astral}])`;
};

const authority = (chars: Chars): string => {
  const userinfo = String.raw`(?:(?:${cls(chars, chars.u + "!$&'()*+,;=:")}|${PCT})*@)?`;
  const host = String.raw`(?:\[(?:[0-9A-Fa-f:.]+|v[0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&'()*+,;=:]+)\]|(?:${cls(chars, chars.u + "!$&'()*+,;=")}|${PCT})*)`;
  return String.raw`//${userinfo}${host}(?::[0-9]*)?`;
};

const pchar = (chars: Chars): string => String.raw`(?:${cls(chars, chars.u + "!$&'()*+,;=:@")}|${PCT})`;

const segments = (chars: Chars): string => String.raw`(?:/${pchar(chars)}*)*`;

const queryAndFragment = (chars: Chars): string =>
  String.raw`(?:\?(?:${cls(chars, chars.u + "!$&'()*+,;=:@/?" + chars.q, true)}|${PCT})*)?(?:#(?:${cls(chars, chars.u + "!$&'()*+,;=:@/?")}|${PCT})*)?`;

/** The URI of RFC 3986, or the IRI of RFC 3987. */
const uriPattern = (chars: Chars): string => {
  const hierPart = String.raw`(?:${authority(chars)}${segments(chars)}|/(?:${pchar(chars)}+${segments(chars)})?|${pchar(chars)}+${segments(chars)}|)`;
  return String.raw`(?:[A-Za-z][A-Za-z0-9+.\-]*:${hierPart}${queryAndFragment(chars)})`;
};

/** The relative-ref of RFC 3986, or the irelative-ref of RFC 3987. */
const relativeRefPattern = (chars: Chars): string => {
  const relativePart = String.raw`(?:${authority(chars)}${segments(chars)}|/(?:${pchar(chars)}+${segments(chars)})?|(?:${cls(chars, chars.u + "!$&'()*+,;=@")}|${PCT})+${segments(chars)}|)`;
  return String.raw`(?:${relativePart}${queryAndFragment(chars)})`;
};

const URI_CHARS: Chars = { u: UNRESERVED, astral: "", q: "", qAstral: "" };

const IRI_CHARS: Chars = { u: UNRESERVED + UCSCHAR, astral: UCSCHAR_ASTRAL, q: IPRIVATE, qAstral: IPRIVATE_ASTRAL };

const URI = uriPattern(URI_CHARS);

const RELATIVE_REF = relativeRefPattern(URI_CHARS);

const IRI = uriPattern(IRI_CHARS).replaceAll(String.raw`\x{`, String.raw`\u{`);

const IRELATIVE_REF = relativeRefPattern(IRI_CHARS).replaceAll(String.raw`\x{`, String.raw`\u{`);

const PATTERNS: Record<string, string> = {
  "duration": String.raw`^P(?:(?:[0-9]+D|[0-9]+M(?:[0-9]+D)?|[0-9]+Y(?:[0-9]+M(?:[0-9]+D)?)?)(?:T(?:[0-9]+H(?:[0-9]+M(?:[0-9]+S)?)?|[0-9]+M(?:[0-9]+S)?|[0-9]+S))?|T(?:[0-9]+H(?:[0-9]+M(?:[0-9]+S)?)?|[0-9]+M(?:[0-9]+S)?|[0-9]+S)|[0-9]+W)$`,
  "ipv4": String.raw`^(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])(?:\.(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])){3}$`,
  "uuid": String.raw`^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$`,
  "json-pointer": String.raw`^(?:/(?:[^~/]|~[01])*)*$`,
  "relative-json-pointer": String.raw`^(?:0|[1-9][0-9]*)(?:#|(?:/(?:[^~/]|~[01])*)*)$`,
  "decimal": String.raw`^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)$`,
  "uri-template": String.raw`^(?:[^\x00-\x20"'<>\\^${"`"}{|}\x7f]|%[0-9A-Fa-f]{2}|\{[+#./;?&=,!@|]?(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})(?:\.?(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2}))*(?::[1-9][0-9]{0,3}|\*)?(?:,(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})(?:\.?(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2}))*(?::[1-9][0-9]{0,3}|\*)?)*\})*$`,
};

/** The formats allowed by the profile. */
export const NAMES = [
  "date", "time", "date-time", "duration", "email", "hostname", "ipv4", "ipv6", "uri", "uri-reference", "iri",
  "iri-reference", "uri-template", "uuid", "json-pointer", "relative-json-pointer", "regex", "decimal", "int32", "html",
  "commonmark",
];

/** Whether `value` passes the format. Values of other types pass. */
export const check = (format: string, value: unknown): boolean => {
  if (format === "int32") {
    return typeof value !== "number" || typeOf(value) !== "integer" || (value >= -2147483648 && value <= 2147483647);
  }
  if (typeof value !== "string") return true;
  switch (format) {
    case "date": return date(value);
    case "time": return time(value);
    case "date-time": {
      const m = /^([^Tt]+)[Tt](.+)$/u.exec(value);
      return m !== null && date(m[1]!) && time(m[2]!);
    }
    case "email": return email(value);
    case "hostname": return hostname(value);
    case "ipv6": return ipv6(value);
    case "uri": return match("^" + URI + "$", value);
    case "uri-reference": return match("^(?:" + URI + "|" + RELATIVE_REF + ")$", value);
    case "iri": return match("^" + IRI + "$", value);
    case "iri-reference": return match("^(?:" + IRI + "|" + IRELATIVE_REF + ")$", value);
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

const ATEXT = String.raw`[A-Za-z0-9!#$%&'*+/=?^_${"`"}{|}~\-]`;

/** RFC 5321 Dot-string or Quoted-string. */
const LOCAL_PART = new RegExp(String.raw`^(?:${ATEXT}+(?:\.${ATEXT}+)*|"(?:[ !#-\[\]-~]|\\[ -~])*")$`, "u");

/** RFC 5321 Snum: 1-3 digits, 0-255, leading zeros allowed. */
const SNUM = String.raw`(?:[01]?[0-9]{1,2}|2[0-4][0-9]|25[0-5])`;

const IPV4_LITERAL = new RegExp(String.raw`^${SNUM}(?:\.${SNUM}){3}$`, "u");

/** RFC 5321 Mailbox, ASCII: a local part of at most 64 characters, `@`, a hostname or an address literal. */
const email = (value: string): boolean => {
  const at = value.lastIndexOf("@");
  if (at === -1 || value.length > 254) return false;
  const local = value.slice(0, at);
  const domain = value.slice(at + 1);
  if (local.length > 64 || !LOCAL_PART.test(local)) return false;
  if (!domain.startsWith("[")) return hostname(domain);
  if (!domain.endsWith("]")) return false;
  const literal = domain.slice(1, -1);
  return IPV4_LITERAL.test(literal) || (/^ipv6:/iu.test(literal) && ipv6Literal(literal.slice(5)));
};

/** RFC 5321 IPv6-addr: 8 groups, or `::` with at most 6 groups; an IPv4 tail counts as 2 groups. */
const ipv6Literal = (value: string): boolean => {
  const tail = /^(.*:)((?:[0-9]{1,3}\.){3}[0-9]{1,3})$/u.exec(value);
  if (tail) {
    if (!IPV4_LITERAL.test(tail[2]!)) return false;
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
  return parts.length === 2 ? count <= 6 : count === 8;
};

const hostname = (value: string): boolean => {
  if (value === "" || new TextEncoder().encode(value).length > 253) return false;
  return value.split(".").every((label) => /^[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/u.test(label));
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
