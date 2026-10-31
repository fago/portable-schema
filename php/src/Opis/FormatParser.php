<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Parsers\KeywordParser;
use Opis\JsonSchema\Parsers\SchemaParser;
use PortableSchema\Formats;

/**
 * `format` with the pinned checks.
 */
final class FormatParser extends KeywordParser
{
    public function type(): string
    {
        return self::TYPE_BEFORE;
    }

    public function parse(SchemaInfo $info, SchemaParser $parser, object $shared): ?Keyword
    {
        $schema = $info->data();
        if (!$this->keywordExists($schema)) {
            return null;
        }
        $format = $this->keywordValue($schema);
        if (!in_array($format, Formats::NAMES, true)) {
            throw $this->keywordException("Format {$format} is not allowed", $info);
        }
        return new FormatKeyword($format);
    }
}
