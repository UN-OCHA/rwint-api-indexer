<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\Manager;

/**
 * Tests for Manager resource manager.
 */
#[AllowMockObjectsWithoutExpectations]
final class ManagerTest extends TestCase {

  use MockDatabaseTrait;

  /**
   * Creates a Manager with the given options and a mock database connection.
   *
   * @param array<string, mixed> $options
   *   Indexing options (kebab-case keys).
   *
   * @return \RWAPIIndexer\Manager
   *   Manager instance.
   */
  private function createManager(array $options): Manager {
    $mock_connection = $this->createMockDatabaseConnection();
    return new Manager($options, $mock_connection);
  }

  /**
   * Constructor accepts an options array and builds a Manager.
   */
  #[Test]
  public function constructorAcceptsOptionsArray(): void {
    $options = [
      'bundle' => 'topic',
      'elasticsearch' => 'http://127.0.0.1:9200',
      'website' => 'https://reliefweb.int',
      'mysql-host' => '127.0.0.1',
      'database' => 'test',
      'base-index-name' => 'test',
    ];
    $manager = $this->createManager($options);
    self::assertNotNull($manager->getMetrics());
  }

  /**
   * GetMetrics() returns a Metrics instance with time and memory.
   */
  #[Test]
  public function getMetricsReturnsMetricsInstance(): void {
    $manager = $this->createManager([
      'bundle' => 'report',
      'elasticsearch' => 'http://127.0.0.1:9200',
      'website' => 'https://reliefweb.int',
      'mysql-host' => '127.0.0.1',
      'database' => 'db',
      'base-index-name' => 'idx',
    ]);
    $metrics = $manager->getMetrics();
    $data = $metrics->get();
    self::assertArrayHasKey('time', $data);
    self::assertArrayHasKey('memory usage', $data);
  }

  /**
   * GetResourceHandler() returns the correct Resource for a bundle.
   */
  #[Test]
  public function getResourceHandlerReturnsResourceForBundle(): void {
    $manager = $this->createManager([
      'bundle' => 'report',
      'elasticsearch' => 'http://127.0.0.1:9200',
      'website' => 'https://reliefweb.int',
      'mysql-host' => '127.0.0.1',
      'database' => 'db',
      'base-index-name' => 'idx',
    ]);
    $report = $manager->getResourceHandler('report');
    self::assertSame('report', $report->getBundle());
    self::assertSame('node', $report->getEntityType());
    self::assertSame('reports', $report->getIndex());

    $topic = $manager->getResourceHandler('topic');
    self::assertSame('topic', $topic->getBundle());
    self::assertSame('node', $topic->getEntityType());
  }

}
