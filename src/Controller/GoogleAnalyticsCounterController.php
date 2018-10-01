<?php

namespace Drupal\google_analytics_counter\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GoogleAnalyticsCounterController.
 *
 * @package Drupal\google_analytics_counter\Controller
 */
class GoogleAnalyticsCounterController extends ControllerBase {

  /**
   * The google_analytics_counter.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Drupal\google_analytics_counter\GoogleAnalyticsCounterManager definition.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface
   */
  protected $manager;

  /**
   * Constructs a Dashboard object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter service.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface $manager
   *   Google Analytics Counter Manager object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, DateFormatter $date_formatter, GoogleAnalyticsCounterManagerInterface $manager) {
    $this->config = $config_factory->get('google_analytics_counter.settings');
    $this->state = $state;
    $this->dateFormatter = $date_formatter;
    $this->time = \Drupal::service('datetime.time');
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('date.formatter'),
      $container->get('google_analytics_counter.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function dashboard() {
    $build = [];
    $build['intro'] = [
      '#markup' => '<h4>' . $this->t('Information on this page is updated during cron runs.') . '</h4>',
    ];

    // The Google section.
    $build['google_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Information from Google Analytics API'),
      '#open' => TRUE,
    ];

    $t_args = $this->getStartDateEndDate();
    $t_args += ['%total_pageviews' => number_format($this->state->get('google_analytics_counter.total_pageviews'))];
    $build['google_info']['total_pageviews'] = [
      '#markup' => $this->t('%total_pageviews pageviews were recorded by Google Analytics for this view between %start_date - %end_date.', $t_args),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $t_args = $this->getStartDateEndDate();
    $t_args += [
      '%total_paths' => number_format($this->state->get('google_analytics_counter.total_paths')),
    ];
    $build['google_info']['total_paths'] = [
      '#markup' => $this->t('%total_paths paths were recorded by Google Analytics for this view between %start_date - %end_date.', $t_args),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    if ($this->state->get('google_analytics_counter.most_recent_query') == '') {
      $t_args = ['%most_recent_query' => 'No query has been run yet or Google is not running queries from your system. See the module\'s README.md or Google\'s documentation.'];
    }
    else {
      $t_args = ['%most_recent_query' => $this->state->get('google_analytics_counter.most_recent_query')];
    }

    // Google Query.
    $build['google_info']['google_query'] = [
      '#type' => 'details',
      '#title' => $this->t('Most recent query to Google'),
      '#open' => FALSE,
    ];

    $build['google_info']['google_query']['most_recent_query'] = [
      '#markup' => $this->t('%most_recent_query', $t_args) . '<br /><br />' . $this->t('The access_token needs to be included with the query. Get the access_token with <em>drush state-get google_analytics_counter.access_token</em>'),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $date_formatted = !empty($this->state->get('google_analytics_counter.data_last_refreshed')) ? $this->dateFormatter->format($this->state->get('google_analytics_counter.data_last_refreshed'), 'custom', 'M d, Y h:i:sa') : '';
    // Todo: The text part should not be in <em class="placeholder">.
    $data_last_refreshed = !empty($this->state->get('google_analytics_counter.data_last_refreshed')) ? $date_formatted . ' is when Google last refreshed analytics data.' : 'Google\'s last refreshed analytics data is currently unavailable.';
    $t_arg = ['%data_last_refreshed' => $data_last_refreshed];
    $build['google_info']['data_last_refreshed'] = [
      '#markup' => $this->t('%data_last_refreshed', $t_arg),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $project_name = $this->manager->googleProjectName();
    $t_args = [
      ':href' => $project_name,
      '@href' => 'Analytics API',
    ];
    $build['google_info']['daily_quota'] = [
      '#markup' => $this->t('Refer to your <a href=:href target="_blank">@href</a> page to view quotas.', $t_args),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    // The Drupal section.
    $build['drupal_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Information from this site'),
      '#open' => TRUE,
    ];

    $build['drupal_info']['number_paths_stored'] = [
      '#markup' => $this->t('%num_of_results paths are currently stored in the local database table.', ['%num_of_results' => number_format($this->manager->getCount('google_analytics_counter'))]),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $build['drupal_info']['total_nodes'] = [
      '#markup' => $this->t('%totalnodes nodes are published on this site.', ['%totalnodes' => number_format($this->state->get('google_analytics_counter.total_nodes'))]),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $build['drupal_info']['total_nodes_with_pageviews'] = [
      '#markup' => $this->t('%num_of_results nodes on this site have pageview counts <em>greater than zero</em>.', ['%num_of_results' => number_format($this->manager->getCount('google_analytics_counter_storage'))]),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $t_args = [
      '%num_of_results' => number_format($this->manager->getCount('google_analytics_counter_storage_all_nodes')),
    ];
    $build['drupal_info']['total_nodes_equal_zero'] = [
      '#markup' => $this->t('%num_of_results nodes on this site have pageview counts.<br /><strong>Note:</strong> The nodes on this site that have pageview counts should equal the number of published nodes.', $t_args),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $t_args = [
      '%queue_count' => number_format($this->manager->getCount('queue')),
      ':href' => Url::fromRoute('google_analytics_counter.admin_settings_form', [], ['absolute' => TRUE])
        ->toString(),
      '@href' => 'settings form',
    ];
    $build['drupal_info']['queue_count'] = [
      '#markup' => $this->t('%queue_count items are in the queue. The number of items in the queue should be 0 after cron runs.<br /><strong>Note:</strong> Having 0 items in the queue confirms that pageview counts are up to date. Increase Queue Time on the <a href=:href>@href</a> to process all the queued items.', $t_args),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    // Top Twenty Results.
    $build['drupal_info']['top_twenty_results'] = [
      '#type' => 'details',
      '#title' => $this->t('Top Twenty Results'),
      '#open' => FALSE,
    ];

    // Top Twenty Results for Google Analytics Counter table.
    $build['drupal_info']['top_twenty_results']['counter'] = [
      '#type' => 'details',
      '#title' => $this->t('Pagepaths'),
      '#open' => FALSE,
      '#attributes' => [
        'class' => ['google-analytics-counter-counter'],
      ],
    ];

    $build['drupal_info']['top_twenty_results']['counter']['summary'] = [
      '#markup' => $this->t("A pagepath can include paths that don't have an NID, like /search."),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $rows = $this->manager->getTopTwentyResults('google_analytics_counter');
    // Display table.
    $build['drupal_info']['top_twenty_results']['counter']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Pagepath'),
        $this->t('Pageviews'),
      ],
      '#rows' => $rows,
    ];

    // Top Twenty Results for Google Analytics Counter Storage table.
    $build['drupal_info']['top_twenty_results']['storage'] = [
      '#type' => 'details',
      '#title' => $this->t('Pageview Totals'),
      '#open' => FALSE,
      '#attributes' => [
        'class' => ['google-analytics-counter-storage'],
      ],
    ];

    $build['drupal_info']['top_twenty_results']['storage']['summary'] = [
      '#markup' => $this->t('A pageview total may be greater than PAGEVIEWS because a pageview total includes page aliases, node/id, and node/id/ URIs.'),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $rows = $this->manager->getTopTwentyResults('google_analytics_counter_storage');
    // Display table.
    $build['drupal_info']['top_twenty_results']['storage']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Nid'),
        $this->t('Pageview Total'),
      ],
      '#rows' => $rows,
    ];

    $build['drupal_info']['last_cron_run'] = [
      '#markup' => $this->t("Cron's last run: %time ago.", ['%time' => $this->dateFormatter->formatTimeDiffSince($this->state->get('system.cron_last'))]),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $temp = $this->state->get('google_analytics_counter.cron_next_execution') - $this->time->getRequestTime();
    if ($temp < 0) {
      // Run cron immediately.
      $destination = \Drupal::destination()->getAsArray();
      $t_args = [
        ':href' => Url::fromRoute('system.run_cron', [], [
          'absolute' => TRUE,
          'query' => $destination,
        ])->toString(),
        '@href' => 'Run cron immediately',
      ];
      $build['drupal_info']['run_cron'] = [
        '#markup' => $this->t('<a href=:href>@href</a>.', $t_args),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
    }

    // Revoke Google authentication.
    $build = $this->manager->revokeAuthenticationMessage($build);

    if ($this->manager->isAuthenticated() === TRUE) {
      return $build;
    }
    else {
      $build = [];
      $this->manager->notAuthenticatedMessage();
      return $build;
    }
  }

  /**
   * Calculates total pageviews for fixed start and end date or for time ago.
   *
   * @return array
   *   Start & end dates.
   */
  protected function getStartDateEndDate() {
    $config = $this->config;

    if (!empty($config->get('general_settings.fixed_start_date'))) {
      $t_args = [
        '%start_date' => $this->dateFormatter
          ->format(strtotime($config->get('general_settings.fixed_start_date')), 'custom', 'M j, Y'),
        '%end_date' => $this->dateFormatter
          ->format(strtotime($config->get('general_settings.fixed_end_date')), 'custom', 'M j, Y'),
      ];
      return $t_args;
    }
    else {
      $t_args = [
        '%start_date' => $this->state->get('system.cron_last') ? $this->dateFormatter
          ->format($this->state->get('system.cron_last') - strtotime(ltrim($config->get('general_settings.start_date'), '-'), 0), 'custom', 'M j, Y') : 'N/A',
        '%end_date' => $this->state->get('system.cron_last') ? $this->dateFormatter
          ->format($this->state->get('system.cron_last'), 'custom', 'M j, Y') : 'N/A',
      ];
      return $t_args;
    }
  }

}
