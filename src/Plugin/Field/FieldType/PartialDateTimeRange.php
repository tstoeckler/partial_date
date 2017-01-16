<?php

namespace Drupal\partial_date\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'partial_date_range' field type.
 *
 * @FieldType(
 *   id = "partial_date_range",
 *   label = @Translation("Partial date and time range"),
 *   description = @Translation("This field stores and renders partial date ranges."),
 *   default_widget = "partial_date_widget",
 *   default_formatter = "partial_date_formatter",
 * )
 */
class PartialDateTimeRange extends PartialDateTime {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $minimum_components = $field_definition->getSetting('minimum_components');

    $properties['timestamp_to'] = DataDefinition::create('float')
      ->setLabel(t('End timestamp'))
      ->setDescription('Contains the best approximation for end value of the partial date')
      ->setRequired(TRUE);

    foreach (partial_date_components() as $key => $label) {
      if ($key == 'timezone') {
        continue;
      }

      $properties[$key . '_to'] = DataDefinition::create('integer')
        ->setLabel($label. t(' end '))
        ->setDescription(t('The ' . $label . ' for the finishing date component.'))
        ->setRequired($minimum_components['to_granularity_' . $key]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field) {
    $schema = parent::schema($field);

    $minimum_components = $field->getSetting('minimum_components');

    $schema['columns']['timestamp_to'] = [
      'type' => 'float',
      'size' => 'big',
      'description' => 'The calculated timestamp for end date stored in UTC as a float for unlimited date range support.',
      'not null' => TRUE,
      'default' => 0,
      'sortable' => TRUE,
    ];
    $schema['indexes']['by_end'] = ['timestamp_to'];

    foreach (partial_date_components() as $key => $label) {
      if ($key == 'timezone') {
        continue;
      }

      $column = $schema['columns'][$key];
      //Add "*_to" columns
      $column['description'] = 'The ' . $label . ' for the finishing date component.';
      $column['not null'] = $minimum_components['to_granularity_' . $key];
      $schema['columns'][$key . '_to'] = $column;
    }
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    if (!parent::isEmpty()) {
      return FALSE;
    }
    $value = $this->getValue();
    if (!empty($value['timestamp_to'])) {
      return FALSE;
    }
    return FALSE;
  }

}
