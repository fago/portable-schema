<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keywords\PropertiesKeyword;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;

/**
 * `properties` that marks its keys as checked also when one fails: `additionalProperties` never applies to them.
 */
final class FullPropertiesKeyword extends PropertiesKeyword
{
    public static function from(PropertiesKeyword $keyword): self
    {
        return new self($keyword->properties);
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        $error = parent::validate($context, $schema);
        $context->addCheckedProperties(array_values(array_intersect($this->propertyKeys, $context->getObjectProperties() ?? [])));
        return $error;
    }
}
