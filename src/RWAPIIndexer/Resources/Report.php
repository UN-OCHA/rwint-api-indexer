<?php

declare(strict_types=1);

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Mapping;
use RWAPIIndexer\Resource;

/**
 * Report resource handler.
 *
 * @phpstan-type ReportProcessItem array{
 *   id: int,
 *   timestamp: string,
 *   url: string,
 *   url_alias?: string,
 *   redirects?: array<int, string>,
 *   title?: string,
 *   body?: string,
 *   'body-html'?: string,
 *   date_created?: int,
 *   date_changed?: int,
 *   date_original?: int,
 *   status?: string,
 *   headline?: bool,
 *   headline_title?: string,
 *   headline_summary?: string,
 *   headline_image?: mixed,
 *   image?: mixed,
 *   file?: mixed,
 *   origin?: string,
 *   primary_country?: mixed,
 *   country?: array<int, array<string, mixed>>,
 *   source?: array<int, array<string, mixed>>,
 *   language?: array<int, array<string, mixed>>,
 *   theme?: array<int, array<string, mixed>>,
 *   format?: array<int, array<string, mixed>>,
 *   ocha_product?: array<int, array<string, mixed>>,
 *   disaster?: array<int, array<string, mixed>>,
 *   disaster_type?: array<int, array<string, mixed>>,
 *   vulnerable_groups?: array<int, array<string, mixed>>,
 *   feature?: array<int, array<string, mixed>>,
 * }
 */
class Report extends Resource {

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
      'field_original_publication_date' => [
        'date_original' => 'value',
      ],
      'body' => [
        'body' => 'value',
      ],
      'field_headline' => [
        'headline' => 'value',
      ],
      'field_headline_title' => [
        'headline_title' => 'value',
      ],
      'field_headline_summary' => [
        'headline_summary' => 'value',
      ],
      'field_headline_image' => [
        'headline_image' => 'image_reference',
      ],
      'field_image' => [
        'image' => 'image_reference',
      ],
      'field_file' => [
        'file' => 'file_reference',
      ],
      'field_origin_notes' => [
        'origin' => 'value',
      ],
    ],
    'references' => [
      'field_primary_country' => 'primary_country',
      'field_country' => 'country',
      'field_source' => 'source',
      'field_language' => 'language',
      'field_theme' => 'theme',
      'field_content_format' => 'format',
      'field_ocha_product' => 'ocha_product',
      'field_disaster' => 'disaster',
      'field_disaster_type' => 'disaster_type',
      'field_vulnerable_groups' => 'vulnerable_groups',
      'field_feature' => 'feature',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected array $processingOptions = [
    'conversion' => [
      'body' => ['links', 'html'],
      'date_created' => ['time'],
      'date_changed' => ['time'],
      'date_original' => ['time'],
      'headline' => ['bool'],
      'country' => ['primary'],
      'primary_country' => ['single'],
    ],
    'references' => [
      'primary_country' => [
        'country' => ['id', 'name', 'shortname', 'iso3', 'location'],
      ],
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
          'disclaimer',
        ],
      ],
      'language' => [
        'language' => ['id', 'name', 'code'],
      ],
      'theme' => [
        'theme' => ['id', 'name'],
      ],
      'format' => [
        'content_format' => ['id', 'name'],
      ],
      'ocha_product' => [
        'ocha_product' => ['id', 'name'],
      ],
      'disaster' => [
        'disaster' => ['id', 'name', 'glide', 'type', 'status'],
      ],
      'disaster_type' => [
        'disaster_type' => ['id', 'name', 'code'],
      ],
      'vulnerable_groups' => [
        'vulnerable_group' => ['id', 'name'],
      ],
      'feature' => [
        'feature' => ['id', 'name'],
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
      ->addString('origin', FALSE)
      // Body.
      ->addString('body')
      ->addString('body-html', NULL)
      // Dates.
      ->addDates('date', ['created', 'changed', 'original'])
      // Headline.
      ->addString('headline.title', TRUE, TRUE)
      ->addString('headline.summary')
      ->addImage('headline.image')
      // Language.
      ->addTaxonomy('language')
      ->addString('language.code', FALSE)
      // Primary country.
      ->addTaxonomy('primary_country', ['shortname', 'iso3'])
      ->addGeoPoint('primary_country.location')
      // Country.
      ->addTaxonomy('country', ['shortname', 'iso3'])
      ->addGeoPoint('country.location')
      ->addBoolean('country.primary')
      // Source.
      ->addTaxonomy('source', ['shortname', 'longname', 'spanish_name'])
      ->addString('source.homepage', NULL)
      ->addString('source.disclaimer', NULL)
      ->addTaxonomy('source.type')
      // Disaster.
      ->addTaxonomy('disaster', ['glide'])
      ->addTaxonomy('disaster.type')
      ->addString('disaster.type.code', FALSE)
      ->addBoolean('disaster.type.primary')
      ->addString('disaster.status', FALSE)
      // Other taxonomies.
      ->addTaxonomy('format')
      ->addTaxonomy('theme')
      ->addTaxonomy('disaster_type')
      ->addString('disaster_type.code', FALSE)
      ->addTaxonomy('vulnerable_groups')
      ->addTaxonomy('ocha_product')
      ->addTaxonomy('feature')
      // File and image.
      ->addImage('image')
      ->addFile('file');

    return $mapping->export();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(array &$item): void {
    /** @var ReportProcessItem $item */

    // Handle dates.
    if (isset($item['date_created'])) {
      $item['date']['created'] = $item['date_created'];
      unset($item['date_created']);
    }
    if (isset($item['date_changed'])) {
      $item['date']['changed'] = $item['date_changed'];
      unset($item['date_changed']);
    }
    if (isset($item['date_original'])) {
      $item['date']['original'] = $item['date_original'];
      unset($item['date_original']);
    }
    elseif (isset($item['date']['created'])) {
      $item['date']['original'] = $item['date']['created'];
    }

    // Handle headline.
    if (!empty($item['headline'])) {
      if (!empty($item['headline_title'])) {
        $headline = [];
        $headline['title'] = $item['headline_title'];
        // Get the summary.
        if (!empty($item['headline_summary'])) {
          $headline['summary'] = $item['headline_summary'];
        }
        // Or extract it from the body if not defined.
        elseif (!empty($item['body-html'])) {
          $summary = strip_tags($item['body-html']);
          if (strlen($summary) > 300) {
            $summary_parts = explode("|||", wordwrap($summary, 300, "|||"));
            $summary = array_shift($summary_parts) . "...";
          }
          $headline['summary'] = $summary;
        }
        // Handle headline image.
        if ($this->processor->processImage($item, 'headline_image', TRUE) === TRUE) {
          $headline['image'] = $item['headline_image'];
        }
        $item['headline'] = $headline;
      }
      else {
        unset($item['headline']);
      }
    }
    else {
      unset($item['headline']);
    }
    unset($item['headline_title']);
    unset($item['headline_summary']);
    unset($item['headline_image']);

    // Handle image.
    if ($this->processor->processImage($item, 'image', TRUE) !== TRUE) {
      unset($item['image']);
    }

    // Handle File.
    if ($this->processor->processFile($item, 'file') !== TRUE) {
      unset($item['file']);
    }

    // Handle origin field. Discard origin that may be email addresses.
    if (isset($item['origin']) && is_string($item['origin']) && strpos($item['origin'], '@') !== FALSE) {
      unset($item['origin']);
    }

    // Handle disasters. Only index published disasters.
    if (!empty($item['disaster']) && is_array($item['disaster'])) {
      foreach ($item['disaster'] as $index => $disaster) {
        if (is_array($disaster)) {
          $status = $disaster['status'] ?? '';
          if ($status !== 'alert' && $status !== 'current' && $status !== 'ongoing' && $status !== 'past') {
            unset($item['disaster'][$index]);
          }
        }
      }
      if (empty($item['disaster'])) {
        unset($item['disaster']);
      }
      else {
        $item['disaster'] = array_values($item['disaster']);
      }
    }
  }

}
