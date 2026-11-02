<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * Turns PHP values into the JSON data model (spec/normalization.md):
 * - a PHP array with the keys 0…n-1 in order is a list, any other a map; `[]` stays a list here and becomes a map
 *   where a schema's `type` allows object and not array, which the validator decides during evaluation;
 * - objects as json_encode converts them: \stdClass and other objects by their public properties, JsonSerializable
 *   by jsonSerialize(), backed enums by their value;
 * - closures, resources, unbacked enums, NAN and INF are unsupported: a `type` error.
 */
final class Normalizer
{
    public static function normalize(mixed $value): Normalized
    {
        $unsupported = [];
        $data = self::value($value, '', $unsupported);
        return new Normalized($data, $unsupported);
    }

    /**
     * @param list<string> $unsupported
     */
    private static function value(mixed $value, string $location, array &$unsupported): mixed
    {
        if ((is_float($value) && !is_finite($value)) || is_resource($value) || gettype($value) === 'resource (closed)'
            || $value instanceof \Closure || ($value instanceof \UnitEnum && !$value instanceof \BackedEnum)) {
            $unsupported[] = $location;
            return null;
        }
        if (is_array($value)) {
            if (self::isList($value)) {
                return array_map(fn ($item, $i) => self::value($item, Pointer::append($location, $i), $unsupported), $value, array_keys($value));
            }
            return self::map($value, $location, $unsupported);
        }
        if (!is_object($value)) {
            return $value;
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \JsonSerializable) {
            return self::value($value->jsonSerialize(), $location, $unsupported);
        }
        return self::map(get_object_vars($value), $location, $unsupported);
    }

    /**
     * @param array<array-key, mixed> $properties
     * @param list<string> $unsupported
     */
    private static function map(array $properties, string $location, array &$unsupported): \stdClass
    {
        $map = new \stdClass();
        foreach ($properties as $name => $property) {
            $map->{$name} = self::value($property, Pointer::append($location, $name), $unsupported);
        }
        return $map;
    }

    /**
     * Whether the keys are 0…n-1 in order.
     */
    public static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
