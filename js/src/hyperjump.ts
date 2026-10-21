import * as Browser from "@hyperjump/browser";
import { registerSchema, unregisterSchema, validate } from "@hyperjump/json-schema/draft-2020-12";
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
      UriTemplate.assertTypeBy(value.href, value.fallback ?? null);
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
  // The dialect meta-schema plus the profile's limits.
  registerSchema(spec("load.json"));
};

/** Matches the URIs a URI template can expand to. */
const templatePattern = (template: string): RegExp =>
  new RegExp("^" + template.split(/\{[^}]*\}/u).map((part) => part.replace(/[.*+?^${}()|[\]\\]/gu, "\\$&")).join(".*") + "$", "u");

/** `urls`: Hyperjump URL by registry id; `ids`: registry id by Hyperjump URL without fragment. */
export type Compiled = { ast: Parameters<typeof Validation.interpret>[2]["ast"]; urls: Map<string, string>; ids: Map<string, string> };

/** Hyperjump's schema store is global: compiles run one at a time. */
let queue: Promise<unknown> = Promise.resolve();

/** Compiles every schema of the registry into one AST. */
export const compile = (registry: Registry): Promise<Compiled> => {
  const compiled = queue.then(() => compileNow(registry));
  queue = compiled.catch(() => undefined);
  return compiled;
};

/** Ids in Hyperjump's store, from the last compile. */
let registered: string[] = [];

const compileNow = async (registry: Registry): Promise<Compiled> => {
  setUp();
  registered.forEach((id) => unregisterSchema(id));
  registered = [...registry.ids()];
  for (const id of registered) {
    unregisterSchema(id);
    registerSchema(structuredClone(registry.get(id)), id, DIALECT);
  }
  const ast = { metaData: {}, plugins: new Set<EvaluationPlugin>() } as unknown as Compiled["ast"];
  const urls = new Map<string, string>();
  const ids = new Map<string, string>();
  const load = await validate("https://portable-schema.dev/v1/load");
  for (const id of registered) {
    if (!load(registry.get(id)).valid) throw new Error(`Invalid schema ${id}`);
  }
  compiling = registry;
  try {
    for (const id of registry.ids()) {
      const schema = await getSchema(id);
      const url = await Validation.compile(schema, ast, schema);
      urls.set(id, url);
      ids.set(url.split("#")[0]!, id);
    }
  } finally {
    compiling = null;
  }
  // Hyperjump also holds the meta-schemas, which are not in the registry.
  const unknown = Object.keys(ast).find((url) => url.includes(":") && !ids.has(url.split("#")[0]!));
  if (unknown !== undefined) throw new Error(`Unknown schema ${unknown}`);
  return { ast, urls, ids };
};

/**
 * A Hyperjump URL as the PHP validator writes it:
 * - the registry id, since Hyperjump normalizes percent-encoding (`%2B` → `+`)
 * - the fragment encoded, since Hyperjump leaves `#` unencoded
 */
const normalize = (url: string, ids: Map<string, string>): string => {
  const hash = url.indexOf("#");
  const base = hash === -1 ? url : url.slice(0, hash);
  const id = ids.get(base) ?? base;
  return hash === -1 ? id : id + "#" + Pointer.toFragment(decodeURIComponent(url.slice(hash + 1)));
};

/** Evaluates a compiled schema, recording the evaluation with the tracer. */
export const evaluate = (compiled: Compiled, url: string, data: unknown, tracer: Tracer, checks: Checks): void => {
  const plugin: EvaluationPlugin = {
    beforeSchema: (schemaUrl, instance, context) => {
      tracer.enterSchema(normalize(schemaUrl, compiled.ids), instance.pointer, typeof context.ast[schemaUrl] === "boolean");
    },
    beforeKeyword: (node, instance) => {
      const url = normalize(node[1], compiled.ids);
      const segments = Pointer.segments(decodeURIComponent(url.split("#")[1] ?? ""));
      tracer.enterKeyword(segments[segments.length - 1] ?? "", url, instance.pointer);
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
