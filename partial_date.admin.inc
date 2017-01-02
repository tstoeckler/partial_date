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

