<?php

/**
 * @file
 * Less freq. functions for field administration.
 */

/**
 * Internal callback for hook_field_info().
 */
//function _partial_date_field_info() {
//  $base = array(
//    'default_widget' => 'partial_date_widget',
//    'default_formatter' => 'partial_date_default',
//    'settings' => array(
//      'estimates' => partial_date_field_estimates_default_settings(),
//      // Minimal set of components before considered incomplete.
//      // Includes each component and any, any_form, and any_to.
//      'minimum_components' => array(),
//    ),
//  );
//  return array(
//    'partial_date' => $base + array(
//      'label' => t('Partial date and time'),
//      'description' => t('This field stores and renders partial dates.'),
//    ),
//    'partial_date_range' => $base + array(
//      'label' => t('Partial date and time range'),
//      'description' => t('This field stores and renders partial dates.'),
//    ),
//  );
//}

/**
 * This generates the best estimate for the date components based on the
 * submitted values.
 */
function partial_date_field_populate_components($item, $start_date = TRUE) {
  $base = array(
    'year' => $start_date ? PD2_YEAR_MIN : PD2_YEAR_MAX,
    'month' => $start_date ? 1 : 12,
    'day' => 0, // Calculate last as this is variable
    'hour' => $start_date ? 0 : 23,
    'minute' => $start_date ? 0 : 59,
    'second' => $start_date ? 0 : 59,
    'timezone' => '',
  );

  foreach (partial_date_components() as $key => $label) {
    $value_key = $start_date ? $key : $key . '_to';
    if (isset($item[$value_key]) && strlen($item[$value_key])) {
      $base[$key] = $item[$value_key];
    }
  }

  if (empty($base['day'])) {
    if ($start_date) {
      $base['day'] = 1;
    }
    else {
      $month_table = partial_date_month_matrix($base['year']);
      if (isset($month_table[$base['month'] - 1])) {
        $base['day'] = $month_table[$base['month'] - 1];
      }
      else {
        $base['day'] = 31;
      }
    }
  }
  return $base;
}

/**
 * Implements hook_field_presave().
 *
 * This assumes data in the format:
 * from
 *   year
 *   year_estimate
 *   month
 *   month_estimate
 *   etc
 * to
 *   year
 *   year_estimate
 *   month
 *   month_estimate
 *   etc
 */
function _partial_date_field_presave($entity_type, $entity, $field, $instance, $langcode, &$items) {
  $has_range = strpos($field['type'], '_range');
  foreach ($items as $delta => $item) {
    $items[$delta] = array();
    $items[$delta]['txt_short'] = isset($item['txt_short']) ? $item['txt_short'] : NULL;
    $items[$delta]['txt_long'] = isset($item['txt_long']) ? $item['txt_long'] : NULL;
    $items[$delta] += partial_date_field_presave_generate_storage_date($item['from']);

    if ($has_range) {
      $item['to'] = empty($item['to']) ? array() : $item['to'];
      $items[$delta] += partial_date_field_presave_generate_storage_date($item['to'], TRUE);
    }

    // Populate empty components with the estimate components. On load, these
    // should be cleared.
    $items[$delta]['data'] = array(
      'check_approximate' => empty($item['check_approximate']) ? 0 : 1,
    );

    foreach (partial_date_components(array('timezone')) as $key => $label) {
      $items[$delta]['data'][$key . '_estimate'] = '';
      $items[$delta]['data'][$key . '_estimate_from_used'] = 0;
      $items[$delta]['data'][$key . '_estimate_to_used'] = 0;
      $from = NULL;
      $to = NULL;
      if (!empty($item['from'][$key . '_estimate'])) {
        $items[$delta]['data'][$key . '_estimate'] = $item['from'][$key . '_estimate'];
        list ($from, $to) = explode('|', $item['from'][$key . '_estimate']);
        if (!isset($item['from'][$key]) || !strlen($item['from'][$key])) {
          $items[$delta][$key] = $from;
          $items[$delta]['data'][$key . '_estimate_from_used'] = 1;
        }
        if ($has_range && (!isset($item['to'][$key]) || !strlen($item['to'][$key]))) {
          $items[$delta][$key . '_to'] = $to;
          $items[$delta]['data'][$key . '_estimate_to_used'] = 1;
        }
      }
      if ($has_range) {
        $items[$delta]['data'][$key . '_to_estimate'] = '';
        if (!empty($item['to'][$key . '_estimate'])) {
          // We use the to estimate if not set
          $items[$delta]['data'][$key . '_to_estimate'] = $item['to'][$key . '_estimate'];
          list ($from_to, $to_to) = explode('|', $item['to'][$key . '_estimate']);
          if (!isset($item['from'][$key]) || !strlen($item['from'][$key]) || $items[$delta]['data'][$key . '_estimate_from_used']) {
            $items[$delta][$key] = is_numeric($from) ? min(array($from_to, $from)) : $from_to;
            $items[$delta]['data'][$key . '_estimate_from_used'] = 1;
          }
          if (!isset($item['to'][$key]) || !strlen($item['to'][$key]) || $items[$delta]['data'][$key . '_estimate_to_used']) {
            $items[$delta][$key . '_to'] = is_numeric($to) ? max(array($to_to, $to)) : $to_to;;
            $items[$delta]['data'][$key . '_estimate_to_used'] = 1;
          }
        }
      }
    }

    // This is done after the estimates are expanded out.
    $items[$delta]['timestamp'] = partial_date_float(partial_date_field_populate_components($items[$delta]));
    if ($has_range) {
      $items[$delta]['timestamp_to'] = partial_date_float(partial_date_field_populate_components($items[$delta], 0));
    }

    $items[$delta]['data'] = serialize($items[$delta]['data']);
  }
}

/**
 * Wrapper to set the storage column.
 */
function partial_date_field_presave_generate_storage_date($item, $end_date = FALSE) {
  $components = array();
  foreach (partial_date_components() as $key => $label) {
    $empty = $key == 'timezone' ? '' : NULL;
    $components[$key . ($end_date ? '_to' : '')] = isset($item[$key]) && strlen($item[$key]) ? $item[$key] : $empty;
  }
  return $components;
}

/**
 * Implements hook_field_validate().
 *
 * Possible error codes:
 * - 'xxxx': The partial_date year is not valid
 *
 * @see partial_date_field_widget_error().
 */
function _partial_date_field_validate($entity_type, $entity, $field, $instance, $langcode, $items, &$errors) {
  $has_range = strpos($field['type'], 'range');
  $date_components = partial_date_components();

  $has_data = FALSE;
  $minimum_components = FALSE;
  if (!empty($field['settings']['minimum_components'])) {
    $minimum_components = array_filter($field['settings']['minimum_components']);
    $widget_settings = $instance['widget']['settings'];
    $widget_components = array();
    foreach (array_filter($widget_settings['granularity']['from']) as $key) {
      $widget_components['from_granularity_' . $key] = $has_range ? t('From @component', array('@component' => $date_components[$key])) : $date_components[$key];
    }
    foreach (array_filter($widget_settings['estimates']['from']) as $key) {
      $widget_components['from_estimates_' . $key] = $has_range ? t('From @component Estimate', array('@component' => $date_components[$key])) : $date_components[$key];
    }
    if ($has_range) {
      foreach (array_filter($widget_settings['granularity']['to']) as $key) {
        $widget_components['to_granularity_' . $key] = t('To @component', array('@component' => $date_components[$key]));
      }
      foreach (array_filter($widget_settings['estimates']['to']) as $key) {
        $widget_components['to_estimates_' . $key] = t('To @component Estimate', array('@component' => $date_components[$key]));
      }
    }
    if (!empty($widget_settings['theme_overrides']['txt_short'])) {
      $widget_components['txt_short'] = t('Short date description');
    }
    if (!empty($widget_settings['theme_overrides']['txt_long'])) {
      $widget_components['txt_long'] = t('Long date description');
    }
    $minimum_components = array_intersect_key($widget_components, $minimum_components);
  }

  foreach ($items as $delta => $item) {
    if (!partial_date_field_is_empty($item, $field)) {
      $has_data = TRUE;
      $incomplete = array();
      if ($minimum_components) {
        foreach ($minimum_components as $key => $label) {
          if (strpos($key, 'from_granularity_') === 0) {
            $component = str_replace('from_granularity_', '', $key);
            if (empty($item['from'][$component])) {
              $errors[$field['field_name']][$langcode][$delta][] = array(
                'error' => 'partial_date_incomplete_from',
                'partial_date_component' => $component,
                'message' => t('@component is required', array('@component' => $label)),
              );
            }
          }
          elseif (strpos($key, 'from_estimates_') === 0) {
            $component = str_replace('from_estimates_', '', $key) . '_estimate';
            if (empty($item['from'][$component])) {
              $errors[$field['field_name']][$langcode][$delta][] = array(
                'error' => 'partial_date_incomplete_from',
                'partial_date_component' => $component,
                'message' => t('@component is required', array('@component' => $label)),
              );
            }
          }
          elseif (strpos($key, 'to_granularity_') === 0) {
            $component = str_replace('to_granularity_', '', $key);
            if (empty($item['to'][$component])) {
              $errors[$field['field_name']][$langcode][$delta][] = array(
                'error' => 'partial_date_incomplete_to',
                'partial_date_component' => $component,
                'message' => t('@component is required', array('@component' => $label)),
              );
            }
          }
          elseif (strpos($key, 'to_estimates_') === 0) {
            $component = str_replace('to_estimates_', '', $key) . '_estimate';
            if (empty($item['to'][$component])) {
              $errors[$field['field_name']][$langcode][$delta][] = array(
                'error' => 'partial_date_incomplete_to',
                'partial_date_component' => $component,
                'message' => t('@component is required', array('@component' => $label)),
              );
            }
          }
          else {
            if (empty($item[$key])) {
              $errors[$field['field_name']][$langcode][$delta][] = array(
                'error' => 'partial_date_incomplete_' . $key,
                'partial_date_component' => $component,
                'message' => t('@component is required', array('@component' => $label)),
              );
            }
          }
        }
      }
    }
  }

  if ($entity_type && $entity && !$has_data && $instance['required']) {
    if (!empty($minimum_components)) {
      $errors[$field['field_name']][$langcode][0][] = array(
        'error' => 'partial_date_is_required',
        'message' => t('@label requires at least one item to be completed. The following components are required: @components', array(
            '@label' => $instance['label'],
            '@components' => implode(', ', $minimum_components),
        )),
      );
    }
    else {
      $errors[$field['field_name']][$langcode][0][] = array(
        'error' => 'partial_date_is_required',
        'message' => t('@label requires at least one item to be completed.', array('@label' => $instance['label'])),
      );
    }
  }
  // @todo - ensure that the estimates match the date.
return;
// TODO
  foreach ($items as $delta => $item) {
    foreach (array('from', 'to') as $position) {
      if (!$has_range && $position == 'to') {
        break;
      }

      $item = $item[$position];
      foreach (partial_date_components() as $key => $label) {
        $value = strlen($item[$key]) ? $item[$key] : FALSE;
        $estimate = strlen($item[$key . '_estimate']) ? $item[$key . '_estimate'] : FALSE;
        if ($value && $estimate) {
          list($start, $end) = explode('|', $estimate);
          $empty_start = FALSE;
          $empty_end = FALSE;
          if (!strlen($start)) {
            $start = $value;
            $empty_start = TRUE;
          }
          if (!strlen($end)) {
            $empty_end = TRUE;
            $end = $value;
          }
/**
 * Helper function to determine the best error message given that we are
 * validating against a range that may or may not have a start or ending value.
 */
function _estimates_error_message($label, $estimate_label, $start, $end) {
  $e = max(array($start, $end));

  if ($start !== FALSE && $end !== FALSE) {
    return t('%label fields do not match. %label must be greater than %start and less than %end if you select %estimate',
        array('%start' => $start, '%end' => $end, '%estimate' => $estimate_label, '%label' => $label));
  }
  elseif ($start !== FALSE) {
    return t('%label fields do not match. %label must be greater than %start if you select %estimate',
        array('%start' => $start, '%estimate' => $estimate_label, '%label' => $label));
  }
  elseif ($end !== FALSE) {
    return t('%label fields do not match. %label must be less than %end if you select %estimate',
        array('%end' => $end, '%estimate' => $estimate_label, '%label' => $label));
  }
}

          $message = FALSE;
          switch ($key) {
            case 'year':
              if ($value < $start) {
                $message = _estimates_error_message($label, $estimate_options[$key][$estimate], $start, $end);
                $message = t('%label fields do not match. %label must be greater than %start if you select %estimate',
                    array('%start' => $start, '%estimate' => $estimate_options[$key][$estimate], '%label' => $label));
              }
              elseif ($value > $end) {
                $message = t('%label fields do not match. %label must be less than %end if you select %estimate',
                    array('%end' => $end, '%estimate' => $estimate_options[$key][$estimate], '%label' => $label));
              }
              break;

            default:
              // If range is x to y && x < y, value must be between x and y
              if ($end > $start) {
                if (!($value >= $start && $value <= $end)) {
                  $message = t('%label fields do not match. %label must be between %start and %end if you select %estimate',
                      array('%start' => $start, '%estimate' => $estimate_options[$key][$estimate], '%label' => $label));
                }
              }
              // If range is x to y && x > y, value must be between be <= x and  >= y
              elseif ($end > $start) {
                if (!($value >= $start || $value <= $end)) {

                }
              }
              // For the use-case where $start == $end
              elseif ($value != $start) {

              }

              if (!($internal_match || $external_match)) {
                $message = t('%label fields do not match. %label must be greater than %start if you select %estimate',
                    array('%start' => $start, '%estimate' => $estimate_options[$key][$estimate], '%label' => $label));
              }
              elseif ($value > $end) {
                $message = t('%label fields do not match. %label must be less than %end if you select %estimate',
                    array('%end' => $end, '%estimate' => $estimate_options[$key][$estimate], '%label' => $label));
              }
          }

          if ($message) {
            $errors[$field['field_name']][$langcode][$delta][] = array(
              'error' => 'partial_date_invalid_' . $key,
              'message' => $message,
            );
          }

        }
      }
      $year;
    }
  }


  foreach ($items as $delta => $item) {
    // Validate we actually have valid year as an integer value.
    if ($message = partial_date_field_validate_year($item, 'year')) {
      $errors[$field['field_name']][$langcode][$delta][] = array(
        'error' => 'partial_date_invalid_year_estimate',
        'message' => $message,
      );
    }
    if (!empty($item['year_estimate']) && !empty($item['year'])) {
      // Search and validate the first match.
      foreach ($year_estimates as $line) {
        if ($line[0] == $item['year_estimate']) {
          break;
        }
      }
      if ($item['year'] < $line[0] || $item['year'] > $line[1]) {
        $message = t('Year fields do not match. Year must be between %start and %end if you select %label', array('%start' => $line[0], '%end' => $line[1], '%label' => $line[2]));
        $errors[$field['field_name']][$langcode][$delta][] = array(
          'error' => 'partial_date_invalid_year_estimate',
          'message' => $message,
        );
      }
    }
    if (!empty($item['year_estimate_to']) && !empty($item['year_to'])) {
      // Search and validate the first match.
      foreach ($year_estimates as $line) {
        if ($line[1] == $item['year_estimate_to']) {
          break;
        }
      }
      if ($item['year_to'] < $line[0] || $item['year_to'] > $line[1]) {
        $message = t('Year fields do not match. Year must be between %start and %end if you select %label', array('%start' => $line[0], '%end' => $line[1], '%label' => $line[2]));
        $errors[$field['field_name']][$langcode][$delta][] = array(
          'error' => 'partial_date_invalid_year_estimate_to',
          'message' => $message,
        );
      }
    }

    if (!empty($item['month_estimate']) && !empty($item['month'])) {
      // Search and validate the first match.
      foreach ($month_estimates as $line) {
        if ($line[0] == $item['month_estimate']) {
          break;
        }
      }
      if (0) {
        $message = t('Month fields do not match. Year must be between %start and %end if you select %label', array('%start' => $line[0], '%end' => $line[1], '%label' => $line[2]));
        $errors[$field['field_name']][$langcode][$delta][] = array(
          'error' => 'partial_date_invalid_year_estimate',
          'message' => $message,
        );
      }
    }
    if (!empty($item['year_estimate_to']) && !empty($item['year_to'])) {
      // Search and validate the first match.
      foreach ($year_estimates as $line) {
        if ($line[1] == $item['year_estimate_to']) {
          break;
        }
      }
      if ($item['year_to'] < $line[0] || $item['year_to'] > $line[1]) {
        $message = t('Year fields do not match. Year must be between %start and %end if you select %label', array('%start' => $line[0], '%end' => $line[1], '%label' => $line[2]));
        $errors[$field['field_name']][$langcode][$delta][] = array(
          'error' => 'partial_date_invalid_year_estimate_to',
          'message' => $message,
        );
      }
    }

    /*
    if ($item['partial_date'] != '' && !valid_partial_date_address(trim($item['partial_date']))) {
    }
    */
  }
}

/**
 * Helper for hook_field_validate() to test that the year in within range.
 */
function partial_date_field_validate_year($item, $key) {
  if (isset($item[$key]) && strlen($item[$key])) {
    $year = $item[$key];
    // Validate that it is a real integer.
    if ((string) $year === (string) (int) $year) {
      if ($year > PD2_YEAR_MIN || $year < PD2_YEAR_MAX) {
        return;
      }
    }
    return t('Year must be an integer value between %start and %end.', array('%start' => PD2_YEAR_MIN, '%end' => PD2_YEAR_MAX));
  }
}

function _partial_date_inline_float_css($component = TRUE) {
  $language = \Drupal::languageManager()->getCurrentLanguage();
  // Language will be \Drupal\Core\Language\Language::\Drupal\Core\Language\Language::\Drupal\Core\Language\Language::\Drupal\Core\Language\Language::\Drupal\Core\Language\Language::LANGUAGE_LTR (0) or \Drupal\Core\Language\Language::\Drupal\Core\Language\Language::\Drupal\Core\Language\Language::\Drupal\Core\Language\Language::\Drupal\Core\Language\Language::LANGUAGE_RTL (1).
  $margin = $component ? '0.5' : '1';
  if ($language->direction) {
    return "float: right; margin-left: {$margin}em;";
  }
  else {
    return "float: left; margin-right: {$margin}em;";
  }
}

################################################################################
#  Field API Hooks & Helpers: Formatter                                        #
################################################################################

################################################################################
#  Element Related Functions:                                                  #
################################################################################
//  - moved to PartialDateElement

