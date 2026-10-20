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
     * @return array{\stdClass, list<\stdClass>} the schema file and the test cases
     */
    public static function load(string $testFile): array
    {
        $schemaFile = substr($testFile, 0, -strlen('.test.json')) . '.json';
        return [Json::decodeFile(self::DIRECTORY . '/' . $schemaFile), Json::decodeFile(self::DIRECTORY . '/' . $testFile)];
    }

    /**
     * Validates the fixture files against fixtures/schema.schema.json and fixtures/tests.schema.json.
     *
     * @return list<string> problems found
     */
    public static function check(\stdClass $schemaFile, array $tests): array
    {
        $validator = new OpisValidator();
        $validator->setMaxErrors(10);
        $problems = [];
        foreach (['schema.schema.json' => $schemaFile, 'tests.schema.json' => $tests] as $meta => $data) {
            $result = $validator->validate($data, Json::decodeFile(self::DIRECTORY . '/' . $meta));
            if (!$result->isValid()) {
                $problems[] = $meta . ': ' . json_encode((new \Opis\JsonSchema\Errors\ErrorFormatter())->format($result->error()));
            }
        }
        return $problems;
    }

    public const SCHEMA_ID = 'urn:portable-schema:fixture';

    public static function validator(\stdClass $schemaFile, \stdClass $case): Validator
    {
        $registry = Registry::withTypes()->add($schemaFile->schema, self::SCHEMA_ID);
        foreach (get_object_vars($schemaFile->registry ?? new \stdClass()) as $id => $schema) {
            $registry->add($schema, $id);
        }
        $validator = new Validator($registry);
        foreach (get_object_vars($case->checks ?? new \stdClass()) as $name => $canned) {
            $errors = array_map(fn (\stdClass $e) => ['code' => $e->code, 'params' => Json::toArray($e->params)], $canned->errors);
            $validator = $validator->withCheck($name, fn (array $requests) => array_fill(0, count($requests), new CheckResult($errors)));
        }
        return $validator;
    }

    public static function run(\stdClass $schemaFile, \stdClass $case): Result
    {
        $options = $case->options ?? new \stdClass();
        return self::validator($schemaFile, $case)->validate(
            $case->data,
            self::SCHEMA_ID,
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
