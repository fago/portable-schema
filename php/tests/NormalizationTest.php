<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

use PHPUnit\Framework\TestCase;
use PortableSchema\Json;
use PortableSchema\Registry;
use PortableSchema\Result;
use PortableSchema\Validator;

enum Suit: string
{
    case Hearts = 'hearts';
}

enum Side
{
    case Left;
}

final class Point implements \JsonSerializable
{
    public function jsonSerialize(): array
    {
        return ['x' => 1, 'y' => []];
    }
}

final class Account
{
    public string $name = 'ann';

    private string $secret = 'x';
}

/**
 * The PHP data model of spec/normalization.md: arrays, objects and the values the model can't hold.
 */
final class NormalizationTest extends TestCase
{
    private const DIALECT = 'https://portable-schema.dev/v1/schema';

    private function validate(string $schema, mixed $data): Result
    {
        $registry = Registry::withTypes()->add(Json::decode('{"$schema": "' . self::DIALECT . '", "$id": "test:s", ' . $schema . '}'));
        return (new Validator($registry))->validate($data, 'test:s');
    }

    /**
     * @return list<array{string, string, array<string, mixed>}>
     */
    private function errors(Result $result): array
    {
        return array_map(fn ($e) => [$e->instanceLocation, $e->code, Json::toArray($e->params)], $result->errors);
    }

    public function testArrayIsAMapUnderTypeObject(): void
    {
        $schema = '"type": "object", "properties": {"a": {}}, "required": ["a"], "additionalProperties": false';
        $this->assertTrue($this->validate($schema, ['a' => 1])->valid);
        $this->assertSame([['', 'required', ['property' => 'a']]], $this->errors($this->validate($schema, [])));
        $this->assertSame(
            [['', 'unknownProperty', ['property' => '0']], ['', 'required', ['property' => 'a']]],
            $this->errors($this->validate($schema, ['b'])),
        );
    }

    public function testArrayIsAListUnderTypeArray(): void
    {
        $schema = '"type": "array", "minItems": 2';
        $this->assertTrue($this->validate($schema, [1, 2])->valid);
        $this->assertSame(
            [['', 'type', ['expected' => ['array'], 'actual' => 'array']]],
            $this->errors($this->validate($schema, ['a' => 1, 'b' => 2])),
        );
        $this->assertSame(
            [['', 'type', ['expected' => ['array'], 'actual' => 'array']]],
            $this->errors($this->validate($schema, [1 => 'a', 0 => 'b'])),
        );
    }

    public function testArrayWithoutTypeIsDecidedByItsKeys(): void
    {
        $schema = '"properties": {"a": {"type": "integer"}}, "items": {"type": "string"}';
        $this->assertSame([['/a', 'type', ['expected' => ['integer'], 'actual' => 'string']]], $this->errors($this->validate($schema, ['a' => 'x'])));
        $this->assertSame([['/0', 'type', ['expected' => ['string'], 'actual' => 'integer']]], $this->errors($this->validate($schema, [1])));
        $this->assertTrue($this->validate('"type": ["object", "array"], "minItems": 1', [1])->valid);
    }

    public function testNestedListIsAMapWhereTypeObjectIsEvaluated(): void
    {
        $schema = '"type": "object", "properties": {"settings": {"$ref": "#/$defs/s"}}, "$defs": {"s": {"type": "object", "additionalProperties": false}}';
        $this->assertTrue($this->validate($schema, ['settings' => []])->valid);
        $this->assertSame(
            [['/settings', 'unknownProperty', ['property' => '0']]],
            $this->errors($this->validate($schema, ['settings' => ['x']])),
        );
    }

    public function testObjectsAsJsonEncodeConvertsThem(): void
    {
        $schema = '"type": "object", "properties": {"p": {"type": "object", "required": ["x"]}, "s": {"const": "hearts"}, "a": {"type": "object", "properties": {"name": {}}, "additionalProperties": false, "required": ["name"]}}';
        $data = new \stdClass();
        $data->p = new Point();
        $data->s = Suit::Hearts;
        $data->a = new Account();
        $this->assertTrue($this->validate($schema, $data)->valid);
        $this->assertTrue($this->validate('"type": "object", "properties": {"p": {"properties": {"y": {"type": "object"}}}}', ['p' => new Point()])->valid);
    }

    public function testValuesOutsideTheModelAreTypeErrors(): void
    {
        $schema = '"properties": {"f": {"type": "string"}, "r": {}, "n": true, "e": {"type": ["string", "null"]}}';
        $result = $this->validate($schema, ['f' => fn () => 1, 'r' => fopen('php://memory', 'r'), 'n' => NAN, 'e' => Side::Left]);
        $this->assertSame([
            ['/e', 'type', ['expected' => ['string', 'null'], 'actual' => 'enum']],
            ['/f', 'type', ['expected' => ['string'], 'actual' => 'closure']],
            ['/n', 'type', ['expected' => [], 'actual' => 'nan']],
            ['/r', 'type', ['expected' => [], 'actual' => 'resource']],
        ], $this->errors($result));
        $this->assertSame('/properties/f/type', $result->errors[1]->keywordLocation);
        $this->assertSame(['', 'type', ['expected' => [], 'actual' => 'infinity']], $this->errors($this->validate('"minimum": 1', INF))[0]);
    }

    public function testSubschemaAtNormalizesTheSameWay(): void
    {
        $registry = Registry::withTypes()->add(Json::decode('{"$schema": "' . self::DIALECT . '", "$id": "test:s", "type": "object", "properties": {"a": {"type": "object", "properties": {"b": {"type": "string"}}}}}'));
        $result = (new Validator($registry))->subschemaAt(['a' => []], 'test:s', 'a.b');
        $this->assertSame(['/properties/a/properties/b'], $result->schemaLocations);
    }
}
