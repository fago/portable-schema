<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PortableSchema\Json;
use PortableSchema\Registry;
use PortableSchema\Validator;

/**
 * The schemas in fixtures/loading.json: refused when loaded, or accepted.
 */
final class LoadingTest extends TestCase
{
    public static function cases(): iterable
    {
        foreach (Json::decodeFile(Fixtures::DIRECTORY . '/loading.json') as $case) {
            yield $case->description => [$case];
        }
    }

    public function testFileIsValid(): void
    {
        $this->assertSame([], Fixtures::problems(Json::decodeFile(Fixtures::DIRECTORY . '/loading.json'), 'loading.schema.json'));
    }

    #[DataProvider('cases')]
    public function testLoading(\stdClass $case): void
    {
        $registry = Registry::withTypes()->add($case->schema);
        if ($case->refused) {
            $this->expectException(\Throwable::class);
            new Validator($registry);
        }
        (new Validator($registry))->validate([], $case->schema->{'$id'});
        $this->addToAssertionCount(1);
    }
}
