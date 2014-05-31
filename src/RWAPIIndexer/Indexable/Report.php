<?php

namespace RWAPIIndexer\Indexable;

class Report extends AbstractIndexable {
  protected $entity_type = 'node';
  protected $entity_bundle = 'report';

  /**
   * Return the mapping for the current indexable.
   *
   * @return array
   *   Mapping.
   */
  public function getMapping() {
    $mapping = new \RWAPIIndexer\Mapping();
    $mapping->addInteger('id')
            ->addString('url', FALSE)
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
            // Language.
            ->addTaxonomy('language')
            ->addString('language.code', FALSE)
            // Primary country.
            ->addTaxonomy('primary_country', array('shortname', 'iso3'))
            ->addFloat('primary_country.latitude')
            ->addFloat('primary_country.longitude')
            // Country.
            ->addTaxonomy('country', array('shortname', 'iso3'))
            ->addFloat('country.latitude')
            ->addFloat('country.longitude')
            ->addBoolean('country.primary')
            // Source.
            ->addTaxonomy('source', array('shortname', 'longname'))
            ->addString('source.homeage', NULL)
            ->addTaxonomy('source.type')
            // Disaster.
            ->addTaxonomy('disaster', array('glide'))
            ->addTaxonomy('disaster.type')
            ->addBoolean('disaster.type.primary')
            // Other taxonomies.
            ->addTaxonomy('format')
            ->addTaxonomy('theme')
            ->addTaxonomy('disaster_type')
            ->addTaxonomy('vulnerable_groups')
            ->addTaxonomy('ocha_product')
            // File and image.
            ->addImage('image')
            ->addFile('file');

    return $mapping->export();
  }

  public function getItems($limit, $offset) {
    $base_url = $this->options['website'];

    $entity_type = $this->entity_type;
    $entity_bundle = $this->entity_bundle;
    $base_table = 'node';
    $base_field = 'nid';

    // Prepare the base query.
    $query = db_select($base_table, $base_table);
    $query->addField($base_table, $base_field, 'id');
    $query->condition($base_table . '.' . $base_field, $offset, '<=');
    $query->condition($base_table . '.type', $entity_bundle);
    $query->groupBy($base_table . '.' . $base_field);
    $query->orderBy($base_table . '.' . $base_field, 'DESC');
    $query->range(0, $limit);

    $options = array(
      'query' => array(
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
          'field_image' => array(
            'image' => 'image_reference',
          ),
          'field_file' => array(
            'file' => 'file_reference',
          ),
          'field_origin_notes' => array(
            'origin' => 'value',
          ),
          'field_primary_country' => array(
            'primary_country' => 'tid',
          ),
          'field_country' => array(
            'country' => 'taxonomy_reference',
          ),
          'field_source' => array(
            'source' => 'taxonomy_reference',
          ),
          'field_language' => array(
            'language' => 'taxonomy_reference',
          ),
          'field_theme' => array(
            'theme' => 'taxonomy_reference',
          ),
          'field_content_format' => array(
            'format' => 'taxonomy_reference',
          ),
          'field_ocha_product' => array(
            'ocha_product' => 'taxonomy_reference',
          ),
          'field_disaster' => array(
            'disaster' => 'taxonomy_reference',
          ),
          'field_disaster_type' => array(
            'disaster_type' => 'taxonomy_reference',
          ),
          'field_vulnerable_groups' => array(
            'vulnerable_groups' => 'taxonomy_reference',
          ),
          'field_feature' => array(
            'feature' => 'taxonomy_reference',
          ),
        ),
      ),
      'process' => array(
        'conversion' => array(
          'body' => 'html',
          'date_created' => 'time',
          'date_changed' => 'time',
          'date_original' => 'time',
          'headline' => 'bool',
        ),
        'reference' => array(
          'primary_country' => array(
            'country' => array('id', 'name', 'shortname', 'iso3'), //, 'latitude', 'longitude'
          ),
          'country' => array(
            'country' => array('id', 'name', 'shortname', 'iso3'),
          ),
          'source' => array(
            'source' => array('id', 'name', 'shortname', 'longname', 'type', 'homepage'),
          ),
          'language' => array(
            'source' => array('id', 'name'),
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
            'disaster_type' => array('id', 'name'),
          ),
          'vulnerable_groups' => array(
            'vulnerable_groups' => array('id', 'name'),
          ),
          'feature' => array(
            'feature' => array('id', 'name'),
          ),
        ),
      ),
    );

    $items = $this->processQuery($query, $entity_type, $base_table, $base_field, $options);

    // TODO: index primary information for disaster type and country.
    foreach ($items as $id => &$item) {
      // URL.
      $item['url'] = $base_url . '/node/' . $item['id'];

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
          // Handle image.
          if ($this->handleImageField($item['headline_image']) === TRUE) {
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
      if ($this->handleImageField($item['image']) !== TRUE) {
        unset($item['image']);
      }

      // Handle File.
      if ($this->handleFileField($item['file']) !== TRUE) {
        unset($item['file']);
      }
    }

    return $items;
  }
}
