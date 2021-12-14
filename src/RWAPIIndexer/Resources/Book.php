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
  protected $queryOptions = [
    'status' => 'status',
    'fields' => [
      'title' => 'title',
      'date_created' => 'created',
      'date_changed' => 'changed',
    ],
    'field_joins' => [
      'body' => [
        'body' => 'value',
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected $processingOptions = [
    'conversion' => [
      'body' => ['links', 'html_iframe'],
      'date_created' => ['time'],
      'date_changed' => ['time'],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function getMapping() {
    $mapping = new Mapping();
    $mapping->addInteger('id')
      ->addString('url', FALSE)
      ->addString('url_alias', FALSE)
      ->addStatus()
      ->addString('title', TRUE, TRUE)
      // Body.
      ->addString('body')
      ->addString('body-html', NULL)
      // Dates.
      ->addDates('date', ['created', 'changed'])
      // Images.
      ->addImage('attached_image');

    return $mapping->export();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(&$item) {
    // Handle dates.
    $item['date'] = [
      'created' => $item['date_created'],
      'changed' => $item['date_changed'],
    ];
    unset($item['date_created']);
    unset($item['date_changed']);

    // Handle images.
    if ($this->processor->processImage($item['attached_image']) !== TRUE) {
      unset($item['attached_image']);
    }
    if ($this->processor->processImage($item['image'], TRUE) !== TRUE) {
      unset($item['image']);
    }
  }

}
