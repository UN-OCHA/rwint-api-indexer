<?php

declare(strict_types=1);

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Mapping;

/**
 * Disaster type resource handler.
 *
 * @phpstan-type DisasterTypeProcessItem array{
 *   id: int,
 *   timestamp: string,
 *   url?: string,
 *   url_alias?: string,
 *   redirects?: array<int, string>,
 *   name?: string,
 *   description?: string,
 *   code?: string,
 * }
 */
class DisasterType extends TaxonomyDefault {

  /**
   * {@inheritdoc}
   */
  protected array $queryOptions = [
    'fields' => [
      'name' => 'name',
      'description' => 'description__value',
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
  public function getMapping(): array {
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
