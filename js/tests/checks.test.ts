import { describe, expect, test } from "vitest";
import { CheckResult, Registry, Validator, type CheckProvider, type CheckRequest, type Result } from "../src/index.js";

const SCHEMA = { $schema: "https://portable-schema.dev/v1/schema", type: "array", items: { type: "string", "portable-check": { "test:Unique": { scope: "tags" } } } };

const validator = () => Validator.create(Registry.withTypes().add(SCHEMA, "test:schema"));

const provider = () => {
  const calls: unknown[] = [];
  const provider: CheckProvider = {
    check: (name: string, requests: CheckRequest[]) => {
      calls.push([name, requests.map((r) => [r.value, r.location, r.options["scope"]])]);
      return requests.map((r) => new CheckResult(r.value === "b" ? [{ code: "check.test:Unique", params: { value: "b" } }] : []));
    },
  };
  return { provider, calls };
};

const errors = (result: Result) => result.errors.map((e) => ({ instanceLocation: e.instanceLocation, code: e.code, params: e.params }));

describe("named checks", () => {
  test("the provider is called once per name", async () => {
    const { provider: p, calls } = provider();
    const result = (await validator()).withProvider("test:Unique", p).validate(["a", "b"], "test:schema");
    expect(calls).toEqual([["test:Unique", [["a", "/0", "tags"], ["b", "/1", "tags"]]]]);
    expect(errors(result)).toEqual([{ instanceLocation: "/1", code: "check.test:Unique", params: { value: "b" } }]);
  });

  test("a callback overrides the provider", async () => {
    const { provider: p, calls } = provider();
    const result = (await validator())
      .withProvider("test:Unique", p)
      .withCheck("test:Unique", (requests) => requests.map(() => new CheckResult()))
      .validate(["a", "b"], "test:schema");
    expect(calls).toEqual([]);
    expect(result.valid).toBe(true);
    expect(result.unchecked).toEqual([]);
  });

  test("a failing provider is unchecked", async () => {
    const result = (await validator())
      .withCheck("test:Unique", () => {
        throw new Error("down");
      })
      .validate(["a"], "test:schema");
    expect(result.valid).toBe(true);
    expect(result.unchecked.map((u) => u.toJSON())).toEqual([{ instanceLocation: "/0", code: "check.test:Unique", reason: "error" }]);
  });
});
