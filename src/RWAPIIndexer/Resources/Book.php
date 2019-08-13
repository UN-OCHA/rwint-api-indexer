<?php

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Resource;
use RWAPIIndexer\Mapping;

/**
 * Book resource handler.
 */
class Book extends Resource {

  /**
   * {@inheritdoc}
   */
  protected $queryOptions = array(
    'fields' => array(
      'title' => 'title',
      'date_created' => 'created',
      'date_changed' => 'changed',
    ),
    'field_joins' => array(
      'field_status' => array(
        'status' => 'value',
      ),
      'body' => array(
        'body' => 'value',
      ),
    ),
  );

  /**
   * {@inheritdoc}
   */
  protected $processingOptions = array(
    'conversion' => array(
      'body' => array('links', 'html_iframe'),
      'date_created' => array('time'),
      'date_changed' => array('time'),
    ),
  );

  /**
   * {@inheritdoc}
   */
  public function getMapping() {
    $mapping = new Mapping();
    $mapping->addInteger('id')
      ->addString('url', FALSE)
      ->addString('url_alias', FALSE)
      ->addString('status', FALSE)
      ->addString('title', TRUE, TRUE)
      // Body.
      ->addString('body')
      ->addString('body-html', NULL)
      // Dates.
      ->addDates('date', array('created', 'changed'));

    return $mapping->export();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(&$item) {
    // Handle dates.
    $item['date'] = array(
      'created' => $item['date_created'],
      'changed' => $item['date_changed'],
    );
    unset($item['date_created']);
    unset($item['date_changed']);
  }

}
