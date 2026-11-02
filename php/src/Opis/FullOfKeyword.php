<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Keywords\ErrorTrait;
use Opis\JsonSchema\Keywords\OfTrait;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;

/**
 * anyOf and oneOf that evaluate every branch, as the JS validator does: for the annotations of all matching branches
 * and for the number of matches.
 */
final class FullOfKeyword implements Keyword
{
    use ErrorTrait;
    use OfTrait;

    /**
     * @param list<mixed> $branches
     */
    public function __construct(private readonly string $keyword, private array $branches)
    {
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        $object = $this->createArrayObject($context);
        $errors = [];
        foreach ($this->branches as $index => $branch) {
            if (!$branch instanceof Schema) {
                $branch = $this->branches[$index] = $context->loader()->loadObjectSchema($branch);
            }
            // Only matching branches evaluate properties and items.
            $evaluated = $this->createArrayObject($context);
            if ($error = $context->validateSchemaWithoutEvaluated($branch, null, false, $evaluated)) {
                $errors[] = $error;
            } elseif ($object) {
                foreach ($evaluated as $value) {
                    $object[] = $value;
                }
            }
        }
        $this->addEvaluatedFromArrayObject($object, $context);
        $matched = count($this->branches) - count($errors);
        if ($this->keyword === 'anyOf' ? $matched > 0 : $matched === 1) {
            return null;
        }
        return $this->error($schema, $context, $this->keyword, 'The data must match the branches', ['matched' => $matched], $errors);
    }
}
