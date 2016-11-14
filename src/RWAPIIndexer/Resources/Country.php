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
        'profile' => 'value',
      ),
      'field_featured' => array(
        'featured' => 'value',
      ),
      'field_location' => array(
        'latitude' => 'lat',
        'longitude' => 'lon',
      ),
      'field_video_playlist' => array(
        'video_playlist' => 'value',
      ),
    ),
  );

  // Options used to process the entity items before indexing.
  protected $processing_options = array(
    'conversion' => array(
      'description' => array('links', 'html'),
      'current' => array('bool'),
      'featured' => array('bool'),
      'latitude' => array('float'),
      'longitude' => array('float'),
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
            // Description.
            ->addString('description')
            ->addString('description-html', NULL)
            // Video playlist.
            ->addString('video_playlist', FALSE);

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
    if (empty($item['profile'])) {
      unset($item['description']);
      unset($item['description-html']);
    }
    unset($item['profile']);

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
