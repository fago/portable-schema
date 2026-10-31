<?php

declare(strict_types=1);

namespace PortableSchema\Check;

interface CheckProvider
{
    /**
     * @param list<CheckRequest> $requests
     * @return list<CheckResult> one result per request, in the same order
     */
    public function check(string $name, array $requests): array;
}
