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
        yield 'dot-dot segment' => [['http://x/a/../b']];
        yield 'dot segment' => [['http://x/./b']];
        yield 'dot segment without authority' => [['test:a/./b']];
        yield 'encoded dot segment' => [['test:a/%2E/b']];
        yield 'trailing dot-dot segment' => [['test:a/..']];
        yield 'file scheme' => [['file:///x']];
        yield 'encoded ? before a dot-dot segment' => [['test:a%3F/../b']];
        yield 'encoded ? before an encoded dot-dot segment' => [['test:a%3F/%2E%2E/b']];
        yield 'meta-schema id' => [['https://portable-schema.dev/v1/load']];
        yield '2020-12 meta-schema id' => [['https://json-schema.org/draft/2020-12/schema']];
        yield 'meta-schema id in other case' => [['HTTPS://JSON-SCHEMA.ORG/draft/2020-12/meta/core']];
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

    public function testAcceptsDotsInSegments(): void
    {
        $registry = (new Registry())->add(true, 'http://x/a.b/..c')->add(true, 'test:a?./b');
        $this->assertSame(['http://x/a.b/..c', 'test:a?./b'], $registry->ids());
    }

    public function testRefusesSchemaIdOtherThanItsId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Registry())->add((object) ['$id' => 'test:o', 'required' => ['a']], 'urn:t');
    }

    public static function nonStringIds(): iterable
    {
        yield 'array' => [[1], null];
        yield 'object' => [new \stdClass(), null];
        yield 'array, with an id' => [[1], 'test:n'];
        yield 'object, with an id' => [new \stdClass(), 'test:n'];
        yield 'null, with an id' => [null, 'test:n'];
    }

    #[DataProvider('nonStringIds')]
    public function testRefusesNonStringSchemaId(mixed $value, ?string $id): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Registry())->add((object) ['$id' => $value], $id);
    }

    /**
     * @var list<string>
     */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }

    /**
     * A temporary directory with $files: relative path => JSON.
     *
     * @param array<string, string> $files
     */
    private function directory(array $files): string
    {
        $directory = sys_get_temp_dir() . '/portable-schema-' . bin2hex(random_bytes(6));
        $this->directories[] = $directory;
        foreach ($files as $path => $json) {
            @mkdir(dirname("$directory/$path"), 0777, true);
            file_put_contents("$directory/$path", $json);
        }
        return $directory;
    }

    public function testMountsADirectory(): void
    {
        $directory = $this->directory([
            'block.block.json' => '{"type": "object"}',
            'block.settings.%2A.json' => '{"$id": "drupal:block.settings.*"}',
            'catalog/plugins/block.json' => '{"enum": ["a"]}',
            'block.block.test.json' => '[]',
        ]);
        $registry = (new Registry())->addDirectory($directory, 'drupal');
        $this->assertSame(['drupal:block.block', 'drupal:block.settings.*', 'drupal:catalog/plugins/block'], $registry->ids());
    }

    public function testSkipsSymbolicLinksAndDotfiles(): void
    {
        $directory = $this->directory(['a.json' => 'true', 'sub/b.json' => 'true', '.c.json' => 'true', '.hidden/d.json' => 'true']);
        symlink("$directory/sub", "$directory/linked");
        symlink("$directory/a.json", "$directory/e.json");
        symlink($directory, "$directory/sub/loop");
        $this->assertSame(['drupal:a', 'drupal:sub/b'], (new Registry())->addDirectory($directory, 'drupal')->ids());
    }

    public function testNamesTheFileWithInvalidJson(): void
    {
        $directory = $this->directory(['a.json' => '{']);
        $this->expectExceptionMessage("The file $directory/a.json is not valid JSON");
        (new Registry())->addDirectory($directory, 'drupal');
    }

    public function testRefusesAFileNameThatDecodesToNonAsciiCharacters(): void
    {
        $directory = $this->directory(['%C3%A4.json' => 'true']);
        $this->expectExceptionMessage("The file $directory/%C3%A4.json has an id with non-ASCII characters.");
        (new Registry())->addDirectory($directory, 'drupal');
    }

    public function testRefusesAnIdOtherThanTheFilePath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Registry())->addDirectory($this->directory(['a.json' => '{"$id": "drupal:b"}']), 'drupal');
    }

    public static function invalidNamespaces(): iterable
    {
        yield 'reserved' => ['portable'];
        yield 'upper case' => ['Drupal'];
        yield 'empty' => [''];
        yield 'with a colon' => ['a:b'];
    }

    #[DataProvider('invalidNamespaces')]
    public function testRefusesNamespace(string $namespace): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Registry())->addDirectory($this->directory(['a.json' => 'true']), $namespace);
    }

    public function testRefusesTheTypesNamespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Registry())->add(true, 'PORTABLE:mine');
    }
}
