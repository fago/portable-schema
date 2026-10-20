<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keywords\DependentSchemasKeyword;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;

/**
 * `dependentSchemas` that evaluates every dependent schema, also after one failed.
 */
final class FullDependentSchemasKeyword extends DependentSchemasKeyword
{
    public static function from(DependentSchemasKeyword $keyword): self
    {
        return new self((object) $keyword->value);
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        $data = $context->currentData();
        $object = $this->createArrayObject($context);
        $errors = [];
        foreach ($this->value as $name => $value) {
            if (!property_exists($data, (string) $name)) {
                continue;
            }
            if (!$value instanceof Schema) {
                $value = $this->value[$name] = $context->loader()->loadObjectSchema($value);
            }
            if ($error = $context->validateSchemaWithoutEvaluated($value, null, false, $object)) {
                $errors[] = $error;
            }
        }
        $this->addEvaluatedFromArrayObject($object, $context);
        return $errors[0] ?? null;
    }
}
