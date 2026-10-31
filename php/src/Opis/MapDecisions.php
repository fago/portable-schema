<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

/**
 * The locations where a `type: object` schema met a list during evaluation: the list is a map there.
 */
final class MapDecisions
{
    /** @var array<string, true> */
    public array $locations = [];

    public function reset(): void
    {
        $this->locations = [];
    }

    /**
     * Whether `type` allows objects and not arrays.
     */
    public static function objectOnly(mixed $type): bool
    {
        $types = is_string($type) ? [$type] : (is_array($type) ? $type : []);
        return in_array('object', $types, true) && !in_array('array', $types, true);
    }
}
