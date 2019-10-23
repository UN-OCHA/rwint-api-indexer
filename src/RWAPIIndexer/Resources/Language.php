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
  protected $queryOptions = array(
    'fields' => array(
      'description' => 'description',
    ),
    'field_joins' => array(
      'field_language_code' => array(
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
      ->addString('name', TRUE, TRUE, '', TRUE)
      // Description.
      ->addString('description')
      // Code.
      ->addString('code', FALSE);

    return $mapping->export();
  }

}
