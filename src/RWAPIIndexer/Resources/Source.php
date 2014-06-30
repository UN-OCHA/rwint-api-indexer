<?php

namespace RWAPIIndexer\Resources;

/**
 * Source resource handler.
 */
class Source extends \RWAPIIndexer\Resource {
  // Entity type and bundle.
  protected $entity_type = 'taxonomy_term';
  protected $bundle = 'source';

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
      'field_longname' => array(
        'longname' => 'value',
      ),
      'field_organization_type' => array(
        'type' => 'tid',
      ),
      'field_homepage' => array(
        'homepage' => 'url',
      ),
      'field_country' => array(
        'country' => 'taxonomy_reference',
      ),
      'field_allowed_content_types' => array(
        'content_type' => 'multi_value',
      ),
    ),
  );

  // Options used to process the entity items before indexing.
  protected $processing_options = array(
    'conversion' => array(
      'description' => array('links', 'html'),
      'content_type' => array('multi_int'),
    ),
    'references' => array(
      'type' => array(
        'organization_type' => array('id', 'name'),
      ),
      'country' => array(
        'country' => array('id', 'name', 'shortname', 'iso3', 'location'),
      ),
    ),
  );

  // Allowed content types for a source.
  protected $content_types = array('job', 'report', 'training');

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
            ->addString('homepage', NULL)
            ->addString('content_type', FALSE)
            // Names.
            ->addString('name', TRUE, TRUE)
            ->addString('shortname', TRUE, TRUE)
            ->addString('longname', TRUE, TRUE)
            // Description.
            ->addString('description')
            ->addString('description-html', NULL)
            // Country.
            ->addTaxonomy('country', array('shortname', 'iso3'))
            ->addGeoPoint('country.location')
            // Organization type.
            ->addTaxonomy('type');

    return $mapping->export();
  }

  /**
   * Process an item, preparing for the indexing.
   *
   * @param array $item
   *   Item to process.
   */
  public function processItem(&$item) {
    // Content type.
    if (!empty($item['content_type'])) {
      foreach ($item['content_type'] as $key => $value) {
        $item['content_type'][$key] = $this->content_types[(int) $value];
      }
    }
  }
}
