<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * Intermediate shaping result of one subtree.
 *
 * @internal
 */
final class Shaped
{
    /**
     * @param list<ValidationError> $errors
     * @param list<Unchecked> $unchecked
     * @param list<array{string, string, mixed}> $annotations [location, name, value], outermost first
     */
    public function __construct(
        public array $errors = [],
        public array $unchecked = [],
        public array $annotations = [],
    ) {
    }

    public function add(Shaped $other): self
    {
        array_push($this->errors, ...$other->errors);
        array_push($this->unchecked, ...$other->unchecked);
        array_push($this->annotations, ...$other->annotations);
        return $this;
    }
}
