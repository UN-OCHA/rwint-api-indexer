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
      'description' => 'description',
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
      // Names.
      ->addString('name', TRUE, TRUE, '', TRUE)
      // Description.
      ->addString('description')
      // Code.
      ->addString('code', FALSE);

    return $mapping->export();
  }

}
