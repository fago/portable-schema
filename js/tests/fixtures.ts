import { existsSync, readdirSync, readFileSync, statSync } from "node:fs";
import { basename, dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";
import { registerSchema, validate, unregisterSchema } from "@hyperjump/json-schema/draft-2020-12";
import { BASIC } from "@hyperjump/json-schema/experimental";
import { parse } from "yaml";
import { CheckResult, Formatter, Registry, Result, Validator, type SubschemaResult } from "../src/index.js";

/** Finds the shared fixtures and runs their cases. */
export const DIRECTORY = fileURLToPath(new URL("../../fixtures", import.meta.url));

export const SCHEMA_ID = "urn:portable-schema:fixture";

/** Examples mount their showcase's `schemas/` as `drupal`. */
export const EXAMPLES_NAMESPACE = "drupal";

export type SchemaFile = { description: string; registry?: Record<string, object | boolean>; schema: object | boolean };

/** A validation case (`valid`) or a subschema case (`location` + `schemaLocations`). */
export type Case = {
  description: string;
  schema?: string;
  data: unknown;
  valid?: boolean;
  errors?: unknown[];
  unchecked?: unknown[];
  options?: { partial?: boolean; emptyAsMissing?: boolean; locations?: string[] };
  messages?: string[];
  checks?: Record<string, { errors: { code: string; params: Record<string, unknown>; message?: string }[]; locations?: string[] }>;
  location?: string;
  schemaLocations?: string[];
  unresolved?: string[];
};

/** An example's `*.test.json`: a case without data and description, and the ID of the schema to validate against. */
export type Expectation = Omit<Case, "description" | "data" | "schema"> & { schema: string };

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

/** Files below examples/ that are neither a case's data nor its expectations. */
export const NON_DATA_FILES = [
  "examples/drupal/agent-tool/repair-loop.mts",
  "examples/drupal/agent-tool/repair-loop.php",
  "examples/drupal/agent-tool/repair-loop.txt",
  "examples/drupal/node-article/introspect.php",
  "examples/drupal/sdc-hero/hero.draft-04.json",
  "examples/drupal/sdc-hero/hero/hero.component.yml",
];

/**
 * Files below examples/, outside `schemas/`, that no case uses: not a `README.md`, not in NON_DATA_FILES, and not a
 * `.yml` or `.json` data file with a `.test.json` next to it.
 */
export const unused = (): string[] => {
  const found: string[] = [];
  const walk = (dir: string): void => {
    for (const name of readdirSync(dir)) {
      const path = join(dir, name);
      const file = relative(DIRECTORY, path);
      if (statSync(path).isDirectory()) {
        if (name !== "schemas") walk(path);
      } else if (name !== "README.md" && !name.endsWith(".test.json") && !NON_DATA_FILES.includes(file)
        && !(/\.(yml|json)$/u.test(name) && existsSync(path.replace(/\.(yml|json)$/u, ".test.json")))) {
        found.push(file);
      }
    }
  };
  walk(join(DIRECTORY, "examples"));
  return found.sort();
};

const read = (file: string): unknown => JSON.parse(readFileSync(join(DIRECTORY, file), "utf8"));

/** Examples hold one data file per `*.test.json`; conformance fixtures hold a schema file and inline cases. */
export const isExample = (testFile: string): boolean => testFile.startsWith("examples/");

/**
 * For a conformance fixture: the schema file and the test cases. For an example: the expectation file and one case,
 * the expectations with the data file's data and its name as description.
 */
export const load = (testFile: string): [SchemaFile | Expectation, Case[]] => {
  const base = testFile.slice(0, -".test.json".length);
  if (!isExample(testFile)) return [read(`${base}.json`) as SchemaFile, read(testFile) as Case[]];
  const expectation = read(testFile) as Expectation;
  return [expectation, [{ ...expectation, description: basename(base), data: data(base) }]];
};

/** The data of `<base>.yml` or `<base>.json`, in the JSON data model. */
const data = (base: string): unknown => {
  const files = [`${base}.yml`, `${base}.json`].filter((file) => existsSync(join(DIRECTORY, file)));
  if (files.length !== 1) throw new Error(`${base} needs one data file, .yml or .json.`);
  const file = files[0]!;
  return file.endsWith(".yml") ? parse(readFileSync(join(DIRECTORY, file), "utf8")) : read(file);
};

/**
 * Validates the fixture files against the fixture file schemas: fixtures/schema.schema.json and
 * fixtures/tests.schema.json, or fixtures/expectation.schema.json for an example.
 */
export const check = async (testFile: string, schemaFile: SchemaFile | Expectation, tests: Case[]): Promise<string[]> =>
  isExample(testFile)
    ? problems(schemaFile, "expectation.schema.json")
    : [...(await problems(schemaFile, "schema.schema.json")), ...(await problems(tests, "tests.schema.json"))];

/** Validates `data` against the fixture file schema `meta`. */
export const problems = async (data: unknown, meta: string): Promise<string[]> => {
  const schema = read(meta) as { $id: string };
  unregisterSchema(schema.$id);
  registerSchema(schema);
  const result = await validate(schema.$id, data as never, BASIC);
  return result.valid ? [] : [`${meta}: ${JSON.stringify(result.errors)}`];
};

export const validator = async (testFile: string, schemaFile: SchemaFile | Expectation, testCase: Case): Promise<Validator> => {
  const registry = Registry.withTypes();
  if (isExample(testFile)) {
    registry.addDirectory(join(DIRECTORY, dirname(testFile), "schemas"), EXAMPLES_NAMESPACE);
  } else {
    const { schema, registry: schemas } = schemaFile as SchemaFile;
    registry.add(schema as never, SCHEMA_ID);
    for (const [id, entry] of Object.entries(schemas ?? {})) registry.add(entry as never, id);
  }
  let validator = await Validator.create(registry);
  for (const [name, canned] of Object.entries(testCase.checks ?? {})) {
    validator = validator.withCheck(name, (requests) => requests.map((request) =>
      new CheckResult(canned.locations === undefined || canned.locations.includes(request.location) ? canned.errors : [])));
  }
  return validator;
};

export const run = async (testFile: string, schemaFile: SchemaFile | Expectation, testCase: Case): Promise<Result | SubschemaResult> => {
  const v = await validator(testFile, schemaFile, testCase);
  if (testCase.location !== undefined) return v.subschemaAt(testCase.data, testCase.schema ?? SCHEMA_ID, testCase.location);
  return v.validate(testCase.data, testCase.schema ?? SCHEMA_ID, testCase.options ?? {});
};

/** The result projected to the compared keys, with sorted object keys. */
export const actual = (result: Result | SubschemaResult): Record<string, unknown> => canonical(result instanceof Result ? {
  messages: Formatter.create().messages(result),
  valid: result.valid,
  errors: result.errors.map((e) => ({ instanceLocation: e.instanceLocation, code: e.code, params: e.params })),
  unchecked: result.unchecked.map((u) => u.toJSON()),
} : { schemaLocations: result.schemaLocations, unresolved: result.unresolved }) as Record<string, unknown>;

/** What the case expects. Without `errors`, errors are not compared unless the case is valid. */
export const expected = (testCase: Case): Record<string, unknown> => {
  if (testCase.location !== undefined) {
    return canonical({ schemaLocations: testCase.schemaLocations, unresolved: testCase.unresolved ?? [] }) as Record<string, unknown>;
  }
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
