<?php

namespace Drupal\google_analytics_counter;


/**
 * Class GoogleAnalyticsCounterAppManager.
 *
 * @package Drupal\google_analytics_counter
 */
interface GoogleAnalyticsCounterAppManagerInterface {

  /**
   * Get total results from Google.
   *
   * @return mixed
   */
  public function getTotalResults();

  /**
   * Request report data.
   *
   * @param array $parameters
   *   An associative array containing:
   *   - profile_id: required [default='ga:profile_id']
   *   - dimensions: optional [ga:pagePath]
   *   - metrics: required [ga:pageviews]
   *   - sort: optional [ga:pageviews]
   *   - start-date: [default=-1 week]
   *   - end_date: optional [default=today]
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
   * Update the path counts.
   *
   * @param integer $index
   *   The index of the chunk to fetch and update.
   *
   * This function is triggered by hook_cron().
   *
   * @throws \Exception
   */
  public function gacUpdatePathCounts($index = 0);

  /**
   * Save the pageview count for a given node.
   *
   * @param integer $nid
   *   The node id.
   * @param string $bundle
   *   The content type of the node.
   * @param int $vid
   *   Revision id value.
   *
   * @throws \Exception
   */
  public function gacUpdateStorage($nid, $bundle, $vid);

  /**
   * Set the parameters for the google query.
   *
   * @return array
   */
  public function setParameters();

  /**
   * Set cache options
   *
   * @param array $parameters
   *
   * @return array
   */
  public function setCacheOptions(array $parameters);

//  /**
//   * Get the count of pageviews for a path.
//   *
//   * @param string $path
//   *   The path to look up.
//   *
//   * @return string
//   *   Count of page views.
//   */
//  public function gacDisplayCount($path);
}