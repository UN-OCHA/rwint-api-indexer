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
      'description' => 'description',
    ],
    'field_joins' => [
      'field_status' => [
        'status' => 'value',
      ],
      'field_shortname' => [
        'shortname' => 'value',
      ],
      'field_iso3' => [
        'iso3' => 'value',
      ],
      'field_profile' => [
        'show_profile' => 'value',
      ],
      'field_featured' => [
        'featured' => 'value',
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
      ->addString('status', FALSE)
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
      // Profile.
      ->addProfile($this->profileSections);

    return $mapping->export();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(&$item) {
    // Current.
    $item['current'] = !empty($item['status']) && $item['status'] === 'current';

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
