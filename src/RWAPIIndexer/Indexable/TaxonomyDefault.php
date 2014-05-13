<?php

namespace RWAPIIndexer\Indexable;

class TaxonomyDefault extends AbstractIndexable {
  protected $entity_type = 'taxonomy_term';

  /**
   * Construct the indexable based on the given website.
   */
  public function __construct($options) {
    $this->entity_bundle = $options['bundle'];
    parent::__construct($options);
  }

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
      'description' => array('type' => 'string', 'omit_norms' => TRUE),
      'description-html' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
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

        $items[$id] = $item;
      }
      $item = prev($taxonomy);
    }

    reset($taxonomy);

    return $items;
  }
}
