import { expect, test } from "vitest";
import { assertTypeBy, expand } from "../src/uri-template.js";

test.each(["test:cat#/$defs/{k}", "test:{#k}", "test:{/k}", "test:{?k}", "test:{.k}", "test:{;k}", "test:{k:2}", "test:{k*}", "test:s.{{p}", "test:s.{p}}", "test:s.{p", "test:s.p}"])(
  "portable-typeBy refuses href %s",
  (href) => expect(() => assertTypeBy(href, null)).toThrow(),
);

test.each(["test:{p}", "test:a#/x", "test:a}"])(
  "portable-typeBy refuses fallback %s",
  (fallback) => expect(() => assertTypeBy("test:{p}", fallback)).toThrow(),
);

test("portable-typeBy accepts {var} and {+var}", () => {
  expect(() => assertTypeBy("test:{a}.{+b}.{c,d.e}", "test:other")).not.toThrow();
  expect(expand("test:{a}.{+b}.{c,d.e}", { a: "x", b: "y/z", c: "1", "d.e": "2" })).toBe("test:x.y/z.1,2");
});
