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
        foreach (['test:cat#/$defs/{k}', 'test:{#k}', 'test:{/k}', 'test:{?k}', 'test:{.k}', 'test:{;k}', 'test:{k:2}', 'test:{k*}'] as $template) {
            yield $template => [$template];
        }
    }

    #[DataProvider('refused')]
    public function testTypeByRefuses(string $template): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UriTemplate::assertTypeBy($template);
    }

    public function testTypeByAccepts(): void
    {
        UriTemplate::assertTypeBy('test:{a}.{+b}.{c,d.e}');
        $this->assertSame('test:x.y/z.1,2', UriTemplate::expand('test:{a}.{+b}.{c,d.e}', ['a' => 'x', 'b' => 'y/z', 'c' => '1', 'd.e' => '2']));
    }
}
