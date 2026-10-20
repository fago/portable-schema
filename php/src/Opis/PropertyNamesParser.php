<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Keywords\PropertyNamesKeyword;
use Opis\JsonSchema\Parsers\Keywords\PropertyNamesKeywordParser;
use Opis\JsonSchema\Parsers\SchemaParser;

final class PropertyNamesParser extends PropertyNamesKeywordParser
{
    public function parse(SchemaInfo $info, SchemaParser $parser, object $shared): ?Keyword
    {
        $keyword = parent::parse($info, $parser, $shared);
        return $keyword instanceof PropertyNamesKeyword ? FullPropertyNamesKeyword::from($keyword) : $keyword;
    }
}
