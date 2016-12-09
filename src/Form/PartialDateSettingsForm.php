<?php

namespace Drupal\partial_date\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * PartialDateSettingsForm is a simple config form to manage common settings for Partial Date module/field type
 *
 * @author CosminFr
 */
class PartialDateSettingsForm extends ConfigFormBase {
  
  CONST SETTINGS = 'partial_date.settings';
  
  public function getFormId() {
    return 'partial_date_settings_form';
  }

  protected function getEditableConfigNames() {
    return [self::SETTINGS];
  }

  protected function estimateComponents(){
    $components = partial_date_components();
    unset($components['timezone']);
    return $components;
  }
  
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::SETTINGS);
    //Only show setting you actually want users to edit
    //TODO: these are just for demo, probably should not be modified by users
    $form['txt_inline_styles'] = array(
      '#type' => 'textfield',
      '#title' => 'Text inline styles',
      '#default_value' => $config->get('partial_date_component_field_txt_inline_styles'),
    );
    $form['inline_styles'] = array(
      '#type' => 'textfield',
      '#title' => 'Inline styles',
      '#default_value' => $config->get('partial_date_component_field_inline_styles'),
    );
    
    $form['estimates'] = array(
      '#type' => 'fieldset',
      '#title' => t('Base estimate values'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
    );
    $form['estimates']['info'] = array(
      '#markup' => t('These fields provide options for additional fields that can be used to represent corresponding date / time components. '
          . 'They define time periods where an event occured when exact details are unknown. <br>'
          . 'All of these fields have the format <i>"start|end|label"</i>, one per line, where start marks when this period started, '
          . 'end marks the end of the period and the label is shown to the user. <br>'
          . '<strong>Note:</strong> if used, the formatters will replace any corresponding date / time component with the options label value.'),
    );
    foreach ($this->estimateComponents() as $key => $label) {
      $lines = $config->get('estimates.'.$key);
      $form['estimates'][$key] = array(
        '#type' => 'textarea',
        '#title' => t('%label range options', array('%label' => $label), array('context' => 'datetime settings')),
        '#default_value' => implode("\n", $lines),
        '#description' => t('Provide relative approximations for %label component.', array('%label' => $label), array('context' => 'datetime settings')),
//        '#element_validate' => $this->partial_date_field_estimates_validate_parse_options(),
//        '#element_validate' => array(array($this, 'partial_date_field_estimates_validate_parse_options')),
        '#date_component' => $key,
      );
    }
    
    return parent::buildForm($form, $form_state);
  }
  
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::service('config.factory')->getEditable(self::SETTINGS);
    //Save any changes
    $config->set('partial_date_component_field_txt_inline_styles', $form_state->getValue('txt_inline_styles'));
    $config->set('partial_date_component_field_inline_styles', $form_state->getValue('inline_styles'));

    foreach ($this->estimateComponents() as $key => $label) {
      $lines = $form_state->getValue('estimates')[$key];
      $config->set('estimates.'.$key, explode('\n', $lines));
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state) {
    //validate "estimates" - previously in partial_date_field_estimates_validate_parse_options()
    $limits = array(
      'month' => 12,
      'day' => 31,
      'hour' => 23,
      'minute' => 59,
      'second' => 59,
    );
    foreach ($this->estimateComponents() as $key => $label) {
      $lines = $form_state->getValue('estimates')[$key];
      $element = $form['estimates'][$key];
      foreach (explode("\n", $lines) as $line) {
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
          $form_state->setError($element, t('The label for the keys %keys is required.', array('%keys' => $from . '|' . $to)));
        } 
        elseif (!is_numeric($from) || !is_numeric($to)) {
          $form_state->setError($element, t('The keys %from and %to must both be numeric.', array('%from' => $from, '%to' => $to)));
        } 
        elseif (isset($limits[$key])) {
          $limit = $limits[$key];
          // We need to preserve empty strings, so cast to temp variables.
          $_from = (int) $from;
          $_to = (int) $to;
          if ($_to > $limit || $_to < 0 || $_from > $limit || $_from < 0) {
            $form_state->setError($element, t('The keys %from and %to must be within the range 0 to %max.', array('%from' => $_from, '%to' => $_to, '%max' => $limit)));
          }
        }
      }
    }
    parent::validateForm($form, $form_state);
  }
  
}
