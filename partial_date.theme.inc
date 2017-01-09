<?php

/**
 * @file
 * The theme callbacks for date formatters and element.
 */

/**
 * Prepares variables for partial dates.
 *
 * Default template: partial-date.html.twig
 *
 * @param array $variables
 *   An array of template variables containing:
 *   - date: An array of partial date information.
 *   - format: The partial date format.
 *   - is_approximate: Whether or not this date is to be displayed in the
 *     approximate form.
 */
function template_preprocess_partial_date(array &$variables) {
  $item = $variables['item'];
  $settings = $variables['settings'];
  $settings['format'] = $variables['format'];
  $settings['is_approximate'] = !empty($variables['is_approximate']);
  return partial_date_format($item, $settings);
}

/**
 * Workaround for theming the range elements inline.
 */
function theme_partial_date_inline_form_element($variables) {
  $element = &$variables['element'];
  // This is also used in the installer, pre-database setup.
  $t = 't';

  // This function is invoked as theme wrapper, but the rendered form element
  // may not necessarily have been processed by form_builder().
  $element += array(
    '#title_display' => 'before',
  );

  // Add element #id for #type 'item'.
  if (isset($element['#markup']) && !empty($element['#id'])) {
    $attributes['id'] = $element['#id'];
  }
  // Add element's #type and #name as class to aid with JS/CSS selectors.
  $attributes['class'][] = 'form-item';
  $attributes['class'][] = 'clearfix';
  if (!empty($element['#type'])) {
    $attributes['class'][] = 'form-type-' . strtr($element['#type'], '_', '-');
  }
  if (!empty($element['#name'])) {
    $attributes['class'][] = 'form-item-' . strtr($element['#name'], array(' ' => '-', '_' => '-', '[' => '-', ']' => ''));
  }
  // Add a class for disabled elements to facilitate cross-browser styling.
  if (!empty($element['#attributes']['disabled'])) {
    $attributes['class'][] = 'form-disabled';
  }
  // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/partial_date.settings.yml and config/schema/partial_date.schema.yml.
if ($css = \Drupal::config('partial_date.settings')->get('partial_date_component_field_inline_range_styles')) {
    $attributes['style'] = $css;
  }
  $output = '<div' . drupal_attributes($attributes) . '>' . "\n";

  // If #title is not set, we don't display any label or required marker.
  if (!isset($element['#title'])) {
    $element['#title_display'] = 'none';
  }
  $prefix = isset($element['#field_prefix']) ? '<span class="field-prefix">' . $element['#field_prefix'] . '</span> ' : '';
  $suffix = isset($element['#field_suffix']) ? ' <span class="field-suffix">' . $element['#field_suffix'] . '</span>' : '';

  switch ($element['#title_display']) {
    case 'before':
    case 'invisible':
      // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
// 
// 
// @see https://www.drupal.org/node/2195739
// $output .= ' ' . theme('form_element_label', $variables);

      $output .= ' ' . $prefix . $element['#children'] . $suffix . "\n";
      break;

    case 'after':
      $output .= ' ' . $prefix . $element['#children'] . $suffix;
      // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
// 
// 
// @see https://www.drupal.org/node/2195739
// $output .= ' ' . theme('form_element_label', $variables) . "\n";

      break;

    case 'none':
    case 'attribute':
      // Output no label and no required marker, only the children.
      $output .= ' ' . $prefix . $element['#children'] . $suffix . "\n";
      break;
  }

  if (!empty($element['#description'])) {
    $output .= '<div class="description">' . $element['#description'] . "</div>\n";
  }

  $output .= "</div>\n";

  return $output;
}

function theme_partial_date_range_inline_element($variables) {
  $element = &$variables['element'];
  $output = '<div class="clearfix partial-date-inline-range">';
  if (isset($element['from']) && isset($element['to'])) {
    $output .= '<div class="clearfix">';
    $output .= \Drupal::service("renderer")->render($element['from']);
    $output .= \Drupal::service("renderer")->render($element['_separator']);
    $output .= \Drupal::service("renderer")->render($element['to']);
    $output .= '</div>';
    if (!empty($element['#component_help'])) {
      $output .= '<div class="description">' . $element['#component_help'] . '</div>';
    }


  }
  $output .= drupal_render_children($element);
  $output .= '</div>';
  return $output;
}
