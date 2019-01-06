<?php

namespace Drupal\Tests\google_analytics_counter\Functional;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests webform dialog utility.
 *
 * @group google_analytics_counter
 *
 */
class GoogleAnalyticsCounterSettingsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'node'];


  /**
   * A node that is indexed by the search module.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
  }


  /**
   * Verifies that the google analytics counter settings page works.
   *
   * @see MediaSourceTest
   */
  public function testGoogleAnalyticsCounterSettingsForm() {
    $this->container->get('module_installer')->install(['google_analytics_counter']);
    $this->resetAll();

    $this->config('google_analytics_counter.settings')
      ->set('general_settings.gac_type_page', 1)
      ->save();

    $admin_user = $this->drupalCreateUser(array(
      'administer site configuration',
      'administer google analytics counter',
    ));
    $this->drupalLogin($admin_user);

  }

}
