<?php

namespace Drupal\partial_date\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

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
      '#theme_wrappers' => array('form_element'),
    ];
  }
  
  /**
   * Process callback.
   */
  public static function process(&$element, FormStateInterface $form_state, &$complete_form) {
    $granularity = $element['#granularity'];
    $estimates = $element['#estimates'];
    $options = $element['#estimate_options'];
    $increments = empty($element['#increments']) ? array() : $element['#increments'];
    $increments += array(
      'second' => 1,
      'minute' => 1,
    );
    $element['#tree'] = TRUE;
    $blank_option = array('' => t('N/A'));
    foreach (partial_date_components() as $key => $label) {
      if (!empty($estimates[$key]) && !empty($options[$key])) {
        $estimate_label = t('@component estimate', array('@component' => $label));
        $element[$key . '_estimate'] = array(
          '#type' => 'select',
          '#title' => $estimate_label,
          '#description' => $estimate_label,
          '#title_display' => 'invisible',
          '#value' => empty($element['#value'][$key . '_estimate']) ? '' : $element['#value'][$key . '_estimate'],
          '#attributes' => $element['#attributes'],
          '#options' => $blank_option + $options[$key],
        );
      }
      if (!empty($granularity[$key])) {
        if ($key == 'year') {
          $element[$key] = array(
            '#type' => 'textfield',
            '#title' => $label,
            '#description' => $label,
            '#title_display' => 'invisible',
            '#value' => empty($element['#value'][$key]) ? '' : $element['#value'][$key],
            '#attributes' => $element['#attributes'],
            '#required' => TRUE,
          );
          $element[$key]['#attributes']['size'] = 5;
        }
        else {
          $inc = empty($increments[$key]) ? 1 : $increments[$key];
          $element[$key] = array(
            '#type' => 'select',
            '#title' => $label,
            '#description' => $label,
            '#title_display' => 'invisible',
            '#value' => isset($element['#value'][$key]) && strlen($element['#value'][$key]) ? $element['#value'][$key] : '',
            '#attributes' => $element['#attributes'],
            '#options' => partial_date_granularity_field_options($key, $blank_option, $inc),
          );
        }
      }
    }

    $css = $element['#component_styles'];
    foreach (\Drupal\Core\Render\Element::children($element) as $child) {
      if ($element[$child]['#type'] != 'value') {
        $element[$child]['#prefix'] = '<div class="partial-date-' . (str_replace('_', '-', $child)) . '" style="' . $css . '">';
        $element[$child]['#suffix'] = '</div>';
      }
    }
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

    $day = empty($element['#value']['day']) ? 1 : $element['#value']['day'];
    $month = empty($element['#value']['month']) ? 1 : $element['#value']['month'];
    $year = empty($element['#value']['year']) ? NULL : $element['#value']['year'];

    $months = partial_date_month_matrix($year);
    if (!isset($months[$month - 1])) {
      $form_state->setError($element, t('The specified month is invalid.'));
    }
    elseif ($day < 1 || $day > $months[$month - 1]) {
      $form_state->setError($element, t('The specified month is invalid.'));
    }

    if (!empty($element['#value']['hour'])) {
      if (!is_numeric($element['#value']['hour']) || $element['#value']['hour'] < 0 || $element['#value']['hour'] > 23) {
        $form_state->setError($element, t('The specified time is invalid. Hours must be a number between 0 and 23'));
      }
    }

    if (!empty($element['#value']['minute'])) {
      if (!is_numeric($element['#value']['minute']) || $element['#value']['minute'] < 0 || $element['#value']['minute'] > 59) {
        $form_state->setError($element, t('The specified time is invalid. Minutes must be a number between 0 and 59'));
      }
    }

    if (!empty($element['#value']['second'])) {
      if (!is_numeric($element['#value']['second']) || $element['#value']['second'] < 0 || $element['#value']['second'] > 59) {
        $form_state->setError($element, t('The specified time is invalid. Seconds must be a number between 0 and 59'));
      }
    }

//    // Testing what removing the additional elements does...
//    // Getting strange submission values.
//    foreach (\Drupal\Core\Render\Element::children($element) as $child) {
//      unset($element[$child]);
//    }
  }

}
