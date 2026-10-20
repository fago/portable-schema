<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Keywords\AllOfKeyword;
use Opis\JsonSchema\Keywords\ConstKeyword;
use Opis\JsonSchema\Keywords\ContainsKeyword;
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
        // opis refuses unsatisfiable const, enum and oneOf values, and drops redundant ones.
        $schema = $info->data();
        if (in_array($this->keyword, ['anyOf', 'oneOf', 'const', 'enum'], true)) {
            if (!$this->keywordExists($schema)) {
                return null;
            }
            $value = $this->keywordValue($schema);
            return match ($this->keyword) {
                'const' => new ConstKeyword($value),
                'enum' => new EnumKeyword($value),
                default => new FullOfKeyword($this->keyword, $value),
            };
        }
        $keyword = $this->inner->parse($info, $parser, $shared);
        return match (true) {
            $keyword instanceof AllOfKeyword => FullAllOfKeyword::from($keyword),
            $keyword instanceof PropertiesKeyword => FullPropertiesKeyword::from($keyword),
            $keyword instanceof PatternPropertiesKeyword => FullPatternPropertiesKeyword::from($keyword),
            $keyword instanceof DependentSchemasKeyword => FullDependentSchemasKeyword::from($keyword),
            $keyword instanceof ContainsKeyword => FullContainsKeyword::from($keyword),
            $keyword instanceof PropertyNamesKeyword => FullPropertyNamesKeyword::from($keyword),
            default => $keyword,
        };
    }
}
