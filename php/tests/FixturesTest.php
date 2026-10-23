<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FixturesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function cases(): iterable
    {
        foreach (Fixtures::files() as $file) {
            [, $tests] = Fixtures::load($file);
            foreach ($tests as $i => $case) {
                yield "$file: {$case->description}" => [$file, $i];
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function files(): iterable
    {
        foreach (Fixtures::files() as $file) {
            yield $file => [$file];
        }
    }

    public function testEveryExampleDataFileHasAnExpectationFile(): void
    {
        $this->assertSame([], Fixtures::unused());
    }

    public function testAgentToolFieldItemImageIsTheNodeArticles(): void
    {
        $path = Fixtures::DIRECTORY . '/examples/drupal/%s/schemas/field_item.image.json';
        $this->assertFileEquals(sprintf($path, 'node-article'), sprintf($path, 'agent-tool'));
    }

    #[DataProvider('files')]
    public function testFixtureFilesAreValid(string $file): void
    {
        [$schemaFile, $tests] = Fixtures::load($file);
        $this->assertSame([], Fixtures::check($file, $schemaFile, $tests));
    }

    #[DataProvider('cases')]
    public function testCase(string $file, int $index): void
    {
        [$schemaFile, $tests] = Fixtures::load($file);
        $case = $tests[$index];
        $expected = Fixtures::expected($case);
        $actual = array_intersect_key(Fixtures::actual(Fixtures::run($file, $schemaFile, $case)), $expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }
}
