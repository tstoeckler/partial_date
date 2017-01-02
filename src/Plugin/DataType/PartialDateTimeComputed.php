<?php

namespace Drupal\partial_date\Plugin\DataType;

use Drupal\Core\TypedData\TypedData;

/**
 * Provides a computed partial date property class for partial date fields.
 */
class PartialDateTimeComputed extends TypedData {

  /**
   * An array of date information for the partial date.
   *
   * @var array
   */
  protected $partialDate = [];

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if (!$this->partialDate) {
      /** @var \Drupal\Core\Field\FieldItemInterface $item */
      $item = $this->getParent();
      $estimates = $item->getFieldDefinition()->getSetting('estimates');

      $data = $item->data;
      foreach (array_keys(partial_date_components()) as $property) {
        $this->partialDate[$property] = '';
        if (empty($data[$property . '_estimate_from_used'])) {
          $this->partialDate[$property] = $item->{$property};
        }

        $this->partialDate[$property . '_estimate'] = '';
        if ($property !== 'timezone' && !empty($data[$property . '_estimate'])) {
          $value = $data[$property . '_estimate'];

          $this->partialDate[$property . '_estimate'] = $value;
          $this->partialDate[$property . '_estimate_label'] = '';
          $this->partialDate[$property . '_estimate_value'] = NULL;

          if ($value) {
            if (!empty($estimates[$property][$value])) {
              $this->partialDate[$property . '_estimate_label'] = $estimates[$property][$value];
            }
            list($from, $to) = explode('|', $value);
            $range_setting = $this->definition->getSetting('range');
            $this->partialDate[$property . '_estimate_value'] = ($range_setting === 'from') ? $from : $to;
          }
        }
      }
    }
    return $this->partialDate;
  }

}
