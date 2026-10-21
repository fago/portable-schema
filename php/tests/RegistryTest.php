<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PortableSchema\Registry;

final class RegistryTest extends TestCase
{
    public static function invalidIds(): iterable
    {
        yield 'not a URI' => [['test:ä']];
        yield 'relative' => [['schema']];
        yield 'equal after percent-decoding (+)' => [['test:a%2Bb', 'test:a+b']];
        yield 'equal after percent-decoding (~)' => [['test:a~b', 'test:a%7Eb']];
        yield 'equal after percent-decoding (%FF)' => [['test:%FF', 'test:%ff']];
        yield 'empty fragment' => [['test:t#']];
        yield 'fragment' => [['urn:t#frag']];
        yield 'pointer fragment' => [['test:a#/b']];
        yield 'host case' => [['http://x/', 'http://X/']];
        yield 'scheme case' => [['HTTP://x/a', 'http://x/a']];
    }

    #[DataProvider('invalidIds')]
    public function testRefusesId(array $ids): void
    {
        $registry = new Registry();
        $this->expectException(\InvalidArgumentException::class);
        foreach ($ids as $id) {
            $registry->add(true, $id);
        }
    }

    public function testAcceptsIdsThatDifferAfterDecoding(): void
    {
        $registry = (new Registry())->add(true, 'test:a%2Bb')->add(true, 'test:a%2Bb')->add(true, 'test:a-b');
        $this->assertSame(['test:a%2Bb', 'test:a-b'], $registry->ids());
    }

    public function testAcceptsIdsThatDifferInPathCase(): void
    {
        $registry = (new Registry())->add(true, 'http://x/a')->add(true, 'http://x/A')->add(true, 'test:%FF');
        $this->assertSame(['http://x/a', 'http://x/A', 'test:%FF'], $registry->ids());
    }

    public function testRefusesSchemaIdWithFragment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Registry())->add((object) ['$id' => 'test:r3#']);
    }
}
