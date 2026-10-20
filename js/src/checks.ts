/** One value a named check is asked about. */
export class CheckRequest {
  result: CheckResult | null = null;

  constructor(
    readonly name: string,
    readonly value: unknown,
    readonly location: string,
    readonly options: Record<string, unknown>,
    readonly parent: unknown,
    readonly root: unknown,
  ) {}
}

export type CheckError = { code: string; params: Record<string, unknown>; message?: string };

/** The outcome of a check for one request: errors, or why it could not run. */
export class CheckResult {
  constructor(
    readonly errors: CheckError[] = [],
    readonly unchecked: string | null = null,
  ) {}

  static unchecked(reason: string): CheckResult {
    return new CheckResult([], reason);
  }
}

export interface CheckProvider {
  /** One result per request, in the same order. */
  check(name: string, requests: CheckRequest[]): CheckResult[];
}

export type CheckCallback = (requests: CheckRequest[]) => CheckResult[];

/** Collects check requests during evaluation and runs them batched per name. */
export class Checks {
  private requests: CheckRequest[] = [];

  reset(): void {
    this.requests = [];
  }

  add(request: CheckRequest): void {
    this.requests.push(request);
  }

  /** Order: local callback, then provider, else unchecked with reason noProvider. */
  run(callbacks: Map<string, CheckCallback>, providers: Map<string, CheckProvider>): void {
    const byName = new Map<string, CheckRequest[]>();
    for (const request of this.requests) {
      byName.set(request.name, [...(byName.get(request.name) ?? []), request]);
    }
    for (const [name, requests] of byName) {
      let results: CheckResult[];
      try {
        const callback = callbacks.get(name);
        const provider = providers.get(name);
        results = callback ? callback(requests)
          : provider ? provider.check(name, requests)
          : requests.map(() => CheckResult.unchecked("noProvider"));
      } catch {
        results = requests.map(() => CheckResult.unchecked("error"));
      }
      requests.forEach((request, i) => {
        request.result = results[i] ?? CheckResult.unchecked("error");
      });
    }
  }
}
