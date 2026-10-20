<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Info\DataInfo;
use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Parsers\KeywordParser;
use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;

/**
 * Boolean subschemas as objects with the internal keyword `portable-boolean`.
 *
 * opis evaluates most boolean subschemas inline, without a schema to trace, and refuses `false` in `allOf`.
 */
final class BooleanSchemas extends KeywordParser
{
    public const KEYWORD = 'portable-boolean';

    private const SINGLE = ['additionalProperties', 'unevaluatedProperties', 'items', 'unevaluatedItems', 'contains', 'propertyNames', 'not', 'if', 'then', 'else'];

    private const MAP = ['properties', 'patternProperties', 'dependentSchemas', '$defs'];

    private const LIST = ['prefixItems', 'allOf', 'anyOf', 'oneOf'];

    public function __construct()
    {
        parent::__construct(self::KEYWORD);
    }

    /**
     * A copy of $schema with every boolean subschema replaced.
     */
    public static function replace(mixed $schema): mixed
    {
        if (!$schema instanceof \stdClass) {
            return $schema;
        }
        $copy = clone $schema;
        foreach (self::SINGLE as $keyword) {
            if (property_exists($copy, $keyword)) {
                $copy->{$keyword} = self::subschema($copy->{$keyword});
            }
        }
        foreach (self::MAP as $keyword) {
            if (($copy->{$keyword} ?? null) instanceof \stdClass) {
                $copy->{$keyword} = (object) array_map(self::subschema(...), get_object_vars($copy->{$keyword}));
            }
        }
        foreach (self::LIST as $keyword) {
            if (is_array($copy->{$keyword} ?? null)) {
                $copy->{$keyword} = array_map(self::subschema(...), $copy->{$keyword});
            }
        }
        return $copy;
    }

    public static function is(SchemaInfo $info): bool
    {
        return $info->isBoolean() || property_exists($info->data(), self::KEYWORD);
    }

    private static function subschema(mixed $schema): mixed
    {
        return is_bool($schema) ? (object) [self::KEYWORD => $schema] : self::replace($schema);
    }

    public function type(): string
    {
        return self::TYPE_BEFORE;
    }

    public function parse(SchemaInfo $info, SchemaParser $parser, object $shared): ?Keyword
    {
        $schema = $info->data();
        if (!$this->keywordExists($schema) || $this->keywordValue($schema) === true) {
            return null;
        }
        return new class () implements Keyword {
            public function validate(ValidationContext $context, Schema $schema): ?ValidationError
            {
                return new ValidationError('false', $schema, DataInfo::fromContext($context), 'No value is valid');
            }
        };
    }
}
