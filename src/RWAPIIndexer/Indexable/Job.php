<?php

namespace RWAPIIndexer\Indexable;

class Job extends AbstractIndexable {
  protected $entity_type = 'node';
  protected $entity_bundle = 'job';

  public function getMapping() {
    return array(
      '_all' => array('enabled' => FALSE),
      'id' => array('type' => 'integer'),
      'url' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
      'status' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
      'title' => array(
        'type' => 'multi_field',
        'fields' => array(
          'title' => array('type' => 'string', 'omit_norms' => TRUE),
          'exact' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        ),
      ),
      'body' => array('type' => 'string', 'omit_norms' => TRUE),
      'body-html' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
      'how_to_apply' => array('type' => 'string', 'omit_norms' => TRUE),
      'how_to_apply-html' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
      'date' => array(
        'properties' => array(
          'created' => array('type' => 'date'),
          'changed' => array('type' => 'date'),
          'closing' => array('type' => 'date'),
        ),
      ),
      'language' => $this->getMultiFieldMapping('language', array('name', 'code')),
      'country' => $this->getMultiFieldMapping('country', array('name', 'shortname', 'iso3')),
      'city' => $this->getMultiFieldMapping('city'),
      'source' => $this->getMultiFieldMapping('source', array('name', 'shortname', 'longname'), array(
        'homepage' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        'type' => $this->getMultiFieldMapping('source.type'),
      )),
      'theme' => $this->getMultiFieldMapping('theme'),
      'type' => $this->getMultiFieldMapping('type'),
      'experience' => $this->getMultiFieldMapping('experience'),
      'career_categories' => $this->getMultiFieldMapping('career_categories'),
      'file' => $this->getFileFieldMapping(),
    );
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
          'field_job_type' => array(
            'type' => 'taxonomy_reference',
          ),
          'field_job_experience' => array(
            'experience' => 'taxonomy_reference',
          ),
          'field_career_categories' => array(
            'career_categories' => 'taxonomy_reference',
          ),
        ),
      ),
      'process' => array(
        'conversion' => array(
          'body' => 'html',
          'how_to_apply' => 'html',
          'date_created' => 'time',
          'date_changed' => 'time',
          'date_closing' => 'time',
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
            'job_type' => array('id', 'name'),
          ),
          'experience' => array(
            'job_experience' => array('id', 'name'),
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
        'closing' => $item['date_closing'],
      );
      unset($item['date_created']);
      unset($item['date_changed']);
      unset($item['date_closing']);

      // Handle File.
      if ($this->handleFileField($item['file']) !== TRUE) {
        unset($item['file']);
      }
    }

    return $items;
  }
}
