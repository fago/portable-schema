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

test("a validator doesn't see schemas added to its registry later", async () => {
  const reg = registry("late");
  const validator = await Validator.create(reg);
  reg.add({ $schema: DIALECT, $id: "test:late.b", type: "string" } as JsonObject);
  expect(() => validator.validate("x", "test:late.b")).toThrow();
  expect(validator.validate({ k: "b" }, "test:late").errors.map((e) => e.code)).toEqual(["typeBy.unresolved"]);
});

test("a validator doesn't see schemas replaced in its registry later", async () => {
  const reg = Registry.withTypes().add({ $schema: DIALECT, $id: "test:c", title: "Count", type: "integer" } as JsonObject);
  const validator = await Validator.create(reg);
  const before = validator.validate("x", "test:c");
  reg.add({ $schema: DIALECT, $id: "test:c", title: "Name", type: "string" } as JsonObject);
  expect(validator.validate("x", "test:c")).toEqual(before);
});
