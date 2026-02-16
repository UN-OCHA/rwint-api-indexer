<?php

declare(strict_types=1);

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Mapping;
use RWAPIIndexer\Resource;

/**
 * Job resource handler.
 *
 * @phpstan-type JobProcessItem array{
 *   id: int,
 *   timestamp: string,
 *   url: string,
 *   url_alias?: string,
 *   redirects?: array<int, string>,
 *   title?: string,
 *   body?: string,
 *   how_to_apply?: string,
 *   date_created?: int,
 *   date_changed?: int,
 *   date_closing?: int,
 *   status?: string,
 *   city?: string,
 *   country?: array<int, array<string, mixed>>,
 *   source?: array<int, array<string, mixed>>,
 *   language?: array<int, array<string, mixed>>,
 *   theme?: array<int, array<string, mixed>>,
 *   type?: array<int, array<string, mixed>>,
 *   experience?: array<int, array<string, mixed>>,
 *   career_categories?: array<int, array<string, mixed>>,
 * }
 */
class Job extends Resource {

  /**
   * {@inheritdoc}
   */
  protected array $queryOptions = [
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
      'field_career_categories' => 'career_categories',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected array $processingOptions = [
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
  public function getMapping(): array {
    $mapping = new Mapping();
    $mapping->addInteger('id')
      ->addString('uuid', FALSE)
      ->addString('url', FALSE)
      ->addString('url_alias', FALSE)
      ->addString('redirects', FALSE)
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
  public function processItem(array &$item): void {
    /** @var JobProcessItem $item */

    // Handle dates.
    if (isset($item['date_created'])) {
      $item['date']['created'] = $item['date_created'];
      unset($item['date_created']);
    }
    if (isset($item['date_changed'])) {
      $item['date']['changed'] = $item['date_changed'];
      unset($item['date_changed']);
    }
    if (isset($item['date_closing'])) {
      $item['date']['closing'] = $item['date_closing'];
      unset($item['date_closing']);
    }

    // Handle city. Keep compatibility.
    if (isset($item['city'])) {
      $item['city'] = [['name' => $item['city']]];
    }
  }

}
