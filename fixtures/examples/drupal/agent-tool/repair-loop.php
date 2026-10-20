<?php

/**
 * Prints the agent tools as MCP tool definitions and a repair loop: calls with invalid arguments, the errors the
 * tool returns, and the corrected calls. repair-loop.mts prints the same in JS.
 *
 *   php repair-loop.php
 */

declare(strict_types=1);

require __DIR__ . '/../../../../vendor/autoload.php';

use PortableSchema\Check\CheckRequest;
use PortableSchema\Check\CheckResult;
use PortableSchema\Exporter;
use PortableSchema\Json;
use PortableSchema\Message\Formatter;
use PortableSchema\Registry;
use PortableSchema\Result;
use PortableSchema\ValidationError;
use PortableSchema\Validator;

const TOOLS = [
    'find_images' => [
        'calls' => [
            '{"query": "skiing photos", "limit": 100, "sort": "date"}',
            '{"query": "ski", "limit": 5}',
        ],
        'result' => '{"images": [{"id": 7, "title": "Ski tour", "url": "https://dri.es/photos/ski-tour.jpg", "alt": "Two skiers on a ridge above the clouds"}]}',
    ],
    'update_image_alt' => [
        'calls' => [
            '{"image_id": "7", "alt": ""}',
            '{"image_id": 70, "alt": "Two skiers on a ridge above the clouds"}',
            '{"image_id": 7, "alt": "Two skiers on a ridge above the clouds"}',
        ],
        'result' => '{"id": 7, "url": "https://dri.es/photos/ski-tour.jpg", "alt": "Two skiers on a ridge above the clouds"}',
    ],
];

/** Files that exist on the site. */
const FILES = [7, 8];

$registry = Registry::withTypes();
foreach (['../schemas', '../catalogs', 'schemas'] as $directory) {
    $registry->addDirectory(__DIR__ . '/' . $directory);
}
$validator = (new Validator($registry))->withCheck('drupal:ValidReference', fn (array $requests) => array_map(
    fn (CheckRequest $request) => in_array($request->value, FILES, true)
        ? new CheckResult()
        : new CheckResult([['code' => 'check.drupal:ValidReference', 'params' => ['id' => $request->value], 'message' => "The referenced file ({$request->value}) does not exist."]]),
    $requests,
));
$exporter = new Exporter($registry);
$formatter = Formatter::create();

$print = fn (mixed $value) => print json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

echo "# MCP tool definitions\n\n";
$definitions = [];
foreach (array_keys(TOOLS) as $name) {
    $input = $registry->get("drupal:tool.$name.input");
    $definitions[] = [
        'name' => $name,
        'title' => $input->title,
        'description' => $input->description,
        'inputSchema' => $exporter->inline("drupal:tool.$name.input"),
        'outputSchema' => $exporter->inline("drupal:tool.$name.output"),
    ];
}
$print($definitions);

/**
 * The tool result for invalid arguments: messages as text, the errors as structured content.
 */
$toolError = fn (Result $result) => [
    'isError' => true,
    'content' => [['type' => 'text', 'text' => implode("\n", $formatter->messages($result))]],
    'structuredContent' => [
        'errors' => array_map(fn (ValidationError $error, string $message) => [
            'instanceLocation' => $error->instanceLocation,
            'code' => $error->code,
            'params' => (object) $error->params,
            'message' => $message,
        ], $result->errors, $formatter->messages($result)),
    ],
];

foreach (TOOLS as $name => $tool) {
    echo "\n# Repair loop: $name\n";
    foreach ($tool['calls'] as $call) {
        echo "\n→ $name $call\n";
        $result = $validator->validate(Json::decode($call), "drupal:tool.$name.input");
        if (!$result->valid) {
            echo '← ';
            $print($toolError($result));
            continue;
        }
        $output = Json::decode($tool['result']);
        $checked = $validator->validate($output, "drupal:tool.$name.output");
        echo '← ', $checked->valid ? 'valid output: ' : 'invalid output: ';
        $print(['structuredContent' => $output]);
    }
}
