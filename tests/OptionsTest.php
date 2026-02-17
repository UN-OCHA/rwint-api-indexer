<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\Options;

/**
 * Tests for Options options handler.
 */
final class OptionsTest extends TestCase {

  /**
   * FromArray() creates an instance with default values.
   */
  #[Test]
  public function fromArrayCreatesOptionsWithDefaults(): void {
    $options = Options::fromArray(['bundle' => 'report']);
    self::assertSame('report', $options->bundle);
    self::assertSame('http://127.0.0.1:9200', $options->elasticsearch);
    self::assertSame('localhost', $options->mysqlHost);
    self::assertSame(3306, $options->mysqlPort);
    self::assertSame(500, $options->chunkSize);
    self::assertSame(1, $options->shards);
    self::assertSame(1, $options->replicas);
    self::assertFalse($options->remove);
    self::assertFalse($options->alias);
  }

  /**
   * FromArray() maps kebab-case array keys to camelCase constructor parameters.
   */
  #[Test]
  public function fromArrayMapsKebabCaseToCamelCase(): void {
    $options = Options::fromArray([
      'bundle' => 'job',
      'elasticsearch' => 'http://es:9200',
      'mysql-host' => 'db',
      'mysql-port' => 3307,
      'chunk-size' => 100,
      'base-index-name' => 'my_index',
      'tag' => 'v1',
    ]);
    self::assertSame('job', $options->bundle);
    self::assertSame('http://es:9200', $options->elasticsearch);
    self::assertSame('db', $options->mysqlHost);
    self::assertSame(3307, $options->mysqlPort);
    self::assertSame(100, $options->chunkSize);
    self::assertSame('my_index', $options->baseIndexName);
    self::assertSame('v1', $options->tag);
  }

  /**
   * FromArray() throws when the bundle is not in the known bundles list.
   */
  #[Test]
  public function invalidBundleThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Invalid bundle 'invalid'. Known bundles:");
    Options::fromArray(['bundle' => 'invalid']);
  }

  /**
   * FromArray() throws when the Elasticsearch option is not a valid URL.
   */
  #[Test]
  public function invalidElasticsearchUrlThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid Elasticsearch option');
    Options::fromArray([
      'bundle' => 'report',
      'elasticsearch' => 'not-a-url',
    ]);
  }

  /**
   * FromArray() throws when the MySQL port is out of range.
   */
  #[Test]
  public function invalidMysqlPortThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid MySQL port');
    Options::fromArray([
      'bundle' => 'report',
      'mysql-port' => 70000,
    ]);
  }

  /**
   * FromArray() throws when chunk-size is outside 1-1000.
   */
  #[Test]
  public function invalidChunkSizeThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid chunk-size');
    Options::fromArray([
      'bundle' => 'report',
      'chunk-size' => 2000,
    ]);
  }

  /**
   * FromArray() throws when shards is outside 1-8.
   */
  #[Test]
  public function invalidShardsThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid shards');
    Options::fromArray([
      'bundle' => 'report',
      'shards' => 10,
    ]);
  }

  /**
   * ValidateBundle() returns the bundle string when it is known.
   */
  #[Test]
  public function validateBundleReturnsBundleWhenKnown(): void {
    self::assertSame('report', Options::validateBundle('report'));
    self::assertSame('country', Options::validateBundle('country'));
  }

  /**
   * ValidateBundle() returns FALSE when the bundle is unknown.
   */
  #[Test]
  public function validateBundleReturnsFalseWhenUnknown(): void {
    self::assertFalse(Options::validateBundle('unknown'));
  }

  /**
   * ValidateMysqlHost() accepts valid hostnames and IP addresses.
   */
  #[Test]
  public function validateMysqlHostAcceptsValidHost(): void {
    self::assertSame('localhost', Options::validateMysqlHost('localhost'));
    self::assertSame('127.0.0.1', Options::validateMysqlHost('127.0.0.1'));
    self::assertSame('db.example.com', Options::validateMysqlHost('db.example.com'));
  }

  /**
   * ValidateMysqlHost() returns FALSE for empty or space-containing strings.
   */
  #[Test]
  public function validateMysqlHostRejectsInvalid(): void {
    self::assertFalse(Options::validateMysqlHost(''));
    self::assertFalse(Options::validateMysqlHost('host with spaces'));
  }

  /**
   * ValidatePostProcessItemHook() accepts NULL, empty string, and callables.
   */
  #[Test]
  public function validatePostProcessItemHookAcceptsCallableAndEmpty(): void {
    self::assertNull(Options::validatePostProcessItemHook(NULL));
    self::assertSame('', Options::validatePostProcessItemHook(''));
    self::assertIsCallable(Options::validatePostProcessItemHook(fn () => NULL));
  }

  /**
   * ValidatePostProcessItemHook() returns FALSE for non-callable values.
   */
  #[Test]
  public function validatePostProcessItemHookRejectsInvalid(): void {
    self::assertFalse(Options::validatePostProcessItemHook('not-callable'));
    self::assertFalse(Options::validatePostProcessItemHook(123));
  }

}
