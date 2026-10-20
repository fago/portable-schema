import { readdirSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { registerSchema, validate } from "@hyperjump/json-schema/draft-2020-12";
import { expect, test } from "vitest";
import { Exporter, Registry, Validator, type JsonObject } from "../src/index.js";
import { DIRECTORY } from "./fixtures.js";

type ExportCase = { description: string; registry?: JsonObject[]; schema: JsonObject; export: JsonObject; tests: { data: unknown; valid: boolean }[] };

const EXPORTS = join(DIRECTORY, "exports");

for (const file of readdirSync(EXPORTS).filter((name) => name.endsWith(".json")).sort()) {
  const cases = JSON.parse(readFileSync(join(EXPORTS, file), "utf8")) as ExportCase[];
  for (const [i, exportCase] of cases.entries()) {
    test(`${file}: ${exportCase.description}`, async () => {
      const registry = Registry.withTypes();
      for (const schema of exportCase.registry ?? []) registry.add(schema);
      registry.add(exportCase.schema);
      const id = exportCase.schema["$id"] as string;
      const exported = new Exporter(registry).inline(id);
      expect(exported).toStrictEqual(exportCase.export);

      const validator = await Validator.create(registry);
      const url = `https://export.test/${file}/${i}`;
      registerSchema(structuredClone(exported), url);
      for (const { data, valid } of exportCase.tests) {
        expect(validator.validate(data, id).valid, JSON.stringify(data)).toBe(valid);
        expect((await validate(url, data as never)).valid, "export: " + JSON.stringify(data)).toBe(valid);
      }
    });
  }
}
