<?php

namespace Drupal\partial_date\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Plugin implementation of the 'partial_date' field type.
 *
 * @FieldType(
 *   id = "partial_date_range",
 *   label = @Translation("Partial date and time range"),
 *   description = @Translation("This field stores and renders partial dates."),
 *   module = "partial_date",
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
    $properties['timestamp_to'] = DataDefinition::create('float')
      ->setLabel(t('End timestamp'))
      ->setDescription('Contains the end value of the partial date');

    foreach (partial_date_components() as $key => $label) {
      if ($key == 'timezone') {
        continue;
      }

      $properties[$key.'_to'] = DataDefinition::create('integer')
        ->setLabel($label. t(' end '))
        ->setDescription(t('The ' . $label . ' for the finishing date component.'));
    }

    $properties['to'] = MapDataDefinition::create()
      ->setLabel(t('To'))
      ->setComputed(TRUE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   * Equivalent of hook_field_schema().
   *
   * This module stores a dates in a string that represents the data that the user
   * entered and a float timestamp that represents the best guess for the date.
   *
   * After tossing up the options a number of times, I've taken the conservative
   * opinion of storing all date components separately rather than storing these
   * in a singular field.
   */
  public static function schema(FieldStorageDefinitionInterface $field) {
    $schema = parent::schema($field);
    $schema['columns']['timestamp_to'] = [
      'type' => 'float',
      'size' => 'big',
      'description' => 'The calculated timestamp for end date stored in UTC as a float for unlimited date range support.',
      'not null' => TRUE,
      'default' => 0,
      'sortable' => TRUE,
    ];
    $schema['indexes']['timestamp_to'] = ['timestamp_to'];

    foreach (partial_date_components() as $key => $label) {
      if ($key == 'timezone') {
        continue;
      }

      $column = $schema['columns'][$key];
      //Add "*_to" columns
      $column['description'] = 'The ' . $label . ' for the finishing date component.';
      $schema['columns'][$key . '_to'] = $column;
    }
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $val_to = $this->get('timestamp_to')->getValue();
    return parent::isEmpty() && !isset($val_to);
  }

  /**
   * Helper function to duplicate the same settings on both the instance and field
   * settings.
   */
  public function fieldSettingsForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $elements = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getSettings();
    foreach (partial_date_components() as $key => $label) {
      $elements['minimum_components']['from_granularity_' . $key]['#title'] = t('From @date_component', array('@date_component' => $label));
    }
    foreach (partial_date_components(array('timezone')) as $key => $label) {
      $elements['minimum_components']['from_estimates_' . $key]['#title'] = t('From Estimate @date_component', array('@date_component' => $label));
    }
    foreach (partial_date_components() as $key => $label) {
      $elements['minimum_components']['to_granularity_' . $key] = array(
        '#type' => 'checkbox',
        '#title' => t('To @date_component', array('@date_component' => $label)),
        '#default_value' => !empty($settings['minimum_components']['to_granularity_' . $key]),
      );
    }
    foreach (partial_date_components(array('timezone')) as $key => $label) {
      $elements['minimum_components']['to_estimates_' . $key] = array(
        '#type' => 'checkbox',
        '#title' => t('To Estimate @date_component', array('@date_component' => $label)),
        '#default_value' => !empty($settings['minimum_components']['to_estimates_' . $key]),
      );
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = parent::defaultFieldSettings();
    $settings['minimum_components'] += array(
      'to_granularity_year' => FALSE,
      'to_granularity_month' => FALSE,
      'to_granularity_day' => FALSE,
      'to_granularity_hour' => FALSE,
      'to_granularity_minute' => FALSE,
      'to_granularity_second' => FALSE,
      'to_granularity_timezone' => FALSE,
      'to_estimate_year' => FALSE,
      'to_estimate_month' => FALSE,
      'to_estimate_day' => FALSE,
      'to_estimate_hour' => FALSE,
      'to_estimate_minute' => FALSE,
      'to_estimate_second' => FALSE,
    );
    return $settings;
  }

}
