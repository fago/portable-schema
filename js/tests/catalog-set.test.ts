import { expect, test } from "vitest";
import { Checks } from "../src/checks.js";
import { compile, evaluate } from "../src/hyperjump.js";
import { Registry, Validator, type JsonObject, type Result } from "../src/index.js";
import { Tracer, type KeywordNode } from "../src/trace.js";

const DIALECT = "https://portable-schema.dev/v1/schema";

const BRANCHES: JsonObject[] = [
  { const: 1, title: "One" }, { const: "1", title: "One as text" }, { const: null, title: "None" }, { const: true, title: "Yes" },
  { const: 2.5, title: "Two and a half" }, { const: { a: [1, 2] }, title: "Pair", "x-portable-provider": "test" },
];

const VALUES = ["1", "1.0", '"1"', "null", "true", "false", "2.5", "3", '"x"', '{"a": [1.0, 2]}', '{"a": [2, 1]}', "[]", "{}", "9007199254740992"];

const registry = (...schemas: JsonObject[]): Registry => {
  const registry = Registry.withTypes();
  for (const schema of schemas) registry.add({ $schema: DIALECT, ...schema } as JsonObject);
  return registry;
};

const outcome = (result: Result) => ({
  valid: result.valid,
  errors: result.errors.map((e) => [e.instanceLocation, e.code, e.params]),
  titles: result.annotations("").allowedTitles,
});

/** Validates `data` and returns the `oneOf` keyword node at the root. */
const oneOf = async (reg: Registry, data: unknown, id: string): Promise<KeywordNode> => {
  const compiled = await compile(reg);
  const tracer = new Tracer();
  evaluate(compiled, compiled.urls.get(id)!, data, tracer, new Checks());
  return tracer.root()!.keywords.find((keyword) => keyword.keyword === "oneOf")!;
};

test("a catalog gives the results of evaluating each branch", async () => {
  // anyOf is never compiled to a set; with distinct consts it means the same.
  const reg = registry({ $id: "test:one", oneOf: BRANCHES }, { $id: "test:any", anyOf: BRANCHES });
  const validator = await Validator.create(reg);
  for (const value of VALUES) {
    const data = JSON.parse(value);
    expect((await oneOf(reg, data, "test:one")).children, value).toEqual([]);
    expect(outcome(validator.validate(data, "test:one")), value).toEqual(outcome(validator.validate(data, "test:any")));
  }
});

test("a scalar enum gives the results of evaluating each value", async () => {
  const validator = await Validator.create(registry(
    { $id: "test:enum", enum: [1, "1", null, true, 2.5, 9007199254740992] },
    { $id: "test:any", anyOf: [{ const: 1 }, { const: "1" }, { const: null }, { const: true }, { const: 2.5 }, { const: 9007199254740992 }] },
  ));
  for (const value of [...VALUES, "9007199254740992.0", "9.007199254740992e15"]) {
    const data = JSON.parse(value);
    expect(outcome(validator.validate(data, "test:enum")), value).toEqual(outcome(validator.validate(data, "test:any")));
  }
});

test("duplicate or non-const branches are evaluated", async () => {
  const reg = registry(
    { $id: "test:duplicate", oneOf: [{ const: 1 }, { const: 1.0 }] },
    { $id: "test:mixed", oneOf: [{ const: 1 }, { type: "string" }] },
    { $id: "test:keyword", oneOf: [{ const: 1 }, { const: 2, minimum: 0 }] },
  );
  const validator = await Validator.create(reg);
  expect(validator.validate(1, "test:duplicate").errors.map((e) => e.code)).toEqual(["enum"]);
  expect((await oneOf(reg, 1, "test:duplicate")).children).toHaveLength(2);
  expect(validator.validate("x", "test:mixed").valid).toBe(true);
  expect((await oneOf(reg, "x", "test:mixed")).children).toHaveLength(2);
  expect(validator.validate(2, "test:keyword").valid).toBe(true);
  expect((await oneOf(reg, 2, "test:keyword")).children).toHaveLength(2);
});

test("a large catalog is checked without evaluating each branch", async () => {
  const branches = Array.from({ length: 10000 }, (_, i) => ({ const: `v${i + 1}`, title: `Value ${i + 1}` }));
  const reg = registry({ $id: "test:large", oneOf: branches });
  const validator = await Validator.create(reg);

  expect(validator.validate("v10000", "test:large").valid).toBe(true);
  expect((await oneOf(reg, "v10000", "test:large")).children).toEqual([]);

  const result = validator.validate("v0", "test:large");
  expect((await oneOf(reg, "v0", "test:large")).children).toEqual([]);
  expect(result.errors.map((e) => e.code)).toEqual(["enum"]);
  expect(result.errors[0]!.params["allowed"]).toHaveLength(10000);
  expect(result.annotations("").allowedTitles![9999]).toBe("Value 10000");
});
