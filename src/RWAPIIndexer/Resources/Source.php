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
      'field_longname' => [
        'longname' => 'value',
      ],
      'field_spanish_name' => [
        'spanish_name' => 'value',
      ],
      'field_homepage' => [
        'homepage' => 'url',
      ],
      'field_allowed_content_types' => [
        'content_type' => 'multi_value',
      ],
      'field_fts_id' => [
        'fts_id' => 'value',
      ],
      'field_term_image' => [
        'logo' => 'image_reference',
      ],
      'field_disclaimer' => [
        'disclaimer' => 'value',
      ],
    ],
    'references' => [
      'field_organization_type' => 'type',
      'field_country' => 'country',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected $processingOptions = [
    'conversion' => [
      'description' => ['links', 'html'],
      'content_type' => ['multi_int'],
      'type' => ['single'],
      'fts_id' => ['int'],
    ],
    'references' => [
      'type' => [
        'organization_type' => ['id', 'name'],
      ],
      'country' => [
        'country' => ['id', 'name', 'shortname', 'iso3', 'location'],
      ],
    ],
  ];

  /**
   * Allowed content types for a source.
   *
   * @var array
   */
  protected $contentTypes = ['job', 'report', 'training'];

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
      ->addTaxonomy('country', ['shortname', 'iso3'])
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
