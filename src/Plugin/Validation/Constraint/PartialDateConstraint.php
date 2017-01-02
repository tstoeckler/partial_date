<?php

namespace Drupal\partial_date\Plugin\Validation\Constraint;

use Drupal\Core\Validation\Annotation\Constraint;

/**
 * Provides a constraint for a valid partial date.
 *
 * @Constraint(
 *   id = "PartialDate",
 *   label = @Translation("Partial date", context = "Validation"),
 * )
 */
class PartialDateConstraint extends Constraint {

}
