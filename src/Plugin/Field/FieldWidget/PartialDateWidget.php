<?php 

/**
 * @file
 * Contains \Drupal\partial_date\Plugin\Field\FieldWidget\PartialDateWidget.
 */

namespace Drupal\partial_date\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an widget for Partial Date fields.
 * (Drupal 7): hook_field_widget_info() => (Drupal 8): "FieldWidget" annotation
 *
 * @FieldWidget(
 *   id = "partial_date_widget",
 *   label = @Translation("Partial date"),
 *   field_types = {
 *     "partial_date",
 *     "partial_date_range", 
 *   },
 * )
 */
class PartialDateWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   * (Drupal 7): hook_field_widget_form() => (Drupal 8): PartialDateWidget::formElement
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $current_langcode = \Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED;
    $help_txt = $this->_partial_date_widget_help_text($current_langcode);

    $settings = $this->getSettings();
    $field    = $items[$delta];
    $fieldDef = $field->getFieldDefinition();
    $type     = $fieldDef->getType();
    $hasRange = strpos($type, 'range');

    $inline_range_style = FALSE;
    if ($hasRange && !empty($settings['theme_overrides']['range_inline'])) {
      $inline_range_style = ' style="' . _partial_date_inline_float_css(FALSE) . '"';
    }
    // Fix the title on multi-value fields.
    if (empty($element['#title'])) {
      $element['#title_display'] = 'invisible';
    }
    elseif ($fieldDef->getFieldStorageDefinition()->getCardinality() == 1) {
      $element['#type'] = 'item';
    }
    $element['#tree'] = TRUE;

    $value = $field->getValue();

    // General styles to nicely format the element inline without having to load
    // external style sheets.
    $config = \Drupal::config('partial_date.settings');
    $css = $config->get('partial_date_component_field_inline_styles');
    $css_txt = $config->get('partial_date_component_field_txt_inline_styles');

    // Correct the timezone based on the widget values.
    $tz_from = empty($value) || empty($value['from']) || empty($value['from']['timezone']) ? NULL : $value['from']['timezone'];
    $value['from']['timezone'] = partial_date_timezone_handling_correlation($tz_from, $settings['tz_handling']);

    if (!partial_date_timezone_option_is_selectable($settings['tz_handling'])) {
      unset($settings['granularity']['from']['timezone']);
      if ($hasRange) {
        unset($settings['granularity']['to']['timezone']);
      }
    }

    $estimate_options = $config->get('estimates');
    $increments = empty($settings['increments']) ? array() : $settings['increments'];
    $element['from'] = array(
      '#type' => 'partial_datetime_element',
      '#title' => $hasRange ? t('Start date') : t('Date'),
      '#title_display' => 'invisible',
      '#default_value' => $value['from'],
      '#granularity' => $settings['granularity']['from'],
      '#estimates' => $settings['estimates']['from'],
      '#estimate_options' => $estimate_options,
      '#component_styles' => $css,
      '#increments' => $increments,
    );
    if ($inline_range_style) {
//      $element['from']['#attributes']['style'] = $inline_range_style;
//      $element['from']['#theme_wrappers'] = array('partial_date_inline_form_element');
//      $element['#theme'] = 'partial_date_range_inline_element';
      $element['from']['#type'] = 'partial_date_inline_element';
    }

    if ($hasRange) {
      $element['_separator'] = array(
        '#type' => 'markup',
        '#markup' => t('<div class="partial-date-separator"' . $inline_range_style . '>to</div>', array(), array('context' => 'datetime')),
      );
      // Correct the timezone based on the widget values.
      $tz_to = empty($value) || empty($value['to']) || empty($value['to']['timezone']) ? NULL : $value['to']['timezone'];
      $value['to']['timezone'] = partial_date_timezone_handling_correlation($tz_to, $settings['tz_handling']);
      $element['to'] = array(
        '#type' => 'partial_datetime_element',
        '#title' => $hasRange ? t('Start date') : t('Date'),
        '#title_display' => 'invisible',
        '#default_value' => $value['to'],
        '#granularity' => $settings['granularity']['to'],
        '#estimates' => $settings['estimates']['to'],
        '#estimate_options' => $estimate_options,
        '#component_styles' => $css,
        '#increments' => $increments,
      );
      if ($inline_range_style) {
        $element['to']['#attributes']['style'] = $inline_range_style;
        $element['to']['#theme_wrappers'] = array('partial_date_inline_form_element');
      }
    }

    $element['#component_help'] = $help_txt['components']; //field_filter_xss($help_txt['components']);
    if (!empty($settings['theme_overrides']['check_approximate'])) {
      $element['check_approximate'] = array(
        '#type' => 'checkbox',
        '#title' => t('Approximation only', array(), array('context' => 'datetime')),
        '#default_value' => empty($value['check_approximate']) ? 0 : $value['check_approximate'],
      );
      if (!empty($help_txt['check_approximate'])) {
        $element['check_approximate']['#description'] = $help_txt['check_approximate']; //field_filter_xss($help_txt['check_approximate']);
      }
    }

    // Calculate these for any JScript states.
    $parents = array();
    if (!empty($element['#field_parents'])) {
      $parents = $element['#field_parents'];
    }
    elseif (!empty($element['#parents'])) {
      $parents = $element['#parents'];
    }
    // field_partial_dates[und][0][check_approximate]
    $parents[] = $field->getName();// ['field_name'];
    $parents[] = $current_langcode;

    foreach (array('txt_short', 'txt_long') as $key) {
      if (!empty($settings['theme_overrides'][$key])) {
        $description = NULL;
        if (!empty($help_txt[$key])) {
          $description = $help_txt[$key]; //field_filter_xss($help_txt[$key]);
        }

        $element[$key] = array(
          '#type' => 'textfield',
          '#title' => $description,
          '#description' => $description,
          '#title_display' => 'invisible',
          '#default_value' => $field->get($key)->getValue() ?: $key,   //empty($value[$key]) ? '' : $value[$key],
          '#prefix' => '<div class="partial-date-' . $key . '"' . ($css_txt ? ' style="' . $css_txt . '"' : '') . '>',
          '#suffix' => '</div>',
          '#maxlength' => 255,
        );
      }
    }

    $element['_remove'] = array(
      '#type' => 'checkbox',
      '#title' => t('Remove date', array(), array('context' => 'datetime')),
      '#default_value' => 0,
      '#access' => empty($settings['hide_remove']),
      '#prefix' => '<div class="partial-date-remove" ' . ($css_txt ? ' style="' . $css_txt . '"' : '') . '>',
      '#suffix' => '</div>',
    );
    if (!empty($help_txt['_remove'])) {
      $element['_remove']['#description'] = $help_txt['_remove']; //field_filter_xss($help_txt['_remove']);
    }

    $element['#prefix'] = '<div class="clearfix">';
    $element['#suffix'] = '</div>';
    return $element;
    
  }

  public static function defaultSettings() {

    return array(
      'year_estimates' => 0,
      'range_empty_start' => 1,
      'year_estimates_values' => '',
      'tz_handling' => 'none',
      'theme_overrides' => array(
        'check_approximate' => 0,
        'txt_short' => 0,
        'txt_long' => 0,
        'range_inline' => 0,
      ),
      'granularity' => array(
        'from' => partial_date_components(),
        'to' => partial_date_components(),
      ),
      'estimates' => array(
        'from' => array_combine(array_keys(partial_date_components(array('timezone'))), array_fill(0, 6, '')),
        'to' => array_combine(array_keys(partial_date_components(array('timezone'))), array_fill(0, 6, '')),
      ),
      'increments' => array(
        'second' => 1,
        'minute' => 1,
      ),
      'hide_remove' > 0,
      // @todo: i18n support here.
      'help_txt' => array(),
    ) + parent::defaultSettings();
  }
  
  /**
   * {@inheritdoc}
   * (Drupal 7): hook_field_widget_settings_form() => (Drupal 8): PartialDateWidget::settingsForm
   */
  public function settingsForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $settings = $this->getSettings();
//    $field    = $this->getFieldSettings();
    $has_range = TRUE; //strpos($field['type'], 'range');
//
    $elements = array();

    $options = partial_date_components();

    $elements['granularity'] = array('#tree' => TRUE);
    $elements['granularity']['from'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Date components'),
      '#default_value' => $settings['granularity']['from'],
      '#options' => $options,
      '#attributes' => array('class' => array('container-inline')),
      '#description' => t('Select the date attributes to collect and store.'),
      '#weight' => -10,
    );
    unset($options['timezone']); //prevent timezone estimate.
    $elements['estimates'] = array('#tree' => TRUE);
    $elements['estimates']['from'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Date component estimates'),
      '#default_value' => $settings['estimates']['from'],
      '#options' => $options,
      '#attributes' => array('class' => array('container-inline')),
      '#description' => t('Select the date component estimate attributes that you want to expose.'),
      '#weight' => -9,
    );

    if ($has_range) {
      $elements['granularity']['to'] = $elements['granularity']['from'];
      $elements['granularity']['to']['#title'] = t('Date components (to date)');
      $elements['granularity']['to']['#default_value'] = $settings['granularity']['to'];
      $elements['granularity']['to']['#weight'] = -8;
      $elements['granularity']['from']['#title'] = t('Date components (from date)');

      $elements['estimates']['to'] = $elements['estimates']['from'];
      $elements['estimates']['to']['#title'] = t('Date component estimates (to date)');
      $elements['estimates']['to']['#default_value'] = $settings['estimates']['to'];
      $elements['estimates']['to']['#weight'] = -7;
      $elements['estimates']['from']['#title'] = t('Date component estimates (from date)');
    }

    $tz_options = partial_date_timezone_handling_options();
    $elements['tz_handling'] = array(
      '#type' => 'select',
      '#title' => t('Time zone handling'),
      '#default_value' => $settings['tz_handling'],
      '#options' => $tz_options,
      '#required' => TRUE,
      '#weight' => -6,
      '#description' => t('Select the timezone handling method for this field. Currently, this is only used to calculate the timestamp that is store in the database. This determines the sorting order when using views integration. Only %none and %date handling options will render the timezone selector to users.',
          array('%none' => $tz_options['none'], '%date' => $tz_options['date'])),
    );
    $incremtOptions = array_combine(array(1, 2, 5, 10, 15, 30), array(1, 2, 5, 10, 15, 30));
    $elements['increments'] = array();
    $elements['increments']['minute'] = array(
      '#type' => 'select',
      '#title' => t('Minute increments'),
      '#default_value' => empty($settings['increments']['minute']) ? 1 : $settings['increments']['minute'],
      '#options' => $incremtOptions,
      '#required' => TRUE,
      '#weight' => -7,
    );
    $elements['increments']['second'] = array(
      '#type' => 'select',
      '#title' => t('Second increments'),
      '#default_value' => empty($settings['increments']['second']) ? 1 : $settings['increments']['second'],
      '#options' => $incremtOptions,
      '#required' => TRUE,
      '#weight' => -7,
    );

    $elements['theme_overrides'] = array('#tree' => TRUE);
    $elements['theme_overrides']['txt_short'] = array(
      '#type' => 'checkbox',
      '#title' => t('Provide a textfield for collection of a short description of the date'),
      '#default_value' => $settings['theme_overrides']['txt_short'],
      '#weight' => -5,
    );
    $elements['theme_overrides']['txt_long'] = array(
      '#type' => 'checkbox',
      '#title' => t('Provide a textfield for collection of a long description of the date'),
      '#default_value' => $settings['theme_overrides']['txt_long'],
      '#weight' => -4,
    );
    $elements['theme_overrides']['check_approximate'] = array(
      '#type' => 'checkbox',
      '#title' => t('Provide a checkbox to specify that the date is approximate'),
      '#default_value' => !empty($settings['theme_overrides']['check_approximate']),
      '#weight' => -3,
    );
    $elements['theme_overrides']['range_inline'] = array(
      '#type' => 'checkbox',
      '#title' => t('Theme range widgets to be rendered inline.'),
      '#default_value' => $has_range ? !empty($settings['theme_overrides']['range_inline']) : 0,
      '#weight' => 0,
      '#access' => $has_range,
    );

    $elements['hide_remove'] = array(
      '#type' => 'checkbox',
      '#title' => t('Hide the %remove checkbox', array('%remove' => t('Remove date', array(), array('context' => 'datetime')))),
      '#default_value' => !empty($settings['hide_remove']),
    );

    $elements['help_txt'] = array(
      '#tree' => TRUE,
      '#type' => 'fieldset',
      '#title' => t('Inline help'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('This provides additional help per component, or a way to override the default description text.'),
    );

    // Hide all bar current language.
    $current_langcode = \Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED;
    if (!isset($settings['help_txt'])) {
      $settings['help_txt'] = array();
    }
    foreach ($settings['help_txt'] as $langcode => $values) {
      if ($current_langcode == $langcode) {
        continue;
      }
      foreach ($values as $index => $value) {
        $settings['help_txt'][$langcode][$index] = array(
          '#type' => 'value',
          '#value' => $value,
        );
      }
    }

    $help_txt = $this->_partial_date_widget_help_text($current_langcode);
    $elements['help_txt'][$current_langcode]['components'] = array(
      '#type' => 'textarea',
      '#title' => t('Date components'),
      '#default_value' => $help_txt['components'],
      '#rows' => 3,
      '#description' => t('Instructions to present under the date or date range components. No help shown by default.'),
    );
    $elements['help_txt'][$current_langcode]['check_approximate'] = array(
      '#type' => 'textarea',
      '#title' => t('Date approximate checkbox'),
      '#default_value' => $help_txt['check_approximate'],
      '#rows' => 3,
      '#description' => t('Instructions to present under the approximate checkbox if used. No help shown by default.'),
    );

    $elements['help_txt'][$current_langcode]['txt_short'] = array(
      '#type' => 'textarea',
      '#title' => t('Short date description'),
      '#default_value' => $help_txt['txt_short'],
      '#rows' => 3,
      '#description' => t('Instructions to present under the short date description if used. Default is %default', array('%default' => t('Short date description'))),
    );
    $elements['help_txt'][$current_langcode]['txt_long'] = array(
      '#type' => 'textarea',
      '#title' => t('Long date description'),
      '#default_value' => $help_txt['txt_long'],
      '#rows' => 3,
      '#description' => t('Instructions to present under the long date description if used. Default is %default', array('%default' => t('Longer description of date'))),
    );
    $elements['help_txt'][$current_langcode]['_remove'] = array(
      '#type' => 'textarea',
      '#title' => t('Remove checkbox'),
      '#default_value' => $help_txt['_remove'],
      '#rows' => 3,
      '#description' => t('Instructions to present under the remove checkbox if shown. No help shown by default.'),
    );
    return $elements;
  }
  
  public function settingsSummary() {
    //TODO make your own summary!
    return parent::settingsSummary();
  }
   /**
   * {@inheritdoc}
   * (Drupal 7): hook_field_widget_error() => (Drupal 8): PartialDateWidget::errorElement
   */
  public function errorElement(array $element, \Symfony\Component\Validator\ConstraintViolationInterface $error, array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    switch ($error['error']) {
      case 'partial_date_incomplete_from':
      case 'partial_date_incomplete_to':
        $base_key = strpos($error['error'], 'from') ? 'from' : 'to';
        if (isset($error['partial_date_component']) && isset($element[$base_key][$error['partial_date_component']])) {
          form_error($element[$base_key][$error['partial_date_component']], $error['message']);
        }
        else {
          form_error($element[$base_key], $error['message']);
        }
        break;

      case 'partial_date_incomplete_txt_short':
      case 'partial_date_incomplete_txt_long':
        $base_key = strpos($error['error'], 'from') ? 'from' : 'to';
        form_error($element['year_to'], $error['message']);
        break;

      default:
        form_error($element['from'], $error['message']);
        break;
    }
  }
 
/**
 *  Helper functions from admin.inc
 */
  
function _partial_date_widget_help_text($langcode = NULL) {
  if (!isset($langcode)) {
    $langcode = \Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED;
  }
  $settings = $this->getSettings();
  $help_txt = array();
  if (empty($settings['help_txt'][$langcode])) {
    $help_txt = array();
  }
  else {
    $help_txt += $settings['help_txt'][$langcode];
  }
  $help_txt += array(
    'components' => '',
    'check_approximate' => '',
    'txt_short' => t('Short description of date'),
    'txt_long' => t('Longer description of date'),
    '_remove' => '',
  );

  return $help_txt;
}


  
 
}
