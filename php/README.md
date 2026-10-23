# fago/portable-schema (PHP)

PHP implementation of [Portable Schema](../README.md), on [opis/json-schema](https://opis.io/json-schema) 2.x. PHP 8.3+.

The Composer package is defined by the repository root's `composer.json`: Packagist reads it only from there.

The validator reads `spec/`, `types/` and `messages/` from the repository, so it runs from a checkout of the whole repository.

## Usage

```php
use PortableSchema\Message\Formatter;
use PortableSchema\Registry;
use PortableSchema\Validator;

$registry = Registry::withTypes()->addDirectory('schemas');
$validator = (new Validator($registry))
    ->withCheck('drupal:UniqueField', fn (array $requests) => /* one CheckResult per request */);

$result = $validator->validate($data, 'drupal:block.block', partial: true);
$result->valid;
$result->errors;     // ValidationError[]: instanceLocation, keywordLocation, code, params
$result->unchecked;  // Unchecked[]
Formatter::create()->messages($result); // English messages
```

- **`new Validator()` compiles** every schema of the registry and checks it against the dialect meta-schema; it throws for a schema outside the profile. It keeps a copy of the registry, so schemas added later are not seen. Construction takes about 0.3 ms per schema; `validate()` reuses the compiled schemas.
- **Data** follows the JSON data model: objects are `\stdClass`, as `json_decode()` returns them.
- **Schemas** are validated by their id in the registry.
- **Exports:** `Exporter::inline()` (self-contained JSON Schema 2020-12) and `Exporter::draft04()`.

## Design

The same as the JS implementation, with the same names ([validator design](../docs/architecture/validator.md)):

| Class | Role |
|---|---|
| `Opis\MetaSchema` | Checks every registry schema against `spec/v1/schema.json` and `spec/v1/load.json`, as the JS validator does; the 2020-12 meta-schemas it builds on are in `php/resources/`, copied from Hyperjump ([README](resources/json-schema-2020-12/README.md)) |
| `Opis\Parser`, `Opis\Draft` | opis 2020-12 with the Portable Schema keywords (`portable-typeBy`, `portable-check`, pinned `format`); every keyword and schema is wrapped to record the evaluation |
| `Trace\Tracer` | The evaluation as a tree of schema and keyword nodes |
| `Shaper` | Turns the trace into one error per problem and applies the options |
| `Formats`, `TypeBy`, `Check\Checks` | Pinned format checks, the type selection of `portable-typeBy`, named checks run batched per name |
| `Message\Formatter` | Message requests from errors and the catalog |

## Tests

```sh
composer test
```

PHPUnit runs every fixture under [`fixtures/`](../fixtures). `scripts/compare-results.sh` compares the full results with the JS implementation.
