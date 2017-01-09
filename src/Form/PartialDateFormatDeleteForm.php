<?php

namespace Drupal\partial_date\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A form confirmation for deleting a Partial date format type
 *
 * @author CosminFr
 */
class PartialDateFormatDeleteForm extends EntityConfirmFormBase {
  //put your code here
  
  //this goes into title (less visible)
  public function getQuestion() {
    return $this->t('Delete partial date format %name ?',
        array('%name' => $this->entity->label() ) );
  }
  
  //this goes into the form (more visible)
  public function getDescription() {
    return $this->t('Are you sure you want to delete %name format?',
        array('%name' => $this->entity->label() ) );
  }

  public function getCancelUrl() {
    return new \Drupal\Core\Url('entity.partial_date_format.list');
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    drupal_set_message($this->t('Partial date format %label has been deleted.',
        array('%label' => $this->entity->label())));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }
}
