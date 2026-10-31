<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Parsers\KeywordParser;
use Opis\JsonSchema\Parsers\SchemaParser;
use PortableSchema\Trace\Tracer;

final class TracingKeywordParser extends KeywordParser
{
    public function __construct(private readonly KeywordParser $inner, string $keyword, private readonly Tracer $tracer)
    {
        parent::__construct($keyword);
    }

    public static function name(KeywordParser $parser): string
    {
        return (fn () => $this->keyword)->call($parser);
    }

    public function type(): string
    {
        return $this->inner->type();
    }

    public function parse(SchemaInfo $info, SchemaParser $parser, object $shared): ?Keyword
    {
        $keyword = $this->inner->parse($info, $parser, $shared);
        return $keyword ? new TracingKeyword($keyword, $this->keyword, $this->tracer) : null;
    }
}
