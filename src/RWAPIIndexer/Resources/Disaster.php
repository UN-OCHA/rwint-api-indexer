<?php

declare(strict_types=1);

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Mapping;
use RWAPIIndexer\Resource;

/**
 * Disaster resource handler.
 *
 * @phpstan-type DisasterProcessItem array{
 *   id: int,
 *   timestamp: string,
 *   url: string,
 *   url_alias?: string,
 *   redirects?: array<int, string>,
 *   name?: string,
 *   description?: string,
 *   status?: string,
 *   date_created?: int,
 *   date_changed?: int,
 *   date_event?: int,
 *   glide?: string,
 *   related_glide?: array<int, string>,
 *   show_profile?: bool|string,
 *   primary_country?: mixed,
 *   primary_type?: mixed,
 *   country?: array<int, array<string, mixed>>,
 *   type?: array<int, array<string, mixed>>,
 * }
 */
class Disaster extends Resource {

  /**
   * {@inheritdoc}
   */
  protected array $queryOptions = [
    'fields' => [
      'name' => 'name',
      'description' => 'description__value',
      'status' => 'moderation_status',
      'date_created' => 'created',
      'date_changed' => 'changed',
    ],
    'field_joins' => [
      'field_disaster_date' => [
        'date_event' => 'value',
      ],
      'field_glide' => [
        'glide' => 'value',
      ],
      'field_glide_related' => [
        'related_glide' => 'value',
      ],
      'field_profile' => [
        'show_profile' => 'value',
      ],
    ],
    'references' => [
      'field_primary_country' => 'primary_country',
      'field_primary_disaster_type' => 'primary_type',
      'field_country' => 'country',
      'field_disaster_type' => 'type',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected array $processingOptions = [
    'conversion' => [
      'description' => ['links'],
      'date_event' => ['time'],
      'date_created' => ['time'],
      'date_changed' => ['time'],
      'featured' => ['bool'],
      'country' => ['primary'],
      'type' => ['primary'],
      'primary_country' => ['single'],
      'primary_type' => ['single'],
      'related_glide' => ['multi_string'],
    ],
    'references' => [
      'primary_country' => [
        'country' => ['id', 'name', 'shortname', 'iso3', 'location'],
      ],
      'primary_type' => [
        'disaster_type' => ['id', 'name', 'code'],
      ],
      'country' => [
        'country' => ['id', 'name', 'shortname', 'iso3', 'location'],
      ],
      'type' => [
        'disaster_type' => ['id', 'name', 'code'],
      ],
    ],
  ];

  /**
   * Profile sections (id => label).
   *
   * @var array<string, array<string, string|bool>>
   */
  private array $profileSections = [
    'key_content' => [
      'label' => 'Key Content',
      'internal' => TRUE,
      'archives' => TRUE,
      'image' => TRUE,
    ],
    'appeals_response_plans' => [
      'label' => 'Appeals & Response Plans',
      'internal' => TRUE,
      'archives' => TRUE,
      'image' => TRUE,
    ],
    'useful_links' => [
      'label' => 'Useful Links',
      'internal' => FALSE,
      'archives' => FALSE,
      'image' => TRUE,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function getMapping(): array {
    $mapping = new Mapping();
    $mapping->addInteger('id')
      ->addString('uuid', FALSE)
      ->addString('url', FALSE)
      ->addString('url_alias', FALSE)
      ->addString('redirects', FALSE)
      ->addStatus()
      ->addDates('date', ['created', 'changed', 'event'])
      ->addBoolean('featured')
      ->addBoolean('current')
      // Names.
      ->addString('name', TRUE, TRUE, '', TRUE, ['en'])
      ->addString('glide', TRUE, TRUE)
      ->addString('related_glide', TRUE, TRUE)
      // Description - legacy.
      ->addString('description')
      ->addString('description-html', NULL)
      // Profile.
      ->addProfile($this->profileSections)
      // Primary country.
      ->addTaxonomy('primary_country', ['shortname', 'iso3'])
      ->addGeoPoint('primary_country.location')
      // Country.
      ->addTaxonomy('country', ['shortname', 'iso3'])
      ->addGeoPoint('country.location')
      ->addBoolean('country.primary')
      // Primary disaster type.
      ->addTaxonomy('primary_type')
      ->addString('primary_type.code', FALSE)
      // Disaster types.
      ->addTaxonomy('type')
      ->addString('type.code', FALSE)
      ->addBoolean('type.primary');

    return $mapping->export();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(array &$item): void {
    /** @var DisasterProcessItem $item */

    // Handle dates.
    if (isset($item['date_event'])) {
      $item['date']['event'] = $item['date_event'];
      // This is for legacy compatibility where the event date was used as the
      // creation date because there was no other date available. It will be
      // overridden with the real creation date below if it exists.
      $item['date']['created'] = $item['date_event'];
      unset($item['date_event']);
    }
    if (isset($item['date_created'])) {
      $item['date']['created'] = $item['date_created'];
      unset($item['date_created']);
    }
    if (isset($item['date_changed'])) {
      $item['date']['changed'] = $item['date_changed'];
      unset($item['date_changed']);
    }

    // Legacy "current" status.
    if (!empty($item['status']) && ($item['status'] === 'current' || $item['status'] === 'ongoing')) {
      $item['current'] = TRUE;
      $item['status'] = 'ongoing';
    }

    // Only keep the description if the profile is checked.
    if (empty($item['show_profile'])) {
      unset($item['description']);
    }
    else {
      $this->processor->processProfile($item, $this->profileSections);
    }
    unset($item['show_profile']);
  }

}
