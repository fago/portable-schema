<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * Input turned into the JSON data model (spec/normalization.md).
 */
final class Normalized
{
    /**
     * @param array<string, string> $unrepresentable the host type name per location of a value the model can't hold;
     *   the value itself is null in $data
     * @param array<string, true> $arrayMaps the locations of maps that were PHP arrays
     */
    public function __construct(
        public mixed $data,
        public readonly array $unrepresentable,
        public array $arrayMaps,
    ) {
    }
}
