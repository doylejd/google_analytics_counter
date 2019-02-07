<?php

namespace Drupal\google_analytics_counter;


/**
 * Defines the Google Analytics Counter message manager.
 *
 * @package Drupal\google_analytics_counter
 */
interface GoogleAnalyticsCounterMessageManagerInterface {

  /**
   * Prints a warning message when not authenticated.
   *
   * @param $build
   *
   */
  public function notAuthenticatedMessage($build = []);

  /**
   * Revoke Google Authentication Message.
   *
   * @param $build
   *
   * @return mixed
   */
  public function revokeAuthenticationMessage($build);

  /**
   * Returns the link with the Google project name if it is available.
   *
   * @return string
   *   Project name.
   */
  public function googleProjectName();

  /**
   * Get the Profile name of the Google view from Drupal.
   *
   * @param string $profile_id
   *   The profile id used in the google query.
   *
   * @return string mixed
   */
  public function getProfileName($profile_id);

  /**
   * Get the the top twenty results for pageviews and pageview_totals.
   *
   * @param string $table
   *   The table from which the results are selected.
   * @param string $date_range
   *   Defines the date range to pull results for.
   *
   * @return mixed
   */
  public function getTopTwentyResults($table, $rows, $date_range);
}
