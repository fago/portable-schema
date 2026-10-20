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
 * An opis keyword parser whose keywords are replaced by ones that evaluate in full, and that loads the schemas the
 * 2020-12 meta-schema allows, as Hyperjump does.
 */
final class FullParser extends KeywordParser
{
    /** Keywords whose value is a non-negative integer; `1.0` is one. */
    private const INTEGERS = ['maxLength', 'minLength', 'maxItems', 'minItems', 'maxContains', 'minContains', 'maxProperties', 'minProperties'];

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
        foreach (self::INTEGERS as $name) {
            if (is_float($schema->{$name} ?? null) && floor($schema->{$name}) === $schema->{$name}) {
                $schema->{$name} = (int) $schema->{$name};
            }
        }
        if (!$this->keywordExists($schema)) {
            return $this->inner->parse($info, $parser, $shared);
        }
        $value = $this->keywordValue($schema);
        if (in_array($this->keyword, ['anyOf', 'oneOf'], true) && $value === []) {
            throw $this->keywordException('{keyword} must not be empty', $info);
        }
        foreach ($this->keyword === 'dependentRequired' && is_object($value) ? get_object_vars($value) : [$value] as $list) {
            if (in_array($this->keyword, ['required', 'type', 'dependentRequired'], true) && is_array($list) && count(array_unique($list, SORT_REGULAR)) !== count($list)) {
                throw $this->keywordException('{keyword} must not repeat a value', $info);
            }
        }
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
        if (!is_bool($value) && !is_object($value)) {
            throw $this->keywordException('{keyword} must be a json schema (object or boolean)', $info);
        }
        $bounds = [];
        foreach (['minContains', 'maxContains'] as $name) {
            $bound = $info->data()->{$name} ?? null;
            if ($bound !== null && (!is_int($bound) || $bound < 0)) {
                throw $this->keywordException('{keyword} must be a non-negative integer', $info, $name);
            }
            $bounds[] = $bound;
        }
        return new FullContainsKeyword($value, ...$bounds);
    }
}
