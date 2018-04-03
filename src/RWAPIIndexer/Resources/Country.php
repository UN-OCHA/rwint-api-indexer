<?php

namespace RWAPIIndexer\Resources;

/**
 * Country resource handler.
 */
class Country extends \RWAPIIndexer\Resource {
  // Options used for building the query to get the items to index.
  protected $query_options = array(
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

  // Options used to process the entity items before indexing.
  protected $processing_options = array(
    'conversion' => array(
      'description' => array('links'),
      'current' => array('bool'),
      'featured' => array('bool'),
      'latitude' => array('float'),
      'longitude' => array('float'),
    ),
  );

  // Profile sections (id => settings).
  private $profile_sections = array(
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
   * Return the mapping for the current resource.
   *
   * @return array
   *   Elasticsearch index type mapping.
   */
  public function getMapping() {
    $mapping = new \RWAPIIndexer\Mapping();
    $mapping->addInteger('id')
            ->addString('url', FALSE)
            ->addString('url_alias', FALSE)
            ->addString('status', FALSE)
            ->addBoolean('current')
            ->addBoolean('featured')
            // Centroid Coordinates.
            ->addGeoPoint('location')
            // Names.
            ->addString('name', TRUE, TRUE)
            ->addString('shortname', TRUE, TRUE)
            ->addString('iso3', TRUE, TRUE)
            // Description -- legacy.
            ->addString('description')
            ->addString('description-html', NULL)
            // Profile.
            ->addProfile($this->profile_sections);

    return $mapping->export();
  }

  /**
   * Process an item, preparing for the indexing.
   *
   * @param array $item
   *   Item to process.
   */
  public function processItem(&$item) {
    // Current.
    $item['current'] = !empty($item['status']) && $item['status'] === 'current';

    // Only keep the description if the profile is checked.
    if (empty($item['show_profile'])) {
      unset($item['description']);
    }
    else {
      $this->processor->processProfile($this->connection, $item, $this->profile_sections);
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
