<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Keywords\ErrorTrait;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;
use PortableSchema\Formats;

final class FormatKeyword implements Keyword
{
    use ErrorTrait;

    public function __construct(private readonly string $format)
    {
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        return Formats::check($this->format, $context->currentData())
            ? null
            : $this->error($schema, $context, 'format', 'Invalid format');
    }
}
