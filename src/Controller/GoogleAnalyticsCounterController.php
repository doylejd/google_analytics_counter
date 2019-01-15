<?php

namespace Drupal\google_analytics_counter\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterHelper;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterMessageManagerInterface;
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
   * The Google Analytics Counter message manager.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterMessageManagerInterface
   */
  protected $messageManager;

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
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterMessageManagerInterface $message_manager
   *   Google Analytics Counter Manager object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, DateFormatter $date_formatter, GoogleAnalyticsCounterManagerInterface $manager, GoogleAnalyticsCounterMessageManagerInterface $message_manager) {
    $this->config = $config_factory->get('google_analytics_counter.settings');
    $this->state = $state;
    $this->dateFormatter = $date_formatter;
    $this->time = \Drupal::service('datetime.time');
    $this->manager = $manager;
    $this->messageManager = $message_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('date.formatter'),
      $container->get('google_analytics_counter.manager'),
      $container->get('google_analytics_counter.message_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function dashboard() {
    $config = $this->config;

    if (!$this->manager->isAuthenticated() === TRUE) {
      $build = [];
      $this->messageManager->notAuthenticatedMessage();

      // Add a link to the revoke form.
      $build = $this->messageManager->revokeAuthenticationMessage($build);

      return $build;
    }

    $build = [];
    $build['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'h4',
      '#value' => $this->t('Information on this page is updated during cron runs.') . '</h4>',
    ];

    // Information from Google.
    $build['google_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Information from Google Analytics API'),
      '#open' => TRUE,
    ];

    // Get and format total pageviews.
    $t_args = $this->getStartDateEndDate();
    $t_args += ['%total_pageviews' => number_format($this->state->get('google_analytics_counter.total_pageviews'))];
    $build['google_info']['total_pageviews'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%total_pageviews pageviews were recorded by Google Analytics for this view between %start_date - %end_date.', $t_args),
    ];

    // Get and format total paths.
    $t_args = $this->getStartDateEndDate();
    $t_args += [
      '%total_paths' => number_format($this->state->get('google_analytics_counter.total_paths')),
    ];
    $build['google_info']['total_paths'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%total_paths paths were recorded by Google Analytics for this view between %start_date - %end_date.', $t_args),
    ];

    // Get the most recent query or print helpful message for site builders.
    if (!$this->state->get('google_analytics_counter.most_recent_query')) {
      $t_args = ['%most_recent_query' => 'No query has been run yet or Google is not running queries from your system. See the module\'s README.md or Google\'s documentation.'];
    }
    else {
      $t_args = ['%most_recent_query' => $this->state->get('google_analytics_counter.most_recent_query')];
    }

    $build['google_info']['google_query'] = [
      '#type' => 'details',
      '#title' => $this->t('Recent query to Google'),
      '#open' => FALSE,
    ];

    $build['google_info']['google_query']['most_recent_query'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%most_recent_query', $t_args) . '<br /><br />' . $this->t('The access_token needs to be included with the query. Get the access_token with <em>drush state-get google_analytics_counter.access_token</em>'),
    ];

    // If available, print dataLastRefreshed from Google.
    if ($this->state->get('google_analytics_counter.data_last_refreshed')) {
      $data_last_refreshed = $this->dateFormatter->format($this->state->get('google_analytics_counter.data_last_refreshed'), 'custom', 'M d, Y h:i:sa'). $this->t(' is when Google last refreshed analytics data.');
    }
    else {
      $data_last_refreshed = "Google's last refreshed analytics data is currently unavailable.";
    }
    $t_arg = ['%data_last_refreshed' => $data_last_refreshed];
    $build['google_info']['data_last_refreshed'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%data_last_refreshed', $t_arg),
    ];

    // Print a message about Google quotas with an embedded link to Analytics API.
    $t_args = [
      ':href' => $this->messageManager->googleProjectName(),
      '@href' => 'Analytics API',
    ];
    $build['google_info']['daily_quota'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Refer to your <a href=:href target="_blank">@href</a> page to view quotas.', $t_args),
    ];

    // Information from Drupal.
    $build['drupal_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Information from this site'),
      '#open' => TRUE,
    ];

    $build['drupal_info']['number_paths_stored'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%num_of_results paths are currently stored in the local database table.', ['%num_of_results' => number_format(GoogleAnalyticsCounterHelper::getCount('google_analytics_counter'))]),
    ];

    $build['drupal_info']['total_nodes'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%totalnodes nodes are published on this site.', ['%totalnodes' => number_format($this->state->get('google_analytics_counter.total_nodes'))]),
    ];

    $build['drupal_info']['total_nodes_with_pageviews'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%num_of_results nodes on this site have pageview counts <em>greater than zero</em>.', ['%num_of_results' => number_format(GoogleAnalyticsCounterHelper::getCount('google_analytics_counter_storage'))]),
    ];

    $t_args = [
      '%num_of_results' => number_format(GoogleAnalyticsCounterHelper::getCount('google_analytics_counter_storage_all_nodes')),
    ];
    $build['drupal_info']['total_nodes_equal_zero'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%num_of_results nodes on this site have pageview counts.<br /><strong>Note:</strong> The nodes on this site that have pageview counts should equal the number of published nodes.', $t_args),
    ];

    $t_args = [
      '%queue_count' => number_format(GoogleAnalyticsCounterHelper::getCount('queue')),
      ':href' => Url::fromRoute('google_analytics_counter.admin_settings_form', [], ['absolute' => TRUE])
        ->toString(),
      '@href' => 'settings form',
    ];
    $build['drupal_info']['queue_count'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%queue_count items are in the queue. The number of items in the queue should be 0 after cron runs.<br />Having 0 items in the queue confirms that pageview counts are up to date. Increase Queue Time on the <a href=:href>@href</a> to process all the queued items.', $t_args),
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
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t("A pagepath can include paths that don't have an NID, like /search."),
    ];

    $rows = $this->messageManager->getTopTwentyResults('google_analytics_counter');
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
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('A pageview total may be greater than PAGEVIEWS because a pageview total includes page aliases, node/id, and node/id/ URIs.'),
    ];

    $rows = $this->messageManager->getTopTwentyResults('google_analytics_counter_storage');
    // Display table.
    $build['drupal_info']['top_twenty_results']['storage']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Nid'),
        $this->t('Pageview Total'),
      ],
      '#rows' => $rows,
    ];

    // Cron Information.
    $build['cron_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Cron Information'),
      '#open' => TRUE,
    ];

    $build['cron_information']['last_cron_run'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t("Cron's last successful run: %time ago.", ['%time' => $this->dateFormatter->formatTimeDiffSince($this->state->get('system.cron_last'))]),
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
        '@href' => 'Run cron immediately.',
      ];
      $build['cron_information']['run_cron'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('<a href=:href>@href</a>', $t_args),
      ];
    }

    // Add a link to the revoke form.
    $build = $this->messageManager->revokeAuthenticationMessage($build);

    return $build;
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
        '%start_date' => $config->get('general_settings.start_date') ? $this->dateFormatter
          ->format(strtotime('yesterday') - strtotime(ltrim($config->get('general_settings.start_date'), '-'), 0), 'custom', 'M j, Y') : 'N/A',
        '%end_date' => $config->get('general_settings.start_date') ? $this->dateFormatter
          ->format(strtotime('yesterday'), 'custom', 'M j, Y') : 'N/A',
      ];

      return $t_args;
    }
  }

}
