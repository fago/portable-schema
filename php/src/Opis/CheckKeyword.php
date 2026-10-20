<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;
use PortableSchema\Check\CheckRequest;
use PortableSchema\Check\Checks;
use PortableSchema\Pointer;
use PortableSchema\Trace\Tracer;

/**
 * portable-check: requests the named checks. They run after evaluation, batched per name.
 */
final class CheckKeyword implements Keyword
{
    /**
     * @param array<string, \stdClass> $checks
     */
    public function __construct(private readonly array $checks, private readonly Tracer $tracer, private readonly Checks $collector)
    {
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        $path = $context->fullDataPath();
        $root = $context->rootData();
        [, $parent] = Pointer::get($root, Pointer::fromSegments(array_slice($path, 0, -1)));
        $requests = [];
        foreach ($this->checks as $name => $options) {
            $request = new CheckRequest($name, $context->currentData(), Pointer::fromSegments($path), $options, $path === [] ? null : $parent, $root);
            $this->collector->add($request);
            $requests[] = $request;
        }
        $this->tracer->current()->details = $requests;
        return null;
    }
}
