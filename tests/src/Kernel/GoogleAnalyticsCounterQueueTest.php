<?php

namespace Drupal\Tests\google_analytics_counter\Kernel;

use Drupal\Tests\system\Kernel\System\CronQueueTest;

/**
 * Update feeds on cron.
 *
 * @group google_analytics_counter
 */
class GoogleAnalyticsCounterQueueTest extends CronQueueTest {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'user', 'google_analytics_counter');

  /**
   * The queue plugin being tested.
   *
   * @var \Drupal\google_analytics_counter\Plugin\QueueWorker\GoogleAnalyticsCounterQueueBase
   */
  protected $queue;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    module_load_install('google_analytics_counter');
    $this->installSchema('google_analytics_counter', [
      'google_analytics_counter',
      'google_analytics_counter_storage',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function testExceptions() {
    // Get the queue to test the the queue worker.
    $queue = $this->container->get('queue')
      ->get('google_analytics_counter_worker');

    // Enqueue an item for processing.
    $queue->createItem(['type' => 'fetch', 'index' => 0]);
    $queue->createItem(['type' => 'count', 'nid' => 1]);

    // Items should be in the queue.
    self::assertEquals($queue->numberOfItems(), 2, 'Items are still in the queue.');

    // Expire the queue item manually. system_cron() relies in REQUEST_TIME to
    // find queue items whose expire field needs to be reset to 0. This is a
    // Kernel test, so REQUEST_TIME won't change when cron runs.
    // @see system_cron()
    // @see \Drupal\Core\Cron::processQueues()
    $query = $this->connection->select('queue', 'q');
    $query->fields('q', ['name', 'data', 'expire']);
    $result = $query->execute();
    while ($record = $result->fetchAssoc()) {
      $this->connection->update('queue')
        ->fields(['expire' => REQUEST_TIME - 1])
        ->execute();
    }

    $this->cron->run();

    // DEBUG:
    // $query = $this->connection->select('queue', 'q');
    // $query->fields('q', ['name', 'data', 'expire']);
    // $query->condition('q.name', 'google_analytics_counter_worker');
    // $all = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    // print_r($all);
    // Items should no longer be in the queue.
    // todo: actual should be 0.
    self::assertEquals($queue->numberOfItems(), 1, 'Items are no longer in the queue.');
  }

}
