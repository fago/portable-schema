<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * Exports Portable Schemas as standard-only JSON Schema for tools that can't resolve the registry, e.g. agent tool
 * definitions or Drupal SDC.
 *
 * Lost in the export: the added keywords (`portable-typeBy`, `portable-check`, …) and `x-portable-*` annotations.
 */
final class Exporter
{
    public const DRAFT_2020_12 = 'https://json-schema.org/draft/2020-12/schema';

    public const DRAFT_04 = 'http://json-schema.org/draft-04/schema#';

    /** Formats draft-04 defines; others are dropped from draft-04 exports. */
    private const DRAFT_04_FORMATS = ['date-time', 'email', 'hostname', 'ipv4', 'ipv6', 'uri'];

    public function __construct(private readonly Registry $registry)
    {
    }

    /**
     * A self-contained JSON Schema 2020-12: every `$ref` merged into the schema that uses it.
     */
    public function inline(string $id): \stdClass
    {
        $schema = $this->resolve($id, []);
        $schema = (object) (['$schema' => self::DRAFT_2020_12] + get_object_vars($schema));
        return $schema;
    }

    /**
     * A self-contained JSON Schema draft-04, as Drupal core's SDC metadata uses.
     */
    public function draft04(string $id): \stdClass
    {
        $schema = $this->toDraft04($this->resolve($id, []));
        return (object) (['$schema' => self::DRAFT_04] + get_object_vars($schema));
    }

    /**
     * @param list<string> $seen references being resolved, to refuse cycles
     */
    private function resolve(string $uri, array $seen): \stdClass|bool
    {
        if (in_array($uri, $seen, true)) {
            throw new \RuntimeException("Cannot inline the recursive reference $uri");
        }
        [$found, $schema] = $this->registry->resolve(str_contains($uri, '#') ? $uri : $uri . '#');
        if (!$found) {
            throw new \OutOfBoundsException("Unknown schema $uri");
        }
        $resolved = $this->schema($schema, explode('#', $uri, 2)[0], [...$seen, $uri]);
        if ($resolved instanceof \stdClass && str_starts_with($uri, 'portable:')) {
            // The type library's own descriptions don't help consumers of a concrete schema.
            unset($resolved->title, $resolved->description);
        }
        return $resolved;
    }

    /**
     * @param list<string> $seen
     */
    private function schema(mixed $schema, string $base, array $seen): mixed
    {
        if (!$schema instanceof \stdClass) {
            return $schema;
        }
        $out = new \stdClass();
        if (isset($schema->{'$ref'})) {
            $ref = $schema->{'$ref'};
            $out = $this->resolve(str_starts_with($ref, '#') ? $base . $ref : $ref, $seen);
            $out = $out instanceof \stdClass ? clone $out : $out;
        }
        foreach (get_object_vars($schema) as $key => $value) {
            if (in_array($key, ['$ref', '$schema', '$id'], true) || str_starts_with($key, 'portable-') || str_starts_with($key, 'x-portable-')) {
                continue;
            }
            $value = match ($key) {
                'properties', 'patternProperties', '$defs', 'dependentSchemas' => $this->map($value, $base, $seen),
                'items', 'additionalProperties', 'unevaluatedProperties', 'not', 'if', 'then', 'else', 'contains', 'propertyNames' => $this->schema($value, $base, $seen),
                'allOf', 'anyOf', 'oneOf', 'prefixItems' => array_map(fn ($s) => $this->schema($s, $base, $seen), $value),
                default => $value,
            };
            $out = $this->merge($out, $key, $value);
        }
        return $out;
    }

    private function map(\stdClass $schemas, string $base, array $seen): \stdClass
    {
        $out = new \stdClass();
        foreach (get_object_vars($schemas) as $name => $schema) {
            $out->{$name} = $this->schema($schema, $base, $seen);
        }
        return $out;
    }

    /**
     * Adds a keyword of the using schema to the referenced one: properties merge, `required` lists join, other
     * keywords of the using schema win.
     */
    private function merge(mixed $target, string $key, mixed $value): mixed
    {
        if (!$target instanceof \stdClass) {
            return $target;
        }
        if (!isset($target->{$key})) {
            $target->{$key} = $value;
        } elseif ($key === 'required') {
            $target->{$key} = array_values(array_unique([...$target->{$key}, ...$value]));
        } elseif ($key === 'properties') {
            $merged = clone $target->{$key};
            foreach (get_object_vars($value) as $name => $schema) {
                if (isset($merged->{$name}) && $merged->{$name} instanceof \stdClass && $schema instanceof \stdClass) {
                    $property = clone $merged->{$name};
                    foreach (get_object_vars($schema) as $k => $v) {
                        $property = $this->merge($property, $k, $v);
                    }
                    $merged->{$name} = $property;
                } else {
                    $merged->{$name} = $schema;
                }
            }
            $target->{$key} = $merged;
        } else {
            $target->{$key} = $value;
        }
        return $target;
    }

    private function toDraft04(mixed $schema): mixed
    {
        if (!$schema instanceof \stdClass) {
            return $schema;
        }
        $out = new \stdClass();
        foreach (get_object_vars($schema) as $key => $value) {
            switch ($key) {
                case 'const':
                    $out->enum = [$value];
                    break;
                case 'exclusiveMinimum':
                case 'exclusiveMaximum':
                    $out->{$key === 'exclusiveMinimum' ? 'minimum' : 'maximum'} = $value;
                    $out->{$key} = true;
                    break;
                case 'unevaluatedProperties':
                    $out->additionalProperties = $this->toDraft04($value);
                    break;
                case 'dependentRequired':
                case 'dependentSchemas':
                    $out->dependencies = (object) array_merge(get_object_vars($out->dependencies ?? new \stdClass()), get_object_vars($this->mapDraft04($value)));
                    break;
                case 'format':
                    if (in_array($value, self::DRAFT_04_FORMATS, true)) {
                        $out->format = $value;
                    }
                    break;
                case 'properties':
                case 'patternProperties':
                    $out->{$key} = $this->mapDraft04($value);
                    break;
                case 'items':
                case 'additionalProperties':
                case 'not':
                    $out->{$key} = $this->toDraft04($value);
                    break;
                case 'allOf':
                case 'anyOf':
                case 'oneOf':
                    $out->{$key} = array_map($this->toDraft04(...), $value);
                    break;
                case 'prefixItems':
                case 'contains':
                case 'propertyNames':
                case 'if':
                case 'then':
                case 'else':
                case '$defs':
                    break;
                default:
                    $out->{$key} = $value;
            }
        }
        return $out;
    }

    private function mapDraft04(\stdClass $schemas): \stdClass
    {
        $out = new \stdClass();
        foreach (get_object_vars($schemas) as $name => $schema) {
            $out->{$name} = $this->toDraft04($schema);
        }
        return $out;
    }
}
