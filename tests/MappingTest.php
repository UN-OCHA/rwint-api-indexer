<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\Mapping;

/**
 * Tests for Mapping Elasticsearch mapping builder.
 */
final class MappingTest extends TestCase {

  /**
   * Export() includes a timestamp date field by default.
   */
  #[Test]
  public function exportIncludesTimestampByDefault(): void {
    $mapping = new Mapping();
    $export = $mapping->export();
    self::assertArrayHasKey('timestamp', $export);
    self::assertSame('date', $export['timestamp']['type'] ?? NULL);
  }

  /**
   * AddInteger() adds an integer field to the mapping.
   */
  #[Test]
  public function addIntegerAddsField(): void {
    $mapping = new Mapping();
    $mapping->addInteger('id');
    $export = $mapping->export();
    self::assertArrayHasKey('id', $export);
    self::assertSame('integer', $export['id']['type'] ?? NULL);
  }

  /**
   * AddFloat() adds a float field to the mapping.
   */
  #[Test]
  public function addFloatAddsField(): void {
    $mapping = new Mapping();
    $mapping->addFloat('score');
    $export = $mapping->export();
    self::assertSame('float', $export['score']['type'] ?? NULL);
  }

  /**
   * AddBoolean() adds a boolean field to the mapping.
   */
  #[Test]
  public function addBooleanAddsField(): void {
    $mapping = new Mapping();
    $mapping->addBoolean('active');
    $export = $mapping->export();
    self::assertSame('boolean', $export['active']['type'] ?? NULL);
  }

  /**
   * AddString() adds a text field by default when index is TRUE.
   */
  #[Test]
  public function addStringAddsTextFieldByDefault(): void {
    $mapping = new Mapping();
    $mapping->addString('title');
    $export = $mapping->export();
    self::assertSame('text', $export['title']['type'] ?? NULL);
  }

  /**
   * AddString() with index FALSE adds a keyword field.
   */
  #[Test]
  public function addStringWithIndexFalseAddsKeyword(): void {
    $mapping = new Mapping();
    $mapping->addString('code', FALSE);
    $export = $mapping->export();
    self::assertSame('keyword', $export['code']['type'] ?? NULL);
  }

  /**
   * AddString() with exact TRUE adds an exact keyword subfield.
   */
  #[Test]
  public function addStringWithExactAddsSubfield(): void {
    $mapping = new Mapping();
    $mapping->addString('name', TRUE, TRUE);
    $export = $mapping->export();
    self::assertArrayHasKey('fields', $export['name']);
    self::assertArrayHasKey('exact', $export['name']['fields']);
    self::assertSame('keyword', $export['name']['fields']['exact']['type'] ?? NULL);
  }

  /**
   * AddStatus() adds the status keyword field with normalizer.
   */
  #[Test]
  public function addStatusAddsStatusField(): void {
    $mapping = new Mapping();
    $mapping->addStatus();
    $export = $mapping->export();
    self::assertSame('keyword', $export['status']['type'] ?? NULL);
    self::assertSame('status', $export['status']['normalizer'] ?? NULL);
  }

  /**
   * AddGeoPoint() adds a geo_point field.
   */
  #[Test]
  public function addGeoPointAddsField(): void {
    $mapping = new Mapping();
    $mapping->addGeoPoint('location');
    $export = $mapping->export();
    self::assertSame('geo_point', $export['location']['type'] ?? NULL);
  }

  /**
   * AddDates() adds date subfield properties.
   */
  #[Test]
  public function addDatesAddsProperties(): void {
    $mapping = new Mapping();
    $mapping->addDates('created', ['value']);
    $export = $mapping->export();
    self::assertArrayHasKey('created', $export);
    self::assertArrayHasKey('properties', $export['created']);
    self::assertSame('date', $export['created']['properties']['value']['type'] ?? NULL);
  }

  /**
   * AddTaxonomy() adds id and name/subfield properties.
   */
  #[Test]
  public function addTaxonomyAddsIdAndSubfields(): void {
    $mapping = new Mapping();
    $mapping->addTaxonomy('theme', ['shortname']);
    $export = $mapping->export();
    self::assertArrayHasKey('theme', $export);
    self::assertSame('integer', $export['theme']['properties']['id']['type'] ?? NULL);
    self::assertArrayHasKey('name', $export['theme']['properties']);
  }

  /**
   * AddImage() adds image properties (id, url, caption, etc.).
   */
  #[Test]
  public function addImageAddsImageProperties(): void {
    $mapping = new Mapping();
    $mapping->addImage('cover');
    $export = $mapping->export();
    self::assertArrayHasKey('cover', $export);
    $properties = $export['cover']['properties'] ?? [];
    self::assertArrayHasKey('id', $properties);
    self::assertArrayHasKey('url', $properties);
    self::assertArrayHasKey('caption', $properties);
  }

  /**
   * AddFile() adds file properties including preview and pagecount.
   */
  #[Test]
  public function addFileAddsFileProperties(): void {
    $mapping = new Mapping();
    $mapping->addFile('file');
    $export = $mapping->export();
    self::assertArrayHasKey('file', $export);
    $properties = $export['file']['properties'] ?? [];
    self::assertArrayHasKey('id', $properties);
    self::assertArrayHasKey('preview', $properties);
    self::assertArrayHasKey('pagecount', $properties);
    self::assertSame('integer', $properties['pagecount']['type'] ?? NULL);
  }

  /**
   * AddRiverSearch() adds id, url, title, override properties.
   */
  #[Test]
  public function addRiverSearchAddsProperties(): void {
    $mapping = new Mapping();
    $mapping->addRiverSearch('sources');
    $export = $mapping->export();
    self::assertArrayHasKey('sources', $export);
    $properties = $export['sources']['properties'] ?? [];
    self::assertSame('keyword', $properties['id']['type'] ?? NULL);
    self::assertSame('keyword', $properties['url']['type'] ?? NULL);
  }

  /**
   * AddProfile() adds profile overview and section mappings.
   */
  #[Test]
  public function addProfileAddsOverviewAndSections(): void {
    $mapping = new Mapping();
    $mapping->addProfile([
      'section1' => ['archives' => FALSE],
    ]);
    $export = $mapping->export();
    self::assertArrayHasKey('profile', $export);
    self::assertArrayHasKey('properties', $export['profile']);
    self::assertArrayHasKey('overview', $export['profile']['properties']);
  }

  /**
   * Fluent add methods return the same Mapping instance.
   */
  #[Test]
  public function fluentCallsReturnSelf(): void {
    $mapping = new Mapping();
    $result = $mapping->addInteger('id')->addString('name')->addStatus();
    self::assertSame($mapping, $result);
  }

}
