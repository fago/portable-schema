<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Keywords\AllOfKeyword;
use Opis\JsonSchema\Keywords\ContainsKeyword;
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
        $keyword = $this->inner->parse($info, $parser, $shared);
        return match (true) {
            $keyword instanceof AllOfKeyword => FullAllOfKeyword::from($keyword),
            $keyword instanceof ContainsKeyword => FullContainsKeyword::from($keyword),
            $keyword instanceof PropertyNamesKeyword => FullPropertyNamesKeyword::from($keyword),
            default => $keyword,
        };
    }
}
