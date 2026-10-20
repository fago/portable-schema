import { readFileSync } from "node:fs";
import { join } from "node:path";
import { expect, test } from "vitest";
import { Registry, Validator, type JsonObject } from "../src/index.js";
import { DIRECTORY } from "./fixtures.js";

const cases = JSON.parse(readFileSync(join(DIRECTORY, "loading.json"), "utf8")) as { description: string; schema: JsonObject; refused: boolean }[];

test.each(cases)("$description", async ({ schema, refused }) => {
  const create = Validator.create(Registry.withTypes().add(schema));
  if (refused) {
    await expect(create).rejects.toThrow();
  } else {
    (await create).validate([], schema["$id"] as string);
  }
});
