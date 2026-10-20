<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * Helpers for the JSON data model: objects are \stdClass, arrays are lists.
 */
final class Json
{
    public static function typeOf(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => is_finite($value) && floor($value) === $value ? 'integer' : 'number',
            is_string($value) => 'string',
            is_array($value) => 'array',
            default => 'object',
        };
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === '' || $value === [] || ($value instanceof \stdClass && get_object_vars($value) === []);
    }

    public static function decode(string $json): mixed
    {
        return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    }

    public static function decodeFile(string $path): mixed
    {
        return self::decode(file_get_contents($path) ?: throw new \RuntimeException("Cannot read $path"));
    }

    /**
     * Converts decoded JSON to plain PHP arrays, e.g. for comparing results.
     */
    public static function toArray(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }
        return is_array($value) ? array_map(self::toArray(...), $value) : $value;
    }

    /**
     * Converts plain PHP values to the JSON data model: associative arrays become objects.
     */
    public static function fromArray(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = array_map(self::fromArray(...), $value);
            return array_is_list($value) ? $value : (object) $value;
        }
        return $value;
    }

    /**
     * Equality in the JSON data model: numbers by value, objects regardless of key order, objects never equal arrays.
     */
    public static function equals(mixed $a, mixed $b): bool
    {
        return self::key($a) === self::key($b);
    }

    /**
     * A string that is the same for equal values.
     */
    public static function key(mixed $value): string
    {
        return json_encode(self::canonical($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function canonical(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);
            return ['o', array_map(fn ($k, $v) => [(string) $k, self::canonical($v)], array_keys($properties), $properties)];
        }
        if (is_array($value)) {
            return ['a', array_map(self::canonical(...), $value)];
        }
        return is_float($value) && self::typeOf($value) === 'integer' && abs($value) < 2 ** 53 ? (int) $value : $value;
    }

    /**
     * Number of Unicode code points.
     */
    public static function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }
}
