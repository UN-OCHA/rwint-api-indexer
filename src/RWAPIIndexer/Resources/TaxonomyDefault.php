<?php

namespace RWAPIIndexer\Resources;

/**
 * Taxnomy default resource handler.
 */
class TaxonomyDefault extends \RWAPIIndexer\Resource {
  protected $entity_type = 'taxonomy_term';

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
            ->addString('status', FALSE)
            // Names.
            ->addString('name', TRUE, TRUE)
            // Description.
            ->addString('description')
            ->addString('description-html', NULL);

    return $mapping->export();
  }
}
