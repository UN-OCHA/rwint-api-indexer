<?php

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Resource;
use RWAPIIndexer\Mapping;

/**
 * Topic resource handler.
 */
class Topic extends Resource {

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
  protected $processingOptions = [
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
  public function getMapping() {
    $mapping = new Mapping();
    $mapping->addInteger('id')
      ->addString('url', FALSE)
      ->addString('url_alias', FALSE)
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
  public function processItem(&$item) {
    // Handle dates.
    $item['date'] = [
      'created' => $item['date_created'],
      'changed' => $item['date_changed'],
    ];
    unset($item['date_created']);
    unset($item['date_changed']);

    // Handle icon.
    if ($this->processor->processImage($item['icon'], TRUE, FALSE, FALSE) !== TRUE) {
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
      if (!empty($item[$id . '_search']) && $this->processor->processRiverSearch($item[$id . '_search'], TRUE)) {
        $rivers[$id] += $item[$id . '_search'];
      }
      else {
        unset($rivers[$id]);
      }
      unset($item[$id . '_search']);
    }

    if (!empty($item['sections']) && $this->processor->processRiverSearch($item['sections'])) {
      foreach ($item['sections'] as $index => $section) {
        $rivers[] = $section + [
          'id' => 'section-' . ($index + 1),
        ];
      }
    }
    unset($item['sections']);

    if (!empty($rivers)) {
      $item['rivers'] = array_values($rivers);
    }
  }

}
