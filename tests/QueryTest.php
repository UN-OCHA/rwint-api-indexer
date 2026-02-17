<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\Database\DatabaseConnection;
use RWAPIIndexer\Query;

/**
 * Tests for RWAPIIndexer\Query entity query handler.
 */
#[AllowMockObjectsWithoutExpectations]
final class QueryTest extends TestCase {

  use MockDatabaseTrait;

  /**
   * Creates a mock database connection for tests.
   *
   * @return \RWAPIIndexer\Database\DatabaseConnection
   *   Mock connection.
   */
  private function createConnection(): DatabaseConnection {
    return $this->createMockDatabaseConnection();
  }

  /**
   * Constructor throws when entity type is not node or taxonomy_term.
   */
  #[Test]
  public function constructorThrowsForUnknownEntityType(): void {
    $connection = $this->createConnection();
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Unknown entity type');
    new Query($connection, 'unknown_type', 'report', []);
  }

  /**
   * NewQuery() for node entity type uses node_field_data table.
   */
  #[Test]
  public function newQueryForNodeUsesNodeTables(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $db_query = $query->newQuery();
    $sql = $db_query->build();
    self::assertStringContainsString('node_field_data', $sql);
  }

  /**
   * NewQuery() for taxonomy_term uses taxonomy_term_field_data table.
   */
  #[Test]
  public function newQueryForTaxonomyTermUsesTaxonomyTables(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'taxonomy_term', 'topic', []);
    $db_query = $query->newQuery();
    $sql = $db_query->build();
    self::assertStringContainsString('taxonomy_term_field_data', $sql);
  }

  /**
   * AddIdField() adds the base table id field (e.g. nid) to the query.
   */
  #[Test]
  public function addIdFieldAddsBaseField(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $db_query = $query->newQuery();
    $query->addIdField($db_query);
    $sql = $db_query->build();
    self::assertStringContainsString('nid', $sql);
  }

  /**
   * SetBundle() for node adds type = bundle condition.
   */
  #[Test]
  public function setBundleForNodeAddsTypeCondition(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $db_query = $query->newQuery();
    $query->setBundle($db_query);
    $sql = $db_query->build();
    self::assertStringContainsString('type', $sql);
    self::assertStringContainsString("'report'", $sql);
  }

  /**
   * SetBundle() for taxonomy_term adds vid = bundle condition.
   */
  #[Test]
  public function setBundleForTaxonomyTermAddsVidCondition(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'taxonomy_term', 'topic', []);
    $db_query = $query->newQuery();
    $query->setBundle($db_query);
    $sql = $db_query->build();
    self::assertStringContainsString('vid', $sql);
    self::assertStringContainsString("'topic'", $sql);
  }

  /**
   * SetOffset() adds base field <= offset when offset is non-empty.
   */
  #[Test]
  public function setOffsetAddsConditionWhenNonEmpty(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $db_query = $query->newQuery();
    $query->setOffset($db_query, 100);
    $sql = $db_query->build();
    self::assertStringContainsString('<=', $sql);
  }

  /**
   * SetLimit() adds range(0, limit) to the query.
   */
  #[Test]
  public function setLimitAddsRange(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $db_query = $query->newQuery();
    $query->setLimit($db_query, 10);
    $sql = $db_query->build();
    self::assertStringContainsString('LIMIT', $sql);
  }

  /**
   * SetOrderBy() adds ORDER BY base field with given direction.
   */
  #[Test]
  public function setOrderByAddsOrderClause(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $db_query = $query->newQuery();
    $query->setOrderBy($db_query, 'DESC');
    $sql = $db_query->build();
    self::assertStringContainsString('ORDER BY', $sql);
    self::assertStringContainsString('DESC', $sql);
  }

  /**
   * SetCount() marks the query as a count query.
   */
  #[Test]
  public function setCountMakesQueryCount(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $db_query = $query->newQuery();
    $query->setCount($db_query);
    $sql = $db_query->build();
    self::assertStringContainsString('COUNT(*)', $sql);
  }

  /**
   * SetIds() adds base field IN (ids) condition.
   */
  #[Test]
  public function setIdsAddsInCondition(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $db_query = $query->newQuery();
    $query->setIds($db_query, [1, 2, 3]);
    $sql = $db_query->build();
    self::assertStringContainsString('IN', $sql);
  }

  /**
   * GetIds() with explicit ids returns them without querying.
   */
  #[Test]
  public function getIdsWithExplicitIdsReturnsThem(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $ids = $query->getIds(NULL, NULL, [5, 10]);
    self::assertSame([5, 10], $ids);
  }

  /**
   * SetGroupBy() adds GROUP BY base table id field.
   */
  #[Test]
  public function setGroupByAddsGroupByClause(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $db_query = $query->newQuery();
    $query->setGroupBy($db_query);
    $sql = $db_query->build();
    self::assertStringContainsString('GROUP BY', $sql);
    self::assertStringContainsString('node_field_data.nid', $sql);
  }

  /**
   * SetFilters() with fields category adds IN condition.
   */
  #[Test]
  public function setFiltersFieldsAddsInCondition(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $db_query = $query->newQuery();
    $query->addIdField($db_query);
    $query->setFilters($db_query, ['fields' => ['title' => ['test']]]);
    $sql = $db_query->build();
    self::assertStringContainsString('node_field_data.title', $sql);
    self::assertStringContainsString('IN', $sql);
  }

  /**
   * SetFilters() with fields value * adds IS NOT NULL.
   */
  #[Test]
  public function setFiltersFieldsStarAddsIsNotNull(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $db_query = $query->newQuery();
    $query->addIdField($db_query);
    $query->setFilters($db_query, ['fields' => ['title' => '*']]);
    $sql = $db_query->build();
    self::assertStringContainsString('IS NOT NULL', $sql);
  }

  /**
   * AddUuidField() adds INNER JOIN and uuid field for node.
   */
  #[Test]
  public function addUuidFieldAddsJoinAndUuidForNode(): void {
    $connection = $this->createConnection();
    $query = new Query($connection, 'node', 'report', []);
    $db_query = $query->newQuery();
    $query->addUuidField($db_query);
    $sql = $db_query->build();
    self::assertStringContainsString('INNER JOIN', $sql);
    self::assertStringContainsString('uuid', $sql);
  }

  /**
   * GetLimit() returns query count when connection returns count statement.
   */
  #[Test]
  public function getLimitReturnsCountFromMockedExecute(): void {
    $count_statement = $this->createMockStatement([], [0 => 50]);
    $connection = $this->createMockDatabaseConnection(['query' => $count_statement]);
    $query = new Query($connection, 'node', 'report', []);
    $limit = $query->getLimit(0, 0);
    self::assertSame(50, $limit);
  }

  /**
   * GetLimit() returns min(limit, count) when limit is positive.
   */
  #[Test]
  public function getLimitReturnsMinWhenLimitPositive(): void {
    $count_statement = $this->createMockStatement([], [0 => 100]);
    $connection = $this->createMockDatabaseConnection(['query' => $count_statement]);
    $query = new Query($connection, 'node', 'report', []);
    $limit = $query->getLimit(10, 0);
    self::assertSame(10, $limit);
  }

  /**
   * GetOffset() returns offset from query when connection returns id statement.
   */
  #[Test]
  public function getOffsetReturnsIdFromMockedExecute(): void {
    $id_statement = $this->createMockStatement([], [0 => 999]);
    $connection = $this->createMockDatabaseConnection(['query' => $id_statement]);
    $query = new Query($connection, 'node', 'report', []);
    $offset = $query->getOffset(0);
    self::assertSame(999, $offset);
  }

  /**
   * GetItems() with file_reference field_join includes _page_count.
   */
  #[Test]
  public function getItemsWithFileReferenceIncludesPageCountInExpression(): void {
    $captured_sql = '';
    $statement = $this->createMockStatement();
    $statement->method('fetchAllAssoc')->willReturn([1 => ['id' => 1]]);
    $connection = $this->getMockBuilder(DatabaseConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['query', 'quote'])
      ->getMock();
    $connection->method('quote')
      ->willReturnCallback(static function (string $value): string {
        return "'" . addslashes($value) . "'";
      });
    $connection->method('query')
      ->willReturnCallback(function (string $sql) use ($statement, &$captured_sql) {
        $captured_sql = $sql;
        return $statement;
      });
    $query = new Query($connection, 'node', 'report', [
      'field_joins' => ['field_file' => ['file' => 'file_reference']],
    ]);
    $query->getItems(1, 0, [1]);
    self::assertStringContainsString('_page_count', $captured_sql);
  }

}
