import { readdirSync, readFileSync } from "node:fs";
import { join } from "node:path";
import * as Draft04 from "@hyperjump/json-schema/draft-04";
import { registerSchema, validate } from "@hyperjump/json-schema/draft-2020-12";
import { expect, test } from "vitest";
import { Exporter, Registry, Validator, type JsonObject } from "../src/index.js";
import { DIRECTORY } from "./fixtures.js";

type ExportCase = {
  description: string;
  registry?: JsonObject[];
  schema: JsonObject;
  export?: JsonObject;
  draft04?: JsonObject | null;
  tests: { data: unknown; valid: boolean }[];
};

const EXPORTS = join(DIRECTORY, "exports");

for (const file of readdirSync(EXPORTS).filter((name) => name.endsWith(".json")).sort()) {
  const cases = JSON.parse(readFileSync(join(EXPORTS, file), "utf8")) as ExportCase[];
  for (const [i, exportCase] of cases.entries()) {
    test(`${file}: ${exportCase.description}`, async () => {
      const registry = Registry.withTypes();
      for (const schema of exportCase.registry ?? []) registry.add(schema);
      registry.add(exportCase.schema);
      const id = exportCase.schema["$id"] as string;
      const exporter = new Exporter(registry);
      const validator = await Validator.create(registry);
      for (const { data, valid } of exportCase.tests) {
        expect(validator.validate(data, id).valid, JSON.stringify(data)).toBe(valid);
      }
      if (exportCase.export !== undefined) {
        const exported = exporter.inline(id);
        expect(exported).toStrictEqual(exportCase.export);
        const url = `https://export.test/${file}/${i}`;
        registerSchema(structuredClone(exported), url);
        for (const { data, valid } of exportCase.tests) {
          expect((await validate(url, data as never)).valid, "export: " + JSON.stringify(data)).toBe(valid);
        }
      }
      if (exportCase.draft04 === null) {
        expect(() => exporter.draft04(id)).toThrow();
      } else if (exportCase.draft04 !== undefined) {
        const exported = exporter.draft04(id);
        expect(exported).toStrictEqual(exportCase.draft04);
        const url = `https://export.test/draft-04/${file}/${i}`;
        Draft04.registerSchema(structuredClone(exported), url);
        for (const { data, valid } of exportCase.tests) {
          expect((await Draft04.validate(url, data as never)).valid, "draft-04: " + JSON.stringify(data)).toBe(valid);
        }
      }
    });
  }
}
