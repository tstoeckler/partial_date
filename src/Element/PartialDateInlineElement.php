<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Drupal\partial_date\Element;

/**
 * Description of PartialDateInlineElement
 *
 * @FormElement("partial_datetime_inline_element")
 * @author CosminFr
 */
class PartialDateInlineElement extends PartialDateElement {
 
  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'partial_date_range_inline_element',
      '#theme_wrappers' => array('partial_date_inline_form_element'),
    ] + parent::getInfo();
  }
  
}
