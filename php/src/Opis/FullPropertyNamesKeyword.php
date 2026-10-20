<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keywords\PropertyNamesKeyword;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;

/**
 * Checks every property name, also after one failed, as the JS validator does.
 */
final class FullPropertyNamesKeyword extends PropertyNamesKeyword
{
    public static function from(PropertyNamesKeyword $keyword): self
    {
        return new self($keyword->value);
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        if (is_bool($this->value)) {
            return parent::validate($context, $schema);
        }
        if (!$this->value instanceof Schema) {
            $this->value = $context->loader()->loadObjectSchema($this->value);
        }
        $errors = [];
        foreach ($context->getObjectProperties() ?? [] as $property) {
            if ($error = $this->value->validate($context->newInstance($property, $schema))) {
                $errors[] = $error;
            }
        }
        return $errors === [] ? null : $this->error($schema, $context, 'propertyNames', 'Invalid property names', [], $errors);
    }
}
