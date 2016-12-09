<?php

namespace Drupal\Tests\partial_date\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\config;

/**
 * Description of PartialDateCofigTest
 *
 * @group partial_date
 */
class PartialDateConfigTest extends UnitTestCase {
  //put your code here
  
  /**
   * @var \Drupal\config Access drupal configuration system
   */
  private $config;
  
  protected function setUp() {
    parent::setUp();
    $this->config = \Drupal::config('partial_date.settings');
  }

  /*
   * Test expected default configuration.
   */
  public function testDefaultConfig() {
    $this->assertEquals('estimate_label', $this->config->get('short.display.year'), 'check short.display.year');
    $this->assertEquals(1, NULL);
  }

}
