<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keywords\PatternPropertiesKeyword;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;

/**
 * `patternProperties` that evaluates every pattern, also after one failed, and marks the matching keys as checked.
 */
final class FullPatternPropertiesKeyword extends PatternPropertiesKeyword
{
    public static function from(PatternPropertiesKeyword $keyword): self
    {
        return new self($keyword->value);
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        $properties = $context->getObjectProperties();
        if (!$properties) {
            return null;
        }
        $checked = [];
        $errors = [];
        foreach ($this->value as $pattern => $value) {
            if (!$value instanceof Schema) {
                $value = $this->value[$pattern] = $context->loader()->loadObjectSchema($value);
            }
            $sub = $this->iterateAndValidate($value, $context, $this->matchedProperties((string) $pattern, $properties, $checked));
            if (!$sub->isEmpty()) {
                $errors[] = $this->error($schema, $context, 'patternProperties', 'Properties must match the schema of {pattern}', ['pattern' => $pattern], $sub);
            }
        }
        $context->addCheckedProperties(array_keys($checked));
        $context->addEvaluatedProperties(array_keys($checked));
        return $errors[0] ?? null;
    }
}
