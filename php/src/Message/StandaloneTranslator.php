<?php

declare(strict_types=1);

namespace PortableSchema\Message;

/**
 * Renders the English source text: `{name}` is replaced with the plain value.
 */
final class StandaloneTranslator implements Translator
{
    public function translate(string $id, string $source, array $params, ?int $count): string
    {
        return preg_replace_callback('/\{([A-Za-z0-9_]+)\}/', fn (array $m) => $params[$m[1]] ?? $m[0], $source);
    }
}
