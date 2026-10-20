<?php

declare(strict_types=1);

namespace PortableSchema\Trace;

/**
 * One keyword evaluated at one instance location, with the subschemas it applied.
 */
final class KeywordNode
{
    public bool $valid = true;

    /** @var list<SchemaNode> */
    public array $children = [];

    /**
     * Keyword-specific results, e.g. of portable-typeBy and portable-check.
     */
    public mixed $details = null;

    public function __construct(
        public readonly string $keyword,
        public readonly string $absoluteKeywordLocation,
        public readonly string $instanceLocation,
    ) {
    }
}
