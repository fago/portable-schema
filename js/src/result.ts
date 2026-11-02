/** One problem, in the result format of spec/errors.md. */
export class ValidationError {
  constructor(
    readonly instanceLocation: string,
    readonly keywordLocation: string,
    readonly absoluteKeywordLocation: string,
    readonly code: string,
    readonly params: Record<string, unknown> = {},
    readonly message: string | null = null,
  ) {}

  toJSON(): Record<string, unknown> {
    const json: Record<string, unknown> = {
      instanceLocation: this.instanceLocation,
      keywordLocation: this.keywordLocation,
      absoluteKeywordLocation: this.absoluteKeywordLocation,
      code: this.code,
      params: this.params,
    };
    if (this.message !== null) json["message"] = this.message;
    return json;
  }
}

/** A check that could not run. */
export class Unchecked {
  constructor(
    readonly instanceLocation: string,
    readonly code: string,
    readonly reason: string,
  ) {}

  toJSON(): { instanceLocation: string; code: string; reason: string } {
    return { instanceLocation: this.instanceLocation, code: this.code, reason: this.reason };
  }
}

/** Annotations at one instance location, from the schemas in effect there. The outermost schema wins. */
export class Annotations {
  /**
   * @param messages custom message source texts by code
   * @param allowedTitles titles of the allowed values of an enum-like oneOf
   */
  constructor(
    public title: string | null = null,
    public messages: Record<string, string> = {},
    public allowedTitles: string[] | null = null,
  ) {}
}

export class Result {
  readonly valid: boolean;

  constructor(
    readonly errors: ValidationError[],
    readonly unchecked: Unchecked[],
    readonly annotationsByLocation: Map<string, Annotations>,
  ) {
    this.valid = errors.length === 0;
  }

  annotations(location: string): Annotations {
    return this.annotationsByLocation.get(location) ?? new Annotations();
  }

  toJSON(): { valid: boolean; errors: Record<string, unknown>[]; unchecked: ReturnType<Unchecked["toJSON"]>[] } {
    return {
      valid: this.valid,
      errors: this.errors.map((e) => e.toJSON()),
      unchecked: this.unchecked.map((u) => u.toJSON()),
    };
  }
}
