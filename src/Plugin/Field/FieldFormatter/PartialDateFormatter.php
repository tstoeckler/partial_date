<?php 

/**
 * @file
 * Contains \Drupal\partial_date\Plugin\Field\FieldFormatter\PartialDateFormatter.
 */

namespace Drupal\partial_date\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use DateTime;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for Partial Date formatter.
 * (Drupal 7): hook_field_formatter_info() => (Drupal 8): "FieldFormatter" annotation
 *
 * @FieldFormatter(
 *   id = "partial_date_formatter",
 *   module = "partial_date",
 *   label = @Translation("Default"),
 *   description = @Translation("Display partial date."),
 *   field_types = {
 *     "partial_date", "partial_date_range",
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   },
 *   settings = {
 *     "use_override" = "none",
 *     "format" = "short", 
 *   },
 * )
 */
class PartialDateFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The partial date format storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $partialDateFormatStorage;

  /**
   * Constructs a partial date formatter.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->partialDateFormatStorage = $entity_type_manager->getStorage('partial_date');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'use_override' => 'none',
      'format' => 'short',
      'reduce' => TRUE,
    ) + parent::defaultSettings();
  }
  
  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $elements = array();

    $elements['use_override'] = array(
      '#title' => t('Use date descriptions rather than date'),
      '#type' => 'radios',
      '#default_value' => $this->getSetting('use_override'),
      '#required' => TRUE,
      '#options' => $this->overrideOptions(),
      '#description' => t('This setting allows date values to be replaced with user specified date descriptions, if applicable. This will use the first non-empty value.'),
    );
    $elements['format'] = array(
      '#title' => t('Partial date format'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('format'),
      '#required' => TRUE,
      '#options' => $this->formatOptions(),
      '#id' => 'partial-date-format-selector',
      '#attached' => array(
        'js' => array(drupal_get_path('module', 'partial_date') . '/partial-date-admin.js'),
      ),
      '#description' => t('You can use any of the predefined partial date formats. If you have administration proviledges, you can configure partial date formats <a href="%config"> here </a>.',
          array('%config' => '/admin/config/regional/date-time/partial-date-formats')),
    );

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();
    if ($this->getSetting('use_override') != 'none') {
      $overrides = $this->overrideOptions();
      $summary[] = t(' User text: ') . $overrides[$this->getSetting('use_override')];
    }
    $types = $this->formatOptions();
    $summary[] = t(' Format: ') . $types[$this->getSetting('format')];
    $item = $this->partial_date_generate_date();
    $example = $this->formatItem($item);

    $summary[] = array(
      '#prefix' => '<strong>',
      '#markup' => t(' Examples: '),
      '#sufix'  => ' </strong> '
    );
    $summary[] = $example;
    return $summary;
  }

  protected function overrideOptions() {
    return array(
      'none' => t('Use date only', array(), array('context' => 'datetime')),
      'short' => t('Use short description', array(), array('context' => 'datetime')),
      'long' => t('Use long description', array(), array('context' => 'datetime')),
      'long_short' => t('Use long or short description', array(), array('context' => 'datetime')),
      'short_long' => t('Use short or long description', array(), array('context' => 'datetime')),
    );
  }

  function formatOptions() {
    $formats = $this->partialDateFormatStorage->loadMultiple();
    $options = array();
    foreach($formats as $key => $format) {
      $options[$key] = $format->label();
    }
    return $options;
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
    foreach ($items as $delta => $item) {
      $override = FALSE;
      if (!is_array($item)) {
        continue;
      }
      $item += array(
        'txt_short' => NULL,
        'txt_long' => NULL,
        'check_approximate' => 0,
      );
      switch ($settings['use_override']) {
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

      if ($override !== FALSE) {
        $element[$delta] = array('#markup' => \Drupal\Component\Utility\SafeMarkup::checkPlain($override));
      }
      else {
        $to = $from = FALSE;
        // The additonal "Approximate only" checkbox.
        $display['settings']['is_approximate'] = FALSE;
        if (!empty($widget_settings['theme_overrides']['check_approximate'])) {
          $display['settings']['is_approximate']  = !empty($item['check_approximate']);
        }
        if (isset($item['from'])) {
          $from = partial_date_field_widget_reduce_date_components($item['from'], TRUE);
        }
        if (isset($item['to'])) {
          $to = partial_date_field_widget_reduce_date_components($item['to'], FALSE);
        }

        if ($to && $from) {
          $element[$delta] = $this->buildRange($from, $to);
        }
        elseif ($to) {
          $element[$delta] = $this->buildDate($to);
        }
        elseif ($from) {
          $element[$delta] = $this->buildDate($from);
        }
      }
    }
    return $element;
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
#   * partial_date_estimate_handling_options()                                 #
#     Formatter options on how to display the estimate values.                 #
#                                                                              #
################################################################################

  protected function buildRange($from, $to) {
    if ($this->getSetting('reduce')) {
      $this->reduceRangeValues($from, $to);
    }

    return [
      '#theme' => 'partial_date_range',
      '#from' => $this->buildDate($from),
      '#to' => $this->buildDate($to),
      '#separator' => '',
    ];
  }

  protected function reduceRangeValues(&$from, &$to) {
    foreach (array_keys(partial_date_components()) as $key) {
      if (!isset($from[$key]) && !isset($to[$key])) {
        continue;
      }
      elseif (!isset($from[$key]) || !isset($to[$key]) || $from[$key] !== $to[$key]) {
        return;
      }
      $from[$key] = NULL;
    }
  }

  function buildDate($date) {
    return array(
      '#theme' => 'partial_date',
      '#date' => $date,
      '#format' => $this->getSetting('format'),
      '#is_approximate' => $this->getSetting('is_approximate'),
    );
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
    // No easy way to test a 0 value :{
    $has_data = FALSE;
    foreach ($components as $key => $value) {
      if (strlen($value)) {
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
  /** @var \Drupal\partial_date\Entity\PartialDateFormatInterface $format */
  $format = $this->partialDateFormatStorage->load($this->getSetting('format'));
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
  $format = $formatSettings->components[$key]['format'];
  
  // If dealing with 12 hour times, recalculate the value.
  if ($format == 'h' || $format == 'g') {
    if ($value > 12) {
      $value -= 12;
    }
    elseif ($value == 0) {
      $value = '12';
    }
  }
  // Add suffixes for year and time formats
  $suffix = '';
  switch ($format) {
    case 'd-S':
    case 'j-S':
      $suffix = partial_date_day_ordinal_suffix($value);
      break;

    case 'y-ce':
    case 'Y-ce':
      $suffix = partial_date_year_designation_decorator($value, $formatSettings->year_designation);
      if (!empty($suffix) && !empty($value)) {
        $value = abs($value);
      }
      break;
  }

  switch ($format) {
    case 'y-ce':
    case 'y':
      return (strlen($value) > 2 ?  substr($value, - 2) : $value) . $suffix;

    case 'F':
      return partial_date_month_names($value) . $suffix;

    case 'M':
      return partial_date_month_abbreviations($value) . $suffix;

    // Numeric representation of the day of the week  0 (for Sunday) through 6 (for Saturday)
    case 'w':
      if (!empty($date['year']) && !empty($date['month'])) {
        return partial_date_day_of_week($date['year'], $date['month'], $value) . $suffix;
      }
      return '';

    // A full textual representation of the day of the week.
    case 'l':
    // A textual representation of a day, three letters.
    case 'D':
      if (!empty($date['year']) && !empty($date['month'])) {
        $day = partial_date_day_of_week($date['year'], $date['month'], $value);
        if ($format == 'D') {
          return partial_date_weekday_name_abbreviations($day, 3) . $suffix;
        }
        else {
          return partial_date_weekday_names($day) . $suffix;
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

/**
 * Gets day of week, 0 = Sunday through 6 = Saturday.
 *
 * Pope Gregory removed 10 days - October 5 to October 14 - from the year 1582
 * and proclaimed that from that time onwards 3 days would be dropped from the
 * calendar every 400 years.
 *
 * Thursday, October 4, 1582 (Julian) was followed immediately by Friday,
 * October 15, 1582 (Gregorian).
 *
 * @see PEAR::Date_Calc
 */
function partial_date_day_of_week($year, $month, $day) {
  $greg_correction = 0;
  if ($year < 1582 || ($year == 1582 && ($month < 10 || ($month == 10 && $day < 15)))) {
    $greg_correction = 3;
  }

  if ($month > 2) {
    $month -= 2;
  }
  else {
    $month += 10;
    $year--;
  }

  $day = floor((13 * $month - 1) / 5) +
         $day + ($year % 100) +
         floor(($year % 100) / 4) +
         floor(($year / 100) / 4) - 2 *
         floor($year / 100) + 77 + $greg_correction;

  return $day - 7 * floor($day / 7);
}

  
}
