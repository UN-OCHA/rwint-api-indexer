<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\Database\DatabaseConnection;
use RWAPIIndexer\Elasticsearch;
use RWAPIIndexer\Options;
use RWAPIIndexer\Processor;
use RWAPIIndexer\Query;
use RWAPIIndexer\References;
use RWAPIIndexer\Resources\Country;
use RWAPIIndexer\Resources\Report;
use RWAPIIndexer\Resources\TaxonomyDefault;

/**
 * Tests for Resource base and concrete resource implementations.
 */
#[AllowMockObjectsWithoutExpectations]
final class ResourceTest extends TestCase {

  use MockDatabaseTrait;

  /**
   * Creates a TaxonomyDefault resource for topic bundle.
   *
   * @return \RWAPIIndexer\Resources\TaxonomyDefault
   *   Resource instance.
   */
  private function createTaxonomyDefaultResource(): TaxonomyDefault {
    $options = Options::fromArray([
      'bundle' => 'topic',
      'elasticsearch' => 'http://127.0.0.1:9200',
      'website' => 'https://reliefweb.int',
    ]);
    $connection = $this->createMockDatabaseConnection();
    $elasticsearch = new Elasticsearch($options->elasticsearch, $options->baseIndexName, $options->tag);
    $references = new References();
    $processor = new Processor($options->website, $connection, $references);
    return new TaxonomyDefault('topic', 'taxonomy_term', 'topics', $elasticsearch, $connection, $processor, $references, $options);
  }

  /**
   * ParseFilters() returns an empty array for an empty filter string.
   */
  #[Test]
  public function parseFiltersEmptyReturnsEmpty(): void {
    $resource = $this->createTaxonomyDefaultResource();
    self::assertSame([], $resource->parseFilters(''));
  }

  /**
   * ParseFilters() parses field:value into conditions[fields][field].
   */
  #[Test]
  public function parseFiltersParsesFieldValue(): void {
    $resource = $this->createTaxonomyDefaultResource();
    $conditions = $resource->parseFilters('name:test');
    self::assertArrayHasKey('fields', $conditions);
    self::assertArrayHasKey('name', $conditions['fields']);
    self::assertSame(['test'], $conditions['fields']['name']);
  }

  /**
   * ParseFilters() parses comma-separated values as an array.
   */
  #[Test]
  public function parseFiltersParsesMultipleValues(): void {
    $resource = $this->createTaxonomyDefaultResource();
    $conditions = $resource->parseFilters('name:val1,val2');
    self::assertSame(['val1', 'val2'], $conditions['fields']['name']);
  }

  /**
   * ParseFilters() treats value * as existence check.
   */
  #[Test]
  public function parseFiltersParsesExistenceWithStar(): void {
    $resource = $this->createTaxonomyDefaultResource();
    $conditions = $resource->parseFilters('name:*');
    self::assertSame('*', $conditions['fields']['name']);
  }

  /**
   * ParseFilters() combines multiple field:value pairs with +.
   */
  #[Test]
  public function parseFiltersCombinesWithPlus(): void {
    $resource = $this->createTaxonomyDefaultResource();
    $conditions = $resource->parseFilters('name:a+description:b');
    self::assertSame(['a'], $conditions['fields']['name']);
    self::assertSame(['b'], $conditions['fields']['description']);
  }

  /**
   * GetReferences() returns reference bundles from processing options.
   */
  #[Test]
  public function getReferencesReturnsBundlesFromProcessingOptions(): void {
    $resource = $this->createTaxonomyDefaultResource();
    // TaxonomyDefault has no references in processingOptions.
    self::assertSame([], $resource->getReferences());
  }

  /**
   * GetMapping() returns non-empty mapping for TaxonomyDefault.
   */
  #[Test]
  public function getMappingReturnsNonEmptyForTaxonomyDefault(): void {
    $resource = $this->createTaxonomyDefaultResource();
    $mapping = $resource->getMapping();
    self::assertNotEmpty($mapping);
    self::assertArrayHasKey('id', $mapping);
    self::assertArrayHasKey('name', $mapping);
  }

  /**
   * Getters return the injected bundle, entity type, index, and dependencies.
   */
  #[Test]
  public function gettersReturnInjectedValues(): void {
    $resource = $this->createTaxonomyDefaultResource();
    self::assertSame('topic', $resource->getBundle());
    self::assertSame('taxonomy_term', $resource->getEntityType());
    self::assertSame('topics', $resource->getIndex());
    self::assertInstanceOf(Elasticsearch::class, $resource->getElasticsearch());
    self::assertInstanceOf(Options::class, $resource->getOptions());
  }

  /**
   * Remaining getters return injected dependencies.
   *
   *  - query options
   *  - processing options
   *  - connection
   *  - processor
   *  - references
   *  - query.
   */
  #[Test]
  public function remainingGettersReturnInjectedDependencies(): void {
    $resource = $this->createTaxonomyDefaultResource();
    self::assertIsArray($resource->getQueryOptions());
    self::assertIsArray($resource->getProcessingOptions());
    self::assertInstanceOf(DatabaseConnection::class, $resource->getConnection());
    self::assertInstanceOf(Processor::class, $resource->getProcessor());
    self::assertInstanceOf(References::class, $resource->getReferencesHandler());
    self::assertInstanceOf(Query::class, $resource->getQuery());
  }

  /**
   * GetResourceHandler() returns a Resource for another bundle.
   */
  #[Test]
  public function getResourceHandlerReturnsResourceForOtherBundle(): void {
    $resource = $this->createTaxonomyDefaultResource();
    $report = $resource->getResourceHandler('report');
    self::assertSame('report', $report->getBundle());
    self::assertSame('node', $report->getEntityType());
    self::assertSame('reports', $report->getIndex());
  }

  /**
   * RemoveItem() calls Elasticsearch removeItem with index and id.
   */
  #[Test]
  public function removeItemCallsElasticsearchRemoveItem(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
      ->onlyMethods(['removeItem'])
      ->getMock();
    $elasticsearch_mock->expects(self::once())
      ->method('removeItem')
      ->with('topics', 42);

    $options = Options::fromArray([
      'bundle' => 'topic',
      'elasticsearch' => 'http://127.0.0.1:9200',
      'website' => 'https://reliefweb.int',
    ]);
    $connection = $this->createMockDatabaseConnection();
    $references = new References();
    $processor = new Processor($options->website, $connection, $references);
    $resource = new TaxonomyDefault('topic', 'taxonomy_term', 'topics', $elasticsearch_mock, $connection, $processor, $references, $options);

    $resource->removeItem(42);
  }

  /**
   * SetAlias() with remove FALSE calls Elasticsearch addAlias.
   */
  #[Test]
  public function setAliasAddCallsElasticsearchAddAlias(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
      ->onlyMethods(['addAlias'])
      ->getMock();
    $elasticsearch_mock->expects(self::once())
      ->method('addAlias')
      ->with('topics');

    $options = Options::fromArray([
      'bundle' => 'topic',
      'elasticsearch' => 'http://127.0.0.1:9200',
      'website' => 'https://reliefweb.int',
    ]);
    $connection = $this->createMockDatabaseConnection();
    $references = new References();
    $processor = new Processor($options->website, $connection, $references);
    $resource = new TaxonomyDefault('topic', 'taxonomy_term', 'topics', $elasticsearch_mock, $connection, $processor, $references, $options);

    $resource->setAlias(FALSE);
  }

  /**
   * SetAlias() with remove TRUE calls Elasticsearch removeAlias.
   */
  #[Test]
  public function setAliasRemoveCallsElasticsearchRemoveAlias(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
      ->onlyMethods(['removeAlias'])
      ->getMock();
    $elasticsearch_mock->expects(self::once())
      ->method('removeAlias')
      ->with('topics');

    $options = Options::fromArray([
      'bundle' => 'topic',
      'elasticsearch' => 'http://127.0.0.1:9200',
      'website' => 'https://reliefweb.int',
    ]);
    $connection = $this->createMockDatabaseConnection();
    $references = new References();
    $processor = new Processor($options->website, $connection, $references);
    $resource = new TaxonomyDefault('topic', 'taxonomy_term', 'topics', $elasticsearch_mock, $connection, $processor, $references, $options);

    $resource->setAlias(TRUE);
  }

  /**
   * Log() with options.log 'echo' outputs the message.
   */
  #[Test]
  public function logWithEchoOutputsMessage(): void {
    $options = Options::fromArray([
      'bundle' => 'topic',
      'elasticsearch' => 'http://127.0.0.1:9200',
      'website' => 'https://reliefweb.int',
      'log' => 'echo',
    ]);
    $connection = $this->createMockDatabaseConnection();
    $elasticsearch = new Elasticsearch($options->elasticsearch, $options->baseIndexName, $options->tag);
    $references = new References();
    $processor = new Processor($options->website, $connection, $references);
    $resource = new TaxonomyDefault('topic', 'taxonomy_term', 'topics', $elasticsearch, $connection, $processor, $references, $options);

    ob_start();
    $resource->log('test message');
    $output = ob_get_clean();
    self::assertSame('test message', $output);
  }

  /**
   * Creates a Report resource for tests.
   *
   * @return \RWAPIIndexer\Resources\Report
   *   Resource instance.
   */
  private function createReportResource(): Report {
    $options = Options::fromArray([
      'bundle' => 'report',
      'elasticsearch' => 'http://127.0.0.1:9200',
      'website' => 'https://reliefweb.int',
    ]);
    $connection = $this->createMockDatabaseConnection();
    $elasticsearch = new Elasticsearch($options->elasticsearch, $options->baseIndexName, $options->tag);
    $references = new References();
    $processor = new Processor($options->website, $connection, $references);
    return new Report('report', 'node', 'reports', $elasticsearch, $connection, $processor, $references, $options);
  }

  /**
   * Creates a Country resource for tests.
   *
   * @return \RWAPIIndexer\Resources\Country
   *   Resource instance.
   */
  private function createCountryResource(): Country {
    $options = Options::fromArray([
      'bundle' => 'country',
      'elasticsearch' => 'http://127.0.0.1:9200',
      'website' => 'https://reliefweb.int',
    ]);
    $connection = $this->createMockDatabaseConnection();
    $elasticsearch = new Elasticsearch($options->elasticsearch, $options->baseIndexName, $options->tag);
    $references = new References();
    $processor = new Processor($options->website, $connection, $references);
    return new Country('country', 'taxonomy_term', 'countries', $elasticsearch, $connection, $processor, $references, $options);
  }

  /**
   * Report getMapping() returns non-empty mapping with title and body.
   */
  #[Test]
  public function reportGetMappingReturnsNonEmpty(): void {
    $resource = $this->createReportResource();
    $mapping = $resource->getMapping();
    self::assertNotEmpty($mapping);
    self::assertArrayHasKey('title', $mapping);
    self::assertArrayHasKey('body', $mapping);
  }

  /**
   * Country getMapping() returns non-empty mapping with name.
   */
  #[Test]
  public function countryGetMappingReturnsNonEmpty(): void {
    $resource = $this->createCountryResource();
    $mapping = $resource->getMapping();
    self::assertNotEmpty($mapping);
    self::assertArrayHasKey('name', $mapping);
  }

}
