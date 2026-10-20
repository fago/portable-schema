<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\KeywordValidator;
use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\Parsers\Vocabulary;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Schemas\ObjectSchema;
use PortableSchema\Check\Checks;
use PortableSchema\Trace\Tracer;

/**
 * Parses Portable Schemas into opis schemas that record their evaluation.
 */
final class Parser extends SchemaParser
{
    public const DIALECT = 'https://portable-schema.dev/v1/schema';

    public function __construct(private readonly Tracer $tracer, private readonly Checks $checks)
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

    public function parseSchema(SchemaInfo $info): Schema
    {
        return new TracingSchema(parent::parseSchema($info), $this->tracer);
    }

    protected function parseSchemaKeywords(SchemaInfo $info, ?KeywordValidator $keywordValidator, array $parsers, object $shared, bool $hasRef = false): Schema
    {
        $schema = parent::parseSchemaKeywords($info, $keywordValidator, $parsers, $shared, $hasRef);
        return $schema instanceof ObjectSchema ? FullObjectSchema::from($schema) : $schema;
    }

    /**
     * The absolute URI of a schema: its document plus a JSON Pointer fragment.
     */
    public static function uri(SchemaInfo $info): string
    {
        $root = $info->root() ?? $info->base() ?? $info->id();
        $document = explode('#', (string) $root, 2)[0];
        return $document . '#' . \PortableSchema\Pointer::fromSegments($info->path());
    }
}
