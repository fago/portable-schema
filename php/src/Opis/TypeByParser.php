<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Parsers\KeywordParser;
use Opis\JsonSchema\Parsers\SchemaParser;
use PortableSchema\Trace\Tracer;

final class TypeByParser extends KeywordParser
{
    public function __construct(string $keyword, private readonly Tracer $tracer)
    {
        parent::__construct($keyword);
    }

    public function type(): string
    {
        return self::TYPE_AFTER_REF;
    }

    public function parse(SchemaInfo $info, SchemaParser $parser, object $shared): ?Keyword
    {
        $schema = $info->data();
        if (!$this->keywordExists($schema)) {
            return null;
        }
        $value = $this->keywordValue($schema);
        return new TypeByKeyword(
            $value->href,
            get_object_vars($value->source),
            $value->fallback ?? null,
            explode('#', (string) ($info->base() ?? $info->root() ?? $info->id()), 2)[0],
            $this->tracer,
        );
    }
}
