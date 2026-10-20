import * as Browser from "@hyperjump/browser";
import { registerSchema, unregisterSchema } from "@hyperjump/json-schema/draft-2020-12";
import {
  addKeyword, canonicalUri, defineVocabulary, getSchema, Validation,
  type EvaluationPlugin, type SchemaDocument,
} from "@hyperjump/json-schema/experimental";
import type { JsonNode } from "@hyperjump/json-schema/instance/experimental";
import * as Instance from "@hyperjump/json-schema/instance/experimental";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { CheckRequest, type Checks } from "./checks.js";
import * as Formats from "./formats.js";
import type { JsonObject } from "./json.js";
import * as Pointer from "./pointer.js";
import type { Registry } from "./registry.js";
import type { KeywordNode, Tracer } from "./trace.js";
import { resolve, TypeBy } from "./type-by.js";
import * as UriTemplate from "./uri-template.js";

export const DIALECT = "https://portable-schema.dev/v1/schema";

const KEYWORD = "https://portable-schema.dev/keyword/";

/** The validation that is running: keywords report to its tracer and checks. */
let active: { tracer: Tracer; checks: Checks } | null = null;

/** The registry being compiled: portable-typeBy compiles its targets from it. */
let compiling: Registry | null = null;

type TypeByAst = { href: string; source: Record<string, string>; fallback: string | null; base: string; targets: Record<string, string> };

let ready = false;

const setUp = (): void => {
  if (ready) return;
  ready = true;

  addKeyword<string>({
    id: KEYWORD + "format",
    compile: async (schema) => {
      const format = Browser.value<string>(schema);
      if (!Formats.NAMES.includes(format)) throw new Error(`Format ${format} is not allowed`);
      return format;
    },
    interpret: (format, instance) => Formats.check(format, Instance.value(instance)),
  });

  addKeyword<TypeByAst>({
    id: KEYWORD + "typeBy",
    compile: async (schema, ast, parentSchema) => {
      const value = Browser.value<{ href: string; source: Record<string, string>; fallback?: string }>(schema);
      UriTemplate.assertTypeBy(value.href);
      UriTemplate.assertTypeBy(value.fallback ?? "");
      const base = canonicalUri(parentSchema).split("#")[0]!;
      const fallback = value.fallback === undefined ? null : resolve(value.fallback, base);
      const pattern = templatePattern(resolve(value.href, base));
      const targets: Record<string, string> = {};
      for (const id of compiling?.ids() ?? []) {
        if (pattern.test(id) || id === fallback) {
          const target = await getSchema(id);
          targets[id] = await Validation.compile(target, ast, target);
        }
      }
      return { href: value.href, source: value.source, fallback: value.fallback ?? null, base, targets };
    },
    interpret: (typeBy, instance, context) => {
      const selection = TypeBy.select(
        typeBy.href, typeBy.source, typeBy.fallback, typeBy.base,
        instance.pointer, Instance.value(instance.root), (uri) => uri in typeBy.targets,
      );
      (active!.tracer.current() as KeywordNode).details = selection;
      return selection.target !== null && Validation.interpret(typeBy.targets[selection.target]!, instance, context);
    },
  });

  addKeyword<Record<string, JsonObject>>({
    id: KEYWORD + "check",
    compile: async (schema) => Browser.value<Record<string, JsonObject>>(schema),
    interpret: (checks, instance) => {
      const location = instance.pointer;
      const root = Instance.value(instance.root);
      const [, parent] = Pointer.get(root, Pointer.fromSegments(Pointer.segments(location).slice(0, -1)));
      const requests = Object.entries(checks).map(([name, options]) =>
        new CheckRequest(name, Instance.value(instance), location, options, location === "" ? null : parent, root));
      for (const request of requests) active!.checks.add(request);
      (active!.tracer.current() as KeywordNode).details = requests;
      return true;
    },
  });

  // Portable Schema pins its own format checks: its dialect is the only one asserting formats here.
  defineVocabulary("https://json-schema.org/draft/2020-12/vocab/format-assertion", { format: KEYWORD + "format" });
  defineVocabulary("https://portable-schema.dev/v1/vocab", {
    "portable-typeBy": KEYWORD + "typeBy",
    "portable-check": KEYWORD + "check",
  });
  const spec = (file: string) => JSON.parse(readFileSync(fileURLToPath(new URL(`../../spec/v1/${file}`, import.meta.url)), "utf8"));
  registerSchema(spec("vocab.json"));
  registerSchema(spec("schema.json"));
};

/** Matches the URIs a URI template can expand to. */
const templatePattern = (template: string): RegExp =>
  new RegExp("^" + template.split(/\{[^}]*\}/u).map((part) => part.replace(/[.*+?^${}()|[\]\\]/gu, "\\$&")).join(".*") + "$", "u");

export type Compiled = { ast: Parameters<typeof Validation.interpret>[2]["ast"]; urls: Map<string, string> };

/** Hyperjump's schema store is global: compiles run one at a time. */
let queue: Promise<unknown> = Promise.resolve();

/** Compiles every schema of the registry into one AST. */
export const compile = (registry: Registry): Promise<Compiled> => {
  const compiled = queue.then(() => compileNow(registry));
  queue = compiled.catch(() => undefined);
  return compiled;
};

const compileNow = async (registry: Registry): Promise<Compiled> => {
  setUp();
  for (const id of registry.ids()) {
    unregisterSchema(id);
    registerSchema(structuredClone(registry.get(id)), id, DIALECT);
  }
  const ast = { metaData: {}, plugins: new Set<EvaluationPlugin>() } as unknown as Compiled["ast"];
  const urls = new Map<string, string>();
  compiling = registry;
  try {
    for (const id of registry.ids()) {
      const schema = await getSchema(id);
      urls.set(id, await Validation.compile(schema, ast, schema));
    }
  } finally {
    compiling = null;
  }
  return { ast, urls };
};

/** Evaluates a compiled schema, recording the evaluation with the tracer. */
export const evaluate = (compiled: Compiled, url: string, data: unknown, tracer: Tracer, checks: Checks): void => {
  const plugin: EvaluationPlugin = {
    beforeSchema: (schemaUrl, instance, context) => {
      tracer.enterSchema(schemaUrl, instance.pointer, typeof context.ast[schemaUrl] === "boolean");
    },
    beforeKeyword: (node, instance) => {
      const segments = Pointer.segments(node[1].split("#")[1] ?? "");
      tracer.enterKeyword(segments[segments.length - 1] ?? "", node[1], instance.pointer);
    },
    afterKeyword: (_node, _instance, _context, valid) => tracer.leave(valid),
    afterSchema: (_url, _instance, _context, valid) => tracer.leave(valid),
  };
  active = { tracer, checks };
  try {
    Validation.interpret(url, Instance.fromJs(data as Parameters<typeof Instance.fromJs>[0]) as JsonNode, {
      ast: compiled.ast,
      plugins: [...compiled.ast.plugins, plugin],
    });
  } finally {
    active = null;
  }
};
