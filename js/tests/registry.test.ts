import { expect, test } from "vitest";
import { Registry } from "../src/index.js";

test.each([
  ["not a URI", ["test:ä"]],
  ["relative", ["schema"]],
  ["equal after percent-decoding (+)", ["test:a%2Bb", "test:a+b"]],
  ["equal after percent-decoding (~)", ["test:a~b", "test:a%7Eb"]],
])("refuses an id: %s", (_name, ids) => {
  const registry = new Registry();
  expect(() => ids.forEach((id) => registry.add(true, id))).toThrow();
});

test("accepts ids that differ after decoding", () => {
  const registry = new Registry().add(true, "test:a%2Bb").add(true, "test:a%2Bb").add(true, "test:a-b");
  expect(registry.ids()).toEqual(["test:a%2Bb", "test:a-b"]);
});
