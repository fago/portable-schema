<?php

declare(strict_types=1);

namespace PortableSchema;

use Opis\JsonSchema\Schema;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Uri;
use Opis\JsonSchema\ValidationContext;
use PortableSchema\Check\CheckProvider;
use PortableSchema\Check\CheckRequest;
use PortableSchema\Check\CheckResult;
use PortableSchema\Check\Checks;
use PortableSchema\Opis\BooleanSchemas;
use PortableSchema\Opis\MetaSchema;
use PortableSchema\Opis\Parser;
use PortableSchema\Opis\RegistryResolver;
use PortableSchema\Trace\Tracer;

/**
 * Validates data against Portable Schemas. Data follows the JSON data model: objects are \stdClass.
 */
final class Validator
{
    private readonly Registry $registry;

    private readonly Tracer $tracer;

    private readonly Checks $checks;

    private readonly SchemaLoader $loader;

    /** @var array<string, \Closure(list<CheckRequest>): list<CheckResult>> */
    private array $callbacks = [];

    /** @var array<string, CheckProvider> */
    private array $providers = [];

    /**
     * Compiles every schema of $registry. Schemas added to $registry later are not seen.
     *
     * @throws \InvalidArgumentException|\Opis\JsonSchema\Exceptions\SchemaException for a schema outside the profile
     */
    public function __construct(Registry $registry)
    {
        $this->registry = clone $registry;
        $this->tracer = new Tracer();
        $this->checks = new Checks();
        $this->loader = new SchemaLoader(new Parser($this->tracer, $this->checks), new RegistryResolver($this->registry), false);
        foreach ($this->registry->ids() as $id) {
            MetaSchema::assertValid($this->registry->get($id), $id);
        }
        foreach ($this->registry->ids() as $id) {
            $this->compile($this->load($id));
        }
    }

    private function load(string $id): Schema
    {
        return $this->loader->loadSchemaById(Uri::parse($id, true)) ?? throw new \OutOfBoundsException("Unknown schema $id");
    }

    /**
     * Parses $schema's subschemas and `$ref` targets, which opis parses only when data reaches them.
     */
    private function compile(Schema $schema): void
    {
        $data = $schema->info()->data();
        if (!$data instanceof \stdClass) {
            return;
        }
        if (is_string($data->{'$ref'} ?? null)) {
            $this->load((string) Uri::merge($data->{'$ref'}, $schema->info()->idBaseRoot(), true));
        }
        foreach (BooleanSchemas::subschemas($data) as $subschema) {
            $this->compile($this->loader->loadObjectSchema($subschema));
        }
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
        $compiled = $this->load($id);
        $data = Json::jsOrder($data);

        $this->tracer->reset();
        $this->checks->reset();
        $compiled->validate(new ValidationContext($data, $this->loader, null, null, [], null, PHP_INT_MAX, false));
        // Taken before the checks run: a callback may validate again.
        $root = $this->tracer->root();
        $this->checks->run($this->callbacks, $this->providers);

        return (new Shaper($this->registry, $data, $partial, $emptyAsMissing, $locations))->shape($root);
    }
}
