<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keywords\ContainsKeyword;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;

/**
 * Evaluates every item, and marks only the matching ones as evaluated, as 2020-12 and the JS validator do.
 */
final class FullContainsKeyword extends ContainsKeyword
{
    public static function from(ContainsKeyword $keyword): self
    {
        return new self($keyword->value, $keyword->min, $keyword->max);
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        if (!$this->value instanceof Schema) {
            $this->value = $context->loader()->loadObjectSchema($this->value);
        }
        $matching = [];
        foreach ($context->currentData() as $index => $item) {
            $context->pushDataPath($index);
            if (!$this->value->validate($context)) {
                $matching[] = $index;
            }
            $context->popDataPath();
        }
        $context->addEvaluatedItems($matching);
        $count = count($matching);
        if ($count < ($this->min ?? 1)) {
            return $this->error($schema, $context, $this->min === null ? 'contains' : 'minContains', 'Too few array items match', ['count' => $count]);
        }
        if ($this->max !== null && $count > $this->max) {
            return $this->error($schema, $context, 'maxContains', 'Too many array items match', ['count' => $count]);
        }
        return null;
    }
}
