<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\Uri;
use PortableSchema\Registry;

/**
 * Loads schemas for opis from the registry.
 */
final class RegistryResolver extends SchemaResolver
{
    public function __construct(private readonly Registry $registry)
    {
    }

    public function resolve(Uri $uri)
    {
        $id = explode('#', (string) $uri, 2)[0];
        return $this->registry->has($id) ? $this->registry->get($id) : null;
    }
}
