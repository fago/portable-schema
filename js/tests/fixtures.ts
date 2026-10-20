import { existsSync, readdirSync, readFileSync, statSync } from "node:fs";
import { dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";
import { registerSchema, validate, unregisterSchema } from "@hyperjump/json-schema/draft-2020-12";
import { BASIC } from "@hyperjump/json-schema/experimental";
import { CheckResult, Formatter, Registry, Validator, type Result } from "../src/index.js";

/** Finds the shared fixtures and runs their cases. */
export const DIRECTORY = fileURLToPath(new URL("../../fixtures", import.meta.url));

export const SCHEMA_ID = "urn:portable-schema:fixture";

export type SchemaFile = { description: string; registry?: Record<string, object | boolean>; schema: object | boolean };

export type Case = {
  description: string;
  data: unknown;
  valid: boolean;
  errors?: unknown[];
  unchecked?: unknown[];
  options?: { partial?: boolean; emptyAsMissing?: boolean; locations?: string[] };
  messages?: string[];
  checks?: Record<string, { errors: { code: string; params: Record<string, unknown> }[] }>;
};

/** Every `*.test.json` below fixtures/, relative to it. */
export const files = (): string[] => {
  const found: string[] = [];
  const walk = (dir: string): void => {
    for (const name of readdirSync(dir)) {
      const path = join(dir, name);
      if (statSync(path).isDirectory()) walk(path);
      else if (name.endsWith(".test.json")) found.push(relative(DIRECTORY, path));
    }
  };
  walk(DIRECTORY);
  return found.sort();
};

const read = (file: string): unknown => JSON.parse(readFileSync(join(DIRECTORY, file), "utf8"));

/** The schema file and the test cases. */
export const load = (testFile: string): [SchemaFile, Case[]] =>
  [read(testFile.slice(0, -".test.json".length) + ".json") as SchemaFile, read(testFile) as Case[]];

/** Validates the fixture files against fixtures/schema.schema.json and fixtures/tests.schema.json. */
export const check = async (schemaFile: SchemaFile, tests: Case[]): Promise<string[]> => {
  const problems: string[] = [];
  for (const [meta, data] of [["schema.schema.json", schemaFile], ["tests.schema.json", tests]] as const) {
    const schema = read(meta) as { $id: string };
    unregisterSchema(schema.$id);
    registerSchema(schema);
    const result = await validate(schema.$id, data as never, BASIC);
    if (!result.valid) problems.push(`${meta}: ${JSON.stringify(result.errors)}`);
  }
  return problems;
};

/** For examples: the `schemas/` and `catalogs/` directories next to the fixture and above it, below fixtures/examples/. */
export const registryDirectories = (testFile: string): string[] => {
  const directories: string[] = [];
  for (let dir = dirname(testFile); dir.startsWith("examples/"); dir = dirname(dir)) {
    for (const name of ["schemas", "catalogs"]) {
      const path = join(DIRECTORY, dir, name);
      if (existsSync(path)) directories.push(path);
    }
  }
  return directories;
};

export const validator = async (testFile: string, schemaFile: SchemaFile, testCase: Case): Promise<Validator> => {
  const registry = Registry.withTypes().add(schemaFile.schema as never, SCHEMA_ID);
  for (const directory of registryDirectories(testFile)) registry.addDirectory(directory);
  for (const [id, schema] of Object.entries(schemaFile.registry ?? {})) registry.add(schema as never, id);
  let validator = await Validator.create(registry);
  for (const [name, canned] of Object.entries(testCase.checks ?? {})) {
    validator = validator.withCheck(name, (requests) => requests.map(() => new CheckResult(canned.errors)));
  }
  return validator;
};

export const run = async (testFile: string, schemaFile: SchemaFile, testCase: Case): Promise<Result> =>
  (await validator(testFile, schemaFile, testCase)).validate(testCase.data, SCHEMA_ID, testCase.options ?? {});

/** The result projected to the compared keys, with sorted object keys. */
export const actual = (result: Result): Record<string, unknown> => canonical({
  messages: Formatter.create().messages(result),
  valid: result.valid,
  errors: result.errors.map((e) => ({ instanceLocation: e.instanceLocation, code: e.code, params: e.params })),
  unchecked: result.unchecked.map((u) => u.toJSON()),
}) as Record<string, unknown>;

/** What the case expects. Without `errors`, errors are not compared unless the case is valid. */
export const expected = (testCase: Case): Record<string, unknown> => {
  const expect: Record<string, unknown> = { valid: testCase.valid };
  if (testCase.errors !== undefined || testCase.valid) expect["errors"] = testCase.errors ?? [];
  expect["unchecked"] = testCase.unchecked ?? [];
  if (testCase.messages !== undefined) expect["messages"] = testCase.messages;
  return canonical(expect) as Record<string, unknown>;
};

const canonical = (value: unknown): unknown => {
  if (Array.isArray(value)) return value.map(canonical);
  if (typeof value === "object" && value !== null) {
    return Object.fromEntries(Object.keys(value).sort().map((key) => [key, canonical((value as Record<string, unknown>)[key])]));
  }
  return value;
};
