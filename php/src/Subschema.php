<?php

declare(strict_types=1);

namespace PortableSchema;

use PortableSchema\Trace\SchemaNode;

/**
 * Walks the schemas that apply at a location of the instance (spec/validation.md, "Subschema at a location").
 * Branches of anyOf, oneOf and if are decided by the trace of a full evaluation of the instance.
 */
final class Subschema
{
    /** @var array<string, bool> verdict per schema URI and instance location, from the trace */
    private array $verdicts = [];

    /** @var list<string> */
    private array $unresolved = [];

    public function __construct(private readonly Registry $registry, private readonly mixed $root, SchemaNode $trace)
    {
        $this->index($trace);
    }

    /**
     * @param string $location a JSON Pointer, or a dotted path
     */
    public function at(string $id, string $location): SubschemaResult
    {
        $this->unresolved = [];
        $instanceLocation = '';
        $found = true;
        $value = $this->root;
        $members = $this->expand(self::member($id, '', ''), $instanceLocation, $found, $value);
        foreach (self::tokens($location) as $token) {
            $anyMatch = false;
            foreach ($members as $m) {
                $schema = $this->schema($m);
                if ($schema instanceof \stdClass && $this->modes($schema, $found, $value)['object'] && (self::hasProperty($schema, $token) || self::patterns($schema, $token) !== [])) {
                    $anyMatch = true;
                }
            }
            $instanceLocation = Pointer::append($instanceLocation, $token);
            [$childFound, $childValue] = self::child($found, $value, $token);
            $next = [];
            $push = function (array $m, string|int ...$keys) use (&$next, $instanceLocation, $childFound, $childValue): void {
                array_push($next, ...$this->expand(self::below($m, ...$keys), $instanceLocation, $childFound, $childValue));
            };
            foreach ($members as $m) {
                $schema = $this->schema($m);
                if (!$schema instanceof \stdClass) {
                    continue;
                }
                $modes = $this->modes($schema, $found, $value);
                if ($modes['object']) {
                    $matching = self::patterns($schema, $token);
                    if (self::hasProperty($schema, $token)) {
                        $push($m, 'properties', $token);
                    }
                    foreach ($matching as $pattern) {
                        $push($m, 'patternProperties', $pattern);
                    }
                    if (!self::hasProperty($schema, $token) && $matching === [] && property_exists($schema, 'additionalProperties')) {
                        $push($m, 'additionalProperties');
                    }
                    if (!$anyMatch && property_exists($schema, 'unevaluatedProperties')) {
                        $push($m, 'unevaluatedProperties');
                    }
                }
                if ($modes['array'] && self::isIndex($token)) {
                    if (is_array($schema->prefixItems ?? null) && (int) $token < count($schema->prefixItems)) {
                        $push($m, 'prefixItems', $token);
                    } elseif (property_exists($schema, 'items')) {
                        $push($m, 'items');
                    }
                }
            }
            $members = $next;
            [$found, $value] = [$childFound, $childValue];
        }
        return new SubschemaResult(array_column($members, 'location'), $this->unresolved);
    }

    /**
     * The member and the schemas its keywords apply, each expanded in place.
     *
     * @param array{id: string, pointer: string, location: string} $m
     * @return list<array{id: string, pointer: string, location: string}>
     */
    private function expand(array $m, string $instanceLocation, bool $found, mixed $value): array
    {
        $out = [$m];
        $schema = $this->schema($m);
        if (!$schema instanceof \stdClass) {
            return $out;
        }
        if (is_string($schema->{'$ref'} ?? null)) {
            $target = $this->ref($schema->{'$ref'}, $m['id']);
            if ($target !== null) {
                array_push($out, ...$this->expand(self::member($target['id'], $target['pointer'], $m['location'] . '/$ref'), $instanceLocation, $found, $value));
            }
        }
        if (is_array($schema->allOf ?? null)) {
            foreach (array_keys($schema->allOf) as $i) {
                array_push($out, ...$this->expand(self::below($m, 'allOf', $i), $instanceLocation, $found, $value));
            }
        }
        foreach (['anyOf', 'oneOf'] as $keyword) {
            $branches = $schema->{$keyword} ?? null;
            if (!is_array($branches) || ($keyword === 'oneOf' && CatalogSet::consts($branches) !== null)) {
                continue;
            }
            if (!$found) {
                $this->unresolved[] = $m['location'] . '/' . $keyword;
                continue;
            }
            foreach (array_keys($branches) as $i) {
                if ($this->valid($m['id'], $m['pointer'] . Pointer::fromSegments([$keyword, $i]), $instanceLocation)) {
                    array_push($out, ...$this->expand(self::below($m, $keyword, $i), $instanceLocation, $found, $value));
                }
            }
        }
        if (property_exists($schema, 'if')) {
            if (!$found) {
                $this->unresolved[] = $m['location'] . '/if';
            } else {
                $branch = $this->valid($m['id'], Pointer::append($m['pointer'], 'if'), $instanceLocation) ? 'then' : 'else';
                if (property_exists($schema, $branch)) {
                    array_push($out, ...$this->expand(self::below($m, $branch), $instanceLocation, $found, $value));
                }
            }
        }
        $typeBy = $schema->{'portable-typeBy'} ?? null;
        if ($typeBy instanceof \stdClass) {
            $selection = TypeBy::select(
                $typeBy->href,
                get_object_vars($typeBy->source),
                $typeBy->fallback ?? null,
                $m['id'],
                $instanceLocation,
                $this->root,
                $this->registry->has(...),
            );
            if ($selection->missing !== []) {
                $this->unresolved[] = $m['location'] . '/portable-typeBy';
            } elseif ($selection->target !== null) {
                array_push($out, ...$this->expand(self::member($selection->target, '', $m['location'] . '/portable-typeBy'), $instanceLocation, $found, $value));
            }
        }
        return $out;
    }

    /**
     * Whether the member's children are looked up as an object's, an array's, or both: by the value, else by `type`.
     *
     * @return array{object: bool, array: bool}
     */
    private function modes(\stdClass $schema, bool $found, mixed $value): array
    {
        if ($found) {
            return ['object' => $value instanceof \stdClass, 'array' => is_array($value)];
        }
        $type = $schema->type ?? null;
        $types = is_string($type) ? [$type] : (is_array($type) ? $type : null);
        return ['object' => $types === null || in_array('object', $types, true), 'array' => $types === null || in_array('array', $types, true)];
    }

    /**
     * @param array{id: string, pointer: string, location: string} $m
     */
    private function schema(array $m): mixed
    {
        [$found, $schema] = Pointer::get($this->registry->get($m['id']), $m['pointer']);
        return $found ? $schema : null;
    }

    /**
     * @return array{id: string, pointer: string}|null
     */
    private function ref(string $ref, string $base): ?array
    {
        [$id, $fragment] = array_pad(explode('#', $ref, 2), 2, null);
        $target = $id === '' ? $base : $id;
        if (!$this->registry->has($target)) {
            return null;
        }
        return ['id' => $target, 'pointer' => $fragment === null ? '' : rawurldecode($fragment)];
    }

    private function valid(string $id, string $pointer, string $instanceLocation): bool
    {
        return $this->verdicts["$id#$pointer\n$instanceLocation"] ?? false;
    }

    private function index(SchemaNode $node): void
    {
        [$id, $fragment] = array_pad(explode('#', $node->uri, 2), 2, null);
        $pointer = $fragment === null ? '' : rawurldecode($fragment);
        $this->verdicts["$id#$pointer\n{$node->instanceLocation}"] ??= $node->valid;
        foreach ($node->keywords as $keyword) {
            foreach ($keyword->children as $child) {
                $this->index($child);
            }
        }
    }

    /**
     * The reference tokens of a JSON Pointer, or of a dotted path, which is split on `.` without escaping.
     *
     * @return list<string>
     */
    private static function tokens(string $location): array
    {
        if ($location === '' || $location === '/') {
            return [];
        }
        return str_starts_with($location, '/') ? Pointer::segments($location) : explode('.', $location);
    }

    /**
     * @return array{bool, mixed}
     */
    private static function child(bool $found, mixed $value, string $token): array
    {
        if ($found && $value instanceof \stdClass && property_exists($value, $token)) {
            return [true, $value->{$token}];
        }
        if ($found && is_array($value) && self::isIndex($token) && (int) $token < count($value)) {
            return [true, $value[(int) $token]];
        }
        return [false, null];
    }

    private static function hasProperty(\stdClass $schema, string $token): bool
    {
        return ($schema->properties ?? null) instanceof \stdClass && property_exists($schema->properties, $token);
    }

    /**
     * The patternProperties keys whose pattern matches the token.
     *
     * @return list<string>
     */
    private static function patterns(\stdClass $schema, string $token): array
    {
        if (!($schema->patternProperties ?? null) instanceof \stdClass) {
            return [];
        }
        return array_values(array_filter(
            array_keys(get_object_vars($schema->patternProperties)),
            fn (string $pattern) => preg_match('~' . str_replace('~', '\~', $pattern) . '~u', $token) === 1,
        ));
    }

    /**
     * @return array{id: string, pointer: string, location: string}
     */
    private static function member(string $id, string $pointer, string $location): array
    {
        return ['id' => $id, 'pointer' => $pointer, 'location' => $location];
    }

    /**
     * @param array{id: string, pointer: string, location: string} $m
     * @return array{id: string, pointer: string, location: string}
     */
    private static function below(array $m, string|int ...$keys): array
    {
        return self::member($m['id'], $m['pointer'] . Pointer::fromSegments($keys), $m['location'] . Pointer::fromSegments($keys));
    }

    private static function isIndex(string $token): bool
    {
        return preg_match('/^(0|[1-9][0-9]*)$/D', $token) === 1;
    }
}
