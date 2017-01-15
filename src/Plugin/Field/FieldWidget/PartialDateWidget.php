<?php 

/**
 * @file
 * Contains \Drupal\partial_date\Plugin\Field\FieldWidget\PartialDateWidget.
 */

namespace Drupal\partial_date\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Drupal\partial_date\DateTools;

/**
 * Provides an widget for Partial Date fields.
 * (Drupal 7): hook_field_widget_info() => (Drupal 8): "FieldWidget" annotation
 *
 * @FieldWidget(
 *   id = "partial_date_widget",
 *   label = @Translation("Partial date and time"),
 *   field_types = {"partial_date"},
 * )
 */
class PartialDateWidget extends WidgetBase {

  protected $allowRange;
  protected $allowTime;

  function __construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings){
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->allowRange = $this->getFieldSetting('has_range');
    $this->allowTime  = $this->getFieldSetting('has_time');
  }

  public function hasRange() {
    return $this->allowRange && $this->getSetting('has_range');
  }

  public function hasTime() {
    return $this->allowTime  && $this->getSetting('has_time');
  }

  /**
   * {@inheritdoc}
   * (Drupal 7): hook_field_widget_form() => (Drupal 8): PartialDateWidget::formElement
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $help_txt = $this->getWidgetHelpText();
    $field = $items[$delta];
    //transform field values to "element" values (as expected by widget)
    $value = $this->massageFieldValues($field->getValue());

    //DEBUG ONLY: var_dump($value);
    // General styles to nicely format the element inline without having to load
    // external style sheets.
    $config = \Drupal::config('partial_date.settings');
    $css = $config->get('partial_date_component_field_inline_styles');
    $css_txt = $config->get('partial_date_component_field_txt_inline_styles');

    // Correct the timezone based on the widget values.
    $tz_from = empty($value) || empty($value['components']) || empty($value['components']['timezone']) ? NULL : $value['components']['timezone'];
    $value['components']['timezone'] = partial_date_timezone_handling_correlation($tz_from, $this->settings['tz_handling']);

    if (!partial_date_timezone_option_is_selectable($this->settings['tz_handling'])) {
      unset($this->settings['components']['timezone']);
      unset($this->settings['components_to']['timezone']);
    }

    $increments = empty($this->settings['increments']) ? array() : $this->settings['increments'];

    $inline_range_style = $this->hasRange() && !empty($this->getSetting('range_inline'));

    $element['#theme_wrappers'][] = 'form_element';
    $element['components'] = array(
      '#type' => 'container',
      '#title' => t('Components'),
      '#title_display' => 'invisible',
    );
    if ($inline_range_style) {
      $element['components']['#attributes'] = array('class' => array('container-inline'));
    }

    $estimate_options = $field_definition->getSetting('estimates');
    $increments = empty($settings['increments']) ? array() : $settings['increments'];
    $element['from'] = array(
      '#type' => 'partial_datetime_element',
      '#title' => $this->hasRange() ? t('Start date') : t('Date'),
      '#title_display' => 'invisible',
      '#default_value' => $value['components']['from'],
      '#field_sufix' => '',
      '#granularity' => $this->settings['components'],
      '#minimum_components' => $this->getFieldSetting('minimum_components'),
      '#component_styles' => $css,
      '#increments' => $increments,
    );
    if ($this->hasRange()) {
      $sep = $help_txt['range_separator'];
      $element['components']['_separator'] = array(
        '#type' => 'markup',
        '#markup' => '<div class="partial-date-separator">&nbsp;' . $sep . '&nbsp;</div>',
      );
      $element['components']['to'] = array(
        '#type' => 'partial_datetime_element',
        '#title' => t('End date'),
        '#title_display' => 'invisible',
        '#default_value' => $value['components']['to'],
        '#field_sufix' => '_to',
        '#granularity' => $this->settings['components_to'],
        '#component_styles' => $css,
        '#increments' => $increments,
      );
    }

    $estimates = array_filter($this->settings['estimates']);
    if (!empty($estimates)) {
      $element['estimates'] = $this->buildEstimatesElement($estimates);
    }

    $element['#component_help'] = $help_txt['components']; //field_filter_xss($help_txt['components']);
//    //Don't see the point of "Approximation only" checkbox. Ignored for now (but could be added back later...
//    if (!empty($this->settings['theme_overrides']['check_approximate'])) {
//      $element['check_approximate'] = array(
//        '#type' => 'checkbox',
//        '#title' => t('Approximation only', array(), array('context' => 'datetime')),
//        '#default_value' => empty($value['check_approximate']) ? 0 : $value['check_approximate'],
//      );
//      if (!empty($help_txt['check_approximate'])) {
//        $element['check_approximate']['#description'] = $help_txt['check_approximate']; //field_filter_xss($help_txt['check_approximate']);
//      }
//    }

//    // Calculate these for any JScript states.
//    $parents = array();
//    if (!empty($element['#field_parents'])) {
//      $parents = $element['#field_parents'];
//    }
//    elseif (!empty($element['#parents'])) {
//      $parents = $element['#parents'];
//    }
//    // field_partial_dates[und][0][check_approximate]
//    $parents[] = $field->getName();// ['field_name'];
//    $parents[] = $current_langcode;


    $element['txt'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('container-inline')),
    );
    $txt_element = array(
      '#type' => 'textfield',
      '#title' => t('Text override'),
      '#title_display' => 'invisible',
      '#maxlength' => 255,
    );
    if (!empty($this->settings['txt_long'])) {
      $description = $help_txt['txt_long'];
      $element['txt']['long'] = $txt_element + array(
        '#id' => 'txt_long',
        '#placeholder' => $description,
        '#default_value' => $value['txt_long'],  //empty($value['txt_long']) ? '' :
        '#size' => 80,
      );
    }
    if (!empty($this->settings['txt_short'])) {
      $description = $help_txt['txt_short'];
      $element['txt']['short'] = $txt_element + array(
        '#id' => 'txt_short',
        '#placeholder' => $description,
        '#default_value' => $value['txt_short'],  //empty($value['txt_short']) ? '' :
        '#maxlength' => 100,
        '#size' => 40,
      );
    }
//    $element['_remove'] = array(
//      '#type' => 'checkbox',
//      '#title' => t('Remove date', array(), array('context' => 'datetime')),
//      '#default_value' => 0,
//      '#access' => empty($this->settings['hide_remove']),
//      '#prefix' => '<div class="partial-date-remove" ' . ($css_txt ? ' style="' . $css_txt . '"' : '') . '>',
//      '#suffix' => '</div>',
//    );
//    if (!empty($help_txt['_remove'])) {
//      $element['_remove']['#description'] = $help_txt['_remove']; //field_filter_xss($help_txt['_remove']);
//    }
    return $element;
  }

  /*
   * Builds estimate selectors with (prefix/sufix help texts)
   * If no estimates are usable, return FALSE
   */
  protected function buildEstimatesElement(array $estimates) {
    $config = \Drupal::config('partial_date.settings');
    $options = $config->get('estimates');
    $help_txt = $this->getWidgetHelpText();
    $has_content = FALSE;
    $element = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('container-inline')),
      '#attached' => array('library' => array('partial_date/estimates')),
    );
    $element['prefix'] = array('#plain_text' => $help_txt['estimates_prefix']);
    foreach ($estimates as $key => $value) {
      if (!empty($value) && !empty($options[$key])) {
        $has_content = TRUE;
        $estimate_label = t('@component estimate', array('@component' => componentLabel($key)));
        $blank_option = array('' => $estimate_label);
        $element[$key . '_estimate'] = array(
          '#type' => 'select',
          '#title' => $estimate_label,
          '#title_display' => 'invisible',
//          '#value' => empty($element['#value'][$key . '_estimate']) ? '' : $element['#value'][$key . '_estimate'],
//          '#attributes' => $element['#attributes'],
          '#options' => $blank_option + $this->prepareEstimateOptions($options[$key]),
          '#attributes' => array(
              'class' => array('estimate_selector'),
              'date_component' => $key,
          ),
        );
      }
    }
    $element['sufix'] = array('#plain_text' => $help_txt['estimates_sufix']);
    if (!$has_content) {
      $element = FALSE;
    }
    return $element;
  }

  protected function prepareEstimateOptions($rawList) {
    $estimateOptions = array();
//    foreach (explode("\n", $rawList) as $line) {
//    \Drupal::logger('partial_date')->debug('estimate options: ' . serialize($rawList));
    foreach ($rawList as $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }
      list($from, $to, $label) = explode('|', $line . '||');
      if (!strlen($from) && !strlen($to)) {
        continue;
      }
      $label = trim($label);
      if (empty($label)) {
        continue;
      }
      $estimateOptions[$from . '|' .$to] = $label;
    }
    return $estimateOptions;
  }

  public static function defaultSettings() {
    $components = array_fill_keys(partial_date_component_keys(), 1);
    return array(
      'txt_short' => 0,
      'txt_long' => 0,
      'has_time' => 1,
      'has_range' => 1,
      'year_estimates_values' => '',
      'tz_handling' => 'none',
      'components' => $components,
      'components_to' => $components,
      'estimates' => array(
        'year' => 1,
        'month' => 1,
      ),
      'range_inline' => TRUE,
      'increments' => array(
        'second' => 1,
        'minute' => 1,
      ),
      'help_txt' => array(),
    ) + parent::defaultSettings();
  }
  
  /**
   * {@inheritdoc}
   * (Drupal 7): hook_field_widget_settings_form() => (Drupal 8): PartialDateWidget::settingsForm
   */
  public function settingsForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    //debug_only:     var_dump($this->settings);
    $elements = array();
    $elements['txt_long'] = array(
      '#type' => 'checkbox',
      '#id' => 'txt_long',
      '#title' => t('Provide a textfield for collection of a long description of the date'),
      '#default_value' => $this->settings['txt_long'],
    );
    $elements['txt_short'] = array(
      '#type' => 'checkbox',
      '#id' => 'txt_short',
      '#title' => t('Provide a textfield for collection of a short description of the date'),
      '#default_value' => $this->settings['txt_short'],
    );
    $elements['has_time'] = array(
      '#type' => 'checkbox',
      '#id' => 'has_time',
      '#title' => t('Show time components'),
      '#default_value' => $this->hasTime(),
      '#description' => t('Clear if not interested in holding time. Check to make time controls available.'),
      '#disabled' => !$this->allowTime,
    );
    //ensure that if field does not allow time specification, the option is not available!
    if (!$this->allowTime) {
      $elements['has_time']['#type'] = 'value';
      $elements['has_time']['#value'] = 0;
      $this->setSetting('has_time', 0);
    }
    $elements['has_range'] = array(
      '#type' => 'checkbox',
      '#id' => 'has_range',
      '#title' => t('Allow range specification'),
      '#default_value' => !empty($this->settings['has_range']),
      '#description' => t('Clear if not holding end values. Check to explicitely show range ending valeus.'),
    );
    //ensure that if field does not allow range detail, the option is cleared and hidden!
    if (!$this->allowRange) {
      $elements['has_range']['#type'] = 'value';
      $elements['has_range']['#value'] = 0;
      $this->setSetting('has_range', 0);
    }
    //Java Script markers to dynamically hide form elements based on the above checkboxes.
    $statesVisible_HasTime = array(
      'visible' => array(
        ':input[id="has_time"]' => array('checked' => TRUE),
      ),
    );
    $statesVisible_HasRange = array(
      'visible' => array(
        ':input[id="has_range"]' => array('checked' => TRUE),
      ),
    );
    $elements['components'] = array(
      '#type' => 'partial_date_components_element',
      '#title' => t('Date components'),
      '#default_value' => $this->settings['components'],
      '#show_time' => $this->allowTime,
      '#description' => t('Select the date attributes to collect and store.'),
      '#time_states' => $statesVisible_HasTime,
    );
    if ($this->allowRange) {
      $elements['components_to'] = $elements['components'];
      $elements['components_to']['#title'] = t('Date components (to date)');
      $elements['components_to']['#default_value'] = $this->settings['components_to'];
      $elements['components_to']['#states'] = $statesVisible_HasRange;
      $elements['components']['#title'] = t('Date components (from date)');

      $elements['estimates'] = array(
        '#type' => 'partial_date_components_element',
        '#title' => t('Show estimates'),
        '#default_value' => $this->settings['estimates'],
        '#description' => t('Select the date component estimate attributes that you want to expose.'),
        '#show_time' => $this->allowTime,
        '#time_states' => $statesVisible_HasTime,
        '#states' => $statesVisible_HasRange,
      );
      $elements['range_inline'] = array(
        '#type' => 'checkbox',
        '#title' => t('Show range end componets on the same line?'),
        '#default_value' => $this->getSetting('range_inline'),
        '#states' => $statesVisible_HasRange,
      );
    }
    if ($this->allowTime) {
      $tz_options = partial_date_timezone_handling_options();
      $elements['tz_handling'] = array(
        '#type' => 'select',
        '#title' => t('Time zone handling'),
        '#default_value' => $this->settings['tz_handling'],
        '#options' => $tz_options,
        '#required' => TRUE,
        '#description' => t('Currently, this is only informative; not used in any calculations. <br>')
            . t('Only %date handling option will render the timezone selector to users.', array('%date' => $tz_options['date'])),
        '#states' => $statesVisible_HasTime,
      );
      $incremtOptions = array_combine(array(1, 2, 5, 10, 15), array(1, 2, 5, 10, 15));
      $elements['increments'] = array();
      $elements['increments']['minute'] = array(
        '#type' => 'select',
        '#title' => t('Minute increments'),
        '#default_value' => empty($this->settings['increments']['minute']) ? 1 : $this->settings['increments']['minute'],
        '#options' => $incremtOptions,
        '#required' => TRUE,
        '#states' => $statesVisible_HasTime,
      );
      $elements['increments']['second'] = array(
        '#type' => 'select',
        '#title' => t('Second increments'),
        '#default_value' => empty($this->settings['increments']['second']) ? 1 : $this->settings['increments']['second'],
        '#options' => $incremtOptions,
        '#required' => TRUE,
        '#states' => $statesVisible_HasTime,
      );
    }
//    $elements['hide_remove'] = array(
//      '#type' => 'checkbox',
//      '#title' => t('Hide the %remove checkbox', array('%remove' => t('Remove date', array(), array('context' => 'datetime')))),
//      '#default_value' => !empty($this->settings['hide_remove']),
//    );
    $elements['help_txt'] = $this->buildHelpTxtElement();
    return $elements;
  }

  protected function buildHelpTxtElement() {
    $element = array(
      '#tree' => TRUE,
      '#type' => 'fieldset',
      '#title' => t('Inline help'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('This provides additional help per component, or a way to override the default description text.'),
    );
    //Let's make these texts language dependent.

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $help_txt = $this->getWidgetHelpText($langcode);
    $element[$langcode]['components'] = array(
      '#type' => 'textarea',
      '#title' => t('Date components'),
      '#default_value' => $help_txt['components'],
      '#rows' => 3,
      '#description' => t('Instructions to present under the date or date range components. No help shown by default.'),
    );
    $element[$langcode]['txt_short'] = array(
      '#type' => 'textfield',
      '#title' => t('Short date description'),
      '#default_value' => $help_txt['txt_short'],
      '#description' => t('Instructions to present for short date description (if used). Default is %default', array('%default' => t('Short date description'))),
      '#states' => array(
        'visible' => array(
          ':input[id="txt_short"]' => array('checked' => TRUE),
        ),
      ),
    );
    $element[$langcode]['txt_long'] = array(
      '#type' => 'textfield',
      '#title' => t('Long date description'),
      '#default_value' => $help_txt['txt_long'],
      '#description' => t('Instructions to present for long date description (if used). Default is %default', array('%default' => t('Longer description of date'))),
      '#states' => array(
        'visible' => array(
          ':input[id="txt_long"]' => array('checked' => TRUE),
        ),
      ),
    );
    $statesVisible_HasRange = array(
      'visible' => array(
        ':input[id="has_range"]' => array('checked' => TRUE),
      ),
    );
    $element[$langcode]['range_separator'] = array(
      '#type' => 'textfield',
      '#title' => t('Range separator'),
      '#default_value' => $help_txt['range_separator'],
      '#description' => t('Choose a short text between start and end components.'),
      '#states' => $statesVisible_HasRange,
    );
    $element[$langcode]['estimates_prefix'] = array(
      '#type' => 'textfield',
      '#title' => t('Estimates prefix'),
      '#default_value' => $help_txt['estimates_prefix'],
      '#description' => t('Choose a short text to show before estimate selectors.'),
      '#states' => $statesVisible_HasRange,
    );
    $element[$langcode]['estimates_sufix'] = array(
      '#type' => 'textfield',
      '#title' => t('Estimates sufix'),
      '#default_value' => $help_txt['estimates_sufix'],
      '#description' => t('Choose a short text to show after estimate selectors.'),
      '#states' => $statesVisible_HasRange,
    );
//    $element[$langcode]['_remove'] = array(
//      '#type' => 'textarea',
//      '#title' => t('Remove checkbox'),
//      '#default_value' => $help_txt['_remove'],
//      '#rows' => 3,
//      '#description' => t('Instructions to present under the remove checkbox if shown. No help shown by default.'),
//    );
    return $element;
  }
  
  public function settingsSummary() {
    $summary = array();
//    $fieldSettings = $this->getFieldSettings();
    $has_range = $this->hasRange();
    $has_time  = $this->hasTime();

    if ($has_time) {
      $timezone = isset($this->settings['tz_handling']) ? $this->settings['tz_handling'] : 'none';
      if ($timezone == 'none') {
        $summary[] = t('No timezone translations');
      } else {
        $tz_options = partial_date_timezone_handling_options();
        $summary[] = t('Timezone handling: ') . $tz_options[$timezone];
      }
    } elseif ($this->allowTime) {
      $summary[] = t('Date only');
    }

    $components = partial_date_components();
    if (!$has_time) {
      remove_time_components($components);
      //unset($components['hour'], $components['minute'], $components['second'], $components['timezone']);
    }
    $from_components = array_filter($this->settings['components']);
    if (!empty($from_components)) {
      $txt = t('Available components: ');
      foreach ($components as $key => $label) {
        if (!empty($from_components[$key])) {
          $txt .= $label . ', ';
        }
      }
      $summary[] = $txt;
    }
    $range_components = array_filter($this->getSetting('components_to'));
    if ($has_range && !empty($range_components)) {
      $txt = t('End of range components: ');
      foreach ($components as $key => $label) {
        if (!empty($range_components[$key])) {
          $txt .= $label . ', ';
        }
      }
      $summary[] = $txt;
    }
    $estimates = array_filter($this->getSetting('estimates'));
    if ($has_range && !empty($estimates)) {
      $txt = t('Use estimates for: ');
      foreach ($components as $key => $label) {
        if (!empty($estimates[$key])) {
          $txt .= $label . ', ';
        }
      }
      $summary[] = $txt;
    }
    if (!empty($this->settings['txt_short']) ||
        !empty($this->settings['txt_long'])) {
      $summary[] = t('Allow text override');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   * Massages the form values into the format expected for field values.
   * Copied from _partial_date_field_presave($entity_type, $entity, $field, $instance, $langcode, &$items)
   *
   * @param array $values
   *   The submitted form values produced by the widget.
   *   - If the widget does not manage multiple values itself, the array holds
   *     the values generated by the multiple copies of the $element generated
   *     by the formElement() method, keyed by delta.
   *   - If the widget manages multiple values, the array holds the values
   *     of the form element generated by the formElement() method.
   * @param array $form
   *   The form structure where field elements are attached to. This might be a
   *   full form structure, or a sub-element of a larger form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   An array of field values, keyed by delta.
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    //prepare field components from form element
    $field = array();
    foreach ($values as $delta => $value) {
      $value += array(
        'txt' => array(),
        'components' => array('from' => '', 'to' => ''),
      );
      $field[$delta] = array();
      if (!empty($value['txt'])) {
        $field[$delta]['txt_short'] = $value['txt']['short'] ?: NULL;
        $field[$delta]['txt_long']  = $value['txt']['long'] ?: NULL;
      }
      foreach (partial_date_components() as $key => $label) {
        if (!empty($value['components']['from'][$key])) {
          $field[$delta][$key] =  $value['components']['from'][$key];
        }
        if (!empty($value['components']['to'][$key])) {
          $field[$delta][$key.'_to'] = $value['components']['to'][$key];
        }
      }
    }
    return $field;
  }

  /**
   * Reverse function of above massageFormaValues
   * Turn field properties into expected element values.
   *
   * @param array $field
   *   Array with field properties.
   *
   * @return array
   *   An array with expected element values.
   */
  public function massageFieldValues(array $field) {
    //prepare field components from form element
    $value = array();
    $value['txt_short'] = isset($field['txt_short']) ? $field['txt_short'] : NULL;
    $value['txt_long']  = isset($field['txt_long'])  ? $field['txt_long']  : NULL;
    foreach (partial_date_component_keys() as $key) {
      $keyTo = $key . '_to';
      $value['components']['from'][$key] = isset($field[$key]) ? $field[$key] : NULL;
      $value['components']['to'][$key] = isset($field[$keyTo]) ? $field[$keyTo] : NULL;
    }
    return $value;
  }

   /**
   * {@inheritdoc}
   * (Drupal 7): hook_field_widget_error() => (Drupal 8): PartialDateWidget::errorElement
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    \Drupal::logger('partial_date')->error('PartialDateWidget.errorElement - title=' . $element['#title'] . '\n error: ' . serialize($error));
    switch ($error->getCode()) {
      case 'partial_date_incomplete_from':
      case 'partial_date_incomplete_to':
        $base_key = strpos($error->getCode(), 'from') ? 'from' : 'to';
        if (isset($error['partial_date_component']) && isset($element[$base_key][$error['partial_date_component']])) {
          return $element[$base_key][$error['partial_date_component']];
        } else {
          return $element[$base_key];
        }
      default:
        return $element;
    }
  }
 
/**
 *  Helper functions from admin.inc
 */
  
function getWidgetHelpText($langcode = NULL) {
  if (!isset($langcode)) {
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
  }
  $help_all = $this->getSetting('help_txt');
  $help_txt = array();
  if (is_array($help_all) && !empty($help_all[$langcode])) {
    $help_txt += $help_all[$langcode];
  }
  //Add some defaults (if there is nothing stored yet) to avoid "index not found" errors
  $help_txt += array(
    'components' => '',
//    'check_approximate' => '',
    'txt_short' => t('Short description of date'),
    'txt_long' => t('Longer description of date'),
    'estimates_prefix' => t('Short description of date'),
    'txt_short' => t('Short description of date'),
    'estimates_prefix' => t('... or choose from pre-defined estimates: '),
    'estimates_sufix' => '',
    'range_separator' => t(' to '),
//    '_remove' => '',
  );

  return $help_txt;
}

function _partial_date_inline_float_css($component = TRUE) {
  $language = \Drupal::languageManager()->getCurrentLanguage();
  $margin = $component ? '0.5' : '1';
  if ($language->getDirection() == $language::DIRECTION_RTL) {
    return "float: right; margin-left: {$margin}em;";
  } else {
    return "float: left; margin-right: {$margin}em;";
  }
}


  
 
}
