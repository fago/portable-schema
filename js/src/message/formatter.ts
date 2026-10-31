import * as Pointer from "../pointer.js";
import type { Result, Unchecked, ValidationError } from "../result.js";
import { Catalog } from "./catalog.js";
import { MessageRequest, StandaloneTranslator, type Translator } from "./translator.js";

/** Message id for codes without a catalog entry. */
export const FALLBACK = "invalid";

/** Label of the root value, which has no property name. */
export const ROOT_LABEL = "Value";

/** Turns errors into message requests, using the catalog and the annotations of the result. */
export class Formatter {
  constructor(private readonly catalog: Catalog = new Catalog({})) {}

  static create(): Formatter {
    return new Formatter(Catalog.default());
  }

  format(error: ValidationError, result: Result): MessageRequest {
    const params: Record<string, unknown> = { ...error.params };
    const location = error.instanceLocation;
    if (error.code === "required" || error.code === "dependentRequired") {
      params["label"] = label(result, Pointer.append(location, String(params["property"])));
      if (params["dependency"] !== undefined) {
        params["dependencyLabel"] = label(result, Pointer.append(location, String(params["dependency"])));
      }
    } else {
      params["label"] = label(result, location);
    }
    if (params["allowed"] !== undefined) {
      params["allowed"] = result.annotations(location).allowedTitles ?? params["allowed"];
    }

    const id = this.catalog.has(error.code) ? error.code : FALLBACK;
    const custom = result.annotations(location).messages[error.code] ?? error.message;
    if (custom !== null) return new MessageRequest(id, custom, texts(params));
    const [source, count] = this.catalog.source(id, params);
    return new MessageRequest(id, source, texts(params), count);
  }

  formatUnchecked(unchecked: Unchecked, result: Result): MessageRequest {
    const params = { label: label(result, unchecked.instanceLocation) };
    const [source] = this.catalog.source("unchecked", params);
    return new MessageRequest("unchecked", source, texts(params));
  }

  /** The English text of every error, in order. */
  messages(result: Result, translator: Translator = new StandaloneTranslator()): string[] {
    return result.errors.map((error) => {
      const request = this.format(error, result);
      return translator.translate(request.id, request.source, request.params, request.count);
    });
  }
}

const label = (result: Result, location: string): string => {
  const title = result.annotations(location).title;
  if (title !== null) return title;
  const segments = Pointer.segments(location);
  return segments.length === 0 ? ROOT_LABEL : segments[segments.length - 1]!;
};

const texts = (params: Record<string, unknown>): Record<string, string> =>
  Object.fromEntries(Object.entries(params).map(([key, value]) => [key, text(value)]));

const text = (value: unknown): string => {
  if (typeof value === "string") return value;
  if (typeof value === "boolean" || typeof value === "number" || value === null) return JSON.stringify(value);
  if (Array.isArray(value)) return value.map(text).join(", ");
  return Object.values(value as object).map(text).join(", ");
};
