<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * The type selection of portable-typeBy, shared by the keyword and the shaper.
 */
final class TypeBy
{
    /**
     * @param array<string, mixed> $values the source values found, by name
     * @param list<string> $missing names of source values that are missing or not scalar
     */
    public function __construct(
        public readonly array $values,
        public readonly array $missing,
        public readonly ?string $resolved,
        public readonly ?string $target,
    ) {
    }

    /**
     * @param array<string, string> $source
     * @param callable(string): bool $exists
     */
    public static function select(string $href, array $source, ?string $fallback, string $base, string $location, mixed $root, callable $exists): self
    {
        $values = [];
        $missing = [];
        $strings = [];
        foreach ($source as $name => $pointer) {
            [$found, $value] = Pointer::resolveRelative($pointer, $location, $root);
            $string = $found ? self::toString($value) : null;
            if ($string === null) {
                $missing[] = $name;
                continue;
            }
            $values[$name] = $value;
            $strings[$name] = $string;
        }
        if ($missing !== []) {
            return new self($values, $missing, null, null);
        }
        $resolved = self::resolve(UriTemplate::expand($href, $strings), $base);
        if (!str_contains($resolved, '#') && $exists($resolved)) {
            return new self($values, [], $resolved, $resolved);
        }
        $fallback = $fallback === null ? null : self::resolve($fallback, $base);
        return new self($values, [], $resolved, $fallback !== null && $exists($fallback) ? $fallback : null);
    }

    private static function toString(mixed $value): ?string
    {
        return match (Json::typeOf($value)) {
            'string' => $value,
            'integer' => Json::number($value),
            'boolean' => $value ? 'true' : 'false',
            default => null,
        };
    }

    private static function resolve(string $uri, string $base): string
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.\-]*:/', $uri)) {
            return $uri;
        }
        return str_starts_with($uri, '#') ? $base . $uri : $uri;
    }
}
