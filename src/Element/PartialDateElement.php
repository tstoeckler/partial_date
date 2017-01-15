<?php

namespace Drupal\partial_date\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\partial_date\DateTools;

/**
 * Provides a form element for partial date widget.
 *
 * @FormElement("partial_datetime_element")
 * @author CosminFr
 */
class PartialDateElement extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#process' => [[get_class($this), 'process']],
      '#element_validate' => [[get_class($this), 'validate']], //array('partial_date_element_validate'),
//      '#theme' => 'partial_date_element',
      '#theme_wrappers' => array(
        'container' => array(
          '#attributes' => array(
            'class' => array('partial-date-element', 'clearfix'),
          ),
        ),
        'form_element',
      ),
    ];
  }
  
  /**
   * Process callback.
   */
  public static function process(&$element, FormStateInterface $form_state, &$complete_form) {
    //add missing array keys to avoid isset(...)
    $element += array(
        '#default_value' => FALSE,
        '#granularity' => FALSE,
        '#minimum_components' => FALSE,
//        '#estimates' => FALSE,
//        '#estimate_options' => FALSE,
//        '#field_sufix' => '',
        '#increments' => array(),
      );
    $granularity = $element['#granularity'];
//    $estimates = $element['#estimates'];
//    $options = $element['#estimate_options'];
    $fieldSufix = $element['#field_sufix'];
    $increments = $element['#increments'];
    $increments += array(
      'second' => 1,
      'minute' => 1,
    );
    $element['#tree'] = TRUE;
    foreach (partial_date_components() as $key => $label) {
      if (!empty($granularity[$key])) {
        $fieldName = $key . $fieldSufix;
          $element[$key] = array(
            '#title' => $label,
            '#placeholder' => $label,
            '#title_display' => 'invisible',
            '#fieldName' => $fieldName,
            '#value' => empty($element['#value'][$key]) ? '' : $element['#value'][$key],
            '#attributes' => array(
                'class' => array('partial_date_component'),
                'fieldName' => $fieldName,
            ),
          );
        if ($key == 'year') {
          $element[$key]['#type'] = 'textfield';
          $element[$key]['#attributes']['size'] = 5;
        } else {
          $inc = empty($increments[$key]) ? 1 : $increments[$key];
          $blank_option = array('' => $label);
          $element[$key]['#type'] = 'select';
          $element[$key]['#options'] = partial_date_granularity_field_options($key, $blank_option, $inc);
        }
      }
    }
//
//    $css = $element['#component_styles'];
//    foreach (\Drupal\Core\Render\Element::children($element) as $child) {
//      if ($element[$child]['#type'] != 'value') {
//        $element[$child]['#prefix'] = '<div class="partial-date-' . (str_replace('_', '-', $child)) . '" style="' . $css . '">';
//        $element[$child]['#suffix'] = '</div>';
//      }
//    }
    return $element;
  }
  
  /**
   * #element_validate callback.
   * {@inheritdoc}
   */
  public static function validate(&$element, FormStateInterface $form_state, &$complete_form) {
    if (!empty($element['#required']) && partial_date_field_is_empty($element['#value'], array('type' => $element['#type']))) {
      $form_state->setError($element, t('The %label field is required.', array('%label' => $element['#title'])));
    }
    $minComponents = is_array($element['#minimum_components']) ? $element['#minimum_components'] : array();

    foreach ($minComponents as $key => $value) {
      if (!empty($value) && empty($element['#value'][$key])) {
        $form_state->setError($element[$key], t('%field is required!', array('%field' => $element[$key]['#title'])));
      }
    }

    $day   = empty($element['#value']['day'])   ? 0 : $element['#value']['day'];
    $month = empty($element['#value']['month']) ? 0 : $element['#value']['month'];
    $year  = empty($element['#value']['year'])  ? 0 : $element['#value']['year'];

    $maxDay = 31;
    $months = DateTools::monthMatrix($year);
    if ($month > 0 && !isset($months[$month - 1])) {
      $maxDay = $months[$month - 1];
    }
    if ($month < 0 || $month > 12) {
      $form_state->setError($element, t('The specified month is invalid.'));
    }
    if ($day < 0 || $day > $maxDay) {
      $form_state->setError($element, t('The specified day is invalid.'));
    }

    $hour   = empty($element['#value']['hour'])   ? 0 : $element['#value']['hour'];
    $minute = empty($element['#value']['minute']) ? 0 : $element['#value']['minute'];
    $second = empty($element['#value']['second']) ? 0 : $element['#value']['second'];

    if (!is_numeric($hour) || $hour < 0 || $hour > 23) {
      $form_state->setError($element, t('The specified time is invalid. Hours must be a number between 0 and 23'));
    }

    if (!is_numeric($minute) || $minute < 0 || $minute > 59) {
      $form_state->setError($element, t('The specified time is invalid. Minutes must be a number between 0 and 59'));
    }

    if (!is_numeric($second) || $second < 0 || $second > 59) {
      $form_state->setError($element, t('The specified time is invalid. Seconds must be a number between 0 and 59'));
    }

  }

}
