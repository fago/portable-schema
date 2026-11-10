<?php

declare(strict_types=1);

namespace PortableSchema\Check;

/**
 * The outcome of a check for one request: errors, or why it could not run.
 */
final class CheckResult
{
    /**
     * @param list<array{code: string, params: array<string, mixed>, message?: string}> $errors
     */
    public function __construct(
        public readonly array $errors = [],
        public readonly ?string $unchecked = null,
    ) {
    }

    public static function unchecked(string $reason): self
    {
        return new self([], $reason);
    }
}
