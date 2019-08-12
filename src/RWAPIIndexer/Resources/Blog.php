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
      'field_author' => array(
        'author' => 'value',
      ),
      'field_image' => array(
        'image' => 'image_reference',
      ),
      'field_attached_images' => array(
        'attached_image' => 'image_reference',
      ),
    ),
    'references' => array(
      'field_tags' => 'tags',
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
    'references' => array(
      'tags' => array(
        'tags' => array('id', 'name'),
      ),
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
      ->addString('author', TRUE, TRUE)
      // Body.
      ->addString('body')
      ->addString('body-html', NULL)
      // Dates.
      ->addDates('date', array('created', 'changed'))
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
    $item['date'] = array(
      'created' => $item['date_created'],
      'changed' => $item['date_changed'],
    );
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
