import { MESSAGES } from "../embedded.js";

type Entry = { text: string } | { plural: string; one: string; other: string };

/** Message source texts by id, in the format of decision 0001. */
export class Catalog {
  constructor(private readonly entries: Record<string, Entry>) {}

  static default(): Catalog {
    return new Catalog(JSON.parse(MESSAGES) as Record<string, Entry>);
  }

  has(id: string): boolean {
    return Object.hasOwn(this.entries, id);
  }

  /** The source text for `id`; for plural entries, the English form for `params[plural]`. Returns [source, count]. */
  source(id: string, params: Record<string, unknown>): [string, number | null] {
    const entry = this.entries[id]!;
    if (!("plural" in entry)) return [entry.text, null];
    const count = Math.trunc(Number(params[entry.plural] ?? 0));
    return [count === 1 ? entry.one : entry.other, count];
  }
}
