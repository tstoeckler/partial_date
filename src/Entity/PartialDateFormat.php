<?php

namespace Drupal\partial_date\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\partial_date\Entity\PartialDateFormatInterface;

/**
 * Defines the FormatType config entity.
 * 
 * @ConfigEntityType(
 *   id = "partial_date_format",
 *   label = @Translation("Partial date format"),
 *   handlers = {
 *     "list_builder" = "Drupal\partial_date\Controller\PartialDateFormatListBuilder",
 *     "form" = {
 *        "add" = "Drupal\partial_date\Form\PartialDateFormatEditForm",
 *        "edit" = "Drupal\partial_date\Form\PartialDateFormatEditForm",
 *        "delete" = "Drupal\partial_date\Form\PartialDateFormatDeleteForm",
 *     }
 *   },
 *   config_prefix = "format",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/regional/date-time/partial-date-format/{format}",
 *     "delete-form" = "/admin/config/regional/date-time/partial-date-format/{format}/delete",
 *   },
 * )
 *
 * @author CosminFr
 */
class PartialDateFormat extends ConfigEntityBase implements PartialDateFormatInterface {
  /**
   * @var string
   */
  public  $id;
  
  /**
   * @var string
   */
  public $meridiem;
  
  /**
   * @var string
   * This controls how year designation is handled: 1BC = 1BCE = -1 and 1AD = 1CE = 1.
   */
  public $year_designation;
  
  /**
   * @var array
   */
  public $display = array(
    'year' => 'estimate_label',
    'month' => 'estimate_label',
    'day' => 'estimate_label',
    'hour' => 'estimate_label',
    'minute' => 'estimate_label',
    'second' => 'none',
    'timezone' => 'none',
  );
  
  /**
   * @var array
   */
  public $components = array(
    'year' => array('format' => 'y-ce', 'empty' => '', 'weight' => 0), 
    'month' => array('format' => 'm', 'empty' => '', 'weight' => 1),
    'day' => array('format' => 'j', 'empty' => '', 'weight' => 2),
    'hour' => array('format' => 'H', 'empty' => '', 'weight' => 3),
    'minute' => array('format' => 'i', 'empty' => '', 'weight' => 4),
    'second' => array('format' => 's', 'empty' => '', 'weight' => 5),
    'timezone' => array('format' => 'T', 'empty' => '', 'weight' => 6),
    'approx' => array('value' => '', 'weight'=> -1),
    'c1' => array('value' => '', 'weight'=> 7),
    'c2' => array('value' => '', 'weight'=> 8),
    'c3' => array('value' => '', 'weight'=> 9),
  );

  /**
   * @var array
   * An array with specific separators.
   */
  public $separator = array(
    'date' => '/',
    'time' => ':',
    'datetime' => ' ',
    'range' => ' - ',
    'other' => ' ',
  );
  
  public function isYearBeforeMonth() {
    $yearWeight  = $this->components['year']['weight'] ?: 0;
    $monthWeight = $this->components['month']['weight'] ?: 0;
    return $yearWeight <= $monthWeight;
  }
  
  public function isMonthBeforeDay() {
    $monthWeight = $this->components['month']['weight'] ?: 0;
    $dayWeight   = $this->components['day']['weight'] ?: 0;
    return $monthWeight <= $dayWeight;
  }
  
  /**
   * Helper functions. Should be moved to configuration (yml files), altough not really user configurable.
   */

  /**
   * Define the possible options for meridiem setting
   * @return "options" array
   */
  public function partial_date_meridiem_options() {
    return array(
      'a' => t('Lowercase (am or pm)'),
      'A' => t('Uppercase (AM or PM)')
    );
  }

  /**
   * Returns a translated option list of supported year designations.
   */
  public function partial_date_year_designation_options() {
    return array(
      'sign' => t('Negative sign (-)', array(), array('context' => 'datetime')),
      'ad' => t('Anno Domini (BC/AD)', array(), array('context' => 'datetime')),
      'bc' => t('Anno Domini (BC only)', array(), array('context' => 'datetime')),
      'ce' => t('Common Era (BCE/CE)', array(), array('context' => 'datetime')),
      'bce' => t('Common Era (BCE only)', array(), array('context' => 'datetime'))
    );
  }

  // TODO: Doco in main module
  public function partial_date_component_format_options($component, array $additional_values = array()) {
    static $options = NULL;
    if (!isset($options)) {
      $options = array(
        'year' => array(
          'Y' => t('A full numeric representation of a year. Eg: -125, 2003', array(), array('context' => 'datetime')),
          'y' => t('A two digit representation of a year. Eg: -25, 03', array(), array('context' => 'datetime')),
          'Y-ce' => t('A full numeric representation of a year with year designation. Eg: 125BC, 125BCE or -125', array(), array('context' => 'datetime')),
          'y-ce' => t('A two digit representation of a year with year designation. Eg: 25BC, 25BCE or -25', array(), array('context' => 'datetime')),
      //        'o' => t('ISO-8601 year number.', array(), array('context' => 'datetime')),
        ),
        'month' => array(
          'F' => t('A full textual representation of a month, January through December.', array(), array('context' => 'datetime')),
          'm' => t('Numeric representation of a month, with leading zeros, 01 through 12', array(), array('context' => 'datetime')),
          'M' => t('A short textual representation of a month, three letters, Jan through Dec.', array(), array('context' => 'datetime')),
          'n' => t('Numeric representation of a month, without leading zeros, 1 through 12', array(), array('context' => 'datetime')),
        ),
        'day' => array(
          'd' => t('Day of the month, 2 digits with leading zeros, 01 through 31', array(), array('context' => 'datetime')),
          'j' => t('Day of the month without leading zeros, 1 through 31.', array(), array('context' => 'datetime')),
          'd-S' => t('Day of the month, 2 digits with leading zeros with English ordinal suffix.', array(), array('context' => 'datetime')),
          'j-S' => t('Day of the month without leading zeros with English ordinal suffix.', array(), array('context' => 'datetime')),
          // 'z' => t('The day of the year (starting from 0).', array(), array('context' => 'datetime')),
          'l' => t('A full textual representation of the day of the week.', array(), array('context' => 'datetime')),
          'D' => t('A textual representation of a day, three letters.', array(), array('context' => 'datetime')),
          // 'N' => t('ISO-8601 numeric representation of the day of the week.', array(), array('context' => 'datetime')),
          // 'S' => t('English ordinal suffix for the day of the month.', array(), array('context' => 'datetime')),
          'w' => t('Numeric representation of the day of the week  0 (for Sunday) through 6 (for Saturday).', array(), array('context' => 'datetime')),
        ),
        'hour' => array(
          'g' => t('12-hour format of an hour without leading zeros, 1 through 12.', array(), array('context' => 'datetime')),
          'G' => t('24-hour format of an hour without leading zeros, 0 through 23.', array(), array('context' => 'datetime')),
          'h' => t('12-hour format of an hour with leading zeros, 01 through 12.', array(), array('context' => 'datetime')),
          'H' => t('24-hour format of an hour with leading zeros, 00 through 23.', array(), array('context' => 'datetime')),
        ),
        'minute' => array(
          'i' => t('Minutes with leading zeros, 00 through 59.', array(), array('context' => 'datetime')),
        ),
        'second' => array(
          's' => t('Seconds, with leading zeros, 00 through 59.', array(), array('context' => 'datetime')),
          //'B' => t('Swatch Internet time.', array(), array('context' => 'datetime')),
        ),
        'timezone' => array(
          'e' => t('Timezone identifier. Eg: UTC, GMT, Atlantic/Azores.', array(), array('context' => 'datetime')),
          'T' => t('Timezone abbreviation. Eg: EST, MDT', array(), array('context' => 'datetime')),
          // 'I' => t('Whether or not the date is in daylight saving time.', array(), array('context' => 'datetime')),
          // 'O' => t('Difference to Greenwich time (GMT) in hours. Eg: +0200', array(), array('context' => 'datetime')),
          // 'P' => t('Difference to Greenwich time (GMT) with colon between hours and minutes. Eg: +02:00', array(), array('context' => 'datetime')),
          // 'Z' => t('Timezone offset in seconds, -43200 through 50400.', array(), array('context' => 'datetime')),
        ),
      );
    }
    return $additional_values + $options[$component];
  }

  
}
