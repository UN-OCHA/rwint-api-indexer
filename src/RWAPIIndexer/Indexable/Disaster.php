<?php

namespace RWAPIIndexer\Indexable;

class Disaster extends AbstractIndexable {
  protected $entity_type = 'taxonomy_term';
  protected $entity_bundle = 'disaster';

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
            ->addDates('date', array('created'))
            ->addBoolean('featured')
            // Names.
            ->addString('name', TRUE, TRUE)
            ->addString('glide', TRUE, TRUE)
            // Description.
            ->addString('description')
            ->addString('description-html', NULL)
            // Primary country.
            ->addTaxonomy('primary_country', array('shortname', 'iso3'))
            ->addFloat('primary_country.latitude')
            ->addFloat('primary_country.longitude')
            // Country.
            ->addTaxonomy('country', array('shortname', 'iso3'))
            ->addFloat('country.latitude')
            ->addFloat('country.longitude')
            ->addBoolean('country.primary')
            // Primary disaster type.
            ->addTaxonomy('primary_type')
            // Disaster types.
            ->addTaxonomy('type')
            ->addBoolean('type.primary')
            ;

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

        $item['date'] = array('created' => $item['date']);

        $items[$id] = $item;
      }
      $item = prev($taxonomy);
    }

    reset($taxonomy);

    return $items;
  }
}
