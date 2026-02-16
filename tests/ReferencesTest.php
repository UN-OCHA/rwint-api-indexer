<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\References;

/**
 * Tests for References references handler.
 */
final class ReferencesTest extends TestCase {

  /**
   * Get() returns an empty array for a bundle that has no items set.
   */
  #[Test]
  public function getReturnsEmptyForUnsetBundle(): void {
    $references = new References();
    self::assertSame([], $references->get('topic'));
  }

  /**
   * Has() returns FALSE when the bundle has no items.
   */
  #[Test]
  public function hasReturnsFalseWhenEmpty(): void {
    $references = new References();
    self::assertFalse($references->has('topic'));
  }

  /**
   * Set() and get() roundtrip preserves the items array.
   */
  #[Test]
  public function setAndGetRoundtrip(): void {
    $references = new References();
    $items = [
      1 => ['id' => 1, 'name' => 'Topic A'],
      2 => ['id' => 2, 'name' => 'Topic B'],
    ];
    $references->set('topic', $items);
    self::assertTrue($references->has('topic'));
    self::assertSame($items, $references->get('topic'));
  }

  /**
   * GetItem() returns the item for the given bundle and id.
   */
  #[Test]
  public function getItemReturnsItemById(): void {
    $references = new References();
    $items = [10 => ['id' => 10, 'name' => 'Country X', 'code' => 'XX']];
    $references->set('country', $items);
    self::assertSame(['id' => 10, 'name' => 'Country X', 'code' => 'XX'], $references->getItem('country', 10));
  }

  /**
   * GetItem() returns NULL when the id is not in the bundle.
   */
  #[Test]
  public function getItemReturnsNullForMissingId(): void {
    $references = new References();
    $references->set('country', [10 => ['id' => 10]]);
    self::assertNull($references->getItem('country', 99));
  }

  /**
   * GetItem() with fields parameter returns only those keys.
   */
  #[Test]
  public function getItemWithFieldsFiltersKeys(): void {
    $references = new References();
    $references->set('topic', [1 => ['id' => 1, 'name' => 'A', 'description' => 'Desc']]);
    self::assertSame(['name' => 'A'], $references->getItem('topic', 1, ['name']));
  }

  /**
   * SetItems() merges new items without removing existing ones.
   */
  #[Test]
  public function setItemsMergesWithoutOverwriting(): void {
    $references = new References();
    $references->set('topic', [1 => ['id' => 1, 'name' => 'First']]);
    $references->setItems('topic', [2 => ['id' => 2, 'name' => 'Second']]);
    self::assertCount(2, $references->get('topic'));
    self::assertSame('First', $references->getItem('topic', 1)['name'] ?? NULL);
    self::assertSame('Second', $references->getItem('topic', 2)['name'] ?? NULL);
  }

  /**
   * SetItems() does not overwrite an existing item with the same id.
   */
  #[Test]
  public function setItemsDoesNotOverwriteExisting(): void {
    $references = new References();
    $references->set('topic', [1 => ['id' => 1, 'name' => 'Original']]);
    $references->setItems('topic', [1 => ['id' => 1, 'name' => 'Replacement']]);
    self::assertSame('Original', $references->getItem('topic', 1)['name'] ?? NULL);
  }

  /**
   * GetNotLoaded() returns all ids when the bundle has no items.
   */
  #[Test]
  public function getNotLoadedReturnsAllIdsWhenBundleEmpty(): void {
    $references = new References();
    self::assertSame([1, 2, 3], $references->getNotLoaded('topic', [1, 2, 3]));
  }

  /**
   * GetNotLoaded() returns only ids not yet in the bundle.
   */
  #[Test]
  public function getNotLoadedReturnsOnlyMissingIds(): void {
    $references = new References();
    $references->set('topic', [1 => ['id' => 1], 2 => ['id' => 2]]);
    self::assertSame([3, 4], array_values($references->getNotLoaded('topic', [1, 2, 3, 4])));
  }

  /**
   * GetNotLoaded() returns an empty array when all ids are loaded.
   */
  #[Test]
  public function getNotLoadedReturnsEmptyWhenAllLoaded(): void {
    $references = new References();
    $references->set('topic', [1 => ['id' => 1], 2 => ['id' => 2]]);
    self::assertSame([], $references->getNotLoaded('topic', [1, 2]));
  }

}
