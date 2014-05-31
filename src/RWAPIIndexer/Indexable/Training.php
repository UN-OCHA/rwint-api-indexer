<?php

namespace RWAPIIndexer\Indexable;

class Training extends AbstractIndexable {
  protected $entity_type = 'node';
  protected $entity_bundle = 'training';

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
            // Body.
            ->addString('body')
            ->addString('body-html', NULL)
            // Dates.
            ->addDates('date', array('created', 'changed', 'registration', 'start', 'end'))
            // Language.
            ->addTaxonomy('language')
            ->addString('language.code', FALSE)
            // Country.
            ->addTaxonomy('country', array('shortname', 'iso3'))
            ->addFloat('country.latitude')
            ->addFloat('country.longitude')
            // Source.
            ->addTaxonomy('source', array('shortname', 'longname'))
            ->addString('source.homeage', NULL)
            ->addTaxonomy('source.type')
            // Other taxonomies
            ->addTaxonomy('city')
            ->addTaxonomy('type')
            ->addTaxonomy('format')
            ->addTaxonomy('theme')
            ->addTaxonomy('career_categories')
            // File.
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
          'field_file' => array(
            'file' => 'file_reference',
          ),
          'field_country' => array(
            'country' => 'taxonomy_reference',
          ),
          'field_city' => array(
            'city' => 'taxonomy_reference',
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
          'field_training_type' => array(
            'type' => 'taxonomy_reference',
          ),
          'field_training_format' => array(
            'format' => 'taxonomy_reference',
          ),
          'field_career_categories' => array(
            'career_categories' => 'taxonomy_reference',
          ),
        ),
      ),
      'process' => array(
        'conversion' => array(
          'body' => 'html',
          'date_created' => 'time',
          'date_changed' => 'time',
          'date_registration' => 'time',
          'date_start' => 'time',
          'date_end' => 'time',
        ),
        'reference' => array(
          'country' => array(
            'country' => array('id', 'name', 'shortname', 'iso3'),
          ),
          'city' => array(
            'city' => array('id', 'name'),
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
          'type' => array(
            'training_type' => array('id', 'name'),
          ),
          'format' => array(
            'training_format' => array('id', 'name'),
          ),
          'career_categories' => array(
            'career_categories' => array('id', 'name'),
          ),
        ),
      ),
    );

    $items = $this->processQuery($query, $entity_type, $base_table, $base_field, $options);

    foreach ($items as $id => &$item) {
      // URL.
      $item['url'] = $base_url . '/node/' . $item['id'];

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

      // Handle File.
      if ($this->handleFileField($item['file']) !== TRUE) {
        unset($item['file']);
      }
    }

    return $items;
  }
}
