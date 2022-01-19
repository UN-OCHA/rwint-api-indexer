<?php

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Resource;
use RWAPIIndexer\Mapping;

/**
 * Job resource handler.
 */
class Job extends Resource {

  /**
   * {@inheritdoc}
   */
  protected $queryOptions = [
    'fields' => [
      'title' => 'title',
      'date_created' => 'created',
      'date_changed' => 'changed',
      'status' => 'moderation_status',
    ],
    'field_joins' => [
      'field_job_closing_date' => [
        'date_closing' => 'value',
      ],
      'body' => [
        'body' => 'value',
      ],
      'field_how_to_apply' => [
        'how_to_apply' => 'value',
      ],
      'field_city' => [
        'city' => 'value',
      ],
    ],
    'references' => [
      'field_country' => 'country',
      'field_source' => 'source',
      'field_language' => 'language',
      'field_theme' => 'theme',
      'field_job_type' => 'type',
      'field_job_experience' => 'experience',
      'field_career_categories' => 'career_category',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected $processingOptions = [
    'conversion' => [
      'body' => ['links', 'html_strict'],
      'how_to_apply' => ['links', 'html_strict'],
      'date_created' => ['time'],
      'date_changed' => ['time'],
      'date_closing' => ['time'],
    ],
    'references' => [
      'country' => [
        'country' => ['id', 'name', 'shortname', 'iso3', 'location'],
      ],
      'source' => [
        'source' => [
          'id',
          'name',
          'shortname',
          'longname',
          'spanish_name',
          'type',
          'homepage',
        ],
      ],
      'language' => [
        'language' => ['id', 'name', 'code'],
      ],
      'theme' => [
        'theme' => ['id', 'name'],
      ],
      'type' => [
        'job_type' => ['id', 'name'],
      ],
      'experience' => [
        'job_experience' => ['id', 'name'],
      ],
      'career_categories' => [
        'career_category' => ['id', 'name'],
      ],
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
      ->addStatus()
      ->addString('title', TRUE, TRUE)
      // Body.
      ->addString('body')
      ->addString('body-html', NULL)
      // How to apply.
      ->addString('how_to_apply')
      ->addString('how_to_apply-html', NULL)
      // Dates.
      ->addDates('date', ['created', 'changed', 'closing'])
      // Language.
      ->addTaxonomy('language')
      ->addString('language.code', FALSE)
      // Country.
      ->addTaxonomy('country', ['shortname', 'iso3'])
      ->addGeoPoint('country.location')
      // Source.
      ->addTaxonomy('source', ['shortname', 'longname', 'spanish_name'])
      ->addString('source.homepage', NULL)
      ->addTaxonomy('source.type')
      // Other taxonomies.
      ->addTaxonomy('city')
      ->addTaxonomy('type')
      ->addTaxonomy('theme')
      ->addTaxonomy('career_categories')
      ->addTaxonomy('experience')
      // File.
      ->addFile('file');

    return $mapping->export();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(&$item) {
    // Handle dates.
    $item['date'] = [
      'created' => $item['date_created'],
      'changed' => $item['date_changed'],
    ];
    if (isset($item['date_closing'])) {
      $item['date']['closing'] = $item['date_closing'];
    }
    unset($item['date_created']);
    unset($item['date_changed']);
    unset($item['date_closing']);

    // Handle city. Keep compatibility.
    if (isset($item['city'])) {
      $item['city'] = [['name' => $item['city']]];
    }
  }

}
