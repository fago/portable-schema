<?php

declare(strict_types=1);

namespace PortableSchema\Message;

/**
 * A message to translate: the catalog id, the English source text, its params and the plural count.
 */
final class MessageRequest
{
    /**
     * @param array<string, string> $params plain-text values for the placeholders
     */
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly array $params,
        public readonly ?int $count = null,
    ) {
    }
}
