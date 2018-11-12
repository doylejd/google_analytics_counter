<?php

namespace Drupal\google_analytics_counter;


/**
 * Class GoogleAnalyticsCounterManager.
 *
 * @package Drupal\google_analytics_counter
 */
interface GoogleAnalyticsCounterManagerInterface {

  /**
   * Begin authentication to Google authentication page with the client_id.
   */
  public function beginGacAuthentication();

  /**
   * Check to make sure we are authenticated with google.
   *
   * @return bool
   *   True if there is a refresh token set.
   */
  public function isAuthenticated();

  /**
   * Instantiate a new GoogleAnalyticsCounterFeed object.
   *
   * @return object
   *   GoogleAnalyticsCounterFeed object to authorize access and request data
   *   from the Google Analytics Core Reporting API.
   */
  public function newGaFeed();

  /**
   * Get the list of available web properties.
   *
   * @return array
   *   Array of options.
   */
  public function getWebPropertiesOptions();

  /**
   * Get the total results from Google.
   *
   * @param string $profile_id
   *   The profile id used in the google query.
   * @param int $index
   *   The index of the chunk to fetch for the queue.
   *
   * @return \Drupal\google_analytics_counter\GoogleAnalyticsCounterFeed
   *   The returned feed after the request has been made.
   */
  public function getTotalResults($profile_id, $index = 0);

  /**
   * Request report data.
   *
   * @param array $parameters
   *   An associative array containing:
   *   - profile_id: required
   *   - dimensions: optional [ga:pagePath]
   *   - metrics: required [ga:pageviews]
   *   - sort: optional [ga:pageviews]
   *   - start-date: [default=-1 week]
   *   - end_date: optional [default=tomorrow]
   *   - start_index: [default=1]
   *   - max_results: optional [default=10,000].
   *   - filters: optional [default=none]
   *   - segment: optional [default=none]
   * @param array $cache_options
   *   An optional associative array containing:
   *   - cid: optional [default=md5 hash]
   *   - expire: optional [default=CACHE_TEMPORARY]
   *   - refresh: optional [default=FALSE].
   *
   * @return \Drupal\google_analytics_counter\GoogleAnalyticsCounterFeed|object
   *   A new GoogleAnalyticsCounterFeed object
   */
  public function reportData($parameters = [], $cache_options = []);

  /**
   * Get the count of pageviews for a path.
   *
   * @param string $path
   *   The path to look up.
   *
   * @return string
   *   Count of page views.
   */
  public function displayGaCount($path);

  /**
   * Update the path counts.
   *
   * @param string $profile_id
   *   The profile id used in the google query.
   * @param int $index
   *   The index of the chunk to fetch and update.
   *
   * This function is triggered by hook_cron().
   *
   * @throws \Exception
   */
  public function updatePathCounts($profile_id, $index = 0);

  /**
   * Save the pageview count for a given node.
   *
   * @param integer $nid
   *   The node id.
   * @param string $bundle
   *   The content type of the node.
   * @param int $vid
   *   Revision id value.
   * @param string $profile_id
   *   The profile id used in the google query.
   *
   * @throws \Exception
   */
  public function updateStorage($nid, $bundle, $vid, $profile_id);

  /**
   * Get the row count of a table, sometimes with conditions.
   *
   * @param string $table
   *
   * @return mixed
   */
  public function getCount($table);

  /**
   * The top twenty results of pageviews and pageview_totals for each profile.
   *
   * @param string $table
   *   The table from which the results are selected.
   * @param string $profile_id
   *   The profile id used in the google query.
   *
   * @return mixed
   */
  public function getTopTwentyResults($table, $profile_id);

  /**
   * Programmatically revoke stored state values.
   */
  public function revoke();

  /**
   * Programmatically revoke stored profile state values.
   *
   * @param string $profile_id
   *   The profile id used in the google query.
   */
  public function revokeProfiles($profile_id);
}