<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;
use PortableSchema\Pointer;
use PortableSchema\Trace\SchemaNode;
use PortableSchema\Trace\Tracer;

final class TracingKeyword implements Keyword
{
    /** Keywords opis evaluates `false` for without a subschema. */
    private const INLINE_FALSE = ['additionalProperties', 'unevaluatedProperties'];

    public function __construct(private readonly Keyword $inner, private readonly string $name, private readonly Tracer $tracer)
    {
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        $location = Pointer::fromSegments($context->fullDataPath());
        $node = $this->tracer->enterKeyword($this->name, Pointer::append(Parser::uri($schema->info()), $this->name), $location);
        $error = $this->inner->validate($context, $schema);
        if ($error && $node->children === [] && in_array($this->name, self::INLINE_FALSE, true)) {
            foreach ($error->args()['properties'] ?? [] as $property) {
                $child = new SchemaNode($node->absoluteKeywordLocation, Pointer::append($location, $property), true);
                $child->valid = false;
                $node->children[] = $child;
            }
        }
        $this->tracer->leave($error === null);
        return $error;
    }
}
