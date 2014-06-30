<?php

namespace RWAPIIndexer\Resources;

/**
 * Country resource handler.
 */
class Country extends \RWAPIIndexer\Resource {
  // Entity type and bundle.
  protected $entity_type = 'taxonomy_term';
  protected $bundle = 'country';

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
            ->addString('description-html', NULL);

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

    // Centroid coordinates.
    if (isset($item['latitude'], $item['longitude'])) {
      $item['location'] = array($item['longitude'], $item['latitude']);
      unset($item['longitude']);
      unset($item['latitude']);
    }
  }
}
