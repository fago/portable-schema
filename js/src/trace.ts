/** One schema evaluated at one instance location. */
export class SchemaNode {
  valid = true;
  keywords: KeywordNode[] = [];

  constructor(
    readonly uri: string,
    readonly instanceLocation: string,
    readonly boolean: boolean,
  ) {}
}

/** One keyword evaluated at one instance location, with the subschemas it applied. */
export class KeywordNode {
  valid = true;
  children: SchemaNode[] = [];

  /** Keyword-specific results, e.g. of portable-typeBy and portable-check. */
  details: unknown = null;

  constructor(
    readonly keyword: string,
    readonly absoluteKeywordLocation: string,
    readonly instanceLocation: string,
  ) {}
}

/** Records the evaluation as a tree of schema and keyword nodes. */
export class Tracer {
  private stack: (SchemaNode | KeywordNode)[] = [];
  private rootNode: SchemaNode | null = null;

  reset(): void {
    this.stack = [];
    this.rootNode = null;
  }

  root(): SchemaNode | null {
    return this.rootNode;
  }

  current(): SchemaNode | KeywordNode | null {
    return this.stack[this.stack.length - 1] ?? null;
  }

  enterSchema(uri: string, instanceLocation: string, boolean: boolean): SchemaNode {
    const node = new SchemaNode(uri, instanceLocation, boolean);
    const parent = this.current();
    if (parent instanceof KeywordNode) parent.children.push(node);
    else if (parent === null) this.rootNode = node;
    this.stack.push(node);
    return node;
  }

  enterKeyword(keyword: string, absoluteKeywordLocation: string, instanceLocation: string): KeywordNode {
    const node = new KeywordNode(keyword, absoluteKeywordLocation, instanceLocation);
    const parent = this.current();
    if (parent instanceof SchemaNode) parent.keywords.push(node);
    this.stack.push(node);
    return node;
  }

  leave(valid: boolean): void {
    const node = this.stack.pop();
    if (node !== undefined) node.valid = valid;
  }
}
