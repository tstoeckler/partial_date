<?php

namespace Drupal\partial_date\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates a partial date constraint.
 */
class PartialDateConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\Core\Field\FieldItemInterface $value */
    if ($value->isEmpty()) {
      return;
    }

    $from = $value->from;

    // Get the file to execute validators.
    $field_storage_definition = $value->getFieldDefinition()->getFieldStorageDefinition();
    $minimum_components = $field_storage_definition->getSetting('minimum_components');
    foreach (array_keys(partial_date_components()) as $component) {
      $required =
        $minimum_components['from_granularity_' . $component]
        || (($component !== 'year') && $minimum_components['from_estimates_' . $component]);

      if ($required && empty($from[$component])) {
        $this->context->addViolation('@component is required', ['@component' => $component]);
      }
    }
    foreach (['txt_short', 'txt_long'] as $property) {
      if ($minimum_components[$property] && !$value->{$property}) {
        $this->context->addViolation('@property is required', ['@property' => $property]);
      }
    }
  }

}