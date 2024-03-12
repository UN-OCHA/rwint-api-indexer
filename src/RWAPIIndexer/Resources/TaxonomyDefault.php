<?php

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Mapping;
use RWAPIIndexer\Resource;

/**
 * Taxnomy default resource handler.
 */
class TaxonomyDefault extends Resource {

  /**
   * {@inheritdoc}
   */
  protected $queryOptions = [
    'fields' => [
      'name' => 'name',
      'description' => 'description__value',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function getMapping() {
    $mapping = new Mapping();
    $mapping->addInteger('id')
      ->addString('uuid', FALSE)
      // Names.
      ->addString('name', TRUE, TRUE, '', TRUE, ['en'])
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
