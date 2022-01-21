<?php

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Resource;
use RWAPIIndexer\Mapping;

/**
 * Disaster resource handler.
 */
class Disaster extends Resource {

  /**
   * {@inheritdoc}
   */
  protected $queryOptions = [
    'fields' => [
      'name' => 'name',
      'description' => 'description__value',
      'status' => 'moderation_status',
    ],
    'field_joins' => [
      'field_disaster_date' => [
        'date' => 'value',
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
  protected $processingOptions = [
    'conversion' => [
      'description' => ['links'],
      'date' => ['time'],
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
   * @var array
   */
  private $profileSections = [
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
  public function getMapping() {
    $mapping = new Mapping();
    $mapping->addInteger('id')
      ->addString('url', FALSE)
      ->addString('url_alias', FALSE)
      ->addStatus()
      ->addDates('date', ['created'])
      ->addBoolean('featured')
      ->addBoolean('current')
      // Names.
      ->addString('name', TRUE, TRUE, '', TRUE)
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
  public function processItem(&$item) {
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
      $this->processor->processProfile($this->connection, $item, $this->profileSections);
    }
    unset($item['show_profile']);

    // Handle date.
    $item['date'] = ['created' => $item['date']];
  }

}
