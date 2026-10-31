import { expect, test } from "vitest";
import { Registry, Validator, type JsonObject, type Result } from "../src/index.js";

/** The JS data model of spec/normalization.md: `undefined` and the values the model can't hold. */
const DIALECT = "https://portable-schema.dev/v1/schema";

const validate = async (schema: JsonObject, data: unknown): Promise<Result> => {
  const registry = Registry.withTypes().add({ $schema: DIALECT, $id: "test:s", ...schema });
  return (await Validator.create(registry)).validate(data, "test:s");
};

const errors = (result: Result): [string, string, Record<string, unknown>][] =>
  result.errors.map((e) => [e.instanceLocation, e.code, e.params]);

test("an undefined property is absent", async () => {
  const schema: JsonObject = { required: ["a"], additionalProperties: false };
  expect(errors(await validate(schema, { a: undefined }))).toEqual([["", "required", { property: "a" }]]);
});

test("an undefined item is null", async () => {
  expect(errors(await validate({ items: { type: "string" } }, [undefined]))).toEqual([["/0", "type", { expected: ["string"], actual: "null" }]]);
});

test("values JSON.parse cannot produce are type errors", async () => {
  class Foo {}
  const schema: JsonObject = { properties: { d: { type: "string" }, m: {}, n: true, b: { type: ["string", "null"] }, f: {}, i: {}, c: {} } };
  const result = await validate(schema, { d: new Date(0), m: new Map(), n: NaN, b: 1n, f: () => 1, i: Infinity, c: new Foo() });
  expect(errors(result)).toEqual([
    ["/b", "type", { expected: ["string", "null"], actual: "unsupported" }],
    ["/c", "type", { expected: [], actual: "unsupported" }],
    ["/d", "type", { expected: ["string"], actual: "unsupported" }],
    ["/f", "type", { expected: [], actual: "unsupported" }],
    ["/i", "type", { expected: [], actual: "unsupported" }],
    ["/m", "type", { expected: [], actual: "unsupported" }],
    ["/n", "type", { expected: [], actual: "unsupported" }],
  ]);
  expect(result.errors[2]!.keywordLocation).toBe("/properties/d/type");
  expect(errors(await validate({ minimum: 1 }, undefined))).toEqual([["", "type", { expected: [], actual: "unsupported" }]]);
  expect(errors(await validate({ minimum: 1 }, Symbol("s")))).toEqual([["", "type", { expected: [], actual: "unsupported" }]]);
});

test("plain objects are objects, whatever their prototype", async () => {
  const schema: JsonObject = { type: "object", required: ["a"] };
  expect((await validate(schema, Object.assign(Object.create(null), { a: 1 }))).valid).toBe(true);
  expect((await validate(schema, { a: [1] })).valid).toBe(true);
});

test("subschemaAt normalizes the same way", async () => {
  const registry = Registry.withTypes().add({ $schema: DIALECT, $id: "test:s", type: "object", properties: { a: { type: "object", properties: { b: { type: "string" } } } } });
  const result = (await Validator.create(registry)).subschemaAt({ a: { b: new Date(0), c: undefined } }, "test:s", "a.b");
  expect(result.schemaLocations).toEqual(["/properties/a/properties/b"]);
});
