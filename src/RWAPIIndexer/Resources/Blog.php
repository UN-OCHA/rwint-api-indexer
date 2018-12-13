<?php

namespace RWAPIIndexer\Resources;

/**
 * Blog resource handler.
 */
class Blog extends \RWAPIIndexer\Resource {
  // Options used for building the query to get the items to index.
  protected $query_options = array(
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
      'field_language' => 'language',
      'field_tags' => 'tags',
    ),
  );

  // Options used to process the entity items before indexing.
  protected $processing_options = array(
    'conversion' => array(
      'body' => array('links', 'html_strict'),
      'date_created' => array('time'),
      'date_changed' => array('time'),
    ),
    'references' => array(
      'language' => array(
        'language' => array('id', 'name', 'code'),
      ),
      'tags' => array(
        'tags' => array('id', 'name'),
      ),
    ),
  );

  /**
   * Return the mapping for the current indexable.
   *
   * @return array
   *   Elasticsearch index type mapping.
   */
  public function getMapping() {
    $mapping = new \RWAPIIndexer\Mapping();
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
            // Language.
            ->addTaxonomy('language')
            ->addString('language.code', FALSE)
            // Tags.
            ->addTaxonomy('tags')
            // Images.
            ->addImage('attached_image')
            ->addImage('image');

    return $mapping->export();
  }

  /**
   * Process an item, preparing for the indexing.
   *
   * @param array $item
   *   Item to process.
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
    if ($this->processor->processImage($item['attached_image'], TRUE) !== TRUE) {
      unset($item['attached_image']);
    }
    if ($this->processor->processImage($item['image'], TRUE) !== TRUE) {
      unset($item['image']);
    }
  }
}
