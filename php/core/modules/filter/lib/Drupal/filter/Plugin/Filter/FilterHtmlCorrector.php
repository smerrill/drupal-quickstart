<?php

/**
 * @file
 * Contains \Drupal\filter\Plugin\Filter\FilterHtmlCorrector.
 */

namespace Drupal\filter\Plugin\Filter;

use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to correct faulty and chopped off HTML.
 *
 * @Filter(
 *   id = "filter_htmlcorrector",
 *   title = @Translation("Correct faulty and chopped off HTML"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   weight = 10
 * )
 */
class FilterHtmlCorrector extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode, $cache, $cache_id) {
    return _filter_htmlcorrector($text);
  }

}