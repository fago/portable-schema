import { describe, expect, test } from "vitest";
import * as Fixtures from "./fixtures.js";

describe.each(Fixtures.files())("%s", (file) => {
  const [schemaFile, tests] = Fixtures.load(file);

  test("fixture files are valid", async () => {
    expect(await Fixtures.check(schemaFile, tests)).toEqual([]);
  });

  test.each(tests.map((testCase) => [testCase.description, testCase] as const))("%s", async (_description, testCase) => {
    const expected = Fixtures.expected(testCase);
    const actual = Fixtures.actual(await Fixtures.run(file, schemaFile, testCase));
    const compared = Object.fromEntries(Object.keys(expected).map((key) => [key, actual[key]]));
    expect(compared).toStrictEqual(expected);
  });
});
