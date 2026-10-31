<?php

declare(strict_types=1);

namespace PortableSchema\Message;

interface Translator
{
    /**
     * @param array<string, string> $params
     */
    public function translate(string $id, string $source, array $params, ?int $count): string;
}
