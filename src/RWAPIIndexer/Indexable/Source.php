<?php

namespace RWAPIIndexer\Indexable;

class Source extends AbstractIndexable {
  protected $entity_type = 'taxonomy_term';
  protected $entity_bundle = 'source';

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
            ->addString('homepage', NULL)
            ->addString('content_type', FALSE)
            // Names.
            ->addString('name', TRUE, TRUE)
            ->addString('shortname', TRUE, TRUE)
            ->addString('longname', TRUE, TRUE)
            // Description.
            ->addString('description')
            ->addString('description-html', NULL)
            // Country.
            ->addTaxonomy('country', array('shortname', 'iso3'))
            ->addFloat('country.latitude')
            ->addFloat('country.longitude')
            // Organization type.
            ->addTaxonomy('type');

    return $mapping->export();
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
