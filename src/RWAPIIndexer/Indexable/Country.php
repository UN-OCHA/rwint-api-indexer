<?php

namespace RWAPIIndexer\Indexable;

class Country extends AbstractIndexable {
  protected $entity_type = 'taxonomy_term';
  protected $entity_bundle = 'country';

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
            ->addBoolean('featured')
            // Names.
            ->addString('name', TRUE, TRUE)
            ->addString('shortname', TRUE, TRUE)
            ->addString('iso3', TRUE, TRUE)
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
