<?php

declare(strict_types=1);

namespace RWAPIIndexer;

/**
 * References handler class.
 */
class References {

  /**
   * Associative array of references.
   *
   * @var array<string, array<int, array<string, mixed>>>
   */
  protected array $references = [];

  /**
   * Set the reference items for the given entity bundle.
   *
   * @param string $bundle
   *   Bundle to which belongs the reference items.
   * @param array<int, array<string, mixed>> $items
   *   Reference items for the given entity bundle.
   */
  public function set(string $bundle, array $items): void {
    $this->references[$bundle] = $items;
  }

  /**
   * Get the items belonging to the given entity bundle.
   *
   * @param string $bundle
   *   Bundle of the reference items to return.
   *
   * @return array<int, array<string, mixed>>
   *   Reference items for the given bundle.
   */
  public function get(string $bundle): array {
    return $this->has($bundle) ? $this->references[$bundle] : [];
  }

  /**
   * Check if the given bundle has reference items.
   *
   * @param string $bundle
   *   Bundle to check.
   *
   * @return bool
   *   Indicates if the entity bundle has reference items stored.
   */
  public function has(string $bundle): bool {
    return !empty($this->references[$bundle]);
  }

  /**
   * Get a particular reference item for the given entity bundle.
   *
   * @param string $bundle
   *   Bundle of the reference item.
   * @param int $id
   *   ID of the reference item.
   * @param string[] $fields
   *   List of fields to return with the item.
   *
   * @return array<string, mixed>|null
   *   Reference item.
   */
  public function getItem(string $bundle, int $id, array $fields = []): array|null {
    if (isset($this->references[$bundle][$id])) {
      if (!empty($fields)) {
        return array_intersect_key($this->references[$bundle][$id], array_flip($fields));
      }
      return $this->references[$bundle][$id];
    }
    return NULL;
  }

  /**
   * Add the given reference items to the corresponding bundle.
   *
   * @param string $bundle
   *   Bundle of the reference items.
   * @param array<int, array<string, mixed>> $items
   *   Reference items to add.
   */
  public function setItems(string $bundle, array $items): void {
    foreach ($items as $id => $item) {
      if (!isset($this->references[$bundle][$id])) {
        $this->references[$bundle][$id] = $item;
      }
    }
  }

  /**
   * Get the Ids from the given list that haven't been loaded yet.
   *
   * @param string $bundle
   *   Bundle to which belong the references.
   * @param int[] $ids
   *   List of reference ids to check.
   *
   * @return int[]
   *   Reference Ids to load.
   */
  public function getNotLoaded(string $bundle, array $ids): array {
    if (!empty($this->references[$bundle])) {
      return array_diff($ids, array_keys($this->references[$bundle]));
    }
    return $ids;
  }

}
