<?php

namespace Drupal\partial_date\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\partial_date\Entity\PartialDateFormat;

/**
 * Description of FormatTypeEditForm
 *
 * @author CosminFr
 */
class PartialDateFormatEditForm extends EntityForm {
  //put your code here
  
  /**
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query.
   */
  public function __construct(QueryFactory $entity_query) {
    $this->entityQuery = $entity_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query')
    );
  }

  
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $elements = parent::form($form, $form_state);
    $format   = $this->entity;

    $elements['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $format->label(),
      '#description' => $this->t("Label for the partial date format."),
      '#required' => TRUE,
    );
    $elements['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $format->id(),
      '#machine_name' => array(
        'exists' => array($this, 'exist'),
      ),
      '#disabled' => !$format->isNew(),
    );

    // Additional custom properties.
    $elements['meridiem'] = array(
      '#type' => 'radios',
      '#title' => t('Ante meridiem and Post meridiem format'),
      '#options' => $format->partial_date_meridiem_options(),
      '#default_value' => $format->meridiem ?: 'a',
    );
    $elements['year_designation'] = array(
      '#type' => 'radios',
      '#title' => t('Year designation format'),
      '#default_value' => $format->year_designation ?: 'bc',
      '#options' => $format->partial_date_year_designation_options(),
      '#required' => TRUE,
      '#description' => t('This controls how year designation is handled: 1BC = 1BCE = -1 and 1AD = 1CE = 1.'),
    );
    $components = partial_date_components();
    $elements['display']   = $this->buildDisplayElements($components, $format);
    $elements['separator'] = $this->buildSeparatorElements($format);

    $custom = array('c1' => t('Custom component 1'), 'c2' => t('Custom component 2'), 'c3' => t('Custom component 3'), 'approx' => t('Approximation text'));
    $elements['components'] = $this->buildComponentsTable($components + $custom, $format);

    return $elements;
  }

  private function buildDisplayElements($components, PartialDateFormat $format) {
    $elements = array(
      '#type' => 'fieldset',
      '#title' => t('Component display'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    foreach ($components as $key => $label) {
      $elements[$key] = array(
        '#type' => 'select',
        '#title' => t('Display source for %label', array('%label' => $label)),
        '#options' => $format->partial_date_estimate_handling_options(),
        '#default_value' => $format->display[$key],
        '#required' => TRUE,
      );
    }
    return $elements;
  }
  
  private function buildSeparatorElements(PartialDateFormat $format) {
    $elements = array(
//      '#type' => 'table',
//      '#header' => array(t('Component'), t('Separator'), ''),
      '#type' => 'fieldset',
      '#title' => t('Component separators'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
//      '#attributes' => array('class' => array('container-inline')),
    );
    $elements['date'] = array(
      '#type' => 'textfield',
      '#title' => t('Date separators'),
//      '#title_display' => 'invisible',
      '#maxlength' => 15,
      '#size' => 8,
      '#default_value' => $format->separator['date'] ?: '/',
      '#description' => t('This separator is used within date part. Empty value is allowed (ex. 20151231). Add spaces if you needed between the separator and the date values.'),
    );
    $elements['time'] = array(
      '#type' => 'textfield',
      '#title' => t('Time separators'),
//      '#title_display' => 'invisible',
      '#maxlength' => 15,
      '#size' => 8,
      '#default_value' => $format->separator['time'] ?: ':',
      '#description' => t('This separator is used within time component. Empty value is allowed. Add spaces if needed.'),
    );
    $elements['datetime'] = array(
      '#type' => 'textfield',
      '#title' => t('Date and time separators'),
//      '#title_display' => 'invisible',
      '#size' => 8,
      '#maxlength' => 15,
      '#default_value' => $format->separator['datetime'] ?: ' ',
      '#description' => t('This separator is used between date and time components. '),
      '#attributes' => array('class' => array('field--label-inline')),
    );
    $elements['other'] = array(
      '#type' => 'textfield',
      '#title' => t('Other separators'),
//      '#title_display' => 'invisible',
      '#size' => 8,
      '#maxlength' => 15,
      '#default_value' => $format->separator['other'] ?: ' ',
      '#description' => t('This separator may be used with year estimations. TODO add better description or deprecate.'),
      '#attributes' => array('class' => array('field--label-inline')),
    );
    $elements['range'] = array(
      '#type' => 'textfield',
      '#title' => t('Range separator'),
//      '#title_display' => 'invisible',
      '#size' => 8,
      '#maxlength' => 15,
      '#default_value' => $format->separator['range'] ?: ' to ',
      '#description' => t('This separator is used to seperate date components in the range element. This defaults to " to " if this field is empty. Add spaces if you need spaces between the separator and the date values.'),
      '#attributes' => array('class' => array('field--label-inline')),
    );
    return $elements;
  }

  private function buildComponentsTable($components, PartialDateFormat $format) {
    $table = array(
      '#type' => 'table',
//      '#title' => t('Component display'),
      '#header' => array(t('Component'), t('Weight'), t('Value format'), t('Value empty text') ),
      '#empty' => t('This should not be empty. Try re-installing Partial Date module.'),
      '#tableselect' => FALSE,
      '#tabledrag' => array(
        array(
          'action' => 'weight',
          'relationship' => 'sibling',
          'group' => 'partial-date-format-order-weight',
        )
      )
    );

    // Build the table rows and columns.
    foreach ($components as $key => $label) {
      $component = $format->components[$key];
      $table[$key]['#attributes']['class'][] = 'draggable';
      $table[$key]['#weight'] = $component['weight'];
      $table[$key]['label']['#plain_text'] = $label;
      $table[$key]['weight'] = array(
        '#type' => 'weight',
        '#title' => t('Weight for %label', array('%label' => $label)),
        '#title_display' => 'invisible',
        '#default_value' => $component['weight'],
        '#attributes' => array('class' => array('partial-date-format-order-weight')),
        '#required' => TRUE,
      );
      
      if (in_array($key, array('c1', 'c2', 'c3', 'approx'))) {
        $table[$key]['value'] = array(
          '#type' => 'textfield',
          '#title' => $label,
          '#title_display' => 'invisible',
          '#default_value' => $component['value'],
        );
        if ($key == 'approx') {
          $table[$key]['value']['#description'] = t('Only shows if the date is flagged as approximate.');
        }
      }
      else {
        $table[$key]['format'] = array(
          '#type' => 'radios',
          '#title' => t('Format for %label', array('%label' => $label)),
          '#title_display' => 'invisible',
          '#options' => $format->partial_date_component_format_options($key),
          '#default_value' => $component['format'],
          '#required' => TRUE,
        );

        $table[$key]['empty'] = array(
          '#type' => 'textfield',
          '#title' => t('Empty text for %label', array('%label' => $label)),
          '#title_display' => 'invisible',
          '#default_value' => $component['empty'],
          '#size' => 8,
        );
      }
    }
    
    return $table;
  }
  
  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $format = $this->entity;
    $status = $format->save();

    if ($status) {
      drupal_set_message($this->t('Saved the %label format.', array(
        '%label' => $format->label(),
      )));
    }
    else {
      drupal_set_message($this->t('The %label format was not saved.', array(
        '%label' => $format->label(),
      )));
    }

    $form_state->setRedirect('entity.partial_date_format.list');
  }

  public function exist($id) {
    $entity = $this->entityQuery->get('partial_date_format')
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }
  
}
