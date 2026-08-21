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
    self::assertSame([], $options->pdoOptions);
    self::assertSame('none', $options->elasticsearchAuthType);
    self::assertTrue($options->elasticsearchVerifyTls);
    self::assertSame('', $options->elasticsearchCaFile);
    self::assertSame(2, $options->elasticsearchRetry);
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
   * FromArray() maps Elasticsearch authentication and TLS options.
   */
  #[Test]
  public function fromArrayMapsElasticsearchAuthAndTlsOptions(): void {
    $options = Options::fromArray([
      'bundle' => 'report',
      'elasticsearch' => 'https://search.example.com:443',
      'elasticsearch-auth-type' => 'ApiKey',
      'elasticsearch-api-key' => 'token-value',
      'elasticsearch-verify-tls' => 'false',
      'elasticsearch-ca-file' => '/path/to/ca.pem',
      'elasticsearch-retry' => '2',
    ]);
    self::assertSame('https://search.example.com:443', $options->elasticsearch);
    self::assertSame('apikey', $options->elasticsearchAuthType);
    self::assertSame('token-value', $options->elasticsearchApiKey);
    self::assertFalse($options->elasticsearchVerifyTls);
    self::assertSame('/path/to/ca.pem', $options->elasticsearchCaFile);
    self::assertSame(2, $options->elasticsearchRetry);
  }

  /**
   * FromArray() throws when elasticsearch-retry is out of range.
   */
  #[Test]
  public function invalidElasticsearchRetryThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid elasticsearch-retry. It must be between 0 and 5.');
    Options::fromArray([
      'bundle' => 'report',
      'elasticsearch-retry' => 6,
    ]);
  }

  /**
   * FromArray() accepts elasticsearch-retry at the allowed bounds.
   */
  #[Test]
  public function elasticsearchRetryAcceptsBounds(): void {
    $zero = Options::fromArray([
      'bundle' => 'report',
      'elasticsearch-retry' => 0,
    ]);
    self::assertSame(0, $zero->elasticsearchRetry);

    $five = Options::fromArray([
      'bundle' => 'report',
      'elasticsearch-retry' => 5,
    ]);
    self::assertSame(5, $five->elasticsearchRetry);
  }

  /**
   * FromArray() throws when the Elasticsearch URL scheme is not HTTP or HTTPS.
   */
  #[Test]
  public function nonHttpElasticsearchUrlThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid Elasticsearch option, it must be a valid HTTP or HTTPS URL.');
    Options::fromArray([
      'bundle' => 'report',
      'elasticsearch' => 'ftp://search.example.com:21',
    ]);
  }

  /**
   * FromArray() throws when the Elasticsearch authentication type is unknown.
   */
  #[Test]
  public function invalidElasticsearchAuthTypeThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid Elasticsearch authentication type');
    Options::fromArray([
      'bundle' => 'report',
      'elasticsearch-auth-type' => 'digest',
    ]);
  }

  /**
   * FromArray() throws when basic auth is missing credentials.
   */
  #[Test]
  public function missingBasicAuthCredentialsThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Missing Elasticsearch basic authentication credentials');
    Options::fromArray([
      'bundle' => 'report',
      'elasticsearch-auth-type' => 'basic',
      'elasticsearch-username' => 'alice',
    ]);
  }

  /**
   * FromArray() throws when apikey auth is missing a key.
   */
  #[Test]
  public function missingApiKeyThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Missing Elasticsearch api key authentication credentials');
    Options::fromArray([
      'bundle' => 'report',
      'elasticsearch-auth-type' => 'apikey',
    ]);
  }

  /**
   * ParseVerifyTls() treats string false as boolean FALSE.
   */
  #[Test]
  public function parseVerifyTlsHandlesStringFalse(): void {
    self::assertFalse(Options::parseVerifyTls('false'));
    self::assertFalse(Options::parseVerifyTls('0'));
    self::assertTrue(Options::parseVerifyTls('true'));
    self::assertTrue(Options::parseVerifyTls(NULL));
  }

  /**
   * ParseVerifyTls() rejects unparseable values.
   */
  #[Test]
  public function parseVerifyTlsRejectsUnknownValue(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid elasticsearch-verify-tls value. Use true or false.');
    Options::parseVerifyTls('nope');
  }

  /**
   * FromArray() rejects unparseable elasticsearch-verify-tls values.
   */
  #[Test]
  public function fromArrayRejectsUnknownVerifyTls(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid elasticsearch-verify-tls value. Use true or false.');
    Options::fromArray([
      'bundle' => 'report',
      'elasticsearch-verify-tls' => 'nope',
    ]);
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
   * FromArray() accepts pdo-options mapped to pdoOptions for PDO.
   */
  #[Test]
  public function fromArrayAcceptsPdoOptions(): void {
    $pdo_options = [
      \PDO::ATTR_EMULATE_PREPARES => FALSE,
      '1002' => 30,
    ];
    $options = Options::fromArray([
      'bundle' => 'report',
      'pdo-options' => $pdo_options,
    ]);
    self::assertSame($pdo_options, $options->pdoOptions);
  }

  /**
   * FromArray() throws when pdo-options is not a flat array of scalars.
   */
  #[Test]
  public function invalidPdoOptionsThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid pdo-options');
    Options::fromArray([
      'bundle' => 'report',
      'pdo-options' => [\PDO::ATTR_TIMEOUT => [1, 2, 3]],
    ]);
  }

  /**
   * ValidatePdoOptions() returns TRUE for an empty array.
   */
  #[Test]
  public function validatePdoOptionsAcceptsEmpty(): void {
    self::assertTrue(Options::validatePdoOptions([]));
  }

  /**
   * ValidatePdoOptions() returns FALSE for nested array values.
   */
  #[Test]
  public function validatePdoOptionsRejectsNestedValues(): void {
    self::assertFalse(Options::validatePdoOptions([3 => ['a' => 1]]));
  }

  /**
   * ValidatePdoOptions() returns FALSE for empty string keys.
   */
  #[Test]
  public function validatePdoOptionsRejectsEmptyStringKey(): void {
    self::assertFalse(Options::validatePdoOptions(['' => 1]));
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
