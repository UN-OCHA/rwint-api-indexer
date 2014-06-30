<?php

namespace RWAPIIndexer;

/**
 * References handler class.
 */
class References {
  // Associative array of references.
  protected $references = array();

  /**
   * Set the reference items for the given entity bundle.
   *
   * @param string $bundle
   *   Bundle to which belongs the reference items.
   * @param string $items
   *   Reference items for the given entity bundle.
   */
  public function set($bundle, $items) {
    $this->references[$bundle] = $items;
  }

  /**
   * Get the items belonging to the given entity bundle.
   *
   * @param string $bundle
   *   Bundle of the reference items to return.
   * @return array
   *   Reference items for the given bundle.
   */
  public function get($bundle) {
    return $this->has($bundle) ? $this->references[$bundle] : array();
  }

  /**
   * Check if the given bundle has reference items.
   *
   * @param string $bundle
   *   Bundle to check.
   * @return boolean
   *   Indicates if the entity bundle has reference items stored.
   */
  public function has($bundle) {
    return !empty($this->references[$bundle]);
  }

  /**
   * Get a particular reference item for the given entity bundle.
   *
   * @param string $bundle
   *   Bundle of the reference item.
   * @param integer $id
   *   ID of the reference item.
   * @param array  $fields
   *   List of fields to return with the item.
   * @return array
   *   Reference item.
   */
  public function getItem($bundle, $id, $fields = array()) {
    if (isset($this->references[$bundle][$id])) {
      if (!empty($fields)) {
        return array_intersect_key($this->references[$bundle][$id], array_flip($fields));
      }
      return $this->references[$bundle][$id];
    }
    return NULL;
  }
}
