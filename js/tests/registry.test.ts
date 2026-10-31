import { mkdirSync, mkdtempSync, rmSync, symlinkSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { getAllRegisteredSchemaUris } from "@hyperjump/json-schema/draft-2020-12";
import { afterEach, expect, test } from "vitest";
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

const roots: string[] = [];

afterEach(() => {
  for (const root of roots.splice(0)) rmSync(root, { recursive: true });
});

/** A temporary directory with `files`: relative path → JSON. */
const directory = (files: Record<string, string>): string => {
  const root = mkdtempSync(join(tmpdir(), "portable-schema-"));
  roots.push(root);
  for (const [path, json] of Object.entries(files)) {
    mkdirSync(dirname(join(root, path)), { recursive: true });
    writeFileSync(join(root, path), json);
  }
  return root;
};

test("mounts a directory", () => {
  const registry = new Registry().addDirectory(directory({
    "block.block.json": '{"type": "object"}',
    "block.settings.%2A.json": '{"$id": "drupal:block.settings.*"}',
    "catalog/plugins/block.json": '{"enum": ["a"]}',
    "block.block.test.json": "[]",
  }), "drupal");
  expect(registry.ids()).toEqual(["drupal:block.block", "drupal:block.settings.*", "drupal:catalog/plugins/block"]);
});

test("skips symbolic links and dotfiles", () => {
  const root = directory({ "a.json": "true", "sub/b.json": "true", ".c.json": "true", ".hidden/d.json": "true" });
  symlinkSync(join(root, "sub"), join(root, "linked"));
  symlinkSync(join(root, "a.json"), join(root, "e.json"));
  symlinkSync(root, join(root, "sub", "loop"));
  expect(new Registry().addDirectory(root, "drupal").ids()).toEqual(["drupal:a", "drupal:sub/b"]);
});

test("names the file with invalid JSON", () => {
  const root = directory({ "a.json": "{" });
  expect(() => new Registry().addDirectory(root, "drupal")).toThrow(`The file ${join(root, "a.json")} is not valid JSON`);
});

test("refuses a file name that decodes to non-ASCII characters", () => {
  const root = directory({ "%C3%A4.json": "true" });
  expect(() => new Registry().addDirectory(root, "drupal")).toThrow(`The file ${join(root, "%C3%A4.json")} has an id with non-ASCII characters.`);
});

test("refuses an $id other than the file path", () => {
  expect(() => new Registry().addDirectory(directory({ "a.json": '{"$id": "drupal:b"}' }), "drupal")).toThrow();
});

test.each([
  ["reserved", "portable"],
  ["upper case", "Drupal"],
  ["empty", ""],
  ["with a colon", "a:b"],
])("refuses a namespace: %s", (_name, namespace) => {
  expect(() => new Registry().addDirectory(directory({ "a.json": "true" }), namespace)).toThrow();
});

test("refuses the types namespace", () => {
  expect(() => new Registry().add(true, "PORTABLE:mine")).toThrow();
});
