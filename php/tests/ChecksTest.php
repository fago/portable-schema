<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

use PHPUnit\Framework\TestCase;
use PortableSchema\Check\CheckProvider;
use PortableSchema\Check\CheckResult;
use PortableSchema\Json;
use PortableSchema\Message\Formatter;
use PortableSchema\Registry;
use PortableSchema\Validator;

final class ChecksTest extends TestCase
{
    private const SCHEMA = '{"$schema": "https://portable-schema.dev/v1/schema", "type": "array", "items": {"type": "string", "portable-check": {"test:Unique": {"scope": "tags"}}}}';

    private function validator(): Validator
    {
        return new Validator(Registry::withTypes()->add(Json::decode(self::SCHEMA), 'test:schema'));
    }

    private function provider(): CheckProvider
    {
        return new class implements CheckProvider {
            public array $calls = [];

            public function check(string $name, array $requests): array
            {
                $this->calls[] = [$name, array_map(fn ($r) => [$r->value, $r->location, $r->options->scope], $requests)];
                return array_map(fn ($r) => new CheckResult($r->value === 'b' ? [['code' => 'check.test:Unique', 'params' => ['value' => 'b']]] : []), $requests);
            }
        };
    }

    public function testProviderIsCalledOncePerName(): void
    {
        $provider = $this->provider();
        $result = $this->validator()->withProvider('test:Unique', $provider)->validate(['a', 'b'], 'test:schema');
        $this->assertSame([['test:Unique', [['a', '/0', 'tags'], ['b', '/1', 'tags']]]], $provider->calls);
        $this->assertSame([['instanceLocation' => '/1', 'code' => 'check.test:Unique', 'params' => ['value' => 'b']]], array_map(fn ($e) => ['instanceLocation' => $e->instanceLocation, 'code' => $e->code, 'params' => $e->params], $result->errors));
    }

    public function testCallbackOverridesProvider(): void
    {
        $provider = $this->provider();
        $result = $this->validator()
            ->withProvider('test:Unique', $provider)
            ->withCheck('test:Unique', fn (array $requests) => array_fill(0, count($requests), new CheckResult()))
            ->validate(['a', 'b'], 'test:schema');
        $this->assertSame([], $provider->calls);
        $this->assertTrue($result->valid);
        $this->assertSame([], $result->unchecked);
    }

    public function testFailingProviderIsUnchecked(): void
    {
        $result = $this->validator()
            ->withCheck('test:Unique', fn () => throw new \RuntimeException('down'))
            ->validate(['a'], 'test:schema');
        $this->assertTrue($result->valid);
        $this->assertSame([['instanceLocation' => '/0', 'code' => 'check.test:Unique', 'reason' => 'error']], array_map(fn ($u) => $u->toArray(), $result->unchecked));
    }

    public function testCallbackCanValidate(): void
    {
        $validator = $this->validator();
        $nested = false;
        $validator = $validator->withCheck('test:Unique', function (array $requests) use (&$validator, &$nested) {
            if (!$nested) {
                $nested = true;
                $validator->validate(['x'], 'test:schema');
            }
            return array_map(fn ($r) => new CheckResult([['code' => 'check.test:Unique', 'params' => []]]), $requests);
        });
        $result = $validator->validate(['a', 1], 'test:schema');
        $this->assertSame(['/0 check.test:Unique', '/1 type'], array_map(fn ($e) => $e->instanceLocation . ' ' . $e->code, $result->errors));
    }

    public function testUncheckedMessage(): void
    {
        $result = $this->validator()->validate(['a'], 'test:schema');
        $request = Formatter::create()->formatUnchecked($result->unchecked[0], $result);
        $this->assertSame(['unchecked', '{label} could not be checked.', ['label' => '0']], [$request->id, $request->source, $request->params]);
    }
}
