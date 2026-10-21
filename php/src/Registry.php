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

    /** @var array<string, string> ids by their percent-decoded form */
    private array $decoded = [];

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
     * The id must be an absolute URI, and must differ from every other id after percent-decoding: Hyperjump decodes
     * ids, so `a%2Bb` and `a+b` would be one schema in JS.
     */
    public function add(\stdClass|bool $schema, ?string $id = null): self
    {
        $id ??= $schema instanceof \stdClass && isset($schema->{'$id'}) ? $schema->{'$id'} : null;
        if ($id === null) {
            throw new \InvalidArgumentException('A schema without $id needs an id.');
        }
        if (!Formats::check('uri', $id)) {
            throw new \InvalidArgumentException("The schema id $id is not an absolute URI.");
        }
        $other = $this->decoded[rawurldecode($id)] ??= $id;
        if ($other !== $id) {
            throw new \InvalidArgumentException("The schema ids $other and $id are the same after percent-decoding.");
        }
        $this->schemas[$id] = $schema;
        return $this;
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
