<?php

/**
 * Prints the full result of every fixture case as JSON lines, for comparing with js/tests/results.mjs.
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use PortableSchema\Tests\Fixtures;

foreach (Fixtures::files() as $file) {
    [$schemaFile, $tests] = Fixtures::load($file);
    foreach ($tests as $case) {
        $result = Fixtures::run($schemaFile, $case);
        echo json_encode([$file, $case->description, $result], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), "\n";
    }
}
