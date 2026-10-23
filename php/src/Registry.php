<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * Schemas and catalogs by $id. Values follow the JSON data model.
 */
final class Registry
{
    public const TYPES_DIRECTORY = __DIR__ . '/../../types/v1';

    /**
     * The meta-schemas the validators load: Hyperjump keeps them in one global store, so a registry id must not be one.
     */
    public const META_SCHEMA_IDS = [
        'https://json-schema.org/draft/2020-12/schema',
        'https://json-schema.org/draft/2020-12/meta/core',
        'https://json-schema.org/draft/2020-12/meta/applicator',
        'https://json-schema.org/draft/2020-12/meta/validation',
        'https://json-schema.org/draft/2020-12/meta/meta-data',
        'https://json-schema.org/draft/2020-12/meta/format-annotation',
        'https://json-schema.org/draft/2020-12/meta/format-assertion',
        'https://json-schema.org/draft/2020-12/meta/content',
        'https://json-schema.org/draft/2020-12/meta/unevaluated',
        'https://portable-schema.dev/v1/schema',
        'https://portable-schema.dev/v1/vocab',
        'https://portable-schema.dev/v1/load',
    ];

    public const TYPES_NAMESPACE = 'portable';

    /** @var array<string, \stdClass|bool> */
    private array $schemas = [];

    /** @var array<string, string> ids by their normalized form */
    private array $normalized = [];

    /**
     * A registry with the type library.
     */
    public static function withTypes(): self
    {
        return (new self())->mount(self::TYPES_DIRECTORY, self::TYPES_NAMESPACE);
    }

    /**
     * Adds a schema under $id, or under its own $id. Ids in the `portable:` namespace are reserved for the type library.
     *
     * The id must be an absolute URI without a fragment, dot segments or the `file:` scheme, must not be a meta-schema
     * id, and must differ from every other id after percent-decoding and with the scheme and authority in lower case:
     * Hyperjump normalizes ids, so `a%2Bb` and `a+b` would be one schema in JS. The schema is one resource: its own
     * `$id`, if any, is $id, and no value below its root has a `$id` or `$schema`.
     */
    public function add(\stdClass|bool $schema, ?string $id = null): self
    {
        $id ??= $schema instanceof \stdClass && is_string($schema->{'$id'} ?? null) ? $schema->{'$id'} : null;
        if ($id === null) {
            throw new \InvalidArgumentException('A schema without $id needs an id.');
        }
        if (str_starts_with(self::normalize($id), self::TYPES_NAMESPACE . ':')) {
            throw new \InvalidArgumentException("The schema id $id is in the reserved namespace " . self::TYPES_NAMESPACE . '.');
        }
        return $this->store($schema, $id);
    }

    private function store(\stdClass|bool $schema, string $id): self
    {
        $normalized = self::normalize($id);
        if (!Formats::check('uri', $id) || str_contains($id, '#') || str_starts_with($normalized, 'file:')
            || preg_match('#[:/]\.{1,2}(/|$)#', self::normalize(explode('?', $id)[0]))) {
            throw new \InvalidArgumentException("The schema id $id is not an absolute URI without a fragment, dot segments or file: scheme.");
        }
        if (in_array($normalized, array_map(self::normalize(...), self::META_SCHEMA_IDS), true)) {
            throw new \InvalidArgumentException("The schema id $id is a meta-schema id.");
        }
        if ($schema instanceof \stdClass && property_exists($schema, '$id') && $schema->{'$id'} !== $id) {
            throw new \InvalidArgumentException("The schema $id has the \$id " . json_encode($schema->{'$id'}, JSON_UNESCAPED_SLASHES) . '.');
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
     * Mounts $directory under $namespace: every *.json file below it, except *.test.json, is added by the id
     * `<namespace>:<relative path without .json>`, percent-decoded. A `$id` in the file must equal that id.
     */
    public function addDirectory(string $directory, string $namespace): self
    {
        if ($namespace === self::TYPES_NAMESPACE) {
            throw new \InvalidArgumentException("The namespace $namespace is reserved for the type library.");
        }
        return $this->mount($directory, $namespace);
    }

    private function mount(string $directory, string $namespace): self
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $namespace)) {
            throw new \InvalidArgumentException("The namespace $namespace is not a lowercase identifier.");
        }
        $directory = rtrim($directory, '/');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        $paths = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'json' && !str_ends_with($file->getFilename(), '.test.json')) {
                $paths[] = $file->getPathname();
            }
        }
        sort($paths);
        foreach ($paths as $path) {
            $id = $namespace . ':' . rawurldecode(substr($path, strlen($directory) + 1, -strlen('.json')));
            $schema = Json::decodeFile($path);
            if ($schema instanceof \stdClass && property_exists($schema, '$id') && $schema->{'$id'} !== $id) {
                throw new \InvalidArgumentException("The file $path has the \$id " . json_encode($schema->{'$id'}, JSON_UNESCAPED_SLASHES) . ", not $id.");
            }
            $this->store($schema, $id);
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
