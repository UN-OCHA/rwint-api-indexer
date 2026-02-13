<?php

declare(strict_types=1);

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Mapping;
use RWAPIIndexer\Resource;

/**
 * Topic resource handler.
 *
 * @phpstan-type TopicProcessItem array{
 *   id: int,
 *   timestamp: string,
 *   url: string,
 *   url_alias?: string,
 *   redirects?: array<int, string>,
 *   title?: string,
 *   introduction?: string,
 *   overview?: string,
 *   resources?: string,
 *   date_created?: int,
 *   date_changed?: int,
 *   status?: string,
 *   featured?: bool,
 *   disasters_search?: mixed,
 *   jobs_search?: mixed,
 *   reports_search?: mixed,
 *   training_search?: mixed,
 *   sections?: mixed,
 *   icon?: mixed,
 *   theme?: array<int, array<string, mixed>>,
 *   disaster_type?: array<int, array<string, mixed>>,
 * }
 */
class Topic extends Resource {

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
      'body' => [
        'introduction' => 'value',
      ],
      'field_overview' => [
        'overview' => 'value',
      ],
      'field_resources' => [
        'resources' => 'value',
      ],
      'field_disasters_search' => [
        'disasters_search' => 'river_search',
      ],
      'field_jobs_search' => [
        'jobs_search' => 'river_search',
      ],
      'field_reports_search' => [
        'reports_search' => 'river_search',
      ],
      'field_training_search' => [
        'training_search' => 'river_search',
      ],
      'field_sections' => [
        'sections' => 'river_search',
      ],
      'field_icon' => [
        'icon' => 'image_reference',
      ],
      'field_featured' => [
        'featured' => 'value',
      ],
    ],
    'references' => [
      'field_theme' => 'theme',
      'field_disaster_type' => 'disaster_type',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected array $processingOptions = [
    'conversion' => [
      'introduction' => ['links', 'html'],
      'overview' => ['links', 'html_iframe_disaster_map'],
      'resources' => ['links', 'html'],
      'date_created' => ['time'],
      'date_changed' => ['time'],
      'featured' => ['bool'],
    ],
    'references' => [
      'theme' => [
        'theme' => ['id', 'name'],
      ],
      'disaster_type' => [
        'disaster_type' => ['id', 'name', 'code'],
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
      ->addString('introduction')
      ->addString('introduction-html', NULL)
      ->addString('overview')
      ->addString('overview-html', NULL)
      ->addString('resources')
      ->addString('resources-html', NULL)
      // Dates.
      ->addDates('date', ['created', 'changed'])
      // Rivers.
      ->addRiverSearch('rivers')
      // Tags.
      ->addTaxonomy('theme')
      ->addTaxonomy('disaster_type')
      ->addString('disaster_type.code', FALSE)
      // Images.
      ->addImage('icon')
      // Flags.
      ->addBoolean('featured');

    return $mapping->export();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(array &$item): void {
    /** @var TopicProcessItem $item */

    // Handle dates.
    if (isset($item['date_created'])) {
      $item['date']['created'] = $item['date_created'];
      unset($item['date_created']);
    }
    if (isset($item['date_changed'])) {
      $item['date']['changed'] = $item['date_changed'];
      unset($item['date_changed']);
    }

    // Handle icon.
    if ($this->processor->processImage($item, 'icon', TRUE, FALSE, FALSE) !== TRUE) {
      unset($item['icon']);
    }

    // Handle rivers.
    $rivers = [
      'disasters' => [
        'id' => 'disasters',
        'title' => 'Disasters',
      ],
      'jobs' => [
        'id' => 'jobs',
        'title' => 'Jobs',
      ],
      'reports' => [
        'id' => 'reports',
        'title' => 'Latest Updates',
      ],
      'training' => [
        'id' => 'training',
        'title' => 'Training',
      ],
    ];

    foreach ($rivers as $id => $info) {
      if (!empty($item[$id . '_search']) && is_array($item[$id . '_search']) && $this->processor->processRiverSearch($item, $id . '_search', TRUE) === TRUE) {
        $rivers[$id] += $item[$id . '_search'];
      }
      else {
        unset($rivers[$id]);
      }
      unset($item[$id . '_search']);
    }

    if (!empty($item['sections']) && is_array($item['sections']) && $this->processor->processRiverSearch($item, 'sections') === TRUE) {
      foreach ($item['sections'] as $index => $section) {
        if (is_array($section)) {
          $rivers[] = $section + [
            'id' => 'section-' . ($index + 1),
          ];
        }
      }
    }
    unset($item['sections']);

    if (!empty($rivers)) {
      $item['rivers'] = array_values($rivers);
    }
  }

}
