<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\Bundles;
use RWAPIIndexer\Elasticsearch;
use RWAPIIndexer\Options;
use RWAPIIndexer\Processor;
use RWAPIIndexer\References;

/**
 * Tests for Bundles bundle manager.
 */
#[AllowMockObjectsWithoutExpectations]
final class BundlesTest extends TestCase {

  use MockDatabaseTrait;

  /**
   * Has() returns TRUE for known entity bundles.
   */
  #[Test]
  public function hasReturnsTrueForKnownBundles(): void {
    self::assertTrue(Bundles::has('report'));
    self::assertTrue(Bundles::has('job'));
    self::assertTrue(Bundles::has('country'));
    self::assertTrue(Bundles::has('topic'));
    self::assertTrue(Bundles::has('language'));
  }

  /**
   * Has() returns FALSE for unknown or empty bundle.
   */
  #[Test]
  public function hasReturnsFalseForUnknownBundle(): void {
    self::assertFalse(Bundles::has('unknown'));
    self::assertFalse(Bundles::has(''));
  }

  /**
   * BUNDLES constant defines all expected bundles with class, type, and index.
   */
  #[Test]
  public function bundlesConstantContainsExpectedKeys(): void {
    $expected = [
      'report', 'job', 'training', 'blog_post', 'book',
      'topic', 'country', 'disaster', 'source',
      'career_category', 'content_format', 'disaster_type', 'feature',
      'job_type', 'language', 'ocha_product', 'organization_type',
      'tag', 'theme', 'training_format', 'training_type',
      'vulnerable_group', 'job_experience',
    ];
    foreach ($expected as $bundle) {
      self::assertArrayHasKey($bundle, Bundles::BUNDLES);
      self::assertArrayHasKey('class', Bundles::BUNDLES[$bundle]);
      self::assertArrayHasKey('type', Bundles::BUNDLES[$bundle]);
      self::assertArrayHasKey('index', Bundles::BUNDLES[$bundle]);
    }
  }

  /**
   * GetResourceHandler() returns a Resource for a known bundle (report).
   */
  #[Test]
  public function getResourceHandlerReturnsResourceForReport(): void {
    $options = Options::fromArray([
      'bundle' => 'report',
      'elasticsearch' => 'http://127.0.0.1:9200',
      'website' => 'https://reliefweb.int',
    ]);
    $connection = $this->createMockDatabaseConnection();
    $elasticsearch = new Elasticsearch(
      $options->elasticsearch,
      $options->baseIndexName,
      $options->tag,
    );
    $references = new References();
    $processor = new Processor($options->website, $connection, $references);

    $resource = Bundles::getResourceHandler(
      'report',
      $elasticsearch,
      $connection,
      $processor,
      $references,
      $options,
    );

    self::assertSame('report', $resource->getBundle());
    self::assertSame('node', $resource->getEntityType());
    self::assertSame('reports', $resource->getIndex());
  }

  /**
   * GetResourceHandler() throws when the bundle is unknown.
   */
  #[Test]
  public function getResourceHandlerThrowsForUnknownBundle(): void {
    $options = Options::fromArray([
      'bundle' => 'report',
      'elasticsearch' => 'http://127.0.0.1:9200',
      'website' => 'https://reliefweb.int',
    ]);
    $connection = $this->createMockDatabaseConnection();
    $elasticsearch = new Elasticsearch(
      $options->elasticsearch,
      $options->baseIndexName,
      $options->tag,
    );
    $references = new References();
    $processor = new Processor($options->website, $connection, $references);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage("No resource handler for the bundle 'unknown'");
    Bundles::getResourceHandler(
      'unknown',
      $elasticsearch,
      $connection,
      $processor,
      $references,
      $options,
    );
  }

}
