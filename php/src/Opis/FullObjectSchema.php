<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Info\DataInfo;
use Opis\JsonSchema\Schemas\ObjectSchema;
use Opis\JsonSchema\ValidationContext;

/**
 * Evaluates every keyword, also after one failed, as the JS validator does.
 */
final class FullObjectSchema extends ObjectSchema
{
    public static function from(ObjectSchema $schema): self
    {
        return new self($schema->info(), null, $schema->types, $schema->before, $schema->after);
    }

    public function doValidate(ValidationContext $context): ?ValidationError
    {
        $keywords = $this->before ?? [];
        if ($this->types && ($type = $context->currentDataType())) {
            $keywords = array_merge($keywords, $this->types[$type] ?? []);
            $super = Helper::getJsonSuperType($type);
            if ($super && isset($this->types[$super])) {
                $keywords = array_merge($keywords, $this->types[$super]);
            }
        }
        $keywords = array_merge($keywords, $this->after ?? []);

        $errors = [];
        foreach ($keywords as $keyword) {
            if ($error = $keyword->validate($context, $this)) {
                $errors[] = $error;
            }
        }
        return match (count($errors)) {
            0 => null,
            1 => $errors[0],
            default => new ValidationError('', $this, DataInfo::fromContext($context), 'Data must match schema', [], $errors),
        };
    }
}
