<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Keywords\ErrorTrait;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;
use PortableSchema\CatalogSet;

/**
 * `enum` or `oneOf` checked by one lookup in a catalog's set.
 */
final class CatalogSetKeyword implements Keyword
{
    use ErrorTrait;

    public function __construct(private readonly string $keyword, private readonly CatalogSet $catalog)
    {
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        return $this->catalog->contains($context->currentData()) ? null : $this->error($schema, $context, $this->keyword, 'The data must be one of the allowed values');
    }
}
