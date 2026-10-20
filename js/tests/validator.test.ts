import { expect, test } from "vitest";
import { Registry, Validator, type JsonObject } from "../src/index.js";

const DIALECT = "https://portable-schema.dev/v1/schema";

const registry = (name: string): Registry => Registry.withTypes()
  .add({ $schema: DIALECT, $id: `test:${name}`, type: "object", "portable-typeBy": { href: `test:${name}.{k}`, source: { k: "0/k" } } } as JsonObject)
  .add({ $schema: DIALECT, $id: `test:${name}.a`, type: "object" } as JsonObject);

test("validators created at the same time resolve their own typeBy targets", async () => {
  const [one, two] = await Promise.all([Validator.create(registry("one")), Validator.create(registry("two"))]);
  expect(one.validate({ k: "a" }, "test:one").valid).toBe(true);
  expect(two.validate({ k: "a" }, "test:two").valid).toBe(true);
});

test("a validator doesn't see the schemas of an earlier registry", async () => {
  await Validator.create(Registry.withTypes().add({ $schema: DIALECT, $id: "test:x", type: "string" } as JsonObject));
  const later = Registry.withTypes().add({ $schema: DIALECT, $id: "test:y", $ref: "test:x" } as JsonObject);
  await expect(Validator.create(later)).rejects.toThrow();
});
