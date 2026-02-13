<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\Elasticsearch;

/**
 * Tests for Elasticsearch handler.
 */
final class ElasticsearchTest extends TestCase {

  /**
   * GetIndexPath() returns base_index_tag with no tag when tag is empty.
   */
  #[Test]
  public function getIndexPathUsesBaseAndTag(): void {
    $elasticsearch = new Elasticsearch('http://localhost:9200', 'reliefweb', '');
    self::assertSame('reliefweb_reports_index', $elasticsearch->getIndexPath('reports'));
  }

  /**
   * GetIndexPath() appends tag to the index path when tag is set.
   */
  #[Test]
  public function getIndexPathWithTagAppendsTag(): void {
    $elasticsearch = new Elasticsearch('http://localhost:9200', 'reliefweb', 'v2');
    self::assertSame('reliefweb_reports_index_v2', $elasticsearch->getIndexPath('reports'));
  }

  /**
   * GetIndexAlias() returns base and index name without _index or tag.
   */
  #[Test]
  public function getIndexAliasUsesBaseOnly(): void {
    $elasticsearch = new Elasticsearch('http://localhost:9200', 'reliefweb', 'v2');
    self::assertSame('reliefweb_reports', $elasticsearch->getIndexAlias('reports'));
  }

  /**
   * Request() throws when method is empty.
   */
  #[Test]
  public function requestThrowsWhenMethodEmpty(): void {
    $elasticsearch = new Elasticsearch('http://localhost:9200', 'base', '');
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method is required');
    $elasticsearch->request('', 'index');
  }

  /**
   * Request() throws when path is empty.
   */
  #[Test]
  public function requestThrowsWhenPathEmpty(): void {
    $elasticsearch = new Elasticsearch('http://localhost:9200', 'base', '');
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Path is required');
    $elasticsearch->request('GET', '');
  }

  /**
   * Request() throws when server URL is empty.
   */
  #[Test]
  public function requestThrowsWhenServerEmpty(): void {
    $elasticsearch = new Elasticsearch('', 'base', '');
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Server is required');
    $elasticsearch->request('GET', 'path');
  }

  /**
   * IndexItems() returns last item key minus one and calls request() for bulk.
   */
  #[Test]
  public function indexItemsReturnsLastOffset(): void {
    $elasticsearch_mock = $this->getMockBuilder(Elasticsearch::class)
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
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
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
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
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
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
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
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
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
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
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
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
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
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
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
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
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
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
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
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
      ->setConstructorArgs(['http://localhost:9200', 'base', ''])
      ->onlyMethods(['request'])
      ->getMock();

    $elasticsearch_mock->expects(self::once())
      ->method('request')
      ->with('DELETE', 'base_reports_index/_doc/42', NULL, FALSE)
      ->willReturn('{}');

    $elasticsearch_mock->removeItem('reports', 42);
  }

}
