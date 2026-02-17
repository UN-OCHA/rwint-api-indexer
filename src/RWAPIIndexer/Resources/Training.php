<?php

declare(strict_types=1);

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Mapping;
use RWAPIIndexer\Resource;

/**
 * Training resource handler.
 *
 * @phpstan-type TrainingProcessItem array{
 *   id: int,
 *   timestamp: string,
 *   url: string,
 *   url_alias?: string,
 *   redirects?: array<int, string>,
 *   title?: string,
 *   body?: string,
 *   date_created?: int,
 *   date_changed?: int,
 *   date_registration?: int,
 *   date_start?: int,
 *   date_end?: int,
 *   status?: string,
 *   event_url?: string,
 *   cost?: string,
 *   fee_information?: string,
 *   how_to_register?: string,
 *   city?: string,
 *   country?: array<int, array<string, mixed>>,
 *   source?: array<int, array<string, mixed>>,
 *   language?: array<int, array<string, mixed>>,
 *   theme?: array<int, array<string, mixed>>,
 *   type?: array<int, array<string, mixed>>,
 *   format?: array<int, array<string, mixed>>,
 *   training_language?: array<int, array<string, mixed>>,
 *   career_categories?: array<int, array<string, mixed>>,
 * }
 */
class Training extends Resource {

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
      'field_cost' => [
        'cost' => 'value',
      ],
      'field_registration_deadline' => [
        'date_registration' => 'value',
      ],
      'field_training_date' => [
        'date_start' => 'value',
        'date_end' => 'end_value',
      ],
      'body' => [
        'body' => 'value',
      ],
      'field_link' => [
        'event_url' => 'uri',
      ],
      'field_fee_information' => [
        'fee_information' => 'value',
      ],
      'field_how_to_register' => [
        'how_to_register' => 'value',
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
      'field_training_type' => 'type',
      'field_training_format' => 'format',
      'field_training_language' => 'training_language',
      'field_career_categories' => 'career_categories',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected array $processingOptions = [
    'conversion' => [
      'body' => ['links', 'html_strict'],
      'how_to_register' => ['links', 'html_strict'],
      'date_created' => ['time'],
      'date_changed' => ['time'],
      'date_registration' => ['time'],
      'date_start' => ['time'],
      'date_end' => ['time'],
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
        'training_type' => ['id', 'name'],
      ],
      'format' => [
        'training_format' => ['id', 'name'],
      ],
      'training_language' => [
        'language' => ['id', 'name', 'code'],
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
      // Event URL.
      ->addString('event_url', FALSE)
      // Fee information.
      ->addString('fee_information')
      // How to register.
      ->addString('how_to_register')
      ->addString('how_to_register-html', NULL)
      // Dates.
      ->addDates('date', [
        'created',
        'changed',
        'registration',
        'start',
        'end',
      ])
      // Cost.
      ->addString('cost', FALSE)
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
      ->addTaxonomy('format')
      ->addTaxonomy('theme')
      ->addTaxonomy('career_categories')
      // Training language.
      ->addTaxonomy('training_language')
      ->addString('training_language.code', FALSE)
      // File.
      ->addFile('file');

    return $mapping->export();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(array &$item): void {
    /** @var TrainingProcessItem $item */

    // Handle dates.
    if (isset($item['date_created'])) {
      $item['date']['created'] = $item['date_created'];
      unset($item['date_created']);
    }
    if (isset($item['date_changed'])) {
      $item['date']['changed'] = $item['date_changed'];
      unset($item['date_changed']);
    }
    if (isset($item['date_registration'])) {
      $item['date']['registration'] = $item['date_registration'];
      unset($item['date_registration']);
    }
    if (isset($item['date_start'])) {
      $item['date']['start'] = $item['date_start'];
      unset($item['date_start']);
    }
    if (isset($item['date_end'])) {
      $item['date']['end'] = $item['date_end'];
      unset($item['date_end']);
    }

    // Handle event URL.
    if (empty($item['event_url'])) {
      unset($item['event_url']);
    }

    // Handle city. Keep compatibility.
    if (isset($item['city'])) {
      $item['city'] = [['name' => $item['city']]];
    }

    // Handle fee information. Remove if cost is free.
    if (isset($item['cost'], $item['fee_information']) && $item['cost'] === 'free') {
      unset($item['fee_information']);
    }
  }

}
