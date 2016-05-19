<?php

namespace RWAPIIndexer\Resources;

/**
 * Disaster type resource handler.
 */
class DisasterType extends \RWAPIIndexer\Resource {
  // Options used for building the query to get the items to index.
  protected $query_options = array(
    'field_joins' => array(
      'field_abbreviation' => array(
        'code' => 'value',
      ),
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
            ->addString('url', FALSE)
            ->addString('url_alias', FALSE)
            ->addString('status', FALSE)
            // Names.
            ->addString('name', TRUE, TRUE)
            // Description.
            ->addString('description')
            ->addString('description-html', NULL)
            // Code
            ->addString('code', FALSE);

    return $mapping->export();
  }
}
