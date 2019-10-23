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
  protected $queryOptions = array(
    'fields' => array(
      'description' => 'description',
    ),
    'field_joins' => array(
      'field_status' => array(
        'status' => 'value',
      ),
      'field_shortname' => array(
        'shortname' => 'value',
      ),
      'field_iso3' => array(
        'iso3' => 'value',
      ),
      'field_profile' => array(
        'show_profile' => 'value',
      ),
      'field_featured' => array(
        'featured' => 'value',
      ),
      'field_location' => array(
        'latitude' => 'lat',
        'longitude' => 'lon',
      ),
    ),
  );

  /**
   * {@inheritdoc}
   */
  protected $processingOptions = array(
    'conversion' => array(
      'description' => array('links'),
      'current' => array('bool'),
      'featured' => array('bool'),
      'latitude' => array('float'),
      'longitude' => array('float'),
    ),
  );

  /**
   * Profile sections (id => settings).
   *
   * @var array
   */
  private $profileSections = array(
    'key_content' => array(
      'label' => 'Key Content',
      'internal' => TRUE,
      'archives' => TRUE,
      'image' => TRUE,
    ),
    'appeals_response_plans' => array(
      'label' => 'Appeals & Response Plans',
      'internal' => TRUE,
      'archives' => TRUE,
      'image' => TRUE,
    ),
    'useful_links' => array(
      'label' => 'Useful Links',
      'internal' => FALSE,
      'archives' => FALSE,
      'image' => TRUE,
    ),
  );

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
      // TODO: fix the coordinates in the main site instead.
      if ($item['latitude'] < -90 || $item['latitude'] > 90) {
        $item['location'] = array('lon' => $item['latitude'], 'lat' => $item['longitude']);
      }
      else {
        $item['location'] = array('lat' => $item['latitude'], 'lon' => $item['longitude']);
      }
      unset($item['longitude']);
      unset($item['latitude']);
    }
  }

}
