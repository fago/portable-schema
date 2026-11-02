<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keywords\AllOfKeyword;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;

/**
 * Evaluates every allOf branch, also after one failed, as the JS validator does.
 */
final class FullAllOfKeyword extends AllOfKeyword
{
    public static function from(AllOfKeyword $keyword): self
    {
        return new self($keyword->value);
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        $object = $this->createArrayObject($context);
        $errors = [];
        foreach ($this->value as $index => $value) {
            if (!$value instanceof Schema) {
                $value = $this->value[$index] = $context->loader()->loadObjectSchema($value);
            }
            if ($error = $context->validateSchemaWithoutEvaluated($value, null, false, $object)) {
                $errors[] = $error;
            }
        }
        $this->addEvaluatedFromArrayObject($object, $context);
        return $errors === [] ? null : $this->error($schema, $context, 'allOf', 'The data should match all schemas', [], $errors);
    }
}
