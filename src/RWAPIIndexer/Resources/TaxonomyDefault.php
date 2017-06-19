<?php

namespace RWAPIIndexer\Resources;

/**
 * Taxnomy default resource handler.
 */
class TaxonomyDefault extends \RWAPIIndexer\Resource {
  // Options used for building the query to get the items to index.
  protected $query_options = array(
    'fields' => array(
      'description' => 'description',
    ),
  );

  /**
   * Return the mapping for the current resource.
   *
   * @return array
   *   Elasticsearch index type mapping.
   */
  public function getMapping() {
    $mapping = new \RWAPIIndexer\Mapping();
    $mapping->addInteger('id')
            // Names.
            ->addString('name', TRUE, TRUE)
            // Description.
            ->addString('description');

    return $mapping->export();
  }

  /**
   * Process an item, preparing for the indexing.
   *
   * @param array $item
   *   Item to process.
   */
  public function processItem(&$item) {
    unset($item['url']);
    unset($item['url_alias']);
  }
}
