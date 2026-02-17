<?php

declare(strict_types=1);

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Mapping;
use RWAPIIndexer\Resource;

/**
 * Blog resource handler.
 *
 * @phpstan-type BlogProcessItem array{
 *   id: int,
 *   timestamp: string,
 *   url: string,
 *   url_alias?: string,
 *   redirects?: array<int, string>,
 *   title?: string,
 *   body?: string,
 *   date_created?: int,
 *   date_changed?: int,
 *   status?: string,
 *   author?: string,
 *   image?: mixed,
 *   attached_image?: mixed,
 *   tags?: array<int, array<string, mixed>>,
 * }
 */
class Blog extends Resource {

  /**
   * {@inheritdoc}
   */
  protected array $queryOptions = [
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
  protected array $processingOptions = [
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
  public function getMapping(): array {
    $mapping = new Mapping();
    $mapping->addInteger('id')
      ->addString('uuid', FALSE)
      ->addString('url', FALSE)
      ->addString('url_alias', FALSE)
      ->addString('redirects', FALSE)
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
  public function processItem(array &$item): void {
    /** @var BlogProcessItem $item */

    // Handle dates.
    if (isset($item['date_created'])) {
      $item['date']['created'] = $item['date_created'];
      unset($item['date_created']);
    }
    if (isset($item['date_changed'])) {
      $item['date']['changed'] = $item['date_changed'];
      unset($item['date_changed']);
    }

    // Handle images.
    if ($this->processor->processImage($item, 'attached_image') !== TRUE) {
      unset($item['attached_image']);
    }
    if ($this->processor->processImage($item, 'image', TRUE) !== TRUE) {
      unset($item['image']);
    }
  }

}
