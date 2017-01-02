<?php

namespace Drupal\partial_date\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\partial_date\Plugin\DataType\PartialDateTimeComputed;

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

    $minimum_components = $field_definition->getSetting('minimum_components');

    $properties['timestamp_to'] = DataDefinition::create('float')
      ->setLabel(t('End timestamp'))
      ->setDescription('Contains the end value of the partial date')
      ->setRequired(TRUE);

    foreach (partial_date_components() as $key => $label) {
      if ($key == 'timezone') {
        continue;
      }

      $properties[$key.'_to'] = DataDefinition::create('integer')
        ->setLabel($label. t(' end '))
        ->setDescription(t('The ' . $label . ' for the finishing date component.'))
        ->setRequired($minimum_components['to_granularity_' . $key]);
    }

    $properties['to'] = MapDataDefinition::create()
      ->setLabel(t('To'))
      ->setClass(PartialDateTimeComputed::class)
      ->setSetting('range', 'to')
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

    $minimum_components = $field->getSetting('minimum_components');

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
      $column['not null'] = $minimum_components['to_granularity_' . $key];
      $schema['columns'][$key . '_to'] = $column;
    }
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = $this->getTypedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $constraints[] = $constraint_manager->create('PartialToDate', []);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();

    $data = $this->data;

    // Provide some default values for the timestamp components. Components with
    // actual values will be replaced below.
    $timestamp_components = [
      'year' => PD2_YEAR_MAX,
      'month' => 12,
      // A sensible default for this will be generated below using the month.
      'day' => 0,
      'hour' => 23,
      'minute' => 59,
      'second' => 59,
      'timezone' => '',
    ];
    foreach (array_keys(partial_date_components()) as $component) {
      $property = $component . '_to';
      $to = $this->to;
      if (isset($to[$component])) {
        $this->{$property} = $to[$component];
      }

      if ($component !== 'timezone') {
        $data[$component . '_estimate_to_used'] = FALSE;

        // The if-statements are broken up because $from_estimate_from is used
        // below even if $to[$component] is not empty.
        if (!empty($from[$component . '_estimate'])) {
          list($from_estimate_from, $from_estimate_to) = explode('|', $from[$component . '_estimate']);
          if (!isset($to[$component]) || !strlen($to[$component])) {
            $this->{$property} = $from_estimate_to;
            $data[$component . '_estimate_to_used'] = TRUE;
          }
        }

        $data[$component . '_to_estimate'] = '';
        if (!empty($to[$component . '_estimate'])) {
          $estimate = $to[$component . '_estimate'];
          $data[$component . '_to_estimate'] = $estimate;
          list($to_estimate_from, $to_estimate_to) = explode('|', $estimate);
          if (!isset($from[$component]) || !strlen($from[$component]) || $data[$component . '_estimate_from_used']) {
            $this->{$component} = isset($from_estimate_from) ? min($from_estimate_from, $to_estimate_from) : $to_estimate_from;
            $data[$component . '_estimate_from_used'] = TRUE;
          }
          if (!isset($to[$component]) || !strlen($to[$component]) || $data[$component . '_estimate_to_used']) {
            $this->{$property} = isset($from_estimate_to) ? min($from_estimate_to, $to_estimate_to) : $to_estimate_to;
            $data[$component . '_estimate_to_used'] = TRUE;
          }
        }
      }

      // Build up components for the timestamp to use.
      $value = $this->{$component};
      if ($value && strlen($value)) {
        $timestamp_components[$component] = $value;
      }
    }
    if (!$timestamp_components['day']) {
      $month_table = partial_date_month_matrix($timestamp_components['year']);
      if (isset($month_table[$timestamp_components['month'] - 1])) {
        $timestamp_components['day'] = $month_table[$timestamp_components['month'] - 1];
      }
      else {
        $timestamp_components['day'] = 31;
      }
    }
    $this->timestamp_to = partial_date_float($timestamp_components);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $val_to = $this->get('timestamp_to')->getValue();
    return parent::isEmpty() && !isset($val_to);
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
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
  public static function defaultStorageSettings() {
    $settings = parent::defaultStorageSettings();
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
