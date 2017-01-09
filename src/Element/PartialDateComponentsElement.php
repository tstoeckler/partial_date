<?php

namespace Drupal\partial_date\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\partial_date\DateTools;

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
    ];
  }
  
  /**
   * Process callback.
   */
  public static function process(&$element, FormStateInterface $form_state, &$complete_form) {
    $options = isset($element['#options']) ? $element['#options'] : partial_date_components(array('timezone'));
    $showTime = isset($element['#show_time']) ? $element['#show_time'] : TRUE; 
    $timeStates = isset($element['#time_states']) ? $element['#time_states'] : FALSE; 
    if (!$showTime) {
      unset($options['hour'], $options['minute'], $options['second']);
    }
    foreach ($options as $key => $label) {
      $element[$key] = array(
        '#type' => 'checkbox',
        '#title' => $label,
        '#value' => isset($element['#value'][$key]) ? $element['#value'][$key] : 0,
      );
      if ($timeStates && _partial_date_component_type($key) == 'time') {
        $element[$key]['#states'] = $timeStates;
      }
    }
    return $element;
  }
 
  /**
   * #element_validate callback.
   * {@inheritdoc}
   */
  public static function validate(&$element, FormStateInterface $form_state, &$complete_form) {
    
//    foreach ($element['data'] as $key => $checkbox) {
//      $element['#value'][$key] = $checkbox['#value'];
//    }
  }
  
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    $result = array();
    if ($input === FALSE) {
      $element += array('#default_value' => array());
      foreach ($element['#default_value'] as $key => $default) {
        $result[$key] = $default;
      }
    } elseif (is_array($input)) {
      foreach ($input as $key => $value) {
        if (isset($value) && $value != 0) {
          $result[$key] = $value;
        }
      }
    } elseif (isset($input)) {
      $result[$input] = $input;
    }
    return $result;
  }
  
}
