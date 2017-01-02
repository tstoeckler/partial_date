<?php

namespace Drupal\partial_date\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates a partial to date constraint.
 */
class PartialToDateConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\Core\Field\FieldItemInterface $value */
    if ($value->isEmpty()) {
      return;
    }

    $to = $value->to;

    // Get the file to execute validators.
    $field_storage_definition = $value->getFieldDefinition()->getFieldStorageDefinition();
    $minimum_components = $field_storage_definition->getSetting('minimum_components');
    foreach (array_keys(partial_date_components()) as $component) {
      $required =
        $minimum_components['to_granularity_' . $component]
        || (($component !== 'year') && $minimum_components['to_estimates_' . $component]);

      if ($required && empty($to[$component])) {
        $this->context->addViolation('@component is required', ['@component' => $component]);
      }
    }
  }

}