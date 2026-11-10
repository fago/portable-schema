<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * The schemas that apply at an instance location, as keywordLocations, and the keywords skipped for a missing value.
 */
final class SubschemaResult
{
    /**
     * @param list<string> $schemaLocations
     * @param list<string> $unresolved
     */
    public function __construct(
        public readonly array $schemaLocations,
        public readonly array $unresolved,
    ) {
    }
}
