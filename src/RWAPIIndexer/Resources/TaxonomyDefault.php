<?php

declare(strict_types=1);

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Mapping;
use RWAPIIndexer\Resource;

/**
 * Taxnomy default resource handler.
 *
 * @phpstan-type TaxonomyDefaultProcessItem array{
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
class TaxonomyDefault extends Resource {

  /**
   * {@inheritdoc}
   */
  protected array $queryOptions = [
    'fields' => [
      'name' => 'name',
      'description' => 'description__value',
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
      ->addString('description');

    return $mapping->export();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(array &$item): void {
    /** @var TaxonomyDefaultProcessItem $item */

    unset($item['url']);
    unset($item['url_alias']);
  }

}
