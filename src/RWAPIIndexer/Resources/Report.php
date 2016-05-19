<?php

namespace RWAPIIndexer\Resources;

/**
 * Report resource handler.
 */
class Report extends \RWAPIIndexer\Resource {
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
      'field_report_date' => array(
        'date_original' => 'value',
      ),
      'body' => array(
        'body' => 'value',
        'headline_summary' => 'summary',
      ),
      'field_headline' => array(
        'headline' => 'value',
      ),
      'field_headline_title' => array(
        'headline_title' => 'value',
      ),
      'field_headline_image' => array(
        'headline_image' => 'image_reference',
      ),
      'field_headline_featured' => array(
        'headline_featured' => 'value',
      ),
      'field_image' => array(
        'image' => 'image_reference',
      ),
      'field_file' => array(
        'file' => 'file_reference',
      ),
      'field_origin_notes' => array(
        'origin' => 'value',
      ),
    ),
    'references' => array(
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
    ),
  );

  // Options used to process the entity items before indexing.
  protected $processing_options = array(
    'conversion' => array(
      'body' => array('links', 'html'),
      'date_created' => array('time'),
      'date_changed' => array('time'),
      'date_original' => array('time'),
      'headline' => array('bool'),
      'headline_featured' => array('bool'),
      'country' => array('primary'),
      'primary_country' => array('single'),
    ),
    'references' => array(
      'primary_country' => array(
        'country' => array('id', 'name', 'shortname', 'iso3', 'location'),
      ),
      'country' => array(
        'country' => array('id', 'name', 'shortname', 'iso3', 'location'),
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
      'format' => array(
        'content_format' => array('id', 'name'),
      ),
      'ocha_product' => array(
        'ocha_product' => array('id', 'name'),
      ),
      'disaster' => array(
        'disaster' => array('id', 'name', 'glide', 'type'),
      ),
      'disaster_type' => array(
        'disaster_type' => array('id', 'name', 'code'),
      ),
      'vulnerable_groups' => array(
        'vulnerable_groups' => array('id', 'name'),
      ),
      'feature' => array(
        'feature' => array('id', 'name'),
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
            ->addString('url_alias', FALSE)
            ->addString('status', FALSE)
            ->addString('title', TRUE, TRUE)
            ->addString('origin', FALSE)
            // Body.
            ->addString('body')
            ->addString('body-html', NULL)
            // Dates.
            ->addDates('date', array('created', 'changed', 'original'))
            // Headline.
            ->addString('headline.title', TRUE, TRUE)
            ->addString('headline.summary')
            ->addImage('headline.image')
            ->addBoolean('headline.featured')
            // Language.
            ->addTaxonomy('language')
            ->addString('language.code', FALSE)
            // Primary country.
            ->addTaxonomy('primary_country', array('shortname', 'iso3'))
            ->addGeoPoint('primary_country.location')
            // Country.
            ->addTaxonomy('country', array('shortname', 'iso3'))
            ->addGeoPoint('country.location')
            ->addBoolean('country.primary')
            // Source.
            ->addTaxonomy('source', array('shortname', 'longname'))
            ->addString('source.homeage', NULL)
            ->addTaxonomy('source.type')
            // Disaster.
            ->addTaxonomy('disaster', array('glide'))
            ->addTaxonomy('disaster.type')
            ->addString('disaster.type.code', FALSE)
            ->addBoolean('disaster.type.primary')
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
      'original' => !empty($item['date.original']) ? $item['date_original'] : $item['date_created'],
    );
    unset($item['date_created']);
    unset($item['date_changed']);
    unset($item['date_original']);

    // Handle headline.
    if (!empty($item['headline'])) {
      if (!empty($item['headline_title'])) {
        $headline = array();
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
        if ($this->processor->processImage($item['headline_image'], TRUE) === TRUE) {
          $headline['image'] = $item['headline_image'];
        }
        // Headline featured.
        if (!empty($item['headline_featured'])) {
          $headline['featured'] = TRUE;
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
    unset($item['headline_featured']);

    // Handle image.
    if ($this->processor->processImage($item['image'], TRUE) !== TRUE) {
      unset($item['image']);
    }

    // Handle File.
    if ($this->processor->processFile($item['file']) !== TRUE) {
      unset($item['file']);
    }
  }
}
