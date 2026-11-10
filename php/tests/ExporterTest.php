<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

use JsonSchema\Validator as Draft04Validator;
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

    public function testFilesAreValid(): void
    {
        foreach (glob(Fixtures::DIRECTORY . '/exports/*.json') as $file) {
            $this->assertSame([], Fixtures::problems(Json::decodeFile($file), 'exports.schema.json'), basename($file));
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
        $exporter = new Exporter($registry);
        $validator = new Validator($registry);
        foreach ($case->tests as $test) {
            $this->assertSame($test->valid, $validator->validate($test->data, $id)->valid, json_encode($test->data));
        }
        if (property_exists($case, 'export')) {
            $export = $exporter->inline($id);
            $this->assertEquals($case->export, $export);
            foreach ($case->tests as $test) {
                $this->assertSame($test->valid, (new GenericValidator())->validate($test->data, $export)->isValid(), 'export: ' . json_encode($test->data));
            }
        }
        if (property_exists($case, 'draft04') && $case->draft04 === null) {
            $this->expectException(\RuntimeException::class);
            $exporter->draft04($id);
        } elseif (property_exists($case, 'draft04')) {
            $export = $exporter->draft04($id);
            $this->assertEquals($case->draft04, $export);
            foreach ($case->tests as $test) {
                $draft04 = new Draft04Validator();
                $draft04->validate($test->data, $export);
                $this->assertSame($test->valid, $draft04->isValid(), 'draft-04: ' . json_encode($test->data));
            }
        }
    }
}
