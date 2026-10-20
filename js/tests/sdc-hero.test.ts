import { readFileSync } from "node:fs";
import { join } from "node:path";
import { expect, test } from "vitest";
import { Exporter, Registry } from "../src/index.js";
import { DIRECTORY } from "./fixtures.js";

const SHOWCASE = join(DIRECTORY, "examples/drupal/sdc-hero");

test("the SDC hero's draft-04 export is committed", () => {
  const exporter = new Exporter(Registry.withTypes().addDirectory(join(SHOWCASE, "schemas")));
  const committed: unknown = JSON.parse(readFileSync(join(SHOWCASE, "hero.draft-04.json"), "utf8"));
  expect(exporter.draft04("drupal:sdc.portable_showcase.hero")).toStrictEqual(committed);
});
