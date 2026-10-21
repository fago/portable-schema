<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * Schemas and catalogs by $id. Values follow the JSON data model.
 */
final class Registry
{
    public const TYPES_DIRECTORY = __DIR__ . '/../../types/v1';

    /** @var array<string, \stdClass|bool> */
    private array $schemas = [];

    /** @var array<string, string> ids by their normalized form */
    private array $normalized = [];

    /**
     * A registry with the type library.
     */
    public static function withTypes(): self
    {
        return (new self())->addDirectory(self::TYPES_DIRECTORY);
    }

    /**
     * Adds a schema under $id, or under its own $id.
     *
     * The id must be an absolute URI without a fragment, dot segments or the `file:` scheme, and must differ from
     * every other id after percent-decoding and with the scheme and authority in lower case: Hyperjump normalizes ids,
     * so `a%2Bb` and `a+b` would be one schema in JS. The schema is one resource: its own `$id`, if any, is $id, and
     * no value below its root has a `$id` or `$schema`.
     */
    public function add(\stdClass|bool $schema, ?string $id = null): self
    {
        $id ??= $schema instanceof \stdClass && isset($schema->{'$id'}) ? $schema->{'$id'} : null;
        if ($id === null) {
            throw new \InvalidArgumentException('A schema without $id needs an id.');
        }
        $normalized = self::normalize($id);
        if (!Formats::check('uri', $id) || str_contains($id, '#') || str_starts_with($normalized, 'file:')
            || preg_match('#[:/]\.{1,2}(/|$)#', explode('?', $normalized)[0])) {
            throw new \InvalidArgumentException("The schema id $id is not an absolute URI without a fragment, dot segments or file: scheme.");
        }
        if ($schema instanceof \stdClass && isset($schema->{'$id'}) && $schema->{'$id'} !== $id) {
            throw new \InvalidArgumentException("The schema $id has the \$id {$schema->{'$id'}}.");
        }
        $embedded = self::embedded($schema, '');
        if ($embedded !== null) {
            throw new \InvalidArgumentException("The schema $id has \$id or \$schema below its root, at $embedded.");
        }
        $other = $this->normalized[$normalized] ??= $id;
        if ($other !== $id) {
            throw new \InvalidArgumentException("The schema ids $other and $id are the same after normalization.");
        }
        $this->schemas[$id] = Json::jsOrder($schema);
        return $this;
    }

    /**
     * $id percent-decoded, with the scheme and authority in lower case.
     */
    private static function normalize(string $id): string
    {
        return rawurldecode(preg_replace_callback('#^[^:]*:(//[^/?]*)?#', fn (array $m) => strtolower($m[0]), $id));
    }

    /**
     * The location of the first value below the root with a string `$id` or `$schema`: Hyperjump reads these in
     * every object, `default` and `const` included.
     */
    private static function embedded(mixed $value, string $location): ?string
    {
        if ($location !== '' && $value instanceof \stdClass && (is_string($value->{'$id'} ?? null) || is_string($value->{'$schema'} ?? null))) {
            return $location;
        }
        if (!is_array($value) && !$value instanceof \stdClass) {
            return null;
        }
        foreach ($value as $key => $item) {
            $embedded = self::embedded($item, Pointer::append($location, $key));
            if ($embedded !== null) {
                return $embedded;
            }
        }
        return null;
    }

    public function __clone()
    {
        $this->schemas = unserialize(serialize($this->schemas));
    }

    /**
     * Adds every *.json file in $directory, recursively, by its $id.
     */
    public function addDirectory(string $directory): self
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        $paths = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'json') {
                $paths[] = $file->getPathname();
            }
        }
        sort($paths);
        foreach ($paths as $path) {
            $this->add(Json::decodeFile($path));
        }
        return $this;
    }

    public function has(string $id): bool
    {
        return isset($this->schemas[$id]);
    }

    public function get(string $id): \stdClass|bool
    {
        return $this->schemas[$id] ?? throw new \OutOfBoundsException("Unknown schema $id");
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->schemas);
    }

    /**
     * Looks up a value by an absolute URI with a JSON Pointer fragment. Returns [found, value].
     *
     * @return array{bool, mixed}
     */
    public function resolve(string $uri): array
    {
        [$id, $fragment] = array_pad(explode('#', $uri, 2), 2, '');
        if (!isset($this->schemas[$id])) {
            return [false, null];
        }
        return Pointer::get($this->schemas[$id], rawurldecode($fragment));
    }
}
