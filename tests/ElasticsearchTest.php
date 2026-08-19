<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\Elasticsearch;
use RWAPIIndexer\Options;

/**
 * Tests for Elasticsearch handler.
 */
final class ElasticsearchTest extends TestCase {

  /**
   * Create indexing options for tests.
   *
   * @param array<string, mixed> $overrides
   *   Option overrides (kebab-case keys).
   *
   * @return \RWAPIIndexer\Options
   *   Options instance.
   */
  private function options(array $overrides = []): Options {
    return Options::fromArray($overrides + [
      'bundle' => 'report',
      'elasticsearch' => 'http://localhost:9200',
      'base-index-name' => 'base',
    ]);
  }

  /**
   * GetIndexPath() returns base_index_tag with no tag when tag is empty.
   */
  #[Test]
  public function getIndexPathUsesBaseAndTag(): void {
    $elasticsearch = new Elasticsearch($this->options([
      'base-index-name' => 'reliefweb',
    ]));
    self::assertSame('reliefweb_reports_index', $elasticsearch->getIndexPath('reports'));
  }

  /**
   * GetIndexPath() appends tag to the index path when tag is set.
   */
  #[Test]
  public function getIndexPathWithTagAppendsTag(): void {
    $elasticsearch = new Elasticsearch($this->options([
      'base-index-name' => 'reliefweb',
      'tag' => 'v2',
    ]));
    self::assertSame('reliefweb_reports_index_v2', $elasticsearch->getIndexPath('reports'));
  }

  /**
   * GetIndexAlias() returns base and index name without _index or tag.
   */
  #[Test]
  public function getIndexAliasUsesBaseOnly(): void {
    $elasticsearch = new Elasticsearch($this->options([
      'base-index-name' => 'reliefweb',
      'tag' => 'v2',
    ]));
    self::assertSame('reliefweb_reports', $elasticsearch->getIndexAlias('reports'));
  }

  /**
   * Request() throws when method is empty.
   */
  #[Test]
  public function requestThrowsWhenMethodEmpty(): void {
    $elasticsearch = new Elasticsearch($this->options());
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method is required');
    $elasticsearch->request('', 'index');
  }

  /**
   * Request() throws when path is empty.
   */
  #[Test]
  public function requestThrowsWhenPathEmpty(): void {
    $elasticsearch = new Elasticsearch($this->options());
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Path is required');
    $elasticsearch->request('GET', '');
  }

  /**
   * IndexItems() returns last item key minus one and calls request() for bulk.
   */
  #[Test]
  public function indexItemsReturnsLastOffset(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs([$this->options()])
      ->onlyMethods(['request'])
      ->getMock();

    $elasticsearch_mock->expects(self::once())
      ->method('request')
      ->with(
        self::identicalTo('POST'),
        self::stringContains('_bulk'),
        self::anything(),
        self::identicalTo(TRUE),
      )
      ->willReturn('{"errors":false}');

    $items = [
      10 => ['id' => 10, 'title' => 'A'],
      20 => ['id' => 20, 'title' => 'B'],
    ];
    $offset = $elasticsearch_mock->indexItems('reports', $items);
    self::assertSame(19, $offset);
  }

  /**
   * Create() calls createIndex() when index does not exist.
   */
  #[Test]
  public function createCallsCreateIndexWhenIndexDoesNotExist(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs([$this->options()])
      ->onlyMethods(['indexExists', 'createIndex'])
      ->getMock();

    $elasticsearch_mock->expects(self::once())
      ->method('indexExists')
      ->with('reports')
      ->willReturn(FALSE);
    $elasticsearch_mock->expects(self::once())
      ->method('createIndex')
      ->with('reports', ['properties' => []], 1, 1);

    $elasticsearch_mock->create('reports', ['properties' => []], 1, 1);
  }

  /**
   * Create() does not call createIndex() when index already exists.
   */
  #[Test]
  public function createSkipsCreateIndexWhenIndexExists(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs([$this->options()])
      ->onlyMethods(['indexExists', 'createIndex'])
      ->getMock();

    $elasticsearch_mock->expects(self::once())
      ->method('indexExists')
      ->with('reports')
      ->willReturn(TRUE);
    $elasticsearch_mock->expects(self::never())
      ->method('createIndex');

    $elasticsearch_mock->create('reports', [], 1, 1);
  }

  /**
   * IndexExists() returns TRUE when request does not throw.
   */
  #[Test]
  public function indexExistsReturnsTrueWhenRequestSucceeds(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs([$this->options()])
      ->onlyMethods(['request'])
      ->getMock();

    $elasticsearch_mock->expects(self::once())
      ->method('request')
      ->with('HEAD', 'base_reports_index')
      ->willReturn('');

    self::assertTrue($elasticsearch_mock->indexExists('reports'));
  }

  /**
   * IndexExists() returns FALSE when request throws 404.
   */
  #[Test]
  public function indexExistsReturnsFalseWhenRequestReturns404(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs([$this->options()])
      ->onlyMethods(['request'])
      ->getMock();

    $exception = new \Exception('Not found', 404);
    $elasticsearch_mock->expects(self::once())
      ->method('request')
      ->with('HEAD', 'base_reports_index')
      ->willThrowException($exception);

    self::assertFalse($elasticsearch_mock->indexExists('reports'));
  }

  /**
   * CreateIndex() calls request() with PUT and correct path/settings.
   */
  #[Test]
  public function createIndexCallsRequestWithPutAndSettings(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs([$this->options()])
      ->onlyMethods(['request'])
      ->getMock();

    $elasticsearch_mock->expects(self::once())
      ->method('request')
      ->with(
        self::identicalTo('PUT'),
        self::identicalTo('base_reports_index'),
        self::callback(function ($data): bool {
          return isset($data['settings']['number_of_shards'])
            && $data['settings']['number_of_shards'] === 2
            && isset($data['settings']['number_of_replicas'])
            && $data['settings']['number_of_replicas'] === 0
            && isset($data['mappings']['properties']);
        }),
        self::identicalTo(FALSE),
      )
      ->willReturn('{"acknowledged":true}');

    $elasticsearch_mock->createIndex('reports', ['id' => ['type' => 'integer']], 2, 0);
  }

  /**
   * Remove() calls request() with DELETE and index path.
   */
  #[Test]
  public function removeCallsRequestWithDelete(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs([$this->options()])
      ->onlyMethods(['request'])
      ->getMock();

    $elasticsearch_mock->expects(self::once())
      ->method('request')
      ->with('DELETE', 'base_reports_index', NULL, FALSE)
      ->willReturn('{"acknowledged":true}');

    $elasticsearch_mock->remove('reports');
  }

  /**
   * Remove() swallows 404 from request.
   */
  #[Test]
  public function removeSwallows404(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs([$this->options()])
      ->onlyMethods(['request'])
      ->getMock();

    $elasticsearch_mock->expects(self::once())
      ->method('request')
      ->willThrowException(new \Exception('Not found', 404));

    $elasticsearch_mock->remove('reports');
    self::assertTrue(TRUE, 'No exception rethrown');
  }

  /**
   * AddAlias() calls request() with POST _aliases and add/remove actions.
   */
  #[Test]
  public function addAliasCallsRequestWithAliasActions(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs([$this->options()])
      ->onlyMethods(['request'])
      ->getMock();

    $elasticsearch_mock->expects(self::once())
      ->method('request')
      ->with(
        self::identicalTo('POST'),
        self::identicalTo('_aliases'),
        self::callback(function (array $data): bool {
          $actions = $data['actions'] ?? [];
          return count($actions) === 2
            && isset($actions[0]['remove']['alias'])
            && $actions[0]['remove']['alias'] === 'base_reports'
            && isset($actions[1]['add']['index'])
            && $actions[1]['add']['index'] === 'base_reports_index'
            && $actions[1]['add']['alias'] === 'base_reports';
        }),
        self::identicalTo(FALSE),
      )
      ->willReturn('{"acknowledged":true}');

    $elasticsearch_mock->addAlias('reports');
  }

  /**
   * RemoveAlias() calls request() with POST _aliases and remove action.
   */
  #[Test]
  public function removeAliasCallsRequestWithRemoveAction(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs([$this->options()])
      ->onlyMethods(['request'])
      ->getMock();

    $elasticsearch_mock->expects(self::once())
      ->method('request')
      ->with(
        self::identicalTo('POST'),
        self::identicalTo('_aliases'),
        self::callback(function (array $data): bool {
          $actions = $data['actions'] ?? [];
          return count($actions) === 1
            && isset($actions[0]['remove']['index'])
            && $actions[0]['remove']['index'] === 'base_reports_index'
            && $actions[0]['remove']['alias'] === 'base_reports';
        }),
        self::identicalTo(FALSE),
      )
      ->willReturn('{"acknowledged":true}');

    $elasticsearch_mock->removeAlias('reports');
  }

  /**
   * RemoveItem() calls request() with DELETE and doc path.
   */
  #[Test]
  public function removeItemCallsRequestWithDocPath(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs([$this->options()])
      ->onlyMethods(['request'])
      ->getMock();

    $elasticsearch_mock->expects(self::once())
      ->method('request')
      ->with('DELETE', 'base_reports_index/_doc/42', NULL, FALSE)
      ->willReturn('{}');

    $elasticsearch_mock->removeItem('reports', 42);
  }

  /**
   * Builds a Basic authorization header.
   */
  #[Test]
  public function buildsBasicAuthorizationHeader(): void {
    $elasticsearch = new TestableElasticsearch($this->options([
      'elasticsearch' => 'https://search.example.com:443',
      'elasticsearch-auth-type' => 'basic',
      'elasticsearch-username' => 'alice',
      'elasticsearch-password' => 'secret',
    ]));

    self::assertSame(
      ['Authorization: Basic ' . base64_encode('alice:secret')],
      $elasticsearch->buildAuthHeaders(),
    );
  }

  /**
   * Builds a Bearer authorization header.
   */
  #[Test]
  public function buildsBearerAuthorizationHeader(): void {
    $elasticsearch = new TestableElasticsearch($this->options([
      'elasticsearch' => 'https://search.example.com:443',
      'elasticsearch-auth-type' => 'bearer',
      'elasticsearch-bearer-token' => 'test-token',
    ]));

    self::assertSame(
      ['Authorization: Bearer test-token'],
      $elasticsearch->buildAuthHeaders(),
    );
  }

  /**
   * Auth headers are included when there is no request body.
   */
  #[Test]
  public function includesAuthHeadersWhenRequestHasNoBody(): void {
    $elasticsearch = new TestableElasticsearch($this->options([
      'elasticsearch' => 'https://search.example.com:443',
      'elasticsearch-auth-type' => 'bearer',
      'elasticsearch-bearer-token' => 'test-token',
    ]));

    self::assertSame(
      ['Authorization: Bearer test-token'],
      $elasticsearch->buildHeaders(NULL, FALSE),
    );
  }

  /**
   * Basic auth requires credentials.
   */
  #[Test]
  public function basicAuthRequiresCredentials(): void {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Missing basic authentication credentials');

    $elasticsearch = new TestableElasticsearch($this->options());
    $elasticsearch->setAuth('basic');
    $elasticsearch->buildAuthHeaders();
  }

  /**
   * Bearer auth requires a token.
   */
  #[Test]
  public function bearerAuthRequiresToken(): void {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Missing bearer authentication token');

    $elasticsearch = new TestableElasticsearch($this->options());
    $elasticsearch->setAuth('bearer');
    $elasticsearch->buildAuthHeaders();
  }

  /**
   * An unknown auth type is rejected.
   */
  #[Test]
  public function unknownAuthTypeIsRejected(): void {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Unsupported search authentication type');

    $elasticsearch = new TestableElasticsearch($this->options());
    $elasticsearch->setAuth('digest');
    $elasticsearch->buildAuthHeaders();
  }

  /**
   * TLS verification can be disabled.
   */
  #[Test]
  public function disablesTlsVerification(): void {
    $elasticsearch = new TestableElasticsearch($this->options([
      'elasticsearch' => 'https://search.example.com:443',
      'elasticsearch-verify-tls' => FALSE,
    ]));

    self::assertSame(
      [
        CURLOPT_SSL_VERIFYPEER => FALSE,
        CURLOPT_SSL_VERIFYHOST => 0,
      ],
      $elasticsearch->tlsOptions(),
    );
  }

  /**
   * A custom CA file is used when verification is enabled.
   */
  #[Test]
  public function usesCustomCaFile(): void {
    $elasticsearch = new TestableElasticsearch($this->options([
      'elasticsearch' => 'https://search.example.com:443',
      'elasticsearch-ca-file' => '/path/to/ca.pem',
    ]));

    self::assertSame(
      [CURLOPT_CAINFO => '/path/to/ca.pem'],
      $elasticsearch->tlsOptions(),
    );
  }

  /**
   * A custom CA is ignored when verification is disabled.
   */
  #[Test]
  public function ignoresCustomCaWhenVerificationDisabled(): void {
    $elasticsearch = new TestableElasticsearch($this->options([
      'elasticsearch' => 'https://search.example.com:443',
      'elasticsearch-verify-tls' => FALSE,
      'elasticsearch-ca-file' => '/path/to/ca.pem',
    ]));

    self::assertSame(
      [
        CURLOPT_SSL_VERIFYPEER => FALSE,
        CURLOPT_SSL_VERIFYHOST => 0,
      ],
      $elasticsearch->tlsOptions(),
    );
  }

}
