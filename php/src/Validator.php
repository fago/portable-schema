<?php

declare(strict_types=1);

namespace PortableSchema;

use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Uri;
use Opis\JsonSchema\ValidationContext;
use PortableSchema\Check\CheckProvider;
use PortableSchema\Check\CheckRequest;
use PortableSchema\Check\CheckResult;
use PortableSchema\Check\Checks;
use PortableSchema\Opis\Parser;
use PortableSchema\Opis\RegistryResolver;
use PortableSchema\Trace\Tracer;

/**
 * Validates data against Portable Schemas. Data follows the JSON data model: objects are \stdClass.
 */
final class Validator
{
    private readonly Tracer $tracer;

    private readonly Checks $checks;

    private readonly SchemaLoader $loader;

    /** @var array<string, \Closure(list<CheckRequest>): list<CheckResult>> */
    private array $callbacks = [];

    /** @var array<string, CheckProvider> */
    private array $providers = [];

    public function __construct(private readonly Registry $registry)
    {
        $this->tracer = new Tracer();
        $this->checks = new Checks();
        $this->loader = new SchemaLoader(new Parser($this->tracer, $this->checks), new RegistryResolver($registry), false);
    }

    public function registry(): Registry
    {
        return $this->registry;
    }

    /**
     * A validator that runs $callback for the named check, overriding any provider.
     *
     * @param callable(list<CheckRequest>): list<CheckResult> $callback
     */
    public function withCheck(string $name, callable $callback): self
    {
        $clone = clone $this;
        $clone->callbacks[$name] = $callback(...);
        return $clone;
    }

    public function withProvider(string $name, CheckProvider $provider): self
    {
        $clone = clone $this;
        $clone->providers[$name] = $provider;
        return $clone;
    }

    /**
     * @param string $id the schema's id in the registry
     * @param list<string>|null $locations
     */
    public function validate(mixed $data, string $id, bool $partial = false, bool $emptyAsMissing = false, ?array $locations = null): Result
    {
        $compiled = $this->loader->loadSchemaById(Uri::parse($id, true)) ?? throw new \OutOfBoundsException("Unknown schema $id");

        $this->tracer->reset();
        $this->checks->reset();
        $compiled->validate(new ValidationContext($data, $this->loader, null, null, [], null, PHP_INT_MAX, false));
        $this->checks->run($this->callbacks, $this->providers);

        return (new Shaper($this->registry, $data, $partial, $emptyAsMissing, $locations))->shape($this->tracer->root());
    }
}
