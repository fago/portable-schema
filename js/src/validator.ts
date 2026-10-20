import { type CheckCallback, type CheckProvider, Checks } from "./checks.js";
import { compile, evaluate, type Compiled } from "./hyperjump.js";
import type { Registry } from "./registry.js";
import type { Result } from "./result.js";
import { Shaper } from "./shaper.js";
import { Tracer } from "./trace.js";

export type ValidateOptions = {
  partial?: boolean;
  emptyAsMissing?: boolean;
  locations?: string[] | null;
};

/**
 * Validates data against Portable Schemas. Data follows the JSON data model.
 */
export class Validator {
  private callbacks = new Map<string, CheckCallback>();
  private providers = new Map<string, CheckProvider>();

  private constructor(private readonly registry: Registry, private readonly compiled: Compiled) {}

  /** Compiles the registry's schemas, so that validation is synchronous. */
  static async create(registry: Registry): Promise<Validator> {
    return new Validator(registry, await compile(registry));
  }

  /** A validator that runs `callback` for the named check, overriding any provider. */
  withCheck(name: string, callback: CheckCallback): Validator {
    const clone = this.clone();
    clone.callbacks.set(name, callback);
    return clone;
  }

  withProvider(name: string, provider: CheckProvider): Validator {
    const clone = this.clone();
    clone.providers.set(name, provider);
    return clone;
  }

  /** @param id the schema's id in the registry */
  validate(data: unknown, id: string, options: ValidateOptions = {}): Result {
    const url = this.compiled.urls.get(id);
    if (url === undefined) throw new Error(`Unknown schema ${id}`);

    const tracer = new Tracer();
    const checks = new Checks();
    evaluate(this.compiled, url, data, tracer, checks);
    checks.run(this.callbacks, this.providers);

    const { partial = false, emptyAsMissing = false, locations = null } = options;
    return new Shaper(this.registry, data, partial, emptyAsMissing, locations).shape(tracer.root()!);
  }

  private clone(): Validator {
    const clone = new Validator(this.registry, this.compiled);
    clone.callbacks = new Map(this.callbacks);
    clone.providers = new Map(this.providers);
    return clone;
  }
}
