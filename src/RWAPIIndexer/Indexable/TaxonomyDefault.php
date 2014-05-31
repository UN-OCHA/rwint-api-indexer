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
            // Names.
            ->addString('name', TRUE, TRUE)
            // Description.
            ->addString('description')
            ->addString('description-html', NULL);

    return $mapping->export();
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
