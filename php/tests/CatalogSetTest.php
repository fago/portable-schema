<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

use PHPUnit\Framework\TestCase;
use PortableSchema\Json;
use PortableSchema\Registry;
use PortableSchema\Result;
use PortableSchema\Trace\KeywordNode;
use PortableSchema\Validator;

final class CatalogSetTest extends TestCase
{
    private const DIALECT = 'https://portable-schema.dev/v1/schema';

    private const BRANCHES = '[{"const": 1, "title": "One"}, {"const": "1", "title": "One as text"}, {"const": null, "title": "None"}, {"const": true, "title": "Yes"}, {"const": 2.5, "title": "Two and a half"}, {"const": {"a": [1, 2]}, "title": "Pair", "x-portable-provider": "test"}]';

    private const VALUES = ['1', '1.0', '"1"', 'null', 'true', 'false', '2.5', '3', '"x"', '{"a": [1.0, 2]}', '{"a": [2, 1]}', '[]', '{}', '9007199254740992'];

    private static function validator(string ...$schemas): Validator
    {
        $registry = Registry::withTypes();
        foreach ($schemas as $schema) {
            $registry->add(Json::decode('{"$schema": "' . self::DIALECT . '", ' . $schema . '}'));
        }
        return new Validator($registry);
    }

    /**
     * @return array{valid: bool, errors: list<array{string, string, array<string, mixed>}>, titles: list<string>|null}
     */
    private static function outcome(Result $result): array
    {
        return [
            'valid' => $result->valid,
            'errors' => array_map(fn ($e) => [$e->instanceLocation, $e->code, Json::toArray($e->params)], $result->errors),
            'titles' => $result->annotations('')->allowedTitles,
        ];
    }

    /**
     * The `oneOf` keyword node at the root of the last validation.
     */
    private static function oneOf(Validator $validator): KeywordNode
    {
        $root = (fn () => $this->tracer)->call($validator)->root();
        foreach ($root->keywords as $keyword) {
            if ($keyword->keyword === 'oneOf') {
                return $keyword;
            }
        }
        throw new \LogicException('No oneOf evaluated');
    }

    public function testCatalogGivesTheResultsOfEvaluatingEachBranch(): void
    {
        // anyOf is never compiled to a set; with distinct consts it means the same.
        $validator = self::validator('"$id": "test:one", "oneOf": ' . self::BRANCHES, '"$id": "test:any", "anyOf": ' . self::BRANCHES);
        foreach (self::VALUES as $value) {
            $one = $validator->validate(Json::decode($value), 'test:one');
            $this->assertSame([], self::oneOf($validator)->children, $value);
            $this->assertEquals(self::outcome($validator->validate(Json::decode($value), 'test:any')), self::outcome($one), $value);
        }
    }

    public function testScalarEnumGivesTheResultsOfEvaluatingEachValue(): void
    {
        $validator = self::validator('"$id": "test:enum", "enum": [1, "1", null, true, 2.5, 9007199254740992]', '"$id": "test:any", "anyOf": [{"const": 1}, {"const": "1"}, {"const": null}, {"const": true}, {"const": 2.5}, {"const": 9007199254740992}]');
        foreach ([...self::VALUES, '9007199254740992.0', '9.007199254740992e15'] as $value) {
            $this->assertEquals(self::outcome($validator->validate(Json::decode($value), 'test:any')), self::outcome($validator->validate(Json::decode($value), 'test:enum')), $value);
        }
    }

    public function testDuplicateOrNonConstBranchesAreEvaluated(): void
    {
        $validator = self::validator('"$id": "test:duplicate", "oneOf": [{"const": 1}, {"const": 1.0}]', '"$id": "test:mixed", "oneOf": [{"const": 1}, {"type": "string"}]', '"$id": "test:keyword", "oneOf": [{"const": 1}, {"const": 2, "minimum": 0}]');
        $this->assertSame(['enum'], array_map(fn ($e) => $e->code, $validator->validate(1, 'test:duplicate')->errors));
        $this->assertCount(2, self::oneOf($validator)->children);
        $this->assertTrue($validator->validate('x', 'test:mixed')->valid);
        $this->assertCount(2, self::oneOf($validator)->children);
        $this->assertTrue($validator->validate(2, 'test:keyword')->valid);
        $this->assertCount(2, self::oneOf($validator)->children);
    }

    public function testLargeCatalogIsCheckedWithoutEvaluatingEachBranch(): void
    {
        $branches = array_map(fn (int $i) => ['const' => "v$i", 'title' => "Value $i"], range(1, 10000));
        $validator = self::validator('"$id": "test:large", "oneOf": ' . json_encode($branches));

        $this->assertTrue($validator->validate('v10000', 'test:large')->valid);
        $this->assertSame([], self::oneOf($validator)->children);

        $result = $validator->validate('v0', 'test:large');
        $this->assertSame([], self::oneOf($validator)->children);
        $this->assertSame(['enum'], array_map(fn ($e) => $e->code, $result->errors));
        $this->assertCount(10000, $result->errors[0]->params['allowed']);
        $this->assertSame('Value 10000', $result->annotations('')->allowedTitles[9999]);
    }
}
