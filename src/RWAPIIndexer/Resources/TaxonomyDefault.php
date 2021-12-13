<?php

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Resource;
use RWAPIIndexer\Mapping;

/**
 * Taxnomy default resource handler.
 */
class TaxonomyDefault extends Resource {

  /**
   * {@inheritdoc}
   */
  protected $queryOptions = [
    'fields' => [
      'description' => 'description',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function getMapping() {
    $mapping = new Mapping();
    $mapping->addInteger('id')
      // Names.
      ->addString('name', TRUE, TRUE, '', TRUE)
      // Description.
      ->addString('description');

    return $mapping->export();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(&$item) {
    unset($item['url']);
    unset($item['url_alias']);
  }

}
