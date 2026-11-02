<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\ValidationContext;

final class Context
{
    /**
     * The data the validation started with. opis gives each `$ref` a sub-context whose root data is the current value.
     */
    public static function root(ValidationContext $context): mixed
    {
        while ($context->parent() !== null) {
            $context = $context->parent();
        }
        return $context->rootData();
    }
}
