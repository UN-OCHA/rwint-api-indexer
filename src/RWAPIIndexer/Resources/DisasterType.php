<?php

namespace RWAPIIndexer\Resources;

/**
 * Disaster type resource handler.
 */
class DisasterType extends \RWAPIIndexer\Resources\TaxonomyDefault {
  // Options used for building the query to get the items to index.
  protected $query_options = array(
    'fields' => array(
      'description' => 'description',
    ),
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
            // Names.
            ->addString('name', TRUE, TRUE)
            // Description.
            ->addString('description')
            // Code
            ->addString('code', FALSE);

    return $mapping->export();
  }
}
