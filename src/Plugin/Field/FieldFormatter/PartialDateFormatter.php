<?php 

/**
 * @file
 * Contains \Drupal\partial_date\Plugin\Field\FieldFormatter\PartialDateFormatter.
 */

namespace Drupal\partial_date\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FormatterBase;
use DateTime;
use Drupal\partial_date\DateTools;
use Drupal\partial_date\Entity\PartialDateFormat;

/**
 * Plugin implementation for Partial Date formatter.
 * (Drupal 7): hook_field_formatter_info() => (Drupal 8): "FieldFormatter" annotation
 *
 * @FieldFormatter(
 *   id = "partial_date_formatter",
 *   module = "partial_date",
 *   label = @Translation("Default"),
 *   description = @Translation("Display partial date."),
 *   field_types = {"partial_date"},
 *   quickedit = {
 *     "editor" = "disabled"
 *   },
 *   settings = {
 *     "use_override" = "none",
 *     "format" = "short", 
 *   },
 * )
 */
class PartialDateFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'use_override' => 'none',
      'range_reduce' => '1',
      'format' => 'short', 
    ) + parent::defaultSettings();
  }
  
  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = array();
    $elements['use_override'] = array(
      '#type' => 'checkbox_with_options',
      '#title' => t('Use date descriptions (if available)'),
      '#default_value' => $this->getSetting('use_override'),
      '#options' => $this->partial_date_txt_override_options(),
      '#checkbox_value' => 'none',
      '#description' => t('This setting allows date values to be replaced with user specified date descriptions, if applicable.'),
    );
    $elements['range_reduce'] = array(
      '#type' => 'checkbox',
      '#title' => t('Reduce common values from range display'),
      '#default_value' => $this->getSetting('range_reduce'),
      '#description' => t('This setting allows a simplified display for range values. For example "2015 Jan-Sep" instead of full specification "2015 Jan-2015 Sep"'),
    );
    $elements['format'] = array(
      '#title' => t('Partial date format'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('format'),
      '#required' => TRUE,
      '#options' => $this->partial_date_format_types(),
//      '#id' => 'partial-date-format-selector',
//      '#attached' => array(
//        'js' => array(drupal_get_path('module', 'partial_date') . '/partial-date-admin.js'),
//      ),
      '#description' => t('You can use any of the predefined partial date formats. '
          . 'Or, you can configure partial date formats <a href=":config">here</a>.',
          array(':config' => '/admin/config/regional/partial-date-formats')),
    );

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();
    if ($this->getSetting('use_override') != 'none') {
      $overrides = $this->partial_date_txt_override_options();
      $summary[] = t(' User text: ') . $overrides[$this->getSetting('use_override')];
    }
    $types   = $this->partial_date_format_types();
    $item    = $this->partial_date_generate_date();
    $example = $this->formatItem($item);
    $summary[] = array('#markup' => t('Format: ') . $types[$this->getSetting('format')]
                        . ' - ' . $example);
    return $summary;
  }
  
  /**
   * {@inheritdoc}
   * (Drupal 7): hook_field_formatter_view() => (Drupal 8): viewElements
   *
   * This handles any text override options before passing the values onto the
   * partial_date_render() or partial_date_render_range().
   */
  public function viewElements(\Drupal\Core\Field\FieldItemListInterface $items, $langcode) {
    $settings = $this->getSettings();
//    $field = $this->getFieldSettings();
//    $has_to_date = strpos($field['type'], 'range');
//    $has_date = strpos($field['type'], 'date');
//    $has_time = strpos($field['type'], 'time');
    $element = array();
    $format  = $this->getCurrentFormat();
    foreach ($items as $delta => $item) {
      //$item is the field (FieldType\PartialDateTime)
      $value = $item->getValue();
      if (!is_array($value)) {
        \Drupal::logger('partial_date')->warning('PartialDateFormatter.viewElements: Unexpected field value:' . serialize($value));
        continue;
      }
      $override = $this->getTextOverride($value);
      if (!empty($override)) {
        $element[$delta] = array('#markup' => $override);
      } else {
        if ($this->getSetting('range_reduce')) {
          $this->rangeReduce($value, $format);
        }
        $from = $this->formatItem($this->getStart($value));
        $to   = $this->formatItem($this->getEnd($value));
        
//        // The additonal "Approximate only" checkbox.
//        $display['settings']['is_approximate'] = FALSE;
//        if (!empty($widget_settings['theme_overrides']['check_approximate'])) {
//          $display['settings']['is_approximate']  = !empty($item['check_approximate']);
//        }
//        if (isset($item['from'])) {
//          $from = $this->partial_date_field_widget_reduce_date_components($item['from'], TRUE);
//        }
//        if (isset($item['to'])) {
//          $to = $this->partial_date_field_widget_reduce_date_components($item['to'], FALSE);
//        }

        $markup = '';
        if ($to && $from) {
          $sep = $format->separator['range'];
          $markup = $from . ' ' . $sep . ' ' . $to;
        } elseif ($to xor $from) {
          $markup = $from ? $from : $to;
        } else {
          $markup = 'N/A';
        }
        $element[$delta] = array('#markup' => $markup);
      }
    }
    return $element;
  }
  
  protected function loadFormats() {
    static $formats = NULL;
    if (!isset($formats)) {
      $storage = \Drupal::entityTypeManager()->getStorage('partial_date_format');
      $qry = \Drupal::entityQuery('partial_date_format')
        ->execute();
      $formats = $storage->loadMultiple($qry);
    }
    return $formats;
  }

  protected function getCurrentFormat(){
    $formats = $this->loadFormats();
    $current = $this->getSetting('format');
    return $formats[$current];
  }
  
  public function getTextOverride($item) {
    $override = '';
    $item += array(
      'txt_short' => NULL,
      'txt_long' => NULL,
    );
    switch ($this->getSetting('use_override')) {
      case 'short':
        if (strlen($item['txt_short'])) {
          $override = $item['txt_short'];
        }
        break;
      case 'long':
        if (strlen($item['txt_long'])) {
          $override = $item['txt_long'];
        }
        break;

      case 'long_short':
        if (strlen($item['txt_long'])) {
          $override = $item['txt_long'];
        }
        elseif (strlen($item['txt_short'])) {
          $override = $item['txt_short'];
        }
        break;
      case 'short_long':
        if (strlen($item['txt_short'])) {
          $override = $item['txt_short'];
        }
        elseif (strlen($item['txt_long'])) {
          $override = $item['txt_long'];
        }
        break;
    }
    return $override;  
  }

################################################################################
#  Helpers:                                                                    #
#   * partial_date_format_default_options()                                    #
#     Default formatter options for the supported format types.                #
#      - moved to config/install/partial_date.format.*.yml                     #
#                                                                              #
#   * partial_date_format_types()                                              #
#     The core format types implemented by the module. Since we are not with   #
#     complete dates, we can not fallback on the standard PHP formatters.      #
#                                                                              #
#   * partial_date_generate_date()                                             #
#     Generates an example date item for deminstration of format only.         #
#     This may not represent the parameters that are passed in.                #
#                                                                              #
#   * partial_date_txt_override_options()                                      #
#     Formatter options on how to use the date descriptions.                   #
#                                                                              #
################################################################################

//  function partial_date_render_range($from = NULL, $to = NULL, $settings = array()) {
//    if (empty($from) && empty($to)) {
//      return '';
//    }
//    // TODO: Make this configurable.
//    $settings += array(
//      'reduce' => TRUE,
//      'format' => 'short',
//    );
//    if ($settings['reduce']) {
//      partial_date_reduce_range_values($from, $to);
//    }
//
//    $from = partial_date_render($from, $settings);
//    $to = partial_date_render($to, $settings);
//
//    if ($to && $from) {
//      // @FIXME
//  // theme() has been renamed to _theme() and should NEVER be called directly.
//  // Calling _theme() directly can alter the expected output and potentially
//  // introduce security issues (see https://www.drupal.org/node/2195739). You
//  // should use renderable arrays instead.
//  // 
//  // 
//  // @see https://www.drupal.org/node/2195739
//  // return theme('partial_date_range', array('from' => $from, 'to' => $to, 'settings' => $settings));
//
//    }
//    // One or both will be empty.
//    return $from . $to;
//  }

//  function partial_date_render($item, $settings = array()) {
//    if (empty($item)) {
//      return '';
//    }
////    $settings += array(
////      'format' => 'short',
////      'is_approximate' => 0,
////    );
//
//    // @FIXME
//  // theme() has been renamed to _theme() and should NEVER be called directly.
//  // Calling _theme() directly can alter the expected output and potentially
//  // introduce security issues (see https://www.drupal.org/node/2195739). You
//  // should use renderable arrays instead.
//  // 
//  // 
//  // @see https://www.drupal.org/node/2195739
//  // return theme('partial_date', array(
//  //     'item' => $item,
//  //     'settings' => $settings['component_settings'],
//  //     'format' => $settings['format'],
//  //     'is_approximate' => $settings['is_approximate'],
//  //   ));
//    return array(
//      '#theme' => 'partial_date',
//      'item' => $item,
//      '#format' => $this->getSetting('format'),
//      'is_approximate' => $this->getSetting('is_approximate'),
//    );
//  }

  function partial_date_format_types() {
    $formats = $this->loadFormats();
    $types = array();
    foreach($formats as $key => $format) {
      $types[$key] = $format->label(); 
    }
    return $types;
  }

  function partial_date_txt_override_options() {
    return array(
      'none' => t('Use date only', array(), array('context' => 'datetime')),
      'short' => t('Use short description', array(), array('context' => 'datetime')),
      'long' => t('Use long description', array(), array('context' => 'datetime')),
      'long_short' => t('Use long or short description', array(), array('context' => 'datetime')),
      'short_long' => t('Use short or long description', array(), array('context' => 'datetime')),
    );
  }

  /*
   * Reduce identical range components to simplify the display.
   * Format is needed to know which side should be cleared. The order in which 
   * year, month and day are displayed is important:
   * Ex. 2015 Jun to 2015 Sep => 2015 Jun to Sep
   * but Jun 2015 to Sep 2015 => Jun to Sep 2015
   * Rules:
   * 1. If all date correspondent components are equal, keep only left side and quit (no time compression)
   * 2. If time components are present, stop further compression (mixed date & time compression is confusing).
   * 3. If same year, check format order:
   *    a. YYYY / MM - compress right  (2015 Jun - Sep)
   *    b. MM / YYYY - compress left   (Jun - Sep 2015)
   *    (not same year - stop further compression)
   * 4. If same month, check format order:
   *    a. MM / DD - compress right  (Jun 15 - 25)
   *    b. DD / MM - compress left   (15 - 25 Jun)
   * (same day was
   */
  public function rangeReduce(array &$values, PartialDateFormat $format) {
    $sameDate = ($values['year']  == $values['year_to']) &&
                ($values['month'] == $values['month_to']) &&
                ($values['day']   == $values['day_to']);
    if ($sameDate) {
      $values['year_to']  = NULL;
      $values['month_to'] = NULL;
      $values['day_to']   = NULL;
      return;
    }
    $hasTime =  isset($values['hour'])   || isset($values['hour_to']) ||
                isset($values['minute']) || isset($values['minute_to']) ||
                isset($values['second']) || isset($values['second_to']);
    if ($hasTime) {  
      return;
    }
    if ($values['year'] == $values['year_to']) {
      //If "year before month" compress right (otherwise left)
      $values['year' . ($format->isYearBeforeMonth() ? '_to' : '')] = NULL;
      if ($values['month'] == $values['month_to']) {
        //If "month before day" compress right (otherwise left)
        $values['month'. ($format->isMonthBeforeDay() ? '_to' : '')] = NULL;
      }
    }
  }
  
  public function getStart(array $values) {
    $result = array();
    foreach (partial_date_component_keys() as $key) {
      $result[$key] = $values[$key];
    }
    return $result;
  }
  
  public function getEnd(array $values) {
    $result = array();
    foreach (partial_date_component_keys() as $key) {
      $result[$key] = $values[$key . '_to'];
    }
    return $result;
  }
  
  public function hasTime(array $values) {
    foreach(array('hour', 'minute', 'second') as $key) {
      if (isset($values[$key]) || isset($values[$key . '_to'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Helper function to assign the correct components into an array that the
   * formatters can use.
   */
  function partial_date_field_widget_reduce_date_components($item, $is_start = TRUE, $is_approx = FALSE) {
    if (empty($item)) {
      return FALSE;
    }
    $components = array();
    foreach (partial_date_components() as $key => $title) {
      if (!empty($item[$key . '_estimate'])) {
        list($start, $end) = explode('|', $item[$key . '_estimate']);
        $components[$key] = $is_start ? $start : $end;
        $components[$key . '_estimate'] = $item[$key . '_estimate'];
        // We hit this on save, so we can not rely on the load set.
        if (isset($item[$key . '_estimate_label'])) {
          $components[$key . '_estimate_label'] = $item[$key . '_estimate_label'];
          $components[$key . '_estimate_value'] = $item[$key . '_estimate_value'];
        }
        if (isset($item[$key . '_estimate_value'])) {
          $components[$key . '_estimate_value'] = $item[$key . '_estimate_value'];
        }
      }
      else {
        $components[$key] = isset($item[$key]) && strlen($item[$key]) ? $item[$key] : NULL;;
      }
    }
    $has_data = FALSE;
    foreach ($components as $key => $value) {
      if (!empty($value)) {
        $has_data = TRUE;
        break;
      }
    }
    if (!$has_data) {
      return FALSE;
    }
    return $components;
  }

  /**
   * This generates a date component based on the specified timestamp and
   * timezone. This is used for demonstrational purposes only, and may fall back
   * to the request timestamp and site timezone.
   *
   * This could throw errors if outside PHP's native date range.
   */
  function partial_date_generate_date($timestamp = REQUEST_TIME, $timezone = NULL) {
    // PHP Date should handle any integer, but outside of the int range, 0 is
    // returned by intval(). On 32 bit systems this is Fri, 13 Dec 1901 20:45:54
    // and Tue, 19 Jan 2038 03:14:07 GMT
    $timestamp = intval($timestamp);
    if (!$timestamp) {
      $timestamp = REQUEST_TIME;
    }
    if (!$timezone) {
      //$timezones = partial_date_granularity_field_options('timezone');
      //$timezone = $timezones[rand(0, count($timezones) - 1)];
      $timezone = partial_date_timezone_handling_correlation('UTC', 'site');
    }
    try {
      $tz = new \DateTimeZone($timezone);
      $date = new DateTime('@' . $timestamp, $tz);
      if ($date) {
        return array(
          'year' => $date->format('Y'),
          'month' => $date->format('n'),
          'day' => $date->format('j'),
          'hour' => $date->format('G'),
          'minute' => $date->format('i'),
          'second' => $date->format('s'),
          'timezone' => $timezone,
        );
      }
    }
    catch (Exception $e) {}

    return FALSE;
  }

  public function formatItem($item) {
    $components = array();
    $format = $this->getCurrentFormat();
    uasort($format->components, 'partial_date_sort');
    // Enforce meridiem if we have a 12 hour format.
    if (isset($format->components['hour'])
        && ($format->components['hour'] == 'h' || $format->components['hour'] == 'g')) {
      if (empty($format->meridiem)) {
        $format->meridiem = 'a';
      }
    }

    // Hide year designation if no valid year.
    if (empty($item['year'])) {
      $format->year_designation = '';
    }

  //  //TODO - review "is_approximate" functionality
  //  if (empty($settings['is_approximate']) || !isset($settings['is_approximate'])) {
  //    $settings['components']['approx'] = '';
  //  }

    $valid_components = partial_date_components();
    $last_type = FALSE;
    foreach ($format->components as $type => $component) {
      $markup = '';
      if (isset($valid_components[$type])) {
        // Value is determined by the $settings['display]
        // If estimate, use this other use value
        $display_type = empty($format->display[$type]) ? 'estimate_label' : $format->display[$type];
        $estimate = empty($item[$type . '_estimate']) ? FALSE : $item[$type . '_estimate'];
  //      $value = isset($item[$type]) && strlen($item[$type]) ? $item[$type] : FALSE;
        // If no estimate, switch to the date only formating option.
        if (!$estimate && ($display_type == 'date_or' || strpos($display_type, 'estimate_') === 0)) {
          $display_type = 'date_only';
        }

        switch ($display_type) {
          case 'none':
            // We need to avoid adding an empty option.
            continue;

          case 'date_only':
          case 'date_or':
            $markup = $this->formatComponent($type, $item, $format);
            break;

          case 'estimate_label':
            $markup = $item[$type . '_estimate_label'];
            // We no longer have a date / time like value.
            $type = 'other';
            break;

          case 'estimate_range':
            list($start, $end) = explode('|', $item[$type . '_estimate']);
            $item[$type] = $start;
            $item[$type . '_to'] = $end;
            $markup = $this->formatComponent($type, $item, $format);
  //          $end = $this->formatComponent($end, $component['format'], $item, $settings);
  //          if (strlen($start) && strlen($end)) {
  //            $markup = t('@estimate_start to @estimate_end', array('@estimate_start' => $start, '@estimate_end' => $end));
  //          }
  //          elseif (strlen($start) xor strlen($end)) {
  //            $markup = strlen($start) ? $start : $end;
  //          }
            break;

          case 'estimate_component':
  //          $markup = $this->formatComponent($item[$type . '_estimate_value'], $component['format'], $item, $settings);
            $item[$type] = $item[$type . '_estimate_value'];
            $markup = $this->formatComponent($type, $item, $format);
            break;
        }

        if (!strlen($markup)) {
          if (isset($component['empty']) && strlen($component['empty'])) {
            // What do we get? If numeric, assume a date / time component, otherwise
            // we can assume that we no longer have a date / time like value.
            $markup = $component['empty'];
            if (!is_numeric($markup)) {
              $type = 'other';
            }
          }
        }
        if (strlen($markup)) {
          if ($separator = _partial_date_component_separator($last_type, $type, $format->separator)) {
            $components[] = $separator;
          }
          $components[] = $markup;
          $last_type = $type;
        }
      }
      elseif (isset($component['value']) && strlen($component['value'])) {
        if ($separator = _partial_date_component_separator($last_type, $type, $format->separator)) {
          $components[] = $separator;
        }
        $components[] = $component['value'];
        $last_type = $type;
      }

    }
    return implode('', $components);
  }

  //function formatComponent($value, $format, &$date, $additional = array()) {
  function formatComponent($key, $date, $formatSettings) {
    $value = isset($date[$key]) && strlen($date[$key]) ? $date[$key] : FALSE;
    if (!$value) {
      return ''; //if component value is missing, return an empty string.
    }
    $keyFormat = $formatSettings->components[$key]['format'];

    // If dealing with 12 hour times, recalculate the value.
    if ($keyFormat == 'h' || $keyFormat == 'g') {
      if ($value > 12) {
        $value -= 12;
      }
      elseif ($value == 0) {
        $value = '12';
      }
    }
    // Add suffixes for year and time formats
    $suffix = '';
    switch ($keyFormat) {
      case 'd-S':
      case 'j-S':
        $suffix = '<sup>' . DateTools::ordinalSuffix($value) . '</sup>';
        break;

      case 'y-ce':
      case 'Y-ce':
        $suffix = partial_date_year_designation_decorator($value, $formatSettings->year_designation);
        if (!empty($suffix) && !empty($value)) {
          $value = abs($value);
        }
        break;
    }

    switch ($keyFormat) {
      case 'y-ce':
      case 'y':
        return (strlen($value) > 2 ?  substr($value, - 2) : $value) . $suffix;

      case 'F':
        return DateTools::monthNames($value) . $suffix;

      case 'M':
        return DateTools::monthAbbreviations($value) . $suffix;

      // Numeric representation of the day of the week  0 (for Sunday) through 6 (for Saturday)
      case 'w':
        if (!empty($date['year']) && !empty($date['month'])) {
          return DateTools::dayOfWeek($date['year'], $date['month'], $value) . $suffix;
        }
        return '';

      // A full textual representation of the day of the week.
      case 'l':
      // A textual representation of a day, three letters.
      case 'D':
        if (!empty($date['year']) && !empty($date['month'])) {
          $day = DateTools::dayOfWeek($date['year'], $date['month'], $value);
          if ($keyFormat == 'D') {
            return DateTools::weekdayAbbreviations($day, 3) . $suffix;
          } else {
            return DateTools::weekdayNames($day) . $suffix;
          }
        }
        return '';

      case 'n':
      case 'j':
      case 'j-S':
      case 'g':
      case 'G':
        return intval($value) . $suffix;

      case 'd-S':
      case 'd':
      case 'h':
      case 'H':
      case 'i':
      case 's':
      case 'm':
        return sprintf('%02s', $value) . $suffix;

      case 'Y-ce':
      case 'Y':
      case 'e':
        return $value . $suffix;

      case 'T':
        try {
          $tz = new DateTimeZone($value);
          $transitions = $tz->getTransitions();
          return $transitions[0]['abbr']  . $suffix;
        }
        catch (Exception $e) {}
        return '';


      // Todo: implement
      // Year types
      // ISO-8601 year number
      case 'o':

      // Day types
      // The day of the year
      case 'z':
      // ISO-8601 numeric representation of the day of the week
      case 'N':

      // Timezone offsets
      // Whether or not the date is in daylight saving time
      case 'I':
      // Difference to Greenwich time (GMT) in hours
      case 'O':
      // Difference to Greenwich time (GMT) with colon between hours and minutes
      case 'P':
      // Timezone offset in seconds
      case 'Z':

      default:
        return '';
    }
  }


}
