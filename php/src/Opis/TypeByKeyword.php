<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Keywords\ErrorTrait;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Uri;
use Opis\JsonSchema\ValidationContext;
use PortableSchema\Pointer;
use PortableSchema\Trace\Tracer;
use PortableSchema\TypeBy;

/**
 * portable-typeBy: evaluates the schema selected by data values.
 */
final class TypeByKeyword implements Keyword
{
    use ErrorTrait;

    /**
     * @param array<string, string> $source
     */
    public function __construct(
        private readonly string $href,
        private readonly array $source,
        private readonly ?string $fallback,
        private readonly string $base,
        private readonly Tracer $tracer,
    ) {
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        $loader = $context->loader();
        $selection = TypeBy::select(
            $this->href,
            $this->source,
            $this->fallback,
            $this->base,
            Pointer::fromSegments($context->fullDataPath()),
            Context::root($context),
            fn (string $uri): bool => ($parsed = Uri::parse($uri, true)) !== null && $loader->loadSchemaById($parsed) !== null,
        );
        $this->tracer->current()->details = $selection;

        if ($selection->target === null) {
            return $this->error($schema, $context, 'portable-typeBy', 'Unresolved type');
        }
        $target = $loader->loadSchemaById(Uri::parse($selection->target, true));
        $error = $target->validate($context->create($schema));
        return $error ? $this->error($schema, $context, 'portable-typeBy', 'The data must match the selected type', [], $error) : null;
    }
}
