<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * The allowed values of a catalog as a hash set (spec/profile.md, "Catalogs"): a scalar `enum`, or a `oneOf` whose
 * branches are distinct `const`s.
 */
final class CatalogSet
{
    private const ANNOTATIONS_ONLY = ['title', 'description', '$comment', 'examples', 'default', 'deprecated', 'readOnly', 'writeOnly'];

    /**
     * @param array<string, true> $keys
     */
    private function __construct(private readonly array $keys)
    {
    }

    public static function fromEnum(mixed $values): ?self
    {
        if (!is_array($values) || array_filter($values, fn ($v) => is_array($v) || $v instanceof \stdClass) !== []) {
            return null;
        }
        return new self(self::keys($values));
    }

    /**
     * Null unless the branches are distinct consts.
     */
    public static function fromOneOf(mixed $branches): ?self
    {
        $consts = self::consts($branches);
        if ($consts === null) {
            return null;
        }
        $keys = self::keys($consts);
        return count($keys) === count($consts) ? new self($keys) : null;
    }

    /**
     * The values of `oneOf` or `anyOf` branches that are each a `const` with annotations only, else null.
     *
     * @return list<mixed>|null
     */
    public static function consts(mixed $branches): ?array
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

    public function contains(mixed $value): bool
    {
        return isset($this->keys[Json::key($value)]);
    }

    /**
     * @param list<mixed> $values
     * @return array<string, true>
     */
    private static function keys(array $values): array
    {
        $keys = [];
        foreach ($values as $value) {
            $keys[Json::key($value)] = true;
        }
        return $keys;
    }
}
