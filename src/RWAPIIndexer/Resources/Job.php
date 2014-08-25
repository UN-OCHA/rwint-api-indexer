<?php

namespace RWAPIIndexer\Resources;

/**
 * Job resource handler.
 */
class Job extends \RWAPIIndexer\Resource {
  // Options used for building the query to get the items to index.
  protected $query_options = array(
    'fields' => array(
      'title' => 'title',
      'date_created' => 'created',
      'date_changed' => 'changed',
    ),
    'field_joins' => array(
      'field_status' => array(
        'status' => 'value',
      ),
      'field_job_closing_date' => array(
        'date_closing' => 'value',
      ),
      'body' => array(
        'body' => 'value',
      ),
      'field_how_to_apply' => array(
        'how_to_apply' => 'value',
      ),
      'field_file' => array(
        'file' => 'file_reference',
      ),
    ),
    'references' => array(
      'field_country' => 'country',
      'field_city' => 'city',
      'field_source' => 'source',
      'field_language' => 'language',
      'field_theme' => 'theme',
      'field_job_type' => 'type',
      'field_job_experience' => 'experience',
      'field_career_categories' => 'career_categories',
    ),
  );

  // Options used to process the entity items before indexing.
  protected $processing_options = array(
    'conversion' => array(
      'body' => array('links', 'html'),
      'how_to_apply' => array('links', 'html'),
      'date_created' => array('time'),
      'date_changed' => array('time'),
      'date_closing' => array('time'),
    ),
    'references' => array(
      'country' => array(
        'country' => array('id', 'name', 'shortname', 'iso3', 'location'),
      ),
      'city' => array(
        'city' => array('id', 'name'),
      ),
      'source' => array(
        'source' => array('id', 'name', 'shortname', 'longname', 'type', 'homepage'),
      ),
      'language' => array(
        'language' => array('id', 'name', 'code'),
      ),
      'theme' => array(
        'theme' => array('id', 'name'),
      ),
      'type' => array(
        'job_type' => array('id', 'name'),
      ),
      'experience' => array(
        'job_experience' => array('id', 'name'),
      ),
      'career_categories' => array(
        'career_categories' => array('id', 'name'),
      ),
    ),
  );

  /**
   * Return the mapping for the current indexable.
   *
   * @return array
   *   Elasticsearch index type mapping.
   */
  public function getMapping() {
    $mapping = new \RWAPIIndexer\Mapping();
    $mapping->addInteger('id')
            ->addString('url', FALSE)
            ->addString('status', FALSE)
            ->addString('title', TRUE, TRUE)
            // Body.
            ->addString('body')
            ->addString('body-html', NULL)
            // How to apply.
            ->addString('how_to_apply')
            ->addString('how_to_apply-html', NULL)
            // Dates.
            ->addDates('date', array('created', 'changed', 'closing'))
            // Language.
            ->addTaxonomy('language')
            ->addString('language.code', FALSE)
            // Country.
            ->addTaxonomy('country', array('shortname', 'iso3'))
            ->addGeoPoint('country.location')
            // Source.
            ->addTaxonomy('source', array('shortname', 'longname'))
            ->addString('source.homeage', NULL)
            ->addTaxonomy('source.type')
            // Other taxonomies
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
   * Process an item, preparing for the indexing.
   *
   * @param array $item
   *   Item to process.
   */
  public function processItem(&$item) {
    // Handle dates.
    $item['date'] = array(
      'created' => $item['date_created'],
      'changed' => $item['date_changed'],
      'closing' => $item['date_closing'],
    );
    unset($item['date_created']);
    unset($item['date_changed']);
    unset($item['date_closing']);

    // Handle File.
    if ($this->processor->processFile($item['file']) !== TRUE) {
      unset($item['file']);
    }
  }
}
