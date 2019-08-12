<?php

namespace RWAPIIndexer\Resources;

use RWAPIIndexer\Resource;
use RWAPIIndexer\Mapping;

/**
 * Training resource handler.
 */
class Training extends Resource {

  /**
   * {@inheritdoc}
   */
  protected $queryOptions = array(
    'fields' => array(
      'title' => 'title',
      'date_created' => 'created',
      'date_changed' => 'changed',
    ),
    'field_joins' => array(
      'field_status' => array(
        'status' => 'value',
      ),
      'field_cost' => array(
        'cost' => 'value',
      ),
      'field_registration_deadline' => array(
        'date_registration' => 'value',
      ),
      'field_training_date' => array(
        'date_start' => 'value',
        'date_end' => 'value2',
      ),
      'body' => array(
        'body' => 'value',
      ),
      'field_fee_information' => array(
        'fee_information' => 'value',
      ),
      'field_how_to_apply' => array(
        'how_to_register' => 'value',
      ),
      'field_city' => array(
        'city' => 'value',
      ),
      'field_file' => array(
        'file' => 'file_reference',
      ),
    ),
    'references' => array(
      'field_country' => 'country',
      'field_source' => 'source',
      'field_language' => 'language',
      'field_theme' => 'theme',
      'field_training_type' => 'type',
      'field_training_format' => 'format',
      'field_training_language' => 'training_language',
      'field_career_categories' => 'career_categories',
    ),
  );

  /**
   * {@inheritdoc}
   */
  protected $processingOptions = array(
    'conversion' => array(
      'body' => array('links', 'html_strict'),
      'how_to_register' => array('links', 'html_strict'),
      'date_created' => array('time'),
      'date_changed' => array('time'),
      'date_registration' => array('time'),
      'date_start' => array('time'),
      'date_end' => array('time'),
    ),
    'references' => array(
      'country' => array(
        'country' => array('id', 'name', 'shortname', 'iso3', 'location'),
      ),
      'source' => array(
        'source' => array(
          'id',
          'name',
          'shortname',
          'longname',
          'spanish_name',
          'type',
          'homepage',
        ),
      ),
      'language' => array(
        'language' => array('id', 'name', 'code'),
      ),
      'theme' => array(
        'theme' => array('id', 'name'),
      ),
      'type' => array(
        'training_type' => array('id', 'name'),
      ),
      'format' => array(
        'training_format' => array('id', 'name'),
      ),
      'training_language' => array(
        'language' => array('id', 'name', 'code'),
      ),
      'career_categories' => array(
        'career_categories' => array('id', 'name'),
      ),
    ),
  );

  /**
   * {@inheritdoc}
   */
  public function getMapping() {
    $mapping = new Mapping();
    $mapping->addInteger('id')
      ->addString('url', FALSE)
      ->addString('url_alias', FALSE)
      ->addString('status', FALSE)
      ->addString('title', TRUE, TRUE)
      // Body.
      ->addString('body')
      ->addString('body-html', NULL)
      // Fee information.
      ->addString('fee_information')
      // How to register.
      ->addString('how_to_register')
      ->addString('how_to_register-html', NULL)
      // Dates.
      ->addDates('date', array(
        'created',
        'changed',
        'registration',
        'start',
        'end',
      ))
      // Cost.
      ->addString('cost', FALSE)
      // Language.
      ->addTaxonomy('language')
      ->addString('language.code', FALSE)
      // Country.
      ->addTaxonomy('country', array('shortname', 'iso3'))
      ->addGeoPoint('country.location')
      // Source.
      ->addTaxonomy('source', array('shortname', 'longname', 'spanish_name'))
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
  public function processItem(&$item) {
    // Handle dates.
    $item['date'] = array(
      'created' => $item['date_created'],
      'changed' => $item['date_changed'],
    );
    if (isset($item['date_registration'])) {
      $item['date']['registration'] = $item['date_registration'];
    }
    if (isset($item['date_start'])) {
      $item['date']['start'] = $item['date_start'];
    }
    if (isset($item['date_end'])) {
      $item['date']['end'] = $item['date_end'];
    }
    unset($item['date_created']);
    unset($item['date_changed']);
    unset($item['date_registration']);
    unset($item['date_start']);
    unset($item['date_end']);

    // Handle city. Keep compatibility.
    if (isset($item['city'])) {
      $item['city'] = array(array('name' => $item['city']));
    }

    // Handle File.
    if ($this->processor->processFile($item['file']) !== TRUE) {
      unset($item['file']);
    }

    // Handle fee information. Remove if cost is free.
    if (isset($item['cost'], $item['fee_information']) && $item['cost'] === 'free') {
      unset($item['fee_information']);
    }
  }

}
