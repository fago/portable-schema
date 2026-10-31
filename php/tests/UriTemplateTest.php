<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PortableSchema\UriTemplate;

final class UriTemplateTest extends TestCase
{
    public static function refused(): iterable
    {
        foreach (['test:cat#/$defs/{k}', 'test:{#k}', 'test:{/k}', 'test:{?k}', 'test:{.k}', 'test:{;k}', 'test:{k:2}', 'test:{k*}', 'test:s.{{p}', 'test:s.{p}}', 'test:s.{p', 'test:s.p}'] as $template) {
            yield "href $template" => [$template, null];
        }
        foreach (['test:{p}', 'test:a#/x', 'test:a}'] as $fallback) {
            yield "fallback $fallback" => ['test:{p}', $fallback];
        }
    }

    #[DataProvider('refused')]
    public function testTypeByRefuses(string $href, ?string $fallback): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UriTemplate::assertTypeBy($href, $fallback);
    }

    public function testTypeByAccepts(): void
    {
        UriTemplate::assertTypeBy('test:{a}.{+b}.{c,d.e}', 'test:other');
        $this->assertSame('test:x.y/z.1,2', UriTemplate::expand('test:{a}.{+b}.{c,d.e}', ['a' => 'x', 'b' => 'y/z', 'c' => '1', 'd.e' => '2']));
    }
}
