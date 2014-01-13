<?php

namespace RWAPIIndexer\Indexable;

class Disaster extends AbstractIndexable {
  protected $entity_type = 'taxonomy_term';
  protected $entity_bundle = 'disaster';

  public function getMapping() {
    return array(
      '_all' => array('enabled' => FALSE),
      'id' => array('type' => 'integer'),
      'url' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
      'status' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
      'current' => array('type' => 'boolean'),
      'featured' => array('type' => 'boolean'),
      'date' => array(
        'properties' => array(
          'created' => array('type' => 'date'),
        ),
      ),
      'name' => array(
        'type' => 'multi_field',
        'fields' => array(
          'name' => array('type' => 'string', 'omit_norms' => TRUE),
          'exact' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        ),
      ),
      'glide' => array(
        'type' => 'multi_field',
        'fields' => array(
          'glide' => array('type' => 'string', 'omit_norms' => TRUE),
          'exact' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        ),
      ),
      'description' => array('type' => 'string', 'omit_norms' => TRUE),
      'description-html' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
      'primary_country' => $this->getMultiFieldMapping('primary_country', array('name', 'shortname', 'iso3')),
      'country' => $this->getMultiFieldMapping('country', array('name', 'shortname', 'iso3'), array(
        'primary' => array('type' => 'boolean', 'index' => 'no'),
      )),
      'primary_type' => $this->getMultiFieldMapping('primary_type'),
      'type' => $this->getMultiFieldMapping('type', array('name'), array(
        'primary' => array('type' => 'boolean', 'index' => 'no'),
      )),
    );
  }

  public function getItems($limit, $offset) {
    $base_url = $this->options['website'];

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

        $item['date'] = array('created' => $item['date']);

        $items[$id] = $item;
      }
      $item = prev($taxonomy);
    }

    reset($taxonomy);

    return $items;
  }
}
