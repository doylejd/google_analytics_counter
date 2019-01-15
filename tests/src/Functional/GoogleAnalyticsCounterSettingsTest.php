<?php

namespace Drupal\Tests\google_analytics_counter\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the google analytics counter settings form.
 *
 * @group google_analytics_counter
 */
class GoogleAnalyticsCounterSettingsTest extends BrowserTestBase {
  const ADMIN_SETTINGS_PATH = 'admin/config/system/google-analytics-counter';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['google_analytics_counter'];

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Verifies that the google analytics counter settings page works.
   *
   * @see MediaSourceTest
   */
  public function testForm() {
    $admin_user = $this->drupalCreateUser(array(
      'administer site configuration',
      'administer google analytics counter',
    ));
    $this->drupalLogin($admin_user);

    // Create item(s) in the queue.
    $queue_name = 'google_analytics_counter_worker';
    $queue = \Drupal::queue($queue_name);

    // Enqueue an item for processing.
    $queue->createItem([$this->randomMachineName() => $this->randomMachineName()]);

    $this->drupalGet(self::ADMIN_SETTINGS_PATH);
    $this->assertSession()->statusCodeEquals(200);

    // Assert Fields.
    $assert = $this->assertSession();
    $assert->fieldExists('cron_interval');
    $assert->fieldExists('chunk_to_fetch');
    $assert->fieldExists('api_dayquota');
    $assert->fieldExists('cache_length');
    $assert->fieldExists('queue_time');
    $assert->fieldExists('start_date');
    $assert->fieldExists('advanced_date_checkbox');
    $assert->fieldExists('fixed_start_date');
    $assert->fieldExists('fixed_end_date');

    $edit = [
      'cron_interval' => 0,
      'chunk_to_fetch' => 5000,
      'api_dayquota' => 10000,
      'cache_length' => 24,
    ];

    // Post form. Assert response.
    $this->submitForm($edit, t('Save configuration'));
    $assert->pageTextContains(t('The configuration options have been saved.'));
  }

}
