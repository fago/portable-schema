# Validator

How the reference validators in PHP and JS are built. Both follow the same design. The normative behaviour is in [`spec/validation.md`](../../spec/validation.md), [`spec/vocabulary.md`](../../spec/vocabulary.md) and [`spec/errors.md`](../../spec/errors.md).

## Flow

```
data ──► normalize ──► evaluate keywords ──► shape errors ──► result {valid, errors, unchecked}
                            │
                            ├─ $ref, portable-typeBy ──► registry: types, catalogs (live or dump)
                            └─ portable-check, portable-refine ──► local callbacks, providers
```

1. **Compile once:**
   - resolve references;
   - check the schema against the meta-schema;
   - bind keyword handlers;
   - precompute regexes and URI templates;
   - compile every scalar `enum`, and every `oneOf` whose branches are all `const`s with distinct values, to a hash set: O(1) lookups by JSON Schema equality, with the branches' annotations kept for labels ([decision 0006](../decisions/0006-catalogs-as-labeled-oneof.md));
   - record locations.

   It generates no code. The compiled schema is cached. `portable-typeBy` targets must be resolvable from the registry at validation time; implementations may compile them eagerly or lazily, with the same result.
2. **Normalize** the data.
3. **Evaluate:** interpret the compiled schema against the data, collecting annotations.
4. **Shape** errors, then apply the reporting options (`partial`, `emptyAsMissing`, `locations`).

Keywords are plugins with a compile part and an interpret part, in both languages.

## API

```php
$result = $validator->validate($data, $schema, partial: true, emptyAsMissing: true, locations: ['/settings']);
```

```ts
const result = validator.validate(data, schema, { partial: true, emptyAsMissing: true, locations: ['/settings'] })
const full = await validator.validateAsync(data, schema)
```

- **`validate()` is synchronous.**
  - In PHP, checks run synchronously.
  - In JS, checks without a synchronous implementation are reported as `unchecked` with reason `pending`.
- **`validateAsync()`** (JS) additionally runs asynchronous checks, e.g. through an integration's HTTP endpoint.

## Named checks

### Providers

Integrations register providers for the checks they offer:

```php
interface CheckProvider {
  /**
   * @param CheckRequest[] $requests
   * @return CheckResult[]
   */
  public function check(string $name, array $requests): array;
}
```

```ts
interface CheckProvider {
  check(name: string, requests: CheckRequest[]): CheckResult[] | Promise<CheckResult[]>
}
```

- **A `CheckRequest`** carries the value, its location, the options, and the parent and root data, for checks that need context.
- **A `CheckResult`** carries errors in the result format: code, params, optionally a rendered `message`.
- **Requests are batched per name:** one call for all values of a check in a validation, e.g. one database query, or one HTTP request from JS.

### Local callbacks

A check can be implemented locally for one validator, overriding any provider:

```php
$validator = $validator->withCheck('drupal:UniqueField', function (array $requests): array {
  // e.g. look up taken titles in local data
});
```

```ts
const v = validator.withCheck('drupal:UniqueField', async (requests) => {
  // e.g. call your own API, or check against cached data
})
```

**Use cases:**
- a server-only check implemented manually in a client;
- tests with fixed answers;
- agents validating against their own data, without the site;
- a project-specific variant of a check.

**Local callbacks are the caller's responsibility:** they keep the check's name, options and codes, but fixtures don't cover them.

### Drupal provider

Lives in the Drupal integration.

- **Mapping:** `drupal:<Name>` maps to the constraint plugin `<Name>`. The options are its constraint options.
- **Execution:** each request runs through the Typed Data validator.
- **Violations become errors:** property path → location, parameters → `params`, translated message → `message`.
- **So every existing constraint, including contrib ones, is usable as a named check.**
- **For JS:** the integration offers an HTTP endpoint, used by a JS provider for `validateAsync()`.
