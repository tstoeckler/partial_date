<?php

/**
 * @file
 * Contains \Drupal\partial_date\Plugin\Field\FieldType\PartialDateTime.
 */

namespace Drupal\partial_date\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'partial_date' field type.
 *
 * @FieldType(
 *   id = "partial_date",
 *   label = @Translation("Partial date and time"),
 *   description = @Translation("This field stores and renders partial dates."),
 *   module = "partial_date",
 *   default_widget = "partial_date_widget",
 *   default_formatter = "partial_date_formatter",
 * )
 *
 * 
 */

class PartialDateTime extends FieldItemBase {

  /**
   * Cache for whether the host is a new revision.
   *
   * Set in preSave and used in update().  By the time update() is called
   * isNewRevision() for the host is always FALSE.
   *
   * @var bool
   */
  protected $newHostRevision;

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('float')
      ->setLabel(t('Timestamp'))
      ->setDescription('Contains best approximation for date value');
    $properties['value_to'] = DataDefinition::create('float')
      ->setLabel(t('End timestamp'))
      ->setDescription('Contains the end value of the partial date');
    $properties['txt_short'] = DataDefinition::create('string')
      ->setLabel(t('Short text'));
    $properties['txt_long'] = DataDefinition::create('string')
      ->setLabel(t('Long text'));
    //Components: 'year', 'month', 'day', 'hour', 'minute', 'second', 'timezone'
    foreach (partial_date_components() as $key => $label) {
      if ($key == 'timezone') {
        $properties[$key] = DataDefinition::create('string')
          ->setLabel($label);
      } else {
        $startDescription = t('The ' . $label . ' for the starting date component.');
        $endDescription   = t('The ' . $label . ' for the finishing date component.');
        $properties[$key] = DataDefinition::create('integer')
           ->setLabel($label)
           ->setDescription($startDescription) ;
        $properties[$key.'_to'] = DataDefinition::create('integer')
           ->setLabel($label. t(' end '))
           ->setDescription($endDescription) ;
      } 
    }
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
    $schema = array(
      'columns' => array(
        'value' => array(
          'type' => 'float',
          'size' => 'big',
          'description' => 'The calculated timestamp for a date stored in UTC as a float for unlimited date range support.',
          'not null' => TRUE,
          'default' => 0,
          'sortable' => TRUE,
        ),
        'value_to' => array(
          'type' => 'float',
          'size' => 'big',
          'description' => 'The calculated timestamp for end date stored in UTC as a float for unlimited date range support.',
          'not null' => TRUE,
          'default' => 0,
          'sortable' => TRUE,
        ),
        // These are instance settings, so add to the schema for every field.
        'txt_short' => array(
          'type' => 'varchar',
          'length' => 100,
          'description' => 'A editable display field for this date for the short format.',
          'not null' => FALSE,
          'sortable' => TRUE,
        ),
        'txt_long' => array(
          'type' => 'varchar',
          'length' => 255,
          'description' => 'A editable display field for this date for the long format.',
          'not null' => FALSE,
          'sortable' => TRUE,
        ),
//        'data' => array(
//          'description' => 'The configuration data for the effect.',
//          'type' => 'blob',
//          'not null' => FALSE,
//          'size' => 'big',
//          'sortable' => FALSE,
//        ),
      ),
      'indexes' => array(
        'main' => array('value'),
        'by_end' => array('value_to'),
      ),
    );

    foreach (partial_date_components() as $key => $label) {
      if ($key == 'timezone') {
        $schema['columns'][$key] = array(
          'type' => 'varchar',
          'length' => 50,
          'description' => 'The ' . $label . ' for the time component.',
          'not null' => FALSE,
          'default' => NULL,
        );
      }
      else {
        $column = array(
          'type' => 'int',
          'description' => 'The ' . $label . ' for the starting date component.',
          'not null' => FALSE,
          'default' => NULL,
          'size' => ($key == 'year' ? 'big' : 'small'),
        );
        $schema['columns'][$key] = $column;
        //Add "*_to" columns
        $column['description'] = 'The ' . $label . ' for the finishing date component.';
        $schema['columns'][$key . '_to'] = $column;
      }
    }
    return $schema;
  }


  protected function deleteConfig($configName) {
    //$config = \Drupal::service('config.factory')->getEditable($configName);
    $config = \Drupal::configFactory()->getEditable($configName);
    if (isset($config)) {
      $config->delete();
    }
  }

  public function delete() {
    $this->deleteConfig('partial_date.settings');
    $this->deleteConfig('partial_date.format');
    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
  //  return !$this->value;
    $val = $this->get('value')->getValue();
    $val_to = $this->get('value_to')->getValue();
    $txtShort = $this->get('txt_short')->getValue();
    $txtLong = $this->get('txt_long')->getValue();
//    $item = $this->getEntity();
//    if ((isset($item['_remove']) && $item['_remove']) || !is_array($item)) {
//      return TRUE;
//    }
//    foreach (array('from', 'to') as $base) {
//      if (empty($item[$base])) {
//        continue;
//      }
//      foreach (partial_date_components() as $key => $label) {
//        if ($key == 'timezone') {
//          continue;
//        }
//        if (isset($item[$base][$key]) && strlen($item[$base][$key])) {
//          return FALSE;
//        }
//        if (isset($item[$base][$key . '_estimate']) && strlen($item[$base][$key . '_estimate'])) {
//          return FALSE;
//        }
//      }
//    }
//
//    return !((isset($item['txt_short']) && strlen($item['txt_short'])) ||
//           (isset($item['txt_long']) && strlen($item['txt_long'])));
    return !(  isset($val) || isset($val_to) ||
              (isset($txtShort) && strlen($txtShort)) ||
              (isset($txtLong)  && strlen($txtLong) )
            );
  }

  /**
   * Helper function to duplicate the same settings on both the instance and field
   * settings.
   */
  public function fieldSettingsForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
//    //parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getSettings();
    $field    = $this->getFieldDefinition();
    $elements = array();
    $elements['minimum_components'] = array(
      '#type' => 'fieldset',
      '#title' => t('Minimum components'),
      '#description' => t('These are used to determine if the field is incomplete during validation. All possible fields are listed here, but these are only checked if enabled in the instance settings.'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    );
    $has_range = strpos($field->getType(), '_range');
    foreach (partial_date_components() as $key => $label) {
      $elements['minimum_components']['from_granularity_' . $key] = array(
        '#type' => 'checkbox',
        '#title' => $has_range ? t('From @date_component', array('@date_component' => $label)) : $label,
        '#default_value' => !empty($settings['minimum_components']['from_granularity_' . $key]),
      );
    }
    foreach (partial_date_components(array('timezone')) as $key => $label) {
      $elements['minimum_components']['from_estimates_' . $key] = array(
        '#type' => 'checkbox',
        '#title' => $has_range
            ? t('From Estimate @date_component', array('@date_component' => $label))
            : t('Estimate @date_component', array('@date_component' => $label)),
        '#default_value' => !empty($settings['minimum_components']['from_estimates_' . $key]),
      );
    }
    if ($has_range) {
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
    }
    $elements['minimum_components']['txt_short'] = array(
      '#type' => 'checkbox',
      '#title' => t('Short date text'),
      '#default_value' => !empty($settings['minimum_components']['txt_short']),
    );
    $elements['minimum_components']['txt_long'] = array(
      '#type' => 'checkbox',
      '#title' => t('Long date text'),
      '#default_value' => !empty($settings['minimum_components']['txt_long']),
    );
    return $elements;
  }
  
  public function preSave() {
    parent::preSave();
  }
  
  public static function defaultFieldSettings() {
    return array(
      'path' => '',
      'hide_blank_items' => TRUE,
      'minimum_components' => array(),
    ) + parent::defaultFieldSettings();
  }
  
  
}
