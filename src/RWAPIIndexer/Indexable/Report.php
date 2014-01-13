<?php

namespace RWAPIIndexer\Indexable;

class Report extends AbstractIndexable {
  protected $entity_type = 'node';
  protected $entity_bundle = 'report';

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
      'origin' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
      'date' => array(
        'properties' => array(
          'created' => array('type' => 'date'),
          'changed' => array('type' => 'date'),
          'original' => array('type' => 'date'),
        ),
      ),
      'headline' => array(
        'properties' => array(
          'title' => array(
            'type' => 'multi_field',
            'fields' => array(
              'title' => array('type' => 'string', 'omit_norms' => TRUE),
              'exact' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
            ),
          ),
          'summary' => array('type' => 'string', 'omit_norms' => TRUE),
          'image' => $this->getImageFieldMapping(),
        ),
      ),
      'language' => $this->getMultiFieldMapping('language', array('name', 'code')),
      'primary_country' => $this->getMultiFieldMapping('primary_country', array('name', 'shortname', 'iso3')),
      'country' => $this->getMultiFieldMapping('country', array('name', 'shortname', 'iso3'), array(
        'primary' => array('type' => 'boolean', 'index' => 'no'),
      )),
      'source' => $this->getMultiFieldMapping('source', array('name', 'shortname', 'longname'), array(
        'homepage' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
        'type' => $this->getMultiFieldMapping('source.type'),
      )),
      'format' => $this->getMultiFieldMapping('format'),
      'theme' => $this->getMultiFieldMapping('theme'),
      'disaster' => $this->getMultiFieldMapping('disaster', array('name'), array(
        'glide' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        'type' => $this->getMultiFieldMapping('disaster.type', array('name'), array(
          'primary' => array('type' => 'boolean', 'index' => 'no'),
        )),
      )),
      'disaster_type' => $this->getMultiFieldMapping('disaster_type'),
      'vulnerable_groups' => $this->getMultiFieldMapping('vulnerable_groups'),
      'ocha_product' => $this->getMultiFieldMapping('ocha_product'),
      'image' => $this->getImageFieldMapping(),
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
          'status' => 'status',
          'date_created' => 'created',
          'date_changed' => 'changed',
        ),
        'field_joins' => array(
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
          'status' => 'bool',
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
