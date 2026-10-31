<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * Input turned into the JSON data model (spec/normalization.md).
 */
final class Normalized
{
    /**
     * @param list<string> $unsupported the locations of values the model can't hold; the value itself is null in $data
     */
    public function __construct(
        public mixed $data,
        public readonly array $unsupported,
    ) {
    }
}
