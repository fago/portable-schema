import { expect, test } from "vitest";
import { Registry } from "../src/index.js";

test.each([
  ["not a URI", ["test:ä"]],
  ["relative", ["schema"]],
  ["equal after percent-decoding (+)", ["test:a%2Bb", "test:a+b"]],
  ["equal after percent-decoding (~)", ["test:a~b", "test:a%7Eb"]],
  ["equal after percent-decoding (%FF)", ["test:%FF", "test:%ff"]],
  ["empty fragment", ["test:t#"]],
  ["fragment", ["urn:t#frag"]],
  ["pointer fragment", ["test:a#/b"]],
  ["host case", ["http://x/", "http://X/"]],
  ["scheme case", ["HTTP://x/a", "http://x/a"]],
])("refuses an id: %s", (_name, ids) => {
  const registry = new Registry();
  expect(() => ids.forEach((id) => registry.add(true, id))).toThrow();
});

test("accepts ids that differ after decoding", () => {
  const registry = new Registry().add(true, "test:a%2Bb").add(true, "test:a%2Bb").add(true, "test:a-b");
  expect(registry.ids()).toEqual(["test:a%2Bb", "test:a-b"]);
});

test("accepts ids that differ in path case", () => {
  const registry = new Registry().add(true, "http://x/a").add(true, "http://x/A").add(true, "test:%FF");
  expect(registry.ids()).toEqual(["http://x/a", "http://x/A", "test:%FF"]);
});

test("refuses a schema $id with a fragment", () => {
  expect(() => new Registry().add({ $id: "test:r3#" })).toThrow();
});
