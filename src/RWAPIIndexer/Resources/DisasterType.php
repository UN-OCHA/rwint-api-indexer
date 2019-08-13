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
  protected $queryOptions = array(
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
   * {@inheritdoc}
   */
  public function getMapping() {
    $mapping = new Mapping();
    $mapping->addInteger('id')
      // Names.
      ->addString('name', TRUE, TRUE)
      // Description.
      ->addString('description')
      // Code.
      ->addString('code', FALSE);

    return $mapping->export();
  }

}
