<?php

namespace RWAPIIndexer\Resources;

/**
 * Book resource handler.
 */
class Book extends \RWAPIIndexer\Resource {
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
    ),
    'references' => array(
      'field_language' => 'language',
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
            // Body.
            ->addString('body')
            ->addString('body-html', NULL)
            // Dates.
            ->addDates('date', array('created', 'changed', 'closing'))
            // Language.
            ->addTaxonomy('language')
            ->addString('language.code', FALSE);

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
  }
}
