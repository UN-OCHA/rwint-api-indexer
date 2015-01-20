<?php

namespace RWAPIIndexer\Resources;

/**
 * Disaster resource handler.
 */
class Disaster extends \RWAPIIndexer\Resource {
  // Options used for building the query to get the items to index.
  protected $query_options = array(
    'fields' => array(
      'description' => 'description',
    ),
    'field_joins' => array(
      'field_status' => array(
        'status' => 'value',
      ),
      'field_disaster_date' => array(
        'date' => 'value',
      ),
      'field_glide' => array(
        'glide' => 'value',
      ),
      'field_featured' => array(
        'featured' => 'value',
      ),
    ),
    'references' => array(
      'field_primary_country' => 'primary_country',
      'field_primary_disaster_type' => 'primary_type',
      'field_country' => 'country',
      'field_disaster_type' => 'type',
    ),
  );

// Options used to process the entity items before indexing.
  protected $processing_options = array(
    'conversion' => array(
      'description' => array('links', 'html'),
      'date' => array('time'),
      'featured' => array('bool'),
      'country' => array('primary'),
      'type' => array('primary'),
      'primary_country' => array('single'),
      'primary_type' => array('single'),
    ),
    'references' => array(
      'primary_country' => array(
        'country' => array('id', 'name', 'shortname', 'iso3', 'location'),
      ),
      'primary_type' => array(
        'disaster_type' => array('id', 'name', 'code'),
      ),
      'country' => array(
        'country' => array('id', 'name', 'shortname', 'iso3', 'location'),
      ),
      'type' => array(
        'disaster_type' => array('id', 'name', 'code'),
      ),
    ),
  );

  /**
   * Return the mapping for the current indexable.
   *
   * @return array
   *   Elasticsearch index type mapping.
   */
  public function getMapping() {
    $mapping = new \RWAPIIndexer\Mapping();
    $mapping->addInteger('id')
            ->addString('url', FALSE)
            ->addString('status', FALSE)
            ->addDates('date', array('created'))
            ->addBoolean('featured')
            ->addBoolean('current')
            // Names.
            ->addString('name', TRUE, TRUE)
            ->addString('glide', TRUE, TRUE)
            // Description.
            ->addString('description')
            ->addString('description-html', NULL)
            // Primary country.
            ->addTaxonomy('primary_country', array('shortname', 'iso3'))
            ->addGeoPoint('primary_country.location')
            // Country.
            ->addTaxonomy('country', array('shortname', 'iso3'))
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
   * Process an item, preparing for the indexing.
   *
   * @param array $item
   *   Item to process.
   */
  public function processItem(&$item) {
    // Handle date.
    $item['date'] = array('created' => $item['date']);

    // Current.
    $item['current'] = !empty($item['status']) && $item['status'] === 'current';
  }
}
