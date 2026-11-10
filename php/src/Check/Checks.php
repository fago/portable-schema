<?php

declare(strict_types=1);

namespace PortableSchema\Check;

/**
 * Collects check requests during evaluation and runs them batched per name.
 */
final class Checks
{
    /** @var list<CheckRequest> */
    private array $requests = [];

    public function reset(): void
    {
        $this->requests = [];
    }

    public function add(CheckRequest $request): void
    {
        $this->requests[] = $request;
    }

    /**
     * Order: local callback, then provider, else unchecked with reason noProvider.
     *
     * @param array<string, callable(list<CheckRequest>): list<CheckResult>> $callbacks
     * @param array<string, CheckProvider> $providers
     */
    public function run(array $callbacks, array $providers): void
    {
        $byName = [];
        foreach ($this->requests as $request) {
            $byName[$request->name][] = $request;
        }
        foreach ($byName as $name => $requests) {
            try {
                $results = match (true) {
                    isset($callbacks[$name]) => $callbacks[$name]($requests),
                    isset($providers[$name]) => $providers[$name]->check($name, $requests),
                    default => array_fill(0, count($requests), CheckResult::unchecked('noProvider')),
                };
            } catch (\Throwable) {
                $results = array_fill(0, count($requests), CheckResult::unchecked('error'));
            }
            foreach ($requests as $i => $request) {
                $request->result = $results[$i] ?? CheckResult::unchecked('error');
            }
        }
    }
}
