<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;
use PortableSchema\Pointer;
use PortableSchema\Trace\Tracer;

final class TracingKeyword implements Keyword
{
    public function __construct(private readonly Keyword $inner, private readonly string $name, private readonly Tracer $tracer)
    {
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        $location = Pointer::fromSegments($context->fullDataPath());
        $this->tracer->enterKeyword($this->name, Pointer::append(Parser::uri($schema->info()), $this->name), $location);
        $error = $this->inner->validate($context, $schema);
        $this->tracer->leave($error === null);
        return $error;
    }
}
