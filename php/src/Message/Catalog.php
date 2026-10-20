<?php

declare(strict_types=1);

namespace PortableSchema\Message;

use PortableSchema\Json;

/**
 * Message source texts by id, in the format of decision 0001.
 */
final class Catalog
{
    public const DEFAULT = __DIR__ . '/../../../messages/en.json';

    /**
     * @param array<string, \stdClass> $entries
     */
    public function __construct(private readonly array $entries)
    {
    }

    public static function default(): self
    {
        return new self(get_object_vars(Json::decodeFile(self::DEFAULT)));
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }

    /**
     * The source text for $id; for plural entries, the English form for $params[plural].
     *
     * @param array<string, mixed> $params
     * @return array{string, ?int} source and count
     */
    public function source(string $id, array $params): array
    {
        $entry = $this->entries[$id];
        if (!isset($entry->plural)) {
            return [$entry->text, null];
        }
        $count = (int) ($params[$entry->plural] ?? 0);
        return [$count === 1 ? $entry->one : $entry->other, $count];
    }
}
