<?php 

/**
 * @file
 * Contains \Drupal\partial_date\Plugin\Field\FieldWidget\PartialDateWidget.
 */

namespace Drupal\partial_date\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Drupal\partial_date\DateTools;

/**
 * Provides an widget for Partial Date fields.
 * (Drupal 7): hook_field_widget_info() => (Drupal 8): "FieldWidget" annotation
 *
 * @FieldWidget(
 *   id = "partial_date_only_widget",
 *   label = @Translation("Partial date only"),
 *   field_types = {
 *     "partial_date",
 *   },
 * )
 */
class PartialDateNoTimeWidget extends PartialDateWidget {

  /**
   * {@inheritdoc}
   */
  protected function initSettings(){
    $this->settings = $this->getSettings();
    $this->allowRange = $this->getFieldSetting('has_range'); //strpos($type, 'range');
    $this->allowTime  = FALSE; 
  }

  public static function defaultSettings() {
    $components = array_fill_keys(partial_date_component_keys(), 1);
    remove_time_components($components);
    return array(
      'has_time' => 0,
      'components' => $components,
      'components_to' => $components,
    ) + parent::defaultSettings();
  }

}
