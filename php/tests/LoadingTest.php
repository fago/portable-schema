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

    #[DataProvider('cases')]
    public function testLoading(\stdClass $case): void
    {
        $validator = new Validator(Registry::withTypes()->add($case->schema));
        if ($case->refused) {
            $this->expectException(\Throwable::class);
        }
        $validator->validate([], $case->schema->{'$id'});
        $this->addToAssertionCount(1);
    }
}
