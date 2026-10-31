<?php

declare(strict_types=1);

namespace PortableSchema\Trace;

/**
 * One schema evaluated at one instance location.
 */
final class SchemaNode
{
    public bool $valid = true;

    /** @var list<KeywordNode> */
    public array $keywords = [];

    public function __construct(
        public readonly string $uri,
        public readonly string $instanceLocation,
        public readonly bool $boolean,
    ) {
    }
}
