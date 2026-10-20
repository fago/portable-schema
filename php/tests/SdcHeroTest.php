<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

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
    private const DIRECTORY = Fixtures::DIRECTORY . '/examples/drupal/sdc-hero';

    private const ID = 'drupal:sdc.portable_showcase.hero';

    private function exporter(): Exporter
    {
        return new Exporter(Registry::withTypes()->addDirectory(self::DIRECTORY . '/schemas'));
    }

    public function testComponentYmlHasTheProps(): void
    {
        $component = Yaml::parseFile(self::DIRECTORY . '/hero/hero.component.yml');
        $props = Json::toArray(Json::decodeFile(self::DIRECTORY . '/schemas/sdc.hero.json'));
        $this->assertEquals(array_diff_key($props, array_flip(['$schema', '$id', 'title', 'description'])), $component['props']);
    }

    public function testDraft04ExportIsCommitted(): void
    {
        $this->assertEquals(Json::decodeFile(self::DIRECTORY . '/hero.draft-04.json'), $this->exporter()->draft04(self::ID));
    }

    public function testDraft04ExportValidatesTheCases(): void
    {
        $schema = $this->exporter()->draft04(self::ID);
        foreach (Json::decodeFile(self::DIRECTORY . '/hero.test.json') as $case) {
            $validator = new Draft04Validator();
            $data = Json::decode(json_encode($case->data));
            $validator->validate($data, $schema);
            $this->assertSame($case->valid, $validator->isValid(), $case->description . ': ' . json_encode($validator->getErrors()));
        }
    }
}
