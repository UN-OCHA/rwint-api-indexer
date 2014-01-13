<?php

namespace RWAPIIndexer\Indexable;

class Source extends AbstractIndexable {
  protected $entity_type = 'taxonomy_term';
  protected $entity_bundle = 'source';

  public function getMapping() {
    return array(
      '_all' => array('enabled' => FALSE),
      'id' => array('type' => 'integer'),
      'url' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
      'status' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
      'name' => array(
        'type' => 'multi_field',
        'fields' => array(
          'name' => array('type' => 'string', 'omit_norms' => TRUE),
          'exact' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        ),
      ),
      'shortname' => array(
        'type' => 'multi_field',
        'fields' => array(
          'shortname' => array('type' => 'string', 'omit_norms' => TRUE),
          'exact' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        ),
      ),
      'longname' => array(
        'type' => 'multi_field',
        'fields' => array(
          'shortname' => array('type' => 'string', 'omit_norms' => TRUE),
          'exact' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        ),
      ),
      'type' => $this->getMultiFieldMapping('organization_type'),
      'country' => $this->getMultiFieldMapping('country', array('name', 'shortname', 'iso3'), array(
        'primary' => array('type' => 'boolean', 'index' => 'no'),
      )),
      'description' => array('type' => 'string', 'omit_norms' => TRUE),
      'description-html' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
      'homepage' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
      'content_type' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
    );
  }

  public function getItems($limit, $offset) {
    $base_url = $this->options['website'];

    $content_types = array('job', 'report', 'training');

    $taxonomy = &$this->taxonomies[$this->entity_bundle];

    $items = array();
    $count = $limit;

    // Loop through the terms from the end.
    $item = end($taxonomy);
    while ($item !== FALSE && $count > 0) {
      $id = $item['id'];
      if ($id <= $offset) {
        $count--;

        // URL.
        $item['url'] = $base_url . '/taxonomy/term/' . $id;

        // Content type.
        if (!empty($item['content_type'])) {
          foreach ($item['content_type'] as $key => $value) {
            $item['content_type'][$key] = $content_types[(int) $value];
          }
        }

        $items[$id] = $item;
      }
      $item = prev($taxonomy);
    }

    reset($taxonomy);

    return $items;
  }
}
