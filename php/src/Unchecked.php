<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * A check that could not run.
 */
final class Unchecked
{
    public function __construct(
        public readonly string $instanceLocation,
        public readonly string $code,
        public readonly string $reason,
    ) {
    }

    /**
     * @return array{instanceLocation: string, code: string, reason: string}
     */
    public function toArray(): array
    {
        return ['instanceLocation' => $this->instanceLocation, 'code' => $this->code, 'reason' => $this->reason];
    }
}
