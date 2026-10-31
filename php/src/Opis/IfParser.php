<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Keywords\IfThenElseKeyword;
use Opis\JsonSchema\Parsers\Keywords\IfThenElseKeywordParser;
use Opis\JsonSchema\Parsers\SchemaParser;

/**
 * `if` that is evaluated also without `then` and `else`, for the properties it evaluates.
 */
final class IfParser extends IfThenElseKeywordParser
{
    public function parse(SchemaInfo $info, SchemaParser $parser, object $shared): ?Keyword
    {
        $keyword = parent::parse($info, $parser, $shared);
        $if = $info->data()->if ?? null;
        return $keyword ?? (is_object($if) ? new IfThenElseKeyword($if, true, true) : null);
    }
}
