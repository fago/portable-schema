/** A message to translate: the catalog id, the English source text, its params and the plural count. */
export class MessageRequest {
  /** @param params plain-text values for the placeholders */
  constructor(
    readonly id: string,
    readonly source: string,
    readonly params: Record<string, string>,
    readonly count: number | null = null,
  ) {}
}

export interface Translator {
  translate(id: string, source: string, params: Record<string, string>, count: number | null): string;
}

/** Renders the English source text: `{name}` is replaced with the plain value. */
export class StandaloneTranslator implements Translator {
  translate(_id: string, source: string, params: Record<string, string>, _count: number | null): string {
    return source.replace(/\{([A-Za-z0-9_]+)\}/gu, (match, name: string) => params[name] ?? match);
  }
}
