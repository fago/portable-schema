import { readdirSync, readFileSync } from "node:fs";
import { builtinModules } from "node:module";
import { join } from "node:path";
import { expect, test } from "vitest";
import { MESSAGES, SPEC } from "../src/embedded.js";
import { Registry, type JsonObject } from "../src/index.js";
import { DIRECTORY } from "./fixtures.js";

const ROOT = join(DIRECTORY, "..");

/** The files with the extension below a directory of the repository, by path relative to it, sorted. */
const files = (directory: string, extension: string): string[] =>
  (readdirSync(join(ROOT, directory), { recursive: true }) as string[]).filter((file) => file.endsWith(extension)).sort();

/** The JSON text of a file of the repository, as js/scripts/embed.mjs embeds it. */
const json = (file: string): string => JSON.stringify(JSON.parse(readFileSync(join(ROOT, file), "utf8")));

test("withTypes() holds the schemas of types/v1", () => {
  const types = files("types/v1", ".json").filter((file) => !file.endsWith(".test.json"));
  const registry = Registry.withTypes();
  expect(registry.ids()).toEqual(types.map((file) => `portable:${file.slice(0, -".json".length)}`));
  expect(registry.ids().map((id) => JSON.stringify(registry.get(id)))).toEqual(types.map((file) => json(`types/v1/${file}`)));
});

test("withTypes() gives every registry its own schemas", () => {
  (Registry.withTypes().get("portable:uuid") as JsonObject)["format"] = "date";
  expect(Registry.withTypes().get("portable:uuid")).toHaveProperty("format", "uuid");
});

test("the meta-schemas and the message catalog are embedded as they are in spec/v1 and messages/", () => {
  expect(SPEC).toEqual(Object.fromEntries(["vocab.json", "schema.json", "schema-strict.json"].map((file) => [file, json(`spec/v1/${file}`)])));
  expect(MESSAGES).toBe(json("messages/en.json"));
});

test("the sources import no Node.js module, so they load in a browser and in a bundle", () => {
  const imports = files("js/src", ".ts").flatMap((file) =>
    [...readFileSync(join(ROOT, "js/src", file), "utf8").matchAll(/\b(?:from|import)\s*\(?\s*["']([^"']+)["']/gu)]
      .map(([, specifier]) => specifier!)
      .filter((specifier) => specifier.startsWith("node:") || builtinModules.includes(specifier))
      .map((specifier) => `${file}: ${specifier}`));
  expect(imports).toEqual([]);
});
