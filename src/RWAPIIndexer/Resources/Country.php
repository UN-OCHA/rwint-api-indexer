<?php

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Resource;
use RWAPIIndexer\Mapping;

/**
 * Country resource handler.
 */
class Country extends Resource {

  /**
   * {@inheritdoc}
   */
  protected $queryOptions = [
    'fields' => [
      'name' => 'name',
      'description' => 'description__value',
      'status' => 'moderation_status',
      'date_created' => 'created',
      'date_changed' => 'changed',
    ],
    'field_joins' => [
      'field_shortname' => [
        'shortname' => 'value',
      ],
      'field_iso3' => [
        'iso3' => 'value',
      ],
      'field_profile' => [
        'show_profile' => 'value',
      ],
      'field_location' => [
        'latitude' => 'lat',
        'longitude' => 'lon',
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected $processingOptions = [
    'conversion' => [
      'description' => ['links'],
      'current' => ['bool'],
      'featured' => ['bool'],
      'latitude' => ['float'],
      'longitude' => ['float'],
      'date_created' => ['time'],
      'date_changed' => ['time'],
    ],
  ];

  /**
   * Profile sections (id => settings).
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
      ->addBoolean('current')
      ->addBoolean('featured')
      // Centroid Coordinates.
      ->addGeoPoint('location')
      // Names.
      ->addString('name', TRUE, TRUE, '', TRUE)
      ->addString('shortname', TRUE, TRUE, '', TRUE)
      ->addString('iso3', TRUE, TRUE)
      // Description -- legacy.
      ->addString('description')
      ->addString('description-html', NULL)
      // Dates.
      ->addDates('date', ['created', 'changed'])
      // Profile.
      ->addProfile($this->profileSections);

    return $mapping->export();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(&$item) {
    // Handle dates.
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
      $this->processor->processProfile($this->connection, $item, $this->profileSections);
    }
    unset($item['show_profile']);

    // Centroid coordinates.
    if (isset($item['latitude'], $item['longitude'])) {
      // @todo fix the coordinates in the main site instead.
      if ($item['latitude'] < -90 || $item['latitude'] > 90) {
        $item['location'] = [
          'lon' => $item['latitude'],
          'lat' => $item['longitude'],
        ];
      }
      else {
        $item['location'] = [
          'lat' => $item['latitude'],
          'lon' => $item['longitude'],
        ];
      }
      unset($item['longitude']);
      unset($item['latitude']);
    }
  }

}
