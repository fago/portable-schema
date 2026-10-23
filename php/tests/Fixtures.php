<?php

declare(strict_types=1);

namespace PortableSchema\Tests;

use Opis\JsonSchema\Validator as OpisValidator;
use PortableSchema\Check\CheckResult;
use PortableSchema\Json;
use PortableSchema\Message\Formatter;
use PortableSchema\Registry;
use PortableSchema\Result;
use PortableSchema\Validator;
use Symfony\Component\Yaml\Yaml;

/**
 * Finds the shared fixtures and runs their cases.
 */
final class Fixtures
{
    public const DIRECTORY = __DIR__ . '/../../fixtures';

    /**
     * Every `*.test.json` below fixtures/, relative to it.
     *
     * @return list<string>
     */
    public static function files(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::DIRECTORY, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.test.json')) {
                $files[] = substr($file->getPathname(), strlen(self::DIRECTORY) + 1);
            }
        }
        sort($files);
        return $files;
    }

    /**
     * Examples hold one data file per `*.test.json`; conformance fixtures hold a schema file and inline cases.
     */
    public static function isExample(string $testFile): bool
    {
        return str_starts_with($testFile, 'examples/');
    }

    /**
     * For a conformance fixture: the schema file and the test cases. For an example: the expectation file and one
     * case, the expectations with the data file's data and its name as description.
     *
     * @return array{\stdClass, list<\stdClass>}
     */
    public static function load(string $testFile): array
    {
        $base = substr($testFile, 0, -strlen('.test.json'));
        $tests = Json::decodeFile(self::DIRECTORY . '/' . $testFile);
        if (!self::isExample($testFile)) {
            return [Json::decodeFile(self::DIRECTORY . "/$base.json"), $tests];
        }
        $case = clone $tests;
        $case->description = basename($base);
        $case->data = self::data(self::DIRECTORY . "/$base");
        return [$tests, [$case]];
    }

    /**
     * The data of `$base.yml` or `$base.json`, in the JSON data model.
     */
    private static function data(string $base): mixed
    {
        $files = array_filter(["$base.yml", "$base.json"], is_file(...));
        if (count($files) !== 1) {
            throw new \RuntimeException("$base needs one data file, .yml or .json.");
        }
        $file = reset($files);
        return str_ends_with($file, '.yml') ? Yaml::parseFile($file, Yaml::PARSE_OBJECT_FOR_MAP) : Json::decodeFile($file);
    }

    /**
     * Validates the fixture files against the fixture file schemas: fixtures/schema.schema.json and
     * fixtures/tests.schema.json, or fixtures/expectation.schema.json for an example.
     *
     * @return list<string> problems found
     */
    public static function check(string $testFile, \stdClass $schemaFile, array $tests): array
    {
        if (self::isExample($testFile)) {
            return self::problems($schemaFile, 'expectation.schema.json');
        }
        return [...self::problems($schemaFile, 'schema.schema.json'), ...self::problems($tests, 'tests.schema.json')];
    }

    /**
     * Validates $data against the fixture file schema $meta.
     *
     * @return list<string> problems found
     */
    public static function problems(mixed $data, string $meta): array
    {
        $validator = new OpisValidator();
        $validator->setMaxErrors(10);
        $result = $validator->validate($data, Json::decodeFile(self::DIRECTORY . '/' . $meta));
        return $result->isValid() ? [] : [$meta . ': ' . json_encode((new \Opis\JsonSchema\Errors\ErrorFormatter())->format($result->error()))];
    }

    public const SCHEMA_ID = 'urn:portable-schema:fixture';

    /**
     * Examples mount their showcase's `schemas/` as `drupal`.
     */
    public const EXAMPLES_NAMESPACE = 'drupal';

    public static function validator(string $testFile, \stdClass $schemaFile, \stdClass $case): Validator
    {
        $registry = Registry::withTypes();
        if (self::isExample($testFile)) {
            $registry->addDirectory(self::DIRECTORY . '/' . dirname($testFile) . '/schemas', self::EXAMPLES_NAMESPACE);
        } else {
            $registry->add($schemaFile->schema, self::SCHEMA_ID);
            foreach (get_object_vars($schemaFile->registry ?? new \stdClass()) as $id => $schema) {
                $registry->add($schema, $id);
            }
        }
        $validator = new Validator($registry);
        foreach (get_object_vars($case->checks ?? new \stdClass()) as $name => $canned) {
            $errors = array_map(fn (\stdClass $e) => ['code' => $e->code, 'params' => Json::toArray($e->params)] + (isset($e->message) ? ['message' => $e->message] : []), $canned->errors);
            $locations = $canned->locations ?? null;
            $validator = $validator->withCheck($name, fn (array $requests) => array_map(
                fn ($request) => new CheckResult($locations === null || in_array($request->location, $locations, true) ? $errors : []),
                $requests,
            ));
        }
        return $validator;
    }

    public static function run(string $testFile, \stdClass $schemaFile, \stdClass $case): Result
    {
        $options = $case->options ?? new \stdClass();
        return self::validator($testFile, $schemaFile, $case)->validate(
            $case->data,
            $case->schema ?? self::SCHEMA_ID,
            partial: $options->partial ?? false,
            emptyAsMissing: $options->emptyAsMissing ?? false,
            locations: $options->locations ?? null,
        );
    }

    /**
     * The result projected to the compared keys, with sorted object keys.
     *
     * @return array{messages: list<string>, valid: bool, errors: list<array<string, mixed>>, unchecked: list<array<string, mixed>>}
     */
    public static function actual(Result $result): array
    {
        return self::canonical([
            'messages' => Formatter::create()->messages($result),
            'valid' => $result->valid,
            'errors' => array_map(fn ($e) => ['instanceLocation' => $e->instanceLocation, 'code' => $e->code, 'params' => Json::toArray($e->params)], $result->errors),
            'unchecked' => array_map(fn ($u) => $u->toArray(), $result->unchecked),
        ]);
    }

    /**
     * What the case expects. Without `errors`, errors are not compared unless the case is valid.
     *
     * @return array{valid: bool, errors?: list<array<string, mixed>>, unchecked: list<array<string, mixed>>, messages?: list<string>}
     */
    public static function expected(\stdClass $case): array
    {
        $expected = ['valid' => $case->valid];
        if (isset($case->errors) || $case->valid) {
            $expected['errors'] = Json::toArray($case->errors ?? []);
        }
        $expected['unchecked'] = Json::toArray($case->unchecked ?? []);
        if (isset($case->messages)) {
            $expected['messages'] = $case->messages;
        }
        return self::canonical($expected);
    }

    private static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }
        return array_map(self::canonical(...), $value);
    }
}
