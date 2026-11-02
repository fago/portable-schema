<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Parsers\KeywordParser;
use Opis\JsonSchema\Parsers\SchemaParser;
use PortableSchema\Check\Checks;
use PortableSchema\Trace\Tracer;

final class CheckParser extends KeywordParser
{
    public function __construct(string $keyword, private readonly Tracer $tracer, private readonly Checks $checks)
    {
        parent::__construct($keyword);
    }

    public function type(): string
    {
        return self::TYPE_AFTER;
    }

    public function parse(SchemaInfo $info, SchemaParser $parser, object $shared): ?Keyword
    {
        $schema = $info->data();
        if (!$this->keywordExists($schema)) {
            return null;
        }
        return new CheckKeyword(get_object_vars($this->keywordValue($schema)), $this->tracer, $this->checks);
    }
}
