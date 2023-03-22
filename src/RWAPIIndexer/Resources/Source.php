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
      'field_longname' => [
        'longname' => 'value',
      ],
      'field_spanish_name' => [
        'spanish_name' => 'value',
      ],
      'field_homepage' => [
        'homepage' => 'uri',
      ],
      'field_allowed_content_types' => [
        'content_type' => 'multi_value',
      ],
      'field_fts_id' => [
        'fts_id' => 'value',
      ],
      'field_logo' => [
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
      'date_created' => ['time'],
      'date_changed' => ['time'],
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
      ->addString('redirects', FALSE)
      ->addStatus()
      ->addString('homepage', FALSE)
      ->addString('content_type', FALSE)
      // Names.
      ->addString('name', TRUE, TRUE, '', TRUE, ['en'])
      ->addString('shortname', TRUE, TRUE, '', TRUE, ['en'])
      ->addString('longname', TRUE, TRUE, '', TRUE, ['en'])
      ->addString('spanish_name', TRUE, TRUE, '', TRUE, ['es'])
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
      // Dates.
      ->addDates('date', ['created', 'changed'])
      // Disclaimer.
      ->addString('disclaimer', FALSE);

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
