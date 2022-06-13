<?php

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Resource;
use RWAPIIndexer\Mapping;

/**
 * Blog resource handler.
 */
class Blog extends Resource {

  /**
   * {@inheritdoc}
   */
  protected $queryOptions = [
    'fields' => [
      'title' => 'title',
      'date_created' => 'created',
      'date_changed' => 'changed',
      'status' => 'moderation_status',
    ],
    'field_joins' => [
      'body' => [
        'body' => 'value',
      ],
      'field_author' => [
        'author' => 'value',
      ],
      'field_image' => [
        'image' => 'image_reference',
      ],
      'field_attached_images' => [
        'attached_image' => 'image_reference',
      ],
    ],
    'references' => [
      'field_tags' => 'tags',
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
    'references' => [
      'tags' => [
        'tag' => ['id', 'name'],
      ],
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
      ->addString('author', TRUE, TRUE)
      // Body.
      ->addString('body')
      ->addString('body-html', NULL)
      // Dates.
      ->addDates('date', ['created', 'changed'])
      // Tags.
      ->addTaxonomy('tags')
      // Images.
      ->addImage('attached_image')
      ->addImage('image');

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
