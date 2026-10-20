<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;
use PortableSchema\Pointer;
use PortableSchema\Trace\Tracer;

final class TracingSchema implements Schema
{
    public function __construct(private readonly Schema $inner, private readonly Tracer $tracer)
    {
    }

    public function info(): SchemaInfo
    {
        return $this->inner->info();
    }

    public function validate(ValidationContext $context): ?ValidationError
    {
        $this->tracer->enterSchema(
            Parser::uri($this->inner->info()),
            Pointer::fromSegments($context->fullDataPath()),
            $this->inner->info()->isBoolean(),
        );
        $error = $this->inner->validate($context);
        $this->tracer->leave($error === null);
        return $error;
    }
}
