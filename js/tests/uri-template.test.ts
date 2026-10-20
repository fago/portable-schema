import { expect, test } from "vitest";
import { assertTypeBy, expand } from "../src/uri-template.js";

test.each(["test:cat#/$defs/{k}", "test:{#k}", "test:{/k}", "test:{?k}", "test:{.k}", "test:{;k}", "test:{k:2}", "test:{k*}"])(
  "portable-typeBy refuses %s",
  (template) => expect(() => assertTypeBy(template)).toThrow(),
);

test("portable-typeBy accepts {var} and {+var}", () => {
  expect(() => assertTypeBy("test:{a}.{+b}.{c,d.e}")).not.toThrow();
  expect(expand("test:{a}.{+b}.{c,d.e}", { a: "x", b: "y/z", c: "1", "d.e": "2" })).toBe("test:x.y/z.1,2");
});
