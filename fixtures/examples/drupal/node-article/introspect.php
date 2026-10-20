<?php

/**
 * @file
 * Generates the Portable Schema of node/article from Typed Data.
 *
 * Run in a Drupal 11 site with core's article_content_type recipe applied:
 *
 *   drush php:script introspect.php -- <showcase directory>
 *
 * Writes:
 * - ../schemas/field_item.<type>.json: each field type's item, from its default settings;
 * - schemas/node.article.json: the bundle's fields, referencing the field item types plus
 *   what the field's own settings add;
 * - ../catalogs/: text formats and languages of the site.
 *
 * The data shape is Typed Data's own: field → list of items → properties.
 */

declare(strict_types=1);

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Symfony\Component\Validator\Constraint;

const DIALECT = 'https://portable-schema.dev/v1/schema';

/** Typed Data data types → library types (docs/drupal.md). */
const TYPES = [
  'string' => 'portable:string',
  'integer' => 'portable:integer',
  'float' => 'portable:number',
  'boolean' => 'portable:boolean',
  'email' => 'portable:email',
  'uri' => 'portable:uri',
  'timestamp' => 'portable:timestamp',
  'timespan' => 'portable:timespan',
  'datetime_iso8601' => 'portable:date-time',
  'duration_iso8601' => 'portable:duration',
  'decimal' => 'portable:decimal',
  'binary' => 'portable:binary',
  'filter_format' => 'drupal:catalog/config/filter_format',
];

/** Constraints a keyword or the data type already covers. */
const COVERED = ['PrimitiveType', 'ComplexData', 'NotNull', 'EntityType'];

[$directory] = $extra + [NULL];
if (!$directory || !is_dir($directory)) {
  throw new \InvalidArgumentException('Usage: drush php:script introspect.php -- <showcase directory>');
}

$constraintManager = \Drupal::service('validation.constraint');
$constraintIds = [];
foreach ($constraintManager->getDefinitions() as $id => $definition) {
  $constraintIds[$definition['class']] = $id;
}

/**
 * Constraints as [plugin ID => options], from definitions and from the item and list classes.
 */
$constraints = function (array $constraints) use ($constraintIds): array {
  $out = [];
  foreach ($constraints as $key => $constraint) {
    if (!$constraint instanceof Constraint) {
      $out[$key] = $constraint ?? [];
      continue;
    }
    $id = $constraintIds[get_class($constraint)] ?? (new \ReflectionClass($constraint))->getShortName();
    $options = [];
    foreach (get_object_vars($constraint) as $name => $value) {
      if (!in_array($name, ['payload', 'groups', 'message'], TRUE) && !str_ends_with($name, 'Message') && $value !== NULL) {
        $options[$name] = $value;
      }
    }
    $out[$id] = $options;
  }
  return $out;
};

/**
 * Applies constraints to a schema: keywords where they map, named checks otherwise.
 */
$apply = function (array &$schema, array $constraints, string $dataType = '') use (&$apply): void {
  foreach ($constraints as $id => $options) {
    $options = (array) $options;
    switch ($id) {
      case 'Length':
        if (isset($options['min'])) {
          $schema['minLength'] = (int) $options['min'];
        }
        if (isset($options['max'])) {
          $schema['maxLength'] = (int) $options['max'];
        }
        break;

      case 'Range':
        if (isset($options['min'])) {
          $schema['minimum'] = (int) $options['min'];
        }
        if (isset($options['max'])) {
          $schema['maximum'] = (int) $options['max'];
        }
        break;

      case 'NotBlank':
        $schema['minLength'] = 1;
        break;

      case 'Uuid':
        $schema['$ref'] = 'portable:uuid';
        break;

      case 'AllowedValues':
      case 'Choice':
        if (!empty($options['choices'])) {
          $schema['enum'] = array_values($options['choices']);
        }
        // Without choices, the options provider decides: catalogs for text formats and languages, the data type
        // for booleans, ValidReference for entity references.
        break;

      case 'ComplexData':
        foreach ($options['properties'] ?? $options as $property => $propertyConstraints) {
          $apply($schema['properties'][$property], is_array($propertyConstraints) ? $propertyConstraints : []);
        }
        break;

      default:
        if (!in_array($id, COVERED, TRUE)) {
          $schema['portable-check']['drupal:' . $id] = (object) array_filter($options, fn ($v) => !is_object($v));
        }
    }
  }
};

/**
 * The schema of one field item, from its property definitions and the item's constraints.
 */
$item = function (FieldDefinitionInterface $field) use ($constraints, $apply): array {
  $definition = $field->getItemDefinition();
  assert($definition instanceof ComplexDataDefinitionInterface);
  $schema = ['type' => 'object', 'properties' => [], 'required' => []];
  foreach ($definition->getPropertyDefinitions() as $name => $property) {
    if ($property->isComputed()) {
      continue;
    }
    $propertySchema = ['title' => (string) $property->getLabel()];
    $type = TYPES[$property->getDataType()] ?? NULL;
    if ($name === 'value' && $field->getType() === 'language') {
      $type = 'drupal:catalog/languages';
    }
    if ($type) {
      $propertySchema['$ref'] = $type;
    }
    if ($description = $property->getDescription()) {
      $propertySchema['description'] = (string) $description;
    }
    $apply($propertySchema, $constraints($property->getConstraints()), $property->getDataType());
    $schema['properties'][$name] = $propertySchema;
    if ($property->isRequired()) {
      $schema['required'][] = $name;
    }
  }

  $instance = \Drupal::service('typed_data_manager')->create($definition);
  $itemConstraints = $constraints($instance->getConstraints());
  if (isset($itemConstraints['FileValidation'])) {
    unset($itemConstraints['FileValidation']);
    $itemConstraints['FileExtension'] = ['extensions' => $field->getSetting('file_extensions')];
  }
  $apply($schema, $itemConstraints);

  // Settings that only widgets enforce, not Typed Data.
  foreach (['alt', 'title'] as $name) {
    if ($field->getType() === 'image' && $field->getSetting("{$name}_field_required")) {
      $schema['required'][] = $name;
      $schema['properties'][$name]['minLength'] = 1;
    }
  }

  $schema['required'] = array_values(array_unique($schema['required']));
  if ($schema['required'] === []) {
    unset($schema['required']);
  }
  return $schema;
};

/**
 * What $schema adds to $base, recursively for objects; NULL if nothing.
 */
$diff = function (array $schema, array $base) use (&$diff): ?array {
  $out = [];
  foreach ($schema as $key => $value) {
    if (!array_key_exists($key, $base)) {
      $out[$key] = $value;
    }
    elseif ($key === 'required') {
      $extra = array_values(array_diff($value, $base[$key]));
      if ($extra) {
        $out[$key] = $extra;
      }
    }
    elseif (is_array($value) && is_array($base[$key]) && !array_is_list($value)) {
      $sub = $diff($value, $base[$key]);
      if ($sub !== NULL) {
        $out[$key] = $sub;
      }
    }
    elseif ($value != $base[$key]) {
      $out[$key] = $value;
    }
  }
  return $out === [] ? NULL : $out;
};

$write = function (string $path, array $schema): void {
  if (!is_dir(dirname($path))) {
    mkdir(dirname($path), 0777, TRUE);
  }
  if (file_put_contents($path, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n") === FALSE) {
    throw new \RuntimeException("Cannot write $path");
  }
  echo "Wrote $path\n";
};

$changesType = function (?array $diff): bool {
  foreach ($diff['properties'] ?? [] as $property) {
    if (isset($property['$ref'])) {
      return TRUE;
    }
  }
  return FALSE;
};

$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'article');
$written = [];
$node = \Drupal::entityTypeManager()->getStorage('node')->create(['type' => 'article']);
$article = [
  '$schema' => DIALECT,
  '$id' => 'drupal:node.article',
  'title' => 'Article',
  'description' => 'node/article, generated from Typed Data by introspect.php.',
  'type' => 'object',
  'properties' => [],
  'required' => [],
  'unevaluatedProperties' => FALSE,
];
foreach ($fields as $name => $field) {
  if ($field->isComputed()) {
    continue;
  }
  $type = $field->getType();
  $itemSchema = $item($field);

  $typeSchema = $item(BaseFieldDefinition::create($type)->setName($name)->setTargetEntityTypeId('node'));
  if (!isset($written[$type])) {
    $written[$type] = TRUE;
    $write(dirname($directory) . "/schemas/field_item.$type.json", [
      '$schema' => DIALECT,
      '$id' => "drupal:field_item.$type",
      'title' => (string) \Drupal::service('plugin.manager.field.field_type')->getDefinition($type)['label'],
      'description' => "Items of $type fields, with the field type's default settings.",
    ] + $typeSchema + ['unevaluatedProperties' => FALSE]);
  }

  $list = ['title' => (string) $field->getLabel(), 'type' => 'array'];
  if ($description = (string) $field->getDescription()) {
    $list['description'] = $description;
  }
  // The field's own settings only add constraints, unless they change a property's type, e.g. string IDs of config
  // entities: then the field gets its own item schema.
  $added = $diff($itemSchema, $typeSchema);
  $list['items'] = $changesType($added) ? $itemSchema + ['unevaluatedProperties' => FALSE] : ['$ref' => "drupal:field_item.$type"] + ($added ?? []);
  $cardinality = $field->getFieldStorageDefinition()->getCardinality();
  if ($cardinality > 0) {
    $list['maxItems'] = $cardinality;
  }
  $listConstraints = $constraints($node->get($name)->getConstraints());
  if (isset($listConstraints['ValidReference'])) {
    $list['items']['properties']['target_id']['portable-check']['drupal:ValidReference'] = new \stdClass();
  }
  if ($field->isRequired()) {
    $list['minItems'] = 1;
    $article['required'][] = $name;
  }
  if ($field->isReadOnly()) {
    $list['readOnly'] = TRUE;
  }
  $article['properties'][$name] = $list;
}
$write("$directory/schemas/node.article.json", $article);

$catalog = fn (string $id, string $title, string $description, array $entries) => [
  '$schema' => DIALECT,
  '$id' => $id,
  'title' => $title,
  'description' => $description,
  'oneOf' => array_map(fn ($const, $title) => ['const' => $const, 'title' => $title], array_keys($entries), $entries),
];
$formats = array_map(fn ($format) => $format->label(), \Drupal::entityTypeManager()->getStorage('filter_format')->loadByProperties(['status' => TRUE]));
ksort($formats);
$write(dirname($directory) . "/catalogs/config.filter_format.json", $catalog('drupal:catalog/config/filter_format', 'Text formats', 'Generated from the enabled filter_format config entities.', $formats));
$languages = array_map(fn ($language) => $language->getName(), \Drupal::languageManager()->getLanguages(\Drupal\Core\Language\LanguageInterface::STATE_ALL));
$write(dirname($directory) . "/catalogs/languages.json", $catalog('drupal:catalog/languages', 'Languages', 'Generated from the site\'s languages, including the special ones.', $languages));
