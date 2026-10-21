<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

use PHPUnit\Framework\TestCase;
use PortableSchema\Json;
use PortableSchema\Registry;
use PortableSchema\Validator;

final class ValidatorTest extends TestCase
{
    private const DIALECT = 'https://portable-schema.dev/v1/schema';

    public function testDoesNotSeeSchemasAddedLater(): void
    {
        $registry = Registry::withTypes()
            ->add(Json::decode('{"$schema": "' . self::DIALECT . '", "$id": "test:late", "type": "object", "portable-typeBy": {"href": "test:late.{k}", "source": {"k": "0/k"}}}'))
            ->add(Json::decode('{"$schema": "' . self::DIALECT . '", "$id": "test:late.a", "type": "object"}'));
        $validator = new Validator($registry);
        $registry->add(Json::decode('{"$schema": "' . self::DIALECT . '", "$id": "test:late.b", "type": "string"}'));
        $this->assertSame(['typeBy.unresolved'], array_map(fn ($e) => $e->code, $validator->validate(Json::decode('{"k": "b"}'), 'test:late')->errors));
        $this->expectException(\OutOfBoundsException::class);
        $validator->validate('x', 'test:late.b');
    }
}
