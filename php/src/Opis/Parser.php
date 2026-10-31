<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\KeywordValidator;
use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\Parsers\Vocabulary;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Schemas\BooleanSchema;
use Opis\JsonSchema\Schemas\ObjectSchema;
use PortableSchema\Check\Checks;
use PortableSchema\Trace\Tracer;

/**
 * Parses Portable Schemas into opis schemas that record their evaluation.
 */
final class Parser extends SchemaParser
{
    public const DIALECT = 'https://portable-schema.dev/v1/schema';

    /** Keywords whose value is a non-negative integer; `1.0` is one. */
    private const INTEGERS = ['maxLength', 'minLength', 'maxItems', 'minItems', 'maxContains', 'minContains', 'maxProperties', 'minProperties'];

    public function __construct(private readonly Tracer $tracer, private readonly Checks $checks, private readonly MapDecisions $decisions)
    {
        parent::__construct([], [
            'allowFilters' => false,
            'allowMappers' => false,
            'allowTemplates' => false,
            'allowGlobals' => false,
            'allowDefaults' => false,
            'allowSlots' => false,
            'allowKeywordValidators' => false,
            'allowPragmas' => false,
            'allowDataKeyword' => false,
            'allowRelativeJsonPointerInRef' => false,
            'allowExclusiveMinMaxAsBool' => false,
            'keepDependenciesKeyword' => false,
            'keepAdditionalItemsKeyword' => false,
            'decodeContent' => [],
            'defaultDraft' => '2020-12',
        ]);
    }

    protected function getDrafts(?Vocabulary $extraVocabulary): array
    {
        return ['2020-12' => new Draft($this->tracer, $this->checks)];
    }

    public function parseDraftVersion(string $schema): ?string
    {
        return $schema === self::DIALECT ? '2020-12' : parent::parseDraftVersion($schema);
    }

    /**
     * Throws a refusal when parsing, where opis defers it to the first validation.
     */
    public function parseSchema(SchemaInfo $info): Schema
    {
        return new TracingSchema($info->isBoolean() ? new BooleanSchema($info) : $this->parseSchemaObject($info), $this->tracer);
    }

    protected function parseSchemaKeywords(SchemaInfo $info, ?KeywordValidator $keywordValidator, array $parsers, object $shared, bool $hasRef = false): Schema
    {
        $data = $info->data();
        foreach (self::INTEGERS as $name) {
            // Integers beyond PHP_INT_MAX limit nothing more than PHP_INT_MAX does.
            if (is_float($data->{$name} ?? null)) {
                $data->{$name} = $data->{$name} >= PHP_INT_MAX ? PHP_INT_MAX : (int) $data->{$name};
            }
        }
        $schema = parent::parseSchemaKeywords($info, $keywordValidator, $parsers, $shared, $hasRef);
        return $schema instanceof ObjectSchema ? FullObjectSchema::from($schema, $this->decisions) : $schema;
    }

    /**
     * The absolute URI of a schema: its document plus a JSON Pointer fragment.
     */
    public static function uri(SchemaInfo $info): string
    {
        $root = $info->root() ?? $info->base() ?? $info->id();
        $document = explode('#', (string) $root, 2)[0];
        return $document . '#' . \PortableSchema\Pointer::toFragment(\PortableSchema\Pointer::fromSegments($info->path()));
    }
}
