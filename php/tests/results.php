<?php

/**
 * Prints the full result and the loaded data of every fixture case as JSON lines, for comparing with js/tests/results.ts.
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use PortableSchema\Message\Formatter;
use PortableSchema\Tests\Fixtures;

foreach (Fixtures::files() as $file) {
    [$schemaFile, $tests] = Fixtures::load($file);
    foreach ($tests as $case) {
        $result = Fixtures::run($file, $schemaFile, $case);
        $messages = Formatter::create()->messages($result);
        echo json_encode([$file, $case->description, $result, $messages, $case->data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
    }
}
