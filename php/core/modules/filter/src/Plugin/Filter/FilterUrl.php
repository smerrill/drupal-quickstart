<?php

/**
 * @file
 * Contains \Drupal\filter\Plugin\Filter\FilterUrl.
 */

namespace Drupal\filter\Plugin\Filter;

use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to convert URLs into links.
 *
 * @Filter(
 *   id = "filter_url",
 *   title = @Translation("Convert URLs into links"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 *   settings = {
 *     "filter_url_length" = 72
 *   }
 * )
 */
class FilterUrl extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, array &$form_state) {
    $form['filter_url_length'] = array(
      '#type' => 'number',
      '#title' => $this->t('Maximum link text length'),
      '#default_value' => $this->settings['filter_url_length'],
      '#min' => 1,
      '#field_suffix' => $this->t('characters'),
      '#description' => $this->t('URLs longer than this number of characters will be truncated to prevent long strings that break formatting. The link itself will be retained; just the text portion of the link will be truncated.'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode, $cache, $cache_id) {
    return _filter_url($text, $this);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('Web page addresses and e-mail addresses turn into links automatically.');
  }

}
