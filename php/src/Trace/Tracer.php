<?php

declare(strict_types=1);

namespace PortableSchema\Trace;

/**
 * Records the evaluation as a tree of schema and keyword nodes.
 */
final class Tracer
{
    /** @var list<SchemaNode|KeywordNode> */
    private array $stack = [];

    private ?SchemaNode $root = null;

    public function reset(): void
    {
        $this->stack = [];
        $this->root = null;
    }

    public function root(): ?SchemaNode
    {
        return $this->root;
    }

    public function current(): SchemaNode|KeywordNode|null
    {
        return $this->stack[count($this->stack) - 1] ?? null;
    }

    public function enterSchema(string $uri, string $instanceLocation, bool $boolean): SchemaNode
    {
        $node = new SchemaNode($uri, $instanceLocation, $boolean);
        $parent = $this->current();
        if ($parent instanceof KeywordNode) {
            $parent->children[] = $node;
        } elseif ($parent === null) {
            $this->root = $node;
        }
        $this->stack[] = $node;
        return $node;
    }

    public function enterKeyword(string $keyword, string $absoluteKeywordLocation, string $instanceLocation): KeywordNode
    {
        $node = new KeywordNode($keyword, $absoluteKeywordLocation, $instanceLocation);
        $parent = $this->current();
        if ($parent instanceof SchemaNode) {
            $parent->keywords[] = $node;
        }
        $this->stack[] = $node;
        return $node;
    }

    public function leave(bool $valid): void
    {
        $node = array_pop($this->stack);
        if ($node !== null) {
            $node->valid = $valid;
        }
    }
}
