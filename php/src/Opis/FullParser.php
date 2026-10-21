<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Keywords\AllOfKeyword;
use Opis\JsonSchema\Keywords\ConstKeyword;
use Opis\JsonSchema\Keywords\DependentSchemasKeyword;
use Opis\JsonSchema\Keywords\EnumKeyword;
use Opis\JsonSchema\Keywords\PatternPropertiesKeyword;
use Opis\JsonSchema\Keywords\PropertiesKeyword;
use Opis\JsonSchema\Keywords\PropertyNamesKeyword;
use Opis\JsonSchema\Parsers\KeywordParser;
use Opis\JsonSchema\Parsers\SchemaParser;

/**
 * An opis keyword parser whose keywords are replaced by ones that evaluate in full.
 */
final class FullParser extends KeywordParser
{
    public function __construct(private readonly KeywordParser $inner)
    {
        parent::__construct(TracingKeywordParser::name($inner));
    }

    public function type(): string
    {
        return $this->inner->type();
    }

    public function parse(SchemaInfo $info, SchemaParser $parser, object $shared): ?Keyword
    {
        $schema = $info->data();
        if (!$this->keywordExists($schema)) {
            return $this->inner->parse($info, $parser, $shared);
        }
        $value = $this->keywordValue($schema);
        // opis refuses unsatisfiable const, enum, oneOf and contains values, and drops redundant ones.
        $keyword = match ($this->keyword) {
            'const' => new ConstKeyword($value),
            'enum' => new EnumKeyword($value),
            'anyOf', 'oneOf' => new FullOfKeyword($this->keyword, $value),
            'contains' => $this->contains($info, $value),
            default => $this->inner->parse($info, $parser, $shared),
        };
        return match (true) {
            $keyword instanceof AllOfKeyword => FullAllOfKeyword::from($keyword),
            $keyword instanceof PropertiesKeyword => FullPropertiesKeyword::from($keyword),
            $keyword instanceof PatternPropertiesKeyword => FullPatternPropertiesKeyword::from($keyword),
            $keyword instanceof DependentSchemasKeyword => FullDependentSchemasKeyword::from($keyword),
            $keyword instanceof PropertyNamesKeyword => FullPropertyNamesKeyword::from($keyword),
            default => $keyword,
        };
    }

    private function contains(SchemaInfo $info, mixed $value): FullContainsKeyword
    {
        return new FullContainsKeyword($value, $info->data()->minContains ?? null, $info->data()->maxContains ?? null);
    }
}
