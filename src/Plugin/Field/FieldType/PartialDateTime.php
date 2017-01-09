<?php

/**
 * @file
 * Contains \Drupal\partial_date\Plugin\Field\FieldType\PartialDateTime.
 */

namespace Drupal\partial_date\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\partial_date\DateTools;
use Drupal\partial_date\Entity\PartialDateFormat;

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
    $properties['timestamp'] = DataDefinition::create('float')
      ->setLabel(t('Timestamp'))
      ->setDescription('Contains best approximation for date value');
    $properties['timestamp_to'] = DataDefinition::create('float')
      ->setLabel(t('End timestamp'))
      ->setDescription('Contains the best approximation for end value of the partial date');
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
        'timestamp' => array(
          'type' => 'float',
          'size' => 'big',
          'description' => 'The calculated timestamp for a date stored in UTC as a float for unlimited date range support.',
          'not null' => TRUE,
          'default' => 0,
          'sortable' => TRUE,
        ),
        'timestamp_to' => array(
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
        'main' => array('timestamp'),
        'by_end' => array('timestamp_to'),
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
    $value = $this->getValue();
    if (empty($value) || !is_array($value)) {
      return TRUE;
    }
    if (!empty($value['timestamp'])) {
      return FALSE;
    }
    if (!empty($value['timestamp_to'])) {
      return FALSE;
    }
    if (!empty($value['txt_short'])) {
      return FALSE;
    }
    if (!empty($value['txt_long'])) {
      return FALSE;
    }
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
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return array(
      'has_time' => TRUE,
      'has_range' => TRUE,
      'require_consistency' => FALSE,
      'minimum_components' => array(
        'year' => FALSE,
        'month' => FALSE,
        'day' => FALSE,
        'hour' => FALSE,
        'minute' => FALSE,
        'second' => FALSE,
      ),
    ) + parent::defaultFieldSettings();
  }
  
  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();
    //debug_only:  var_dump($settings);
    $elements = array();
    $elements['has_time'] = array(
      '#type' => 'checkbox',
      '#id' => 'has_time',
      '#title' => t('Allow time specification'),
      '#default_value' => !empty($settings['has_time']),
      '#description' => t('Clear if not interested in holding time. Check to make time controls available.'),
    );
    $elements['has_range'] = array(
      '#type' => 'checkbox',
      '#id' => 'has_range',
      '#title' => t('Allow range specification'),
      '#default_value' => !empty($settings['has_range']),
      '#description' => t('Clear if not holding end values. Check to explicitely show end of range values.'),
    );
    $elements['require_consistency'] = array(
      '#type' => 'checkbox',
      '#title' => t('Require consistent values'),
      '#default_value' => !empty($settings['require_consistency']),
      '#description' => t('Check to enforce a consistent date. For example, if day component is set, month (and year) are required too.'),
    );
    $elements['minimum_components'] = array(
      '#type' => 'partial_date_components_element',
      '#title' => t('Minimum components'),
      '#default_value' => $settings['minimum_components'],
      '#description' => t('These are used to determine if the field is incomplete during validation.'),
       //dynamically show/hide time components using javascript (based on another form element)
      '#time_states' =>  array(
          'visible' => array(
            ':input[id="has_time"]' => array('checked' => TRUE),
          ),
        ),
    );
    return $elements;
  }
  
  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    $this->normalizeValues();
    $values = $this->fillEmptyValues();
    //Calculate timestamps
    $this->set('timestamp', $this->getTimestamp($values));
    $this->set('timestamp_to', $this->getTimestampTo($values));
  }

  /*
   * Fill any missing values from the other end of the range (if any).
   * Ex. if year=NULL, but year_to=2015, make year=2015 too 
   *   and viceversa, if year_to not set, but we have a year set.
   * Note: If both values are set (and different) stop the normalization for 
   * the rest of the components.
   * Ex. from 2015 Jan 15 to Jul
   * The year is assumed the same, but the day is not. 
   */
  public function normalizeValues(){
    $values = $this->getValue();
    foreach (partial_date_component_keys() as $key){
      $keyTo = $key . '_to';
      if (!empty($values[$key]) && empty($values[$keyTo])) {
        $this->set($keyTo, $values[$key]);
      } elseif (!empty($values[$keyTo]) && empty($values[$key])) {
        $this->set($key, $values[$keyTo]);
      } elseif (!empty($values[$keyTo]) && !empty($values[$key]) && $values[$keyTo] != $values[$key]) {
        break;
      }
    }    
  }
  
  public function getTimestamp($values) {
    $date = $values['year'] . '.'
        . sprintf('%02s', $values['month'])   // 0 or 1-12
        . sprintf('%02s', $values['day'])     // 0 or 1-31
        . sprintf('%02s', $values['hour'])    // 0 or 1-24
        . sprintf('%02s', $values['minute'])  // 0 or 1-60
        . sprintf('%02s', $values['second']); // 0 or 1-60
    return ((double) $date);
  }
  
  public function getTimestampTo($values) {
    $date = $values['year_to'] . '.'
        . sprintf('%02s', $values['month_to'])   // 0 or 1-12
        . sprintf('%02s', $values['day_to'])     // 0 or 1-31
        . sprintf('%02s', $values['hour_to'])    // 0 or 1-24
        . sprintf('%02s', $values['minute_to'])  // 0 or 1-60
        . sprintf('%02s', $values['second_to']); // 0 or 1-60
    return ((double) $date);
  }
  
  /**
   * This generates the best estimate for the date components based on the
   * submitted values.
   */
  function fillEmptyValues() {
    static $base;
    if (!isset($base)) {
      $base = array(
        'year'   => PD2_YEAR_MIN,    'year_to'   => PD2_YEAR_MAX,
        'month'  => 1,               'month_to'  => 12,
        'day'    => 1,               'day_to'    => NULL, //should be re-calculated
        'hour'   => 0,               'hour_to'   => 23,
        'minute' => 0,               'minute_to' => 59,
        'second' => 0,               'second_to' => 59,
      );
    }
    $values = array_filter($this->getValue()) + $base;
    if (empty($values['day_to'])) {
      $values['day_to'] = DateTools::lastDayOfMonth($values['month_to'], $values['year_to']);
    }
    return $values;
  }
  
  public function hasRangeValue() {
    if (!$this->getSetting('has_range')) {
      return FALSE;  //range values not allowed!
    }
    $values = $this->getValue();
    foreach (partial_date_component_keys() as $key) {
      if (!empty($values[$key . '_to'])) {
        return TRUE;
      }
    }
    return FALSE;
  }
  
  public function hasTimeValue() {
    if (!$this->getSetting('has_time')) {
      return FALSE;  //time values not allowed!
    }
    $values = $this->getValue();
    foreach (array('hour', 'minute', 'second') as $key) {
      if (!empty($values[$key]) || !empty($values[$key . '_to'])) {
        return TRUE;
      }
    }
    return FALSE;
  }
  
  
}
