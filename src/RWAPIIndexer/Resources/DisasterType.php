<?php

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Mapping;

/**
 * Disaster type resource handler.
 */
class DisasterType extends TaxonomyDefault {

  /**
   * {@inheritdoc}
   */
  protected $queryOptions = [
    'fields' => [
      'description' => 'description',
    ],
    'field_joins' => [
      'field_disaster_type_code' => [
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
