<?php

namespace Drupal\partial_date\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a form element for partial date widget.
 *
 * @FormElement("partial_date_components_element")
 * @author CosminFr
 */
class PartialDateComponentsElement extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#process' => [[get_class($this), 'process']],
      '#element_validate' => [[get_class($this), 'validate']], //array('partial_date_element_validate'),
      '#theme' => 'partial_date_components_element',
      '#theme_wrappers' => array('form_element'),
      '#options' => partial_date_components(['timezone']),
      '#show_time' => TRUE,
      '#time_states' => FALSE,
    ];
  }
  
  /**
   * Process callback.
   */
  public static function process(&$element, FormStateInterface $form_state, &$complete_form) {
    if ($element['#show_time']) {
      unset($element['#options']['hour'], $element['#options']['minute'], $element['#options']['second']);
    }
    foreach ($element['#options'] as $key => $label) {
      $element[$key] = array(
        '#type' => 'checkbox',
        '#title' => $label,
        '#value' => isset($element['#value'][$key]) ? $element['#value'][$key] : 0,
      );
      if ($element['#time_states'] && _partial_date_component_type($key) == 'time') {
        $element[$key]['#states'] = $element['#time_states'];
      }
    }
    return $element;
  }

  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    $result = array();
    if ($input === FALSE) {
      $element += array('#default_value' => array());
      $result = $element['#default_value'];
    }
    elseif (is_array($input)) {
      foreach ($input as $key => $value) {
        if (isset($value) && $value != 0) {
          $result[$key] = $value;
        }
      }
    }
    elseif (isset($input)) {
      $result[$input] = $input;
    }
    return $result;
  }
  
}
