<?php

namespace Drupal\partial_date\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\partial_date\Plugin\DataType\PartialDateTimeComputed;
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
    $minimum_components = $field_definition->getSetting('minimum_components');
    $properties['timestamp'] = DataDefinition::create('float')
      ->setLabel(t('Timestamp'))
      ->setDescription('Contains best approximation for date value');
    $properties['timestamp_to'] = DataDefinition::create('float')
      ->setLabel(t('End timestamp'))
      ->setDescription('Contains the best approximation for end value of the partial date');
    $properties['txt_short'] = DataDefinition::create('string')
      ->setLabel(t('Short text'))
      ->setRequired($minimum_components['txt_short']);
    $properties['txt_long'] = DataDefinition::create('string')
      ->setLabel(t('Long text'))
      ->setRequired($minimum_components['txt_long']);
    //Components: 'year', 'month', 'day', 'hour', 'minute', 'second', 'timezone'
    foreach (partial_date_components() as $key => $label) {
      if ($key == 'timezone') {
        $properties[$key] = DataDefinition::create('string')
          ->setLabel($label);
      }
      else {
        $properties[$key] = DataDefinition::create('integer')
          ->setLabel($label)
          ->setDescription(t('The ' . $label . ' for the starting date component.'));
      }
    }

    /** @see \Drupal\partial_date\Plugin\Field\FieldType\PartialDateTime::setValue() */
    $properties['check_approximate'] = DataDefinition::create('boolean')
      ->setLabel(t('Check approximate'))
      ->setComputed(TRUE);

    $properties['from'] = MapDataDefinition::create()
      ->setLabel(t('From'))
      ->setClass(PartialDateTimeComputed::class)
      ->setSetting('range', 'from')
      ->setComputed(TRUE);

    $properties['data'] = MapDataDefinition::create()
      ->setLabel(t('Data'));
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
    $minimum_components = $field->getSetting('minimum_components');

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
        ),
        'txt_long' => array(
          'type' => 'varchar',
          'length' => 255,
          'description' => 'A editable display field for this date for the long format.',
          'not null' => FALSE,
        ),
        'data' => array(
          'description' => 'The configuration data for the effect.',
          'type' => 'blob',
          'not null' => FALSE,
          'size' => 'big',
          'serialize' => TRUE,
        ),
      ),
      'indexes' => array(
        'main' => array('timestamp'),
        'by_end' => array('timestamp_to'),
      ),
    );

    foreach (partial_date_components() as $key => $label) {
      if ($key === 'timezone') {
        $column = array(
          'type' => 'varchar',
          'length' => 50,
          'description' => 'The ' . $label . ' for the time component.',
        );
      }
      else {
        $column = array(
          'type' => 'int',
          'description' => 'The ' . $label . ' for the starting date component.',
          'size' => ($key === 'year') ? 'big' : 'small',
        );
      }
      $column += array(
        'not null' => $minimum_components['from_granularity_' . $key],
        'default' => NULL,
      );
      $schema['columns'][$key] = $column;
    }
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = $this->getTypedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $constraints[] = $constraint_manager->create('PartialDate', []);
    $constraints[] = $constraint_manager->create('ComplexData', [
      'year' => [
        'Range' => [
          'min' => PD2_YEAR_MIN,
          'max' => PD2_YEAR_MAX,
        ],
      ],
    ]);

    return $constraints;
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

    $data = $this->data;
    $data['check_approximate'] = $this->check_approximate;

    // Provide some default values for the timestamp components. Components with
    // actual values will be replaced below.
    $timestamp_components = [
      'year' => PD2_YEAR_MIN,
      'month' => 1,
      'day' => 1,
      'hour' => 0,
      'minute' => 0,
      'second' => 0,
      'timezone' => '',
    ];
    foreach (array_keys(partial_date_components()) as $component) {
      // Synchronize the properties with the computed values.
      $from = $this->from;
      if (isset($from[$component])) {
        $this->{$component} = $from[$component];
      }

      // Fill in any estimated values.
      if ($component !== 'timezone') {
        $data[$component . '_estimate'] = '';
        $data[$component . '_estimate_from_used'] = FALSE;

        if (!empty($from[$component . '_estimate'])) {
          $estimate = $from[$component . '_estimate'];
          $data[$component . '_estimate'] = $estimate;
          list($estimate_from) = explode('|', $estimate);
          if (!isset($from[$component]) || !strlen($from[$component])) {
            $this->{$component} = $estimate_from;
            $data[$component . '_estimate_from_used'] = TRUE;
          }
        }
      }

      // Build up components for the timestamp to use.
      $value = $this->{$component};
      if ($value && strlen($value)) {
        $timestamp_components[$component] = $value;
      }
    }
    $this->timestamp = partial_date_float($timestamp_components);

    $this->data = $data;
  }

  protected function deleteConfig($configName) {
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
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $settings = $this->getSettings();
    $elements['minimum_components'] = array(
      '#type' => 'details',
      '#title' => t('Minimum components'),
      '#description' => t('These are used to determine if the field is incomplete during validation. All possible fields are listed here, but these are only checked if enabled in the instance settings.'),
      '#open' => FALSE,
    );
    foreach (partial_date_components() as $key => $label) {
      $elements['minimum_components']['from_granularity_' . $key] = array(
        '#type' => 'checkbox',
        '#title' => $label,
        '#default_value' => $settings['minimum_components']['from_granularity_' . $key],
      );
    }
    foreach (partial_date_components(array('timezone')) as $key => $label) {
      $elements['minimum_components']['from_estimates_' . $key] = array(
        '#type' => 'checkbox',
        '#title' => t('Estimate @date_component', array('@date_component' => $label)),
        '#default_value' => $settings['minimum_components']['from_estimates_' . $key],
      );
    }
    $elements['minimum_components']['txt_short'] = array(
      '#type' => 'checkbox',
      '#title' => t('Short date text'),
      '#default_value' => $settings['minimum_components']['txt_short'],
    );
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
    $elements['minimum_components']['txt_long'] = array(
      '#type' => 'checkbox',
      '#title' => t('Long date text'),
      '#default_value' => $settings['minimum_components']['txt_long'],
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


  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();
    $elements['estimates'] = array(
      '#type' => 'details',
      '#title' => t('Base estimate values'),
      '#description' => t('These fields provide options for additional fields that can be used to represent corresponding date / time components. They define time periods where an event occured when exact details are unknown. All of these fields have the format "start|end|label", one per line, where start marks when this period started, end marks the end of the period and the label is shown to the user. Instance settings will be used whenever possible on forms, but views integration (once completed) will use the field values. Note that if used, the formatters will replace any corresponding date / time component with the options label value.'),
      '#open' => FALSE,
    );
    foreach (partial_date_components() as $key => $label) {
      if ($key == 'timezone') {
        continue;
      }
      $value = array();
      foreach ($settings['estimates'][$key] as $range => $option_label) {
        $value[] = $range . '|' . $option_label;
      }
      $elements['estimates'][$key] = array(
        '#type' => 'textarea',
        '#title' => t('%label range options', array('%label' => $label), array('context' => 'datetime settings')),
        '#default_value' => implode("\n", $value),
        '#description' => t('Provide relative approximations for this date / time component.'),
        '#element_validate' => array(static::class . '::validateEstimateOptions'),
        '#date_component' => $key,
      );
    }

    return $elements;
  }

  /**
   * Form element validation handler for estimate options.
   */
  public static function validateEstimateOptions(&$element, FormStateInterface &$form_state, &$complete_form) {
    $items = array();
    foreach (explode("\n", $element['#value']) as $line) {
      $line = trim($line);
      if (!empty($line)) {
        list($from, $to, $label) = explode('|', $line . '||');
        if (!strlen($from) && !strlen($to)) {
          continue;
        }
        $label = trim($label);
        if (empty($label)) {
          $form_state->setError($element, t('The label for the keys %keys is required.', array('%keys' => $from . '|' . $to)));
        }
        elseif (!is_numeric($from) || !is_numeric($to)) {
          $form_state->setError($element, t('The keys %from and %to must both be numeric.', array('%from' => $from, '%to' => $to)));
        }
        else {
          // We need to preserve empty strings, so cast to temp variables.
          $_from = (int) $from;
          $_to = (int) $to;
          $limits = array(
            'month' => 12,
            'day' => 31,
            'hour' => 23,
            'minute' => 59,
            'second' => 59,
          );
          if (isset($limits[$element['#date_component']])) {
            $limit = $limits[$element['#date_component']];
            if ($_to > $limit || $_to < 0 || $_from > $limit || $_from < 0) {
              $form_state->setError($element, t('The keys %from and %to must be within the range 0 to !max.', array('%from' => $_from, '%to' => $_to, '!max' => $limit)));
              continue;
            }
          }
          $items[$from . '|' . $to] = $label;
        }
      }
    }

    $form_state->setValueForElement($element, $items);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $settings = array(
      'has_time' => TRUE,
      'has_range' => TRUE,
      'require_consistency' => FALSE,
      'minimum_components' => array(
        'from_granularity_year' => FALSE,
        'from_granularity_month' => FALSE,
        'from_granularity_day' => FALSE,
        'from_granularity_hour' => FALSE,
        'from_granularity_minute' => FALSE,
        'from_granularity_second' => FALSE,
        'from_granularity_timezone' => FALSE,
        'from_estimate_year' => FALSE,
        'from_estimate_month' => FALSE,
        'from_estimate_day' => FALSE,
        'from_estimate_hour' => FALSE,
        'from_estimate_minute' => FALSE,
        'from_estimate_second' => FALSE,
        'txt_short' => FALSE,
        'txt_long' => FALSE,
      ),
    );
    return $settings + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = array(
      'path' => '',
      'hide_blank_items' => TRUE,
      'estimates' => array(
        'year' => array(
          '-60000|1600' => t('Pre-colonial'),
          '1500|1599' => t('16th century'),
          '1600|1699' => t('17th century'),
          '1700|1799' => t('18th century'),
          '1800|1899' => t('19th century'),
          '1900|1999' => t('20th century'),
          '2000|2099' => t('21st century'),
        ),
        'month' => array(
          '11|1' => t('Winter'),
          '2|4' => t('Spring'),
          '5|7' => t('Summer'),
          '8|10' => t('Autumn'),
        ),
        'day' => array(
          '0|12' => t('The start of the month'),
          '10|20' => t('The middle of the month'),
          '18|31' => t('The end of the month'),
        ),
        'hour' => array(
          '6|18' => t('Day time'),
          '6|12' => t('Morning'),
          '12|13' => t('Noon'),
          '12|18' => t('Afternoon'),
          '18|22' => t('Evening'),
          '0|1' => t('Midnight'),
          '18|6' => t('Night'),
        ),
        'minute' => array(),
        'second' => array(),
      ),
    );
    return $settings + parent::defaultFieldSettings();
  }


  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    // Treat the values as property value of the main property, if no array is
    // given.
    if (isset($values) && !is_array($values)) {
      $values = [static::mainPropertyName() => $values];
    }
    if (isset($values)) {
      $values += [
        'data' => [],
      ];
    }
    // Unserialize the data property.
    // @todo The storage controller should take care of this, see
    //   https://www.drupal.org/node/2414835
    if (is_string($values['data'])) {
      $values['data'] = unserialize($values['data']);
    }
    // Instead of using a separate class for the 'check_approximate' computed
    // property, we just set it here, as we have the value of the 'data'
    // property available anyway.
    if (isset($values['data']['check_approximate'])) {
      $this->writePropertyValue('check_approximate', $values['data']['check_approximate']);
    }
    parent::setValue($values, $notify);
  }

}
