<?php

declare(strict_types=1);

namespace PortableSchema;

final class Result implements \JsonSerializable
{
    public readonly bool $valid;

    /**
     * @param list<ValidationError> $errors
     * @param list<Unchecked> $unchecked
     * @param array<string, Annotations> $annotations by instance location
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $unchecked,
        public readonly array $annotations,
    ) {
        $this->valid = $errors === [];
    }

    public function annotations(string $location): Annotations
    {
        return $this->annotations[$location] ?? new Annotations();
    }

    /**
     * @return array{valid: bool, errors: list<array<string, mixed>>, unchecked: list<array<string, string>>}
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => array_map(fn (ValidationError $e) => $e->toArray(), $this->errors),
            'unchecked' => array_map(fn (Unchecked $u) => $u->toArray(), $this->unchecked),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return ['valid' => $this->valid, 'errors' => $this->errors, 'unchecked' => $this->unchecked];
    }
}
