<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator as Draft04Validator;
use PHPUnit\Framework\TestCase;
use PortableSchema\Exporter;
use PortableSchema\Json;
use PortableSchema\Registry;
use Symfony\Component\Yaml\Yaml;

/**
 * The SDC hero showcase: component.yml, draft-04 export and core's draft-04 validator.
 */
final class SdcHeroTest extends TestCase
{
    private const SHOWCASE = 'examples/drupal/sdc-hero';

    private const DIRECTORY = Fixtures::DIRECTORY . '/' . self::SHOWCASE;

    private const ID = 'drupal:sdc.portable_showcase.hero';

    private function exporter(): Exporter
    {
        return new Exporter(Registry::withTypes()->addDirectory(self::DIRECTORY . '/schemas', Fixtures::EXAMPLES_NAMESPACE));
    }

    public function testComponentYmlHasTheProps(): void
    {
        $component = Yaml::parseFile(self::DIRECTORY . '/hero/hero.component.yml');
        $props = Json::toArray(Json::decodeFile(self::DIRECTORY . '/schemas/sdc.portable_showcase.hero.json'));
        $this->assertEquals(array_diff_key($props, array_flip(['$schema', '$id', 'title', 'description'])), $component['props']);
    }

    public function testDraft04ExportIsCommitted(): void
    {
        $this->assertEquals(Json::decodeFile(self::DIRECTORY . '/hero.draft-04.json'), $this->exporter()->draft04(self::ID));
    }

    /**
     * @return list<\stdClass>
     */
    private function cases(): array
    {
        $files = array_filter(Fixtures::files(), fn (string $file) => str_starts_with($file, self::SHOWCASE . '/'));
        return array_merge(...array_map(fn (string $file) => Fixtures::load($file)[1], array_values($files)));
    }

    public function testDraft04ExportValidatesTheCases(): void
    {
        $schema = $this->exporter()->draft04(self::ID);
        foreach ($this->cases() as $case) {
            $validator = new Draft04Validator();
            $data = Json::decode(json_encode($case->data));
            $validator->validate($data, $schema);
            $this->assertSame($case->valid, $validator->isValid(), $case->description . ': ' . json_encode($validator->getErrors()));
        }
    }

    /**
     * Core's ComponentValidator: type casting, and props the schema doesn't declare are dropped before validation.
     */
    public function testDraft04ExportInCoresMode(): void
    {
        $schema = $this->exporter()->draft04(self::ID);
        $differ = [];
        foreach ($this->cases() as $case) {
            $props = (object) array_intersect_key(get_object_vars($case->data), get_object_vars($schema->properties));
            $validator = new Draft04Validator();
            $validator->validate($props, $schema, Constraint::CHECK_MODE_TYPE_CAST);
            if ($validator->isValid() !== $case->valid) {
                $differ[] = $case->description;
            }
        }
        $this->assertSame(['hero--unknown-prop'], $differ);
    }
}
