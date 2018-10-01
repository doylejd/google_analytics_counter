<?php

namespace Drupal\google_analytics_counter\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GoogleAnalyticsCounterSettingsForm.
 *
 * @package Drupal\google_analytics_counter\Form
 */
class GoogleAnalyticsCounterSettingsForm extends ConfigFormBase {

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface
   */
  protected $manager;

  /**
   * Constructs a new SiteMaintenanceModeForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface $manager
   *   Google Analytics Counter Manager object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, GoogleAnalyticsCounterManagerInterface $manager) {
    parent::__construct($config_factory);
    $this->state = $state;
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('google_analytics_counter.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'google_analytics_counter_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['google_analytics_counter.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('google_analytics_counter.settings');

    $t_args = [
      ':href' => Url::fromUri('https://developers.google.com/analytics/devguides/reporting/core/v3/limits-quotas')->toString(),
      '@href' => 'Limits and Quotas on API Requests',
    ];
    $form['cron_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum time to wait before fetching Google Analytics data (in minutes)'),
      '#default_value' => $config->get('general_settings.cron_interval'),
      '#min' => 0,
      '#max' => 10000,
      '#description' => $this->t('Google Analytics data is fetched and processed during cron. On the largest systems, cron may run every minute which could result in exceeding Google\'s quota policies. See <a href=:href target="_blank">@href</a> for more information. To bypass the minimum time to wait, set this value to 0.', $t_args),
      '#required' => TRUE,
    ];

    $form['chunk_to_fetch'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of items to fetch from Google Analytics in one request'),
      '#default_value' => $config->get('general_settings.chunk_to_fetch'),
      '#min' => 1,
      '#max' => 10000,
      '#description' => $this->t('The number of items to be fetched from Google Analytics in one request. The maximum allowed by Google is 10000. Default: 1000 items.'),
      '#required' => TRUE,
    ];

    $project_name = $this->manager->googleProjectName();
    $t_args = [
      ':href' => Url::fromUri('https://developers.google.com/analytics/devguides/reporting/core/v3/limits-quotas')->toString(),
      '@href' => 'Limits and Quotas on API Requests',
      ':href2' => $project_name,
      '@href2' => 'Analytics API',
    ];
    $form['api_dayquota'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum GA API requests per day'),
      '#default_value' => $config->get('general_settings.api_dayquota'),
      '#min' => 1,
      '#max' => 10000,
      '#description' => $this->t('This is the daily limit of requests <strong>per view</strong> per day. Refer to your <a href=:href2 target="_blank">@href2</a> page to view quotas.', $t_args),
      '#required' => TRUE,
    ];

    $form['cache_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Google Analytics query cache (in hours)'),
      '#description' => $this->t('Limit the time in hours before getting fresh data with the same query to Google Analytics. Minimum: 0 hours. Maximum: 730 hours (approx. one month).'),
      '#default_value' => $config->get('general_settings.cache_length') / 3600,
      '#min' => 0,
      '#max' => 730,
      '#required' => TRUE,
    ];

    $t_args = [
      '%queue_count' => $this->manager->getCount('queue'),
    ];
    $form['queue_time'] = [
      '#type' => 'number',
      '#title' => $this->t('Queue Time (in seconds)'),
      '#default_value' => $config->get('general_settings.queue_time'),
      '#min' => 1,
      '#max' => 10000,
      '#required' => TRUE,
      '#description' => $this->t('%queue_count items are in the queue. The number of items in the queue should be 0 after cron runs.<br /><strong>Note:</strong> Having 0 items in the queue confirms that pageview counts are up to date. Increase Queue Time to process all the queued items. Default: 120 seconds.', $t_args),
    ];

    // Google Analytics start date settings.
    $form['start_date_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Query Dates for Google Analytics'),
      '#open' => TRUE,
    ];

    $start_date = [
      '-1 day' => $this->t('-1 day'),
      '-1 week' => $this->t('-1 week'),
      '-1 month' => $this->t('-1 month'),
      '-3 months' => $this->t('-3 months'),
      '-6 months' => $this->t('-6 months'),
      '-1 year' => $this->t('-1 year'),
      '2005-01-01' => $this->t('Since 2005-01-01'),
    ];

    $form['start_date_details']['start_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Start date for Google Analytics queries'),
      '#default_value' => $config->get('general_settings.start_date'),
      '#description' => $this->t('The earliest valid start date for Google Analytics is 2005-01-01.'),
      '#options' => $start_date,
      '#states' => [
        'disabled' => [
          ':input[name="advanced_date_checkbox"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="advanced_date_checkbox"]' => ['checked' => FALSE],
        ],
        'visible' => [
          ':input[name="advanced_date_checkbox"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['start_date_details']['advanced_date'] = [
      '#type' => 'details',
      '#title' => $this->t('Query with fixed dates'),
      '#states' => [
        'open' => [
          ':input[name="advanced_date_checkbox"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['start_date_details']['advanced_date']['advanced_date_checkbox'] = [
      '#type' => 'checkbox',
      '#title' => '<strong>' . $this->t('FIXED DATES') . '</strong>',
      '#default_value' => $config->get('general_settings.advanced_date_checkbox'),
      '#description' => $this->t('Select if you wish to query Google Analytics with a fixed start date and a fixed end date.'),
    ];

    $form['start_date_details']['advanced_date']['fixed_start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Fixed start date'),
      '#description' => $this->t('Set a fixed start date for Google Analytics queries. Disabled if FIXED DATES is <strong>unchecked</strong>.'),
      '#default_value' => $config->get('general_settings.fixed_start_date'),
      '#states' => [
        'disabled' => [
          ':input[name="advanced_date_checkbox"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['start_date_details']['advanced_date']['fixed_end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Fixed end date'),
      '#description' => $this->t('Set a fixed end date for Google Analytics queries. Disabled if FIXED DATES is <strong>unchecked</strong>.'),
      '#default_value' => $config->get('general_settings.fixed_end_date'),
      '#states' => [
        'disabled' => [
          ':input[name="advanced_date_checkbox"]' => ['checked' => FALSE],
        ],
      ],
    ];

    if ($this->manager->isAuthenticated() !== TRUE) {
      $this->manager->notAuthenticatedMessage();
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('google_analytics_counter.settings');

    // hook_queue_info_alter() requires a cache rebuild.
    if ($form_state->getValue('queue_time') != $config->get('general_settings.queue_time')) {
      drupal_flush_all_caches();
    }

    $config
      ->set('general_settings.cron_interval', $form_state->getValue('cron_interval'))
      ->set('general_settings.chunk_to_fetch', $form_state->getValue('chunk_to_fetch'))
      ->set('general_settings.api_dayquota', $form_state->getValue('api_dayquota'))
      ->set('general_settings.cache_length', $form_state->getValue('cache_length') * 3600)
      ->set('general_settings.queue_time', $form_state->getValue('queue_time'))
      ->set('general_settings.start_date', $form_state->getValue('start_date'))
      ->set('general_settings.advanced_date_checkbox', $form_state->getValue('advanced_date_checkbox'))
      ->set('general_settings.fixed_start_date', $form_state->getValue('advanced_date_checkbox') == 1 ? $form_state->getValue('fixed_start_date') : '')
      ->set('general_settings.fixed_end_date', $form_state->getValue('advanced_date_checkbox') == 1 ? $form_state->getValue('fixed_end_date') : '')
      ->save();

    parent::submitForm($form, $form_state);
  }

}
