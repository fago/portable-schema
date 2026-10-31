<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * Turns PHP values into the JSON data model (spec/normalization.md):
 * - a PHP array with the keys 0…n-1 in order is a list, any other a map; a schema's `type: object` makes a list a map
 *   too, which the validator decides during evaluation;
 * - objects as json_encode converts them: \stdClass and other objects by their public properties, JsonSerializable
 *   by jsonSerialize(), backed enums by their value;
 * - closures, resources, other enums, NAN and INF are a `type` error.
 */
final class Normalizer
{
    public static function normalize(mixed $value): Normalized
    {
        $unrepresentable = [];
        $arrayMaps = [];
        $data = self::value($value, '', $unrepresentable, $arrayMaps);
        return new Normalized($data, $unrepresentable, $arrayMaps);
    }

    /**
     * @param array<string, string> $unrepresentable
     * @param array<string, true> $arrayMaps
     */
    private static function value(mixed $value, string $location, array &$unrepresentable, array &$arrayMaps): mixed
    {
        if (is_float($value) && !is_finite($value)) {
            $unrepresentable[$location] = is_nan($value) ? 'nan' : 'infinity';
            return null;
        }
        if (is_resource($value) || gettype($value) === 'resource (closed)') {
            $unrepresentable[$location] = 'resource';
            return null;
        }
        if (is_array($value)) {
            if (self::isList($value)) {
                return array_map(fn ($item, $i) => self::value($item, Pointer::append($location, $i), $unrepresentable, $arrayMaps), $value, array_keys($value));
            }
            $arrayMaps[$location] = true;
            return self::map($value, $location, $unrepresentable, $arrayMaps);
        }
        if (!is_object($value)) {
            return $value;
        }
        if ($value instanceof \Closure || ($value instanceof \UnitEnum && !$value instanceof \BackedEnum)) {
            $unrepresentable[$location] = $value instanceof \Closure ? 'closure' : 'enum';
            return null;
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \JsonSerializable) {
            return self::value($value->jsonSerialize(), $location, $unrepresentable, $arrayMaps);
        }
        return self::map(get_object_vars($value), $location, $unrepresentable, $arrayMaps);
    }

    /**
     * @param array<array-key, mixed> $properties
     * @param array<string, string> $unrepresentable
     * @param array<string, true> $arrayMaps
     */
    private static function map(array $properties, string $location, array &$unrepresentable, array &$arrayMaps): \stdClass
    {
        $map = new \stdClass();
        foreach ($properties as $name => $property) {
            $map->{$name} = self::value($property, Pointer::append($location, $name), $unrepresentable, $arrayMaps);
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
