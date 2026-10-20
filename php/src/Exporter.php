<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * Exports Portable Schemas as standard-only JSON Schema for tools that can't resolve the registry, e.g. agent tool
 * definitions or Drupal SDC.
 *
 * Lost in the export: the added keywords (`portable-typeBy`, `portable-check`, …), `x-portable-*` annotations and the
 * pinned formats: generic validators treat `format` as an annotation and know neither `decimal` nor `int32`.
 */
final class Exporter
{
    public const DRAFT_2020_12 = 'https://json-schema.org/draft/2020-12/schema';

    public const DRAFT_04 = 'http://json-schema.org/draft-04/schema#';

    /**
     * Formats kept in draft-04 exports: the ones draft-04 defines, and uri-reference, which SDC uses and
     * justinrainbow/json-schema checks.
     */
    private const DRAFT_04_FORMATS = ['date-time', 'email', 'hostname', 'ipv4', 'ipv6', 'uri', 'uri-reference'];

    /** Keywords whose result depends on the keywords listed, in the same schema. */
    private const DEPENDENT = [
        'additionalProperties' => ['properties', 'patternProperties', 'additionalProperties', 'unevaluatedProperties', 'allOf', 'anyOf', 'oneOf', 'if', 'then', 'else', 'dependentSchemas'],
        'unevaluatedProperties' => ['properties', 'patternProperties', 'additionalProperties', 'unevaluatedProperties', 'allOf', 'anyOf', 'oneOf', 'if', 'then', 'else', 'dependentSchemas'],
        'items' => ['prefixItems', 'items', 'unevaluatedItems', 'contains', 'allOf', 'anyOf', 'oneOf', 'if', 'then', 'else'],
        'unevaluatedItems' => ['prefixItems', 'items', 'unevaluatedItems', 'contains', 'allOf', 'anyOf', 'oneOf', 'if', 'then', 'else'],
    ];

    /** Keywords that only annotate: the using schema's value wins. */
    private const ANNOTATIONS = ['title', 'description', '$comment', 'examples', 'default', 'deprecated', 'readOnly', 'writeOnly'];

    /** Bounds that merge to the stricter value. */
    private const BOUNDS = [
        'minimum' => 'max', 'exclusiveMinimum' => 'max', 'minLength' => 'max', 'minItems' => 'max', 'minProperties' => 'max',
        'maximum' => 'min', 'exclusiveMaximum' => 'min', 'maxLength' => 'min', 'maxItems' => 'min', 'maxProperties' => 'min',
    ];

    /** Draft-04 keywords kept as they are; everything else not converted is dropped. */
    private const DRAFT_04_KEYWORDS = ['title', 'description', 'default', 'type', 'enum', 'multipleOf', 'maxLength', 'minLength', 'pattern', 'maxItems', 'minItems', 'uniqueItems', 'maxProperties', 'minProperties', 'required'];

    /** Keywords that evaluate properties in place, which draft-04's additionalProperties doesn't see. */
    private const IN_PLACE = ['allOf', 'anyOf', 'oneOf', 'if', 'then', 'else', 'dependentSchemas', '$ref'];

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
        $use = new \stdClass();
        foreach (get_object_vars($schema) as $key => $value) {
            if (in_array($key, ['$ref', '$schema', '$id'], true) || str_starts_with($key, 'portable-') || str_starts_with($key, 'x-portable-')) {
                continue;
            }
            $use->{$key} = match ($key) {
                'properties', 'patternProperties', '$defs', 'dependentSchemas' => $this->map($value, $base, $seen),
                'items', 'additionalProperties', 'unevaluatedProperties', 'unevaluatedItems', 'not', 'if', 'then', 'else', 'contains', 'propertyNames' => $this->schema($value, $base, $seen),
                'allOf', 'anyOf', 'oneOf', 'prefixItems' => array_map(fn ($s) => $this->schema($s, $base, $seen), $value),
                default => $value,
            };
        }
        if (!isset($schema->{'$ref'})) {
            return $use;
        }
        $ref = $schema->{'$ref'};
        $target = $this->resolve(str_starts_with($ref, '#') ? $base . $ref : $ref, $seen);
        if (self::mergeable($target, $use)) {
            return self::merge($target, $use);
        }
        return (object) (['allOf' => [$target, ...($use->allOf ?? [])]] + get_object_vars($use));
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
     * Whether a referenced schema and the keywords next to the `$ref` can be one schema with the same meaning:
     * no keyword in both, except `required`, bounds, annotations and `properties` (recursively), and no keyword
     * that depends on the properties or items the other declares. Otherwise the export uses `allOf`.
     */
    private static function mergeable(mixed $target, \stdClass $use): bool
    {
        if ($target === true) {
            return true;
        }
        if (!$target instanceof \stdClass) {
            return false;
        }
        foreach (get_object_vars($use) as $key => $value) {
            if (!property_exists($target, $key) || $key === 'required' || isset(self::BOUNDS[$key]) || in_array($key, self::ANNOTATIONS, true) || Json::toArray($target->{$key}) === Json::toArray($value)) {
                continue;
            }
            if ($key !== 'properties') {
                return false;
            }
            foreach (get_object_vars($value) as $name => $schema) {
                if (property_exists($target->properties, $name) && !($schema instanceof \stdClass && self::mergeable($target->properties->{$name}, $schema))) {
                    return false;
                }
            }
        }
        // The using schema's unevaluated* keywords see the referenced schema's results anyway.
        $checks = [[$target, self::DEPENDENT, $use], [$use, array_diff_key(self::DEPENDENT, ['unevaluatedProperties' => 0, 'unevaluatedItems' => 0]), $target]];
        foreach ($checks as [$schema, $dependent, $other]) {
            foreach ($dependent as $keyword => $keywords) {
                if (property_exists($schema, $keyword) && array_intersect($keywords, array_keys(get_object_vars($other))) !== []) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * The keywords next to the `$ref` added to the referenced schema: `required` lists join, the stricter bound
     * wins, properties merge, annotations of the using schema win.
     */
    private static function merge(mixed $target, \stdClass $use): mixed
    {
        if ($target === true) {
            return $use;
        }
        $out = clone $target;
        foreach (get_object_vars($use) as $key => $value) {
            if (!property_exists($out, $key)) {
                $out->{$key} = $value;
            } elseif ($key === 'required') {
                $out->required = array_values(array_unique([...$out->required, ...$value]));
            } elseif (isset(self::BOUNDS[$key])) {
                $out->{$key} = self::BOUNDS[$key]($out->{$key}, $value);
            } elseif ($key !== 'properties') {
                $out->{$key} = $value;
            } else {
                $properties = clone $out->properties;
                foreach (get_object_vars($value) as $name => $schema) {
                    $properties->{$name} = property_exists($properties, $name) ? self::merge($properties->{$name}, $schema) : $schema;
                }
                $out->properties = $properties;
            }
        }
        return $out;
    }

    /**
     * @param bool $booleanAllowed whether draft-04 allows a boolean here (additionalProperties, additionalItems)
     */
    private function toDraft04(mixed $schema, bool $booleanAllowed = false): mixed
    {
        if (is_bool($schema)) {
            return $booleanAllowed ? $schema : ($schema ? new \stdClass() : (object) ['not' => new \stdClass()]);
        }
        if (!$schema instanceof \stdClass) {
            return $schema;
        }
        if (isset($schema->unevaluatedProperties) && array_intersect(self::IN_PLACE, array_keys(get_object_vars($schema))) !== []) {
            throw new \RuntimeException('Draft-04 has no unevaluatedProperties: additionalProperties would not see the properties of ' . implode(', ', array_intersect(self::IN_PLACE, array_keys(get_object_vars($schema)))));
        }
        $out = new \stdClass();
        foreach (get_object_vars($schema) as $key => $value) {
            switch ($key) {
                case 'const':
                    $out->enum = [$value];
                    break;
                case 'minimum':
                case 'maximum':
                case 'exclusiveMinimum':
                case 'exclusiveMaximum':
                    $this->bound($out, $schema, str_ends_with($key, 'inimum') ? 'minimum' : 'maximum');
                    break;
                case 'unevaluatedProperties':
                case 'additionalProperties':
                    $out->additionalProperties = $this->toDraft04($value, true);
                    break;
                case 'dependentRequired':
                case 'dependentSchemas':
                    $dependencies = $key === 'dependentRequired' ? $value : $this->mapDraft04($value);
                    foreach (get_object_vars($dependencies) as $name => $dependency) {
                        if (isset($out->dependencies->{$name})) {
                            throw new \RuntimeException("Draft-04 dependencies can't hold both dependentRequired and dependentSchemas for $name");
                        }
                        $out->dependencies ??= new \stdClass();
                        $out->dependencies->{$name} = $dependency;
                    }
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
                case 'prefixItems':
                    $out->items = array_map(fn ($s) => $this->toDraft04($s), $value);
                    if (property_exists($schema, 'items')) {
                        $out->additionalItems = $this->toDraft04($schema->items, true);
                    }
                    break;
                case 'items':
                    if (!property_exists($schema, 'prefixItems')) {
                        $out->items = $this->toDraft04($value);
                    }
                    break;
                case 'not':
                    $out->not = $this->toDraft04($value);
                    break;
                case 'allOf':
                case 'anyOf':
                case 'oneOf':
                    $out->{$key} = array_map(fn ($s) => $this->toDraft04($s), $value);
                    break;
                default:
                    if (in_array($key, self::DRAFT_04_KEYWORDS, true)) {
                        $out->{$key} = $value;
                    }
            }
        }
        return $out;
    }

    /**
     * The stricter of `minimum` and `exclusiveMinimum` (or the maximums), as draft-04's boolean exclusive form.
     */
    private function bound(\stdClass $out, \stdClass $schema, string $keyword): void
    {
        if (isset($out->{$keyword})) {
            return;
        }
        $exclusive = 'exclusive' . ucfirst($keyword);
        $inclusive = $schema->{$keyword} ?? null;
        $strict = $schema->{$exclusive} ?? null;
        $lower = $keyword === 'minimum';
        if ($strict !== null && ($inclusive === null || ($lower ? $strict >= $inclusive : $strict <= $inclusive))) {
            $out->{$keyword} = $strict;
            $out->{$exclusive} = true;
        } else {
            $out->{$keyword} = $inclusive;
        }
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
