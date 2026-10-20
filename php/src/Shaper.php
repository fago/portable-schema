<?php

declare(strict_types=1);

namespace PortableSchema;

use PortableSchema\Check\CheckRequest;
use PortableSchema\Trace\KeywordNode;
use PortableSchema\Trace\SchemaNode;

/**
 * Turns the evaluation trace into one error per problem (spec/errors.md, "Shaping"),
 * then applies the reporting options.
 */
final class Shaper
{
    /** Keywords whose subschema is located at the keyword itself. */
    private const REFERENCES = ['$ref', '$dynamicRef', 'portable-typeBy'];

    /** Keywords whose subschema results are reported as the keyword's own error. */
    private const OWN_ERROR = ['not', 'contains'];

    /** Keywords that report a `false` subschema as an unknown property. */
    private const PROPERTIES = ['properties', 'patternProperties', 'additionalProperties', 'unevaluatedProperties'];

    /** Keywords that apply only where no other subschema evaluated. */
    private const UNEVALUATED = ['unevaluatedProperties', 'unevaluatedItems'];

    private const ANNOTATIONS_ONLY = ['title', 'description', '$comment', 'examples', 'default', 'deprecated', 'readOnly', 'writeOnly'];

    /**
     * @param list<string>|null $locations
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly mixed $data,
        private readonly bool $partial = false,
        private readonly bool $emptyAsMissing = false,
        private readonly ?array $locations = null,
    ) {
    }

    public function shape(SchemaNode $root): Result
    {
        $shaped = $this->schema($root, '');

        $mistyped = [];
        foreach ($shaped->errors as $error) {
            if ($error->code === 'type') {
                $mistyped[$error->instanceLocation] = true;
            }
        }
        $checked = fn (string $code, string $location) => !str_starts_with($code, 'check.') || !isset($mistyped[$location]);

        $errors = $this->withoutReportedProperties(array_values(array_filter(
            $shaped->errors,
            fn (ValidationError $e) => $this->reported($e->instanceLocation) && $checked($e->code, $e->instanceLocation),
        )));
        usort($errors, fn (ValidationError $a, ValidationError $b) => Pointer::compare($a->instanceLocation, $b->instanceLocation) ?: Pointer::compare($a->keywordLocation, $b->keywordLocation));
        $errors = $this->unique($errors, fn (ValidationError $e) => [$e->instanceLocation, $e->code, $e->params]);

        $unchecked = array_values(array_filter($shaped->unchecked, fn (Unchecked $u) => $this->reported($u->instanceLocation) && $checked($u->code, $u->instanceLocation)));
        usort($unchecked, fn (Unchecked $a, Unchecked $b) => Pointer::compare($a->instanceLocation, $b->instanceLocation));
        $unchecked = $this->unique($unchecked, fn (Unchecked $u) => [$u->instanceLocation, $u->code, $u->reason]);

        $annotations = [];
        foreach ($shaped->annotations as [$location, $name, $value]) {
            $annotations[$location] ??= new Annotations();
            $target = $annotations[$location];
            if ($name === 'messages') {
                $target->messages += $value;
            } else {
                $target->{$name} ??= $value;
            }
        }

        return new Result($errors, $unchecked, $annotations);
    }

    private function schema(SchemaNode $node, string $location): Shaped
    {
        $shaped = new Shaped();
        if ($node->boolean) {
            if (!$node->valid) {
                $shaped->errors[] = new ValidationError($node->instanceLocation, $location, $node->uri, 'false');
            }
            return $shaped;
        }
        $shaped->annotations = $this->annotations($node);

        $keywords = $this->inDocumentOrder($node);
        $typeFailed = false;
        foreach ($keywords as $keyword) {
            $typeFailed = $typeFailed || ($keyword->keyword === 'type' && !$keyword->valid);
        }
        foreach ($keywords as $keyword) {
            if ($typeFailed && $keyword->keyword !== 'type') {
                continue;
            }
            $shaped->add($this->keyword($keyword, $node, $location));
        }
        $shaped->errors = array_values(array_filter($shaped->errors, fn (ValidationError $e) => !$this->missing($e->instanceLocation)));
        $shaped->unchecked = array_values(array_filter($shaped->unchecked, fn (Unchecked $u) => !$this->missing($u->instanceLocation)));
        return $shaped;
    }

    private function keyword(KeywordNode $node, SchemaNode $parent, string $parentLocation): Shaped
    {
        $location = Pointer::append($parentLocation, $node->keyword);
        [, $value] = $this->registry->resolve($node->absoluteKeywordLocation);
        [, $instance] = Pointer::get($this->data, $node->instanceLocation);
        $error = fn (string $code, array $params = [], ?string $at = null, ?string $message = null) => new ValidationError($at ?? $node->instanceLocation, $location, $node->absoluteKeywordLocation, $code, $params, $message);

        switch ($node->keyword) {
            case 'oneOf':
            case 'anyOf':
                return $this->branches($node, $parent, $parentLocation, $location, $value, $instance, $error);

            case 'if':
                $shaped = new Shaped();
                foreach ($node->children as $child) {
                    if (!str_ends_with($child->uri, '/if')) {
                        $shaped->add($this->schema($child, $this->childLocation($node, $child, $parent, $parentLocation, $location)));
                    }
                }
                return $shaped;

            case 'portable-typeBy':
                return $this->typeBy($node, $parent, $parentLocation, $location, $error);

            case 'portable-check':
                return $this->check($node, $error);
        }

        if (in_array($node->keyword, self::OWN_ERROR, true)) {
            return new Shaped($node->valid ? [] : [$error($node->keyword)]);
        }

        if ($node->children === []) {
            return new Shaped($node->valid ? [] : $this->leaf($node->keyword, $value, $instance, $error));
        }

        $shaped = new Shaped();
        $evaluated = in_array($node->keyword, self::UNEVALUATED, true) ? $this->evaluated($parent, $node) : [];
        $names = $instance instanceof \stdClass ? array_keys(get_object_vars($instance)) : [];
        foreach ($node->children as $i => $child) {
            if (isset($evaluated[$child->instanceLocation])) {
                continue;
            }
            if ($child->boolean && !$child->valid && in_array($node->keyword, self::PROPERTIES, true)) {
                $segments = Pointer::segments($child->instanceLocation);
                $shaped->errors[] = $error('unknownProperty', ['property' => end($segments)]);
                continue;
            }
            if ($node->keyword === 'propertyNames' && !$child->valid) {
                $shaped->errors[] = $error('propertyNames', ['property' => $names[$i] ?? null]);
                continue;
            }
            $shaped->add($this->schema($child, $this->childLocation($node, $child, $parent, $parentLocation, $location)));
        }
        return $shaped;
    }

    /**
     * Drops `unknownProperty` errors for properties with another error at or below them: one error per problem.
     *
     * @param list<ValidationError> $errors
     * @return list<ValidationError>
     */
    private function withoutReportedProperties(array $errors): array
    {
        return array_values(array_filter($errors, function (ValidationError $e) use ($errors) {
            if ($e->code !== 'unknownProperty') {
                return true;
            }
            $property = Pointer::append($e->instanceLocation, (string) $e->params['property']);
            foreach ($errors as $other) {
                if (Pointer::isWithin($other->instanceLocation, $property)) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Instance locations evaluated by a schema other than `false`, without $skip and the subschemas that drop their
     * annotations: failed `anyOf`/`oneOf` branches, `if` conditions and `contains` items, and `not`. For the
     * unevaluated* keywords, since the base libraries differ in what a failed subschema evaluated.
     *
     * @return array<string, true>
     */
    private function evaluated(SchemaNode $node, ?KeywordNode $skip = null, array &$locations = []): array
    {
        if (!$node->boolean) {
            $locations[$node->instanceLocation] = true;
        }
        foreach ($node->keywords as $keyword) {
            foreach ($keyword === $skip ? [] : $keyword->children as $child) {
                if (!$this->discarded($keyword, $child)) {
                    $this->evaluated($child, null, $locations);
                }
            }
        }
        return $locations;
    }

    private function discarded(KeywordNode $keyword, SchemaNode $child): bool
    {
        return match ($keyword->keyword) {
            'not' => true,
            'anyOf', 'oneOf', 'contains' => !$child->valid,
            'if' => !$child->valid && str_ends_with($child->uri, '/if'),
            default => false,
        };
    }

    /**
     * The keywords of a schema in the order of the schema document, since the base libraries evaluate in different orders.
     *
     * @return list<KeywordNode>
     */
    private function inDocumentOrder(SchemaNode $node): array
    {
        [, $schema] = $this->registry->resolve($node->uri);
        $order = $schema instanceof \stdClass ? array_flip(array_keys(get_object_vars($schema))) : [];
        $keywords = $node->keywords;
        usort($keywords, fn (KeywordNode $a, KeywordNode $b) => ($order[$a->keyword] ?? PHP_INT_MAX) <=> ($order[$b->keyword] ?? PHP_INT_MAX));
        return $keywords;
    }

    /**
     * Errors of a keyword that applies no subschema.
     *
     * @return list<ValidationError>
     */
    private function leaf(string $keyword, mixed $value, mixed $instance, \Closure $error): array
    {
        switch ($keyword) {
            case 'type':
                return [$error('type', ['expected' => is_array($value) ? $value : [$value], 'actual' => Json::typeOf($instance)])];
            case 'required':
                if ($this->partial || !$instance instanceof \stdClass) {
                    return [];
                }
                $errors = [];
                foreach ($value as $property) {
                    if (!property_exists($instance, $property)) {
                        $errors[] = $error('required', ['property' => $property]);
                    }
                }
                return $errors;
            case 'dependentRequired':
                if ($this->partial || !$instance instanceof \stdClass) {
                    return [];
                }
                $errors = [];
                foreach ($value as $dependency => $properties) {
                    if (!property_exists($instance, $dependency)) {
                        continue;
                    }
                    foreach ($properties as $property) {
                        if (!property_exists($instance, $property)) {
                            $errors[] = $error('dependentRequired', ['property' => $property, 'dependency' => $dependency]);
                        }
                    }
                }
                return $errors;
            case 'const':
                return [$error('const', ['expected' => $value])];
            case 'enum':
                return [$error('enum', ['value' => $instance, 'allowed' => $value])];
            case 'minLength':
            case 'maxLength':
                return [$error($keyword, ['limit' => $value, 'actual' => Json::length($instance)])];
            case 'minItems':
            case 'maxItems':
                return [$error($keyword, ['limit' => $value, 'actual' => count($instance)])];
            case 'minProperties':
            case 'maxProperties':
                return [$error($keyword, ['limit' => $value, 'actual' => count(get_object_vars($instance))])];
            case 'pattern':
                return [$error('pattern', ['pattern' => $value])];
            case 'format':
                return [$error('format.' . $value)];
            case 'minimum':
            case 'maximum':
            case 'exclusiveMinimum':
            case 'exclusiveMaximum':
                return [$error($keyword, ['limit' => $value])];
            case 'multipleOf':
                return [$error('multipleOf', ['divisor' => $value])];
            case 'uniqueItems':
                return [$error('uniqueItems', ['indexes' => $this->duplicates($instance)])];
            default:
                return [$error($keyword)];
        }
    }

    private function branches(KeywordNode $node, SchemaNode $parent, string $parentLocation, string $location, mixed $value, mixed $instance, \Closure $error): Shaped
    {
        $consts = $this->consts($value);
        if ($consts !== null) {
            if ($node->valid) {
                return new Shaped();
            }
            $titles = array_map(fn (\stdClass $branch) => is_string($branch->title ?? null) ? $branch->title : null, $value);
            $annotations = in_array(null, $titles, true) ? [] : [[$node->instanceLocation, 'allowedTitles', $titles]];
            return new Shaped([$error('enum', ['value' => $instance, 'allowed' => $consts])], [], $annotations);
        }

        $results = [];
        $matched = 0;
        foreach ($node->children as $i => $child) {
            $results[$i] = $this->schema($child, $this->childLocation($node, $child, $parent, $parentLocation, $location));
            $matched += $child->valid ? 1 : 0;
        }

        if ($node->valid) {
            $shaped = new Shaped();
            foreach ($node->children as $i => $child) {
                if ($child->valid) {
                    $shaped->add($results[$i]);
                }
            }
            return $shaped;
        }
        if ($matched > 0 || !$instance instanceof \stdClass || $results === []) {
            return new Shaped([$error($node->keyword, ['matched' => $matched])]);
        }
        return $results[$this->bestBranch($value, $instance, $results)];
    }

    /**
     * The branch whose const discriminator matches, else the one with the fewest errors; ties go to the first.
     *
     * @param array<int, Shaped> $results
     */
    private function bestBranch(array $branches, \stdClass $instance, array $results): int
    {
        foreach ($results as $i => $result) {
            $properties = $branches[$i]->properties ?? null;
            if (!$properties instanceof \stdClass) {
                continue;
            }
            $discriminators = array_filter(get_object_vars($properties), fn ($p) => $p instanceof \stdClass && property_exists($p, 'const'));
            if ($discriminators === []) {
                continue;
            }
            foreach ($discriminators as $name => $property) {
                if (!property_exists($instance, $name) || !Json::equals($instance->{$name}, $property->const)) {
                    continue 2;
                }
            }
            return $i;
        }
        $best = array_key_first($results);
        foreach ($results as $i => $result) {
            if (count($result->errors) < count($results[$best]->errors)) {
                $best = $i;
            }
        }
        return $best;
    }

    /**
     * The values of a oneOf whose branches are all consts, else null.
     *
     * @return list<mixed>|null
     */
    private function consts(mixed $branches): ?array
    {
        if (!is_array($branches) || $branches === []) {
            return null;
        }
        $consts = [];
        foreach ($branches as $branch) {
            if (!$branch instanceof \stdClass || !property_exists($branch, 'const')) {
                return null;
            }
            foreach (array_keys(get_object_vars($branch)) as $key) {
                if ($key !== 'const' && !in_array($key, self::ANNOTATIONS_ONLY, true) && !str_starts_with($key, 'x-')) {
                    return null;
                }
            }
            $consts[] = $branch->const;
        }
        return $consts;
    }

    private function typeBy(KeywordNode $node, SchemaNode $parent, string $parentLocation, string $location, \Closure $error): Shaped
    {
        /** @var TypeBy $selection */
        $selection = $node->details;
        if ($selection->target !== null) {
            $shaped = new Shaped();
            foreach ($node->children as $child) {
                $shaped->add($this->schema($child, $location));
            }
            return $shaped;
        }
        if ($selection->missing !== [] || ($this->partial && $this->emptyAsMissing && array_filter($selection->values, Json::isEmpty(...)) !== [])) {
            if ($this->partial) {
                return new Shaped();
            }
            return new Shaped([$error('typeBy.unresolved', ['value' => null, 'resolved' => null])]);
        }
        $values = $selection->values;
        return new Shaped([$error('typeBy.unresolved', ['value' => count($values) === 1 ? reset($values) : (object) $values, 'resolved' => $selection->resolved])]);
    }

    private function check(KeywordNode $node, \Closure $error): Shaped
    {
        $shaped = new Shaped();
        /** @var CheckRequest $request */
        foreach ($node->details as $request) {
            $result = $request->result;
            if ($result === null) {
                continue;
            }
            if ($result->unchecked !== null) {
                $shaped->unchecked[] = new Unchecked($request->location, 'check.' . $request->name, $result->unchecked);
            }
            foreach ($result->errors as $e) {
                $shaped->errors[] = $error($e['code'], $e['params'], $request->location, $e['message'] ?? null);
            }
        }
        return $shaped;
    }

    /**
     * @return list<array{string, string, mixed}>
     */
    private function annotations(SchemaNode $node): array
    {
        [, $schema] = $this->registry->resolve($node->uri);
        if (!$schema instanceof \stdClass) {
            return [];
        }
        $location = $node->instanceLocation;
        $annotations = [];
        if (is_string($schema->title ?? null) && !str_starts_with($node->uri, 'portable:')) {
            $annotations[] = [$location, 'title', $schema->title];
        }
        if (($schema->{'x-portable-messages'} ?? null) instanceof \stdClass) {
            $annotations[] = [$location, 'messages', get_object_vars($schema->{'x-portable-messages'})];
        }
        if (($schema->properties ?? null) instanceof \stdClass) {
            foreach (get_object_vars($schema->properties) as $name => $property) {
                $title = $this->title($property, explode('#', $node->uri, 2)[0]);
                if ($title !== null) {
                    $annotations[] = [Pointer::append($location, $name), 'title', $title];
                }
            }
        }
        return $annotations;
    }

    /**
     * The title of a property's schema, or of the schema it references, also for missing properties.
     */
    private function title(mixed $schema, string $base): ?string
    {
        if (!$schema instanceof \stdClass) {
            return null;
        }
        if (is_string($schema->title ?? null)) {
            return $schema->title;
        }
        $ref = $schema->{'$ref'} ?? null;
        if (!is_string($ref) || str_starts_with($ref, 'portable:')) {
            return null;
        }
        $uri = str_starts_with($ref, '#') ? $base . $ref : $ref;
        [, $target] = $this->registry->resolve(str_contains($uri, '#') ? $uri : $uri . '#');
        return $target instanceof \stdClass && is_string($target->title ?? null) ? $target->title : null;
    }

    /**
     * The keyword location of a subschema: below its keyword in the same schema, or at the keyword for references.
     */
    private function childLocation(KeywordNode $node, SchemaNode $child, SchemaNode $parent, string $parentLocation, string $location): string
    {
        if (!in_array($node->keyword, self::REFERENCES, true) && str_starts_with($child->uri, $parent->uri . '/')) {
            return $parentLocation . rawurldecode(substr($child->uri, strlen($parent->uri)));
        }
        return $location;
    }

    /**
     * @return list<int> indexes of items that equal an earlier item
     */
    private function duplicates(array $items): array
    {
        $seen = [];
        $indexes = [];
        foreach ($items as $i => $item) {
            $key = Json::key($item);
            if (isset($seen[$key])) {
                $indexes[] = $i;
            }
            $seen[$key] = true;
        }
        return $indexes;
    }

    /**
     * With partial and emptyAsMissing, an empty property value counts as missing.
     */
    private function missing(string $location): bool
    {
        if (!$this->partial || !$this->emptyAsMissing || $location === '') {
            return false;
        }
        [, $parent] = Pointer::get($this->data, Pointer::fromSegments(array_slice(Pointer::segments($location), 0, -1)));
        [$found, $value] = Pointer::get($this->data, $location);
        return $parent instanceof \stdClass && $found && Json::isEmpty($value);
    }

    private function reported(string $location): bool
    {
        if ($this->locations === null) {
            return true;
        }
        foreach ($this->locations as $base) {
            if (Pointer::isWithin($location, $base)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @template T
     * @param list<T> $items
     * @return list<T>
     */
    private function unique(array $items, \Closure $key): array
    {
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            $k = Json::key($key($item));
            if (!isset($seen[$k])) {
                $seen[$k] = true;
                $unique[] = $item;
            }
        }
        return $unique;
    }
}
