<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

use Opis\JsonSchema\Validator as GenericValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PortableSchema\Exporter;
use PortableSchema\Json;
use PortableSchema\Registry;
use PortableSchema\Validator;

/**
 * The fixtures in fixtures/exports.
 */
final class ExporterTest extends TestCase
{
    public static function cases(): iterable
    {
        foreach (glob(Fixtures::DIRECTORY . '/exports/*.json') as $file) {
            foreach (Json::decodeFile($file) as $case) {
                yield basename($file) . ': ' . $case->description => [$case];
            }
        }
    }

    #[DataProvider('cases')]
    public function testExport(\stdClass $case): void
    {
        $registry = Registry::withTypes();
        foreach ($case->registry ?? [] as $schema) {
            $registry->add($schema);
        }
        $registry->add($case->schema);
        $id = $case->schema->{'$id'};
        $export = (new Exporter($registry))->inline($id);
        $this->assertEquals($case->export, $export);

        $validator = new Validator($registry);
        foreach ($case->tests as $test) {
            $this->assertSame($test->valid, $validator->validate($test->data, $id)->valid, json_encode($test->data));
            $this->assertSame($test->valid, (new GenericValidator())->validate($test->data, $export)->isValid(), 'export: ' . json_encode($test->data));
        }
    }
}
