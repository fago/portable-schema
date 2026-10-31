<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * Annotations at one instance location, from the schemas in effect there. The outermost schema wins.
 */
final class Annotations
{
    /**
     * @param array<string, string> $messages custom message source texts by code
     * @param list<string>|null $allowedTitles titles of the allowed values of an enum-like oneOf
     */
    public function __construct(
        public ?string $title = null,
        public array $messages = [],
        public ?array $allowedTitles = null,
    ) {
    }
}
