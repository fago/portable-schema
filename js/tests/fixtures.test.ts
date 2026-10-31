import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";
import * as Fixtures from "./fixtures.js";

test("every example data file has an expectation file", () => {
  expect(Fixtures.unused()).toEqual([]);
});

test("agent-tool's field_item.image is the node article's", () => {
  const read = (showcase: string) => readFileSync(join(Fixtures.DIRECTORY, `examples/drupal/${showcase}/schemas/field_item.image.json`), "utf8");
  expect(read("agent-tool")).toBe(read("node-article"));
});

describe.each(Fixtures.files())("%s", (file) => {
  const [schemaFile, tests] = Fixtures.load(file);

  test("fixture files are valid", async () => {
    expect(await Fixtures.check(file, schemaFile, tests)).toEqual([]);
  });

  test.each(tests.map((testCase) => [testCase.description, testCase] as const))("%s", async (_description, testCase) => {
    const expected = Fixtures.expected(testCase);
    const actual = Fixtures.actual(await Fixtures.run(file, schemaFile, testCase));
    const compared = Object.fromEntries(Object.keys(expected).map((key) => [key, actual[key]]));
    expect(compared).toStrictEqual(expected);
  });
});
