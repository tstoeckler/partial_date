<?php

namespace Drupal\partial_date\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the FormatType config entity.
 * 
 * @ConfigEntityType(
 *   id = "partial_date_format",
 *   label = @Translation("Partial date format"),
 *   handlers = {
 *     "list_builder" = "Drupal\partial_date\Controller\PartialDateFormatListBuilder",
 *     "form" = {
 *        "add" = "Drupal\partial_date\Form\PartialDateFormatEditForm",
 *        "edit" = "Drupal\partial_date\Form\PartialDateFormatEditForm",
 *        "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "format",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "collection" = "/admin/config/regional/partial-date-format",
 *     "add-form" = "/admin/config/regional/partial-date-format/add",
 *     "edit-form" = "/admin/config/regional/partial-date-format/manage/{partial_date_format}",
 *     "delete-form" = "/admin/config/regional/partial-date-format/manage/{partial_date_format}/delete",
 *   },
 * )
 *
 * @author CosminFr
 */
class PartialDateFormat extends ConfigEntityBase implements PartialDateFormatInterface {

  /**
   * @var string
   */
  public $id;
  
  /**
   * @var string
   */
  public $meridiem = 'a';
  
  /**
   * @var string
   * This controls how year designation is handled: 1BC = 1BCE = -1 and 1AD = 1CE = 1.
   */
  public $year_designation = 'ce';
  
  /**
   * @var array
   */
  public $display = array(
    'year' => 'estimate_label',
    'month' => 'estimate_label',
    'day' => 'estimate_label',
    'hour' => 'estimate_label',
    'minute' => 'estimate_label',
    'second' => 'none',
    'timezone' => 'none',
  );
  
  /**
   * @var array
   */
  public $components = array(
    'year' => array('format' => 'y-ce', 'empty' => '', 'weight' => 0), 
    'month' => array('format' => 'm', 'empty' => '', 'weight' => 1),
    'day' => array('format' => 'j', 'empty' => '', 'weight' => 2),
    'hour' => array('format' => 'H', 'empty' => '', 'weight' => 3),
    'minute' => array('format' => 'i', 'empty' => '', 'weight' => 4),
    'second' => array('format' => 's', 'empty' => '', 'weight' => 5),
    'timezone' => array('format' => 'T', 'empty' => '', 'weight' => 6),
    'approx' => array('value' => '', 'weight'=> -1),
    'c1' => array('value' => '', 'weight'=> 7),
    'c2' => array('value' => '', 'weight'=> 8),
    'c3' => array('value' => '', 'weight'=> 9),
  );

  /**
   * @var array
   * An array with specific separators.
   */
  public $separator = array(
    'date' => '/',
    'time' => ':',
    'datetime' => ' ',
    'range' => ' to ',
    'other' => ' ',
  );
  
}
