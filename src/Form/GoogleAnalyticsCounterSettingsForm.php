<?php

namespace Drupal\google_analytics_counter\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterHelper;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GoogleAnalyticsCounterSettingsForm.
 *
 * @package Drupal\google_analytics_counter\Form
 */
class GoogleAnalyticsCounterSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

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
   * Create an instance of GoogleAnalyticsCounterSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface $manager
   *   Google Analytics Counter Manager object.
   */
    public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entityTypeManager,
    EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    StateInterface $state,
    GoogleAnalyticsCounterManagerInterface $manager
  ) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->state = $state;
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
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

    $project_name = GoogleAnalyticsCounterHelper::googleProjectName();
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

    $t_arg = [
      '%queue_count' => $this->manager->getCount('queue'),
    ];
    $form['queue_time'] = [
      '#type' => 'number',
      '#title' => $this->t('Queue Time (in seconds)'),
      '#default_value' => $config->get('general_settings.queue_time'),
      '#min' => 1,
      '#max' => 10000,
      '#required' => TRUE,
      '#description' => $this->t('%queue_count items are in the queue. The number of items in the queue should be 0 after cron runs.', $t_arg) .
        '<br /><strong>' . $this->t('Note:') .'</strong>'. $this->t(' Having 0 items in the queue confirms that pageview counts are up to date. Increase Queue Time to process all the queued items during a single cron run. Default: 120 seconds.') .
        '<br />' . $this->t('Changing the Queue time will require that the cache to be cleared, which may take a minute after submission.'),
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

    $header = [
      'type' => $this->t('Items'),
      'operations' => $this->t('Operations'),
    ];
    $form['content_types_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Select content types to add the google analytics counter field to:'),
      '#open' => TRUE,
    ];
    $form['content_types_container']['content_types'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('There are no content types.'),
    ];

    // Get the content types.
    $content_types = \Drupal::service('entity.manager')->getStorage('node_type')->loadMultiple();
    foreach ($content_types as $machine_name => $content_type) {
      $content_types[$content_type->id()] = $content_type->label();
    }
    $content_types_list = [
      '#theme' => 'item_list',
      '#items' => $content_types,
      '#context' => ['list_style' => 'comma-list'],
      '#empty' => $this->t('none'),
    ];
    $form['content_types_container']['content_types'][$content_type->id()] = [
      'type' => [
        '#type' => 'inline_template',
        '#template' => '<strong>{{ label }}</strong></br><span id="selected-{{ content_type_id }}">{{ selected_bundles }}</span>',
        '#context' => [
          'label' => $this->t('@bundle types', ['@bundle' => $content_type->label()]),
          'content_type_id' => $content_type->id(),
          'selected_bundles' => $content_types_list,
        ],
      ],
      'operations' => [
        '#type' => 'operations',
        '#links' => [
          'select' => [
            'title' => $this->t('Select'),
            'url' => Url::fromRoute('google_analytics_counter.type_edit_form'),
            'attributes' => [
              'class' => ['use-ajax'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => Json::encode([
                'width' => 700,
              ]),
            ],
          ],
        ],
      ],
    ];

    if ($this->manager->isAuthenticated() !== TRUE) {
      GoogleAnalyticsCounterHelper::notAuthenticatedMessage();
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('google_analytics_counter.settings');

    $current_queue_time = $config->get('general_settings.queue_time');

    $values = $form_state->getValues();
    $config
      ->set('general_settings.cron_interval', $values['cron_interval'])
      ->set('general_settings.chunk_to_fetch', $values['chunk_to_fetch'])
      ->set('general_settings.api_dayquota', $values['api_dayquota'])
      ->set('general_settings.cache_length', $values['cache_length'] * 3600)
      ->set('general_settings.queue_time', $values['queue_time'])
      ->set('general_settings.start_date', $values['start_date'])
      ->set('general_settings.advanced_date_checkbox', $values['advanced_date_checkbox'])
      ->set('general_settings.fixed_start_date', $values['advanced_date_checkbox'] == 1 ? $values['fixed_start_date'] : '')
      ->set('general_settings.fixed_end_date', $values['advanced_date_checkbox'] == 1 ? $values['fixed_end_date'] : '')
      ->save();

    // If the queue time has change the cache needs to be cleared.
    if ($current_queue_time != $values['queue_time']) {
      drupal_flush_all_caches();
      \Drupal::messenger()->addMessage(t(('Caches cleared.')));
    }

    parent::submitForm($form, $form_state);
  }

}
