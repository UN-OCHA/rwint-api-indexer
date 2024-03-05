<?php

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Mapping;

/**
 * Language resource handler.
 */
class Language extends TaxonomyDefault {

  /**
   * {@inheritdoc}
   */
  protected $queryOptions = [
    'fields' => [
      'name' => 'name',
      'description' => 'description__value',
    ],
    'field_joins' => [
      'field_language_code' => [
        'code' => 'value',
      ],
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
      ->addString('description')
      // Code.
      ->addString('code', FALSE);

    return $mapping->export();
  }

}
