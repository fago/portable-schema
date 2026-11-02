<?php

declare(strict_types=1);

namespace PortableSchema\Check;

/**
 * One value a named check is asked about.
 */
final class CheckRequest
{
    public ?CheckResult $result = null;

    public function __construct(
        public readonly string $name,
        public readonly mixed $value,
        public readonly string $location,
        public readonly \stdClass $options,
        public readonly mixed $parent,
        public readonly mixed $root,
    ) {
    }
}
