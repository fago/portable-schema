<?php

declare(strict_types=1);

namespace PortableSchema\Opis;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PortableSchema\Pointer;

/**
 * Checks schemas against the dialect meta-schema `spec/v1/schema.json`, as Hyperjump does when it compiles.
 *
 * - The meta-schemas assert no formats: their own `$schema` is plain 2020-12.
 * - Defaults off: opis would write the meta-schema's `default`s into the checked schema.
 */
final class MetaSchema
{
    private const DIRECTORY = __DIR__ . '/../../resources/json-schema-2020-12';

    private const SPEC = __DIR__ . '/../../../spec/v1';

    private static ?Validator $validator = null;

    /** @var array<string, true> schemas found valid, by their JSON */
    private static array $valid = [];

    /**
     * @throws \InvalidArgumentException if $schema is not valid against the meta-schema
     */
    public static function assertValid(\stdClass|bool $schema, string $id): void
    {
        $json = json_encode($schema, JSON_THROW_ON_ERROR);
        if (isset(self::$valid[$json])) {
            return;
        }
        $error = self::validator()->validate($schema, Parser::DIALECT)->error();
        if ($error === null) {
            self::$valid[$json] = true;
            return;
        }
        while ($error->subErrors() !== []) {
            $error = $error->subErrors()[0];
        }
        $location = Pointer::fromSegments(array_map(strval(...), $error->data()->fullPath()));
        throw new \InvalidArgumentException("Invalid schema $id at \"$location\": " . (new ErrorFormatter())->formatErrorMessage($error));
    }

    private static function validator(): Validator
    {
        if (self::$validator === null) {
            self::$validator = new Validator();
            self::$validator->parser()->setOption('allowFormats', false)->setOption('allowDefaults', false);
            $resolver = self::$validator->resolver();
            foreach (glob(self::DIRECTORY . '/*.json') as $file) {
                $resolver->registerFile('https://json-schema.org/draft/2020-12/meta/' . basename($file, '.json'), $file);
            }
            $resolver->registerFile(Parser::DIALECT, self::SPEC . '/schema.json');
            $resolver->registerFile('https://portable-schema.dev/v1/vocab', self::SPEC . '/vocab.json');
        }
        return self::$validator;
    }
}
