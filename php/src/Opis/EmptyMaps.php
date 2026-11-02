<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

/**
 * The locations where `[]` met a schema whose `type` allows object and not array: a map there (spec/normalization.md).
 */
final class EmptyMaps
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
