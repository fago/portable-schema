import { getAllRegisteredSchemaUris } from "@hyperjump/json-schema/draft-2020-12";
import { expect, test } from "vitest";
import { Registry, Validator } from "../src/index.js";

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
  ["dot-dot segment", ["http://x/a/../b"]],
  ["dot segment", ["http://x/./b"]],
  ["dot segment without authority", ["test:a/./b"]],
  ["encoded dot segment", ["test:a/%2E/b"]],
  ["trailing dot-dot segment", ["test:a/.."]],
  ["file scheme", ["file:///x"]],
  ["encoded ? before a dot-dot segment", ["test:a%3F/../b"]],
  ["encoded ? before an encoded dot-dot segment", ["test:a%3F/%2E%2E/b"]],
  ["meta-schema id", ["https://portable-schema.dev/v1/load"]],
  ["2020-12 meta-schema id", ["https://json-schema.org/draft/2020-12/schema"]],
  ["meta-schema id in other case", ["HTTPS://JSON-SCHEMA.ORG/draft/2020-12/meta/core"]],
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

test("accepts dots in segments", () => {
  const registry = new Registry().add(true, "http://x/a.b/..c").add(true, "test:a?./b");
  expect(registry.ids()).toEqual(["http://x/a.b/..c", "test:a?./b"]);
});

test("refuses a schema $id other than its id", () => {
  expect(() => new Registry().add({ $id: "test:o", required: ["a"] }, "urn:t")).toThrow();
});

test.each([
  ["array", [1], undefined],
  ["object", {}, undefined],
  ["array, with an id", [1], "test:n"],
  ["object, with an id", {}, "test:n"],
  ["null, with an id", null, "test:n"],
])("refuses a non-string schema $id: %s", (_name, value, id) => {
  expect(() => new Registry().add({ $id: value }, id)).toThrow();
});

test("META_SCHEMA_IDS are the schemas Hyperjump holds besides the registry's", async () => {
  const registry = Registry.withTypes();
  await Validator.create(registry);
  const others = getAllRegisteredSchemaUris().filter((uri) => !registry.ids().includes(uri));
  expect(others.sort()).toEqual([...Registry.META_SCHEMA_IDS].sort());
});
