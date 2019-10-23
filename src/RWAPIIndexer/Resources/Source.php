<?php

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Resource;
use RWAPIIndexer\Mapping;

/**
 * Source resource handler.
 */
class Source extends Resource {

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
      'field_longname' => array(
        'longname' => 'value',
      ),
      'field_spanish_name' => array(
        'spanish_name' => 'value',
      ),
      'field_homepage' => array(
        'homepage' => 'url',
      ),
      'field_allowed_content_types' => array(
        'content_type' => 'multi_value',
      ),
      'field_fts_id' => array(
        'fts_id' => 'value',
      ),
      'field_term_image' => array(
        'logo' => 'image_reference',
      ),
      'field_disclaimer' => array(
        'disclaimer' => 'value',
      ),
    ),
    'references' => array(
      'field_organization_type' => 'type',
      'field_country' => 'country',
    ),
  );

  /**
   * {@inheritdoc}
   */
  protected $processingOptions = array(
    'conversion' => array(
      'description' => array('links', 'html'),
      'content_type' => array('multi_int'),
      'type' => array('single'),
      'fts_id' => array('int'),
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

  /**
   * Allowed content types for a source.
   *
   * @var array
   */
  protected $contentTypes = array('job', 'report', 'training');

  /**
   * {@inheritdoc}
   */
  public function getMapping() {
    $mapping = new Mapping();
    $mapping->addInteger('id')
      ->addString('url', FALSE)
      ->addString('url_alias', FALSE)
      ->addString('status', FALSE)
      ->addString('homepage', FALSE)
      ->addString('content_type', FALSE)
      // Names.
      ->addString('name', TRUE, TRUE, '', TRUE)
      ->addString('shortname', TRUE, TRUE, '', TRUE)
      ->addString('longname', TRUE, TRUE, '', TRUE)
      ->addString('spanish_name', TRUE, TRUE, '', TRUE)
      // Description.
      ->addString('description')
      ->addString('description-html', NULL)
      // Country.
      ->addTaxonomy('country', array('shortname', 'iso3'))
      ->addGeoPoint('country.location')
      // Organization type.
      ->addTaxonomy('type')
      // FTS ID.
      ->addInteger('fts_id')
      // Logo.
      ->addImage('logo')
      // Disclaimer.
      ->addString('disclaimer', FALSE);

    return $mapping->export();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(&$item) {
    // Content type.
    if (!empty($item['content_type'])) {
      foreach ($item['content_type'] as $key => $value) {
        $item['content_type'][$key] = $this->contentTypes[(int) $value];
      }
    }

    // Handle logo.
    if ($this->processor->processImage($item['logo'], TRUE, FALSE, FALSE) !== TRUE) {
      unset($item['logo']);
    }
  }

}
