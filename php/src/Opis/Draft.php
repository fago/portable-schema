<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Parsers\Drafts\Draft202012;
use Opis\JsonSchema\Parsers\KeywordParser;
use Opis\JsonSchema\Parsers\Keywords\FormatKeywordParser;
use Opis\JsonSchema\Parsers\Keywords\IfThenElseKeywordParser;
use Opis\JsonSchema\Parsers\Keywords\PropertyNamesKeywordParser;
use PortableSchema\Check\Checks;
use PortableSchema\Trace\Tracer;

/**
 * 2020-12 with the Portable Schema keywords, every keyword traced.
 */
final class Draft extends Draft202012
{
    public function __construct(private readonly Tracer $tracer, private readonly Checks $checks)
    {
        parent::__construct();
    }

    protected function getRefKeywordParser(): KeywordParser
    {
        return new TracingKeywordParser(parent::getRefKeywordParser(), '$ref', $this->tracer);
    }

    protected function getKeywordParsers(): array
    {
        $parsers = [];
        foreach (parent::getKeywordParsers() as $parser) {
            $parsers[] = match (true) {
                $parser instanceof FormatKeywordParser => new FormatParser('format'),
                $parser instanceof PropertyNamesKeywordParser => new PropertyNamesParser('propertyNames'),
                $parser instanceof IfThenElseKeywordParser => new IfParser('if', 'then', 'else'),
                default => $parser,
            };
        }
        $parsers[] = new TypeByParser('portable-typeBy', $this->tracer);
        $parsers[] = new CheckParser('portable-check', $this->tracer, $this->checks);

        return [
            new BooleanSchemas(),
            ...array_map(
                fn (KeywordParser $parser) => new TracingKeywordParser($parser, TracingKeywordParser::name($parser), $this->tracer),
                $parsers,
            ),
        ];
    }
}
