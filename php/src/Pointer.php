<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * JSON Pointers (RFC 6901) and the core form of Relative JSON Pointers.
 */
final class Pointer
{
    public const RELATIVE_PATTERN = '@^(0|[1-9][0-9]*)(#|(/([^~/]|~[01])*)*)$@u';

    /**
     * A pointer as URI fragment: every byte outside RFC 3986's fragment characters percent-encoded.
     */
    public static function toFragment(string $pointer): string
    {
        return preg_replace_callback("@[^A-Za-z0-9\\-._~!$&'()*+,;=:\@/?]@", fn (array $m) => sprintf('%%%02X', ord($m[0])), $pointer);
    }

    /**
     * @param list<string|int> $segments
     */
    public static function fromSegments(array $segments): string
    {
        $pointer = '';
        foreach ($segments as $segment) {
            $pointer .= '/' . self::escape((string) $segment);
        }
        return $pointer;
    }

    /**
     * @return list<string>
     */
    public static function segments(string $pointer): array
    {
        if ($pointer === '') {
            return [];
        }
        return array_map(self::unescape(...), array_slice(explode('/', $pointer), 1));
    }

    public static function escape(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }

    public static function unescape(string $segment): string
    {
        return str_replace(['~1', '~0'], ['/', '~'], $segment);
    }

    public static function append(string $pointer, string|int $segment): string
    {
        return $pointer . '/' . self::escape((string) $segment);
    }

    /**
     * Whether $pointer equals $base or lies below it.
     */
    public static function isWithin(string $pointer, string $base): bool
    {
        return $base === '' || $pointer === $base || str_starts_with($pointer, $base . '/');
    }

    /**
     * Looks up a value. Returns [found, value].
     *
     * @return array{bool, mixed}
     */
    public static function get(mixed $data, string $pointer): array
    {
        foreach (self::segments($pointer) as $segment) {
            if ($data instanceof \stdClass && property_exists($data, $segment)) {
                $data = $data->{$segment};
            } elseif (is_array($data) && preg_match('/^(0|[1-9][0-9]*)$/', $segment) && array_key_exists((int) $segment, $data)) {
                $data = $data[(int) $segment];
            } else {
                return [false, null];
            }
        }
        return [true, $data];
    }

    /**
     * Resolves a Relative JSON Pointer from $location in $root. Returns [found, value].
     *
     * @return array{bool, mixed}
     */
    public static function resolveRelative(string $relative, string $location, mixed $root): array
    {
        if (!preg_match(self::RELATIVE_PATTERN, $relative, $m)) {
            return [false, null];
        }
        $segments = self::segments($location);
        $up = (int) $m[1];
        if ($up > count($segments)) {
            return [false, null];
        }
        $segments = array_slice($segments, 0, count($segments) - $up);
        if ($m[2] === '#') {
            if ($segments === []) {
                return [false, null];
            }
            $key = end($segments);
            [, $parent] = self::get($root, self::fromSegments(array_slice($segments, 0, -1)));
            return [true, is_array($parent) ? (int) $key : $key];
        }
        return self::get($root, self::fromSegments($segments) . $m[2]);
    }

    /**
     * Compares pointers segment by segment: array indexes numerically, other segments by code point.
     */
    public static function compare(string $a, string $b): int
    {
        $sa = self::segments($a);
        $sb = self::segments($b);
        for ($i = 0, $n = min(count($sa), count($sb)); $i < $n; $i++) {
            $x = $sa[$i];
            $y = $sb[$i];
            if ($x === $y) {
                continue;
            }
            if (ctype_digit($x) && ctype_digit($y)) {
                return (int) $x <=> (int) $y;
            }
            return strcmp($x, $y) <=> 0;
        }
        return count($sa) <=> count($sb);
    }
}
