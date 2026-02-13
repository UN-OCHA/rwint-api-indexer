<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests\Database;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\Database\Statement;
use RWAPIIndexer\Tests\MockDatabaseTrait;

/**
 * Tests for Database\Statement result set behavior using a mock connection.
 *
 * Note: Statement extends PDOStatement and PHP's PDO does not allow a custom
 * statement class with a public constructor when using ATTR_STATEMENT_CLASS, so
 * we cannot use real SQLite to test Statement's fetchAllAssoc, fetchAllKeyed,
 * fetchField, fetchCol directly. These tests verify behavior via mocks.
 */
#[AllowMockObjectsWithoutExpectations]
final class StatementTest extends TestCase {

  use MockDatabaseTrait;

  /**
   * A result set can be fetched as numeric rows and keyed by first column.
   */
  #[Test]
  public function resultSetCanBeFetchedAsKeyValuePairs(): void {
    $rows = [[1, 'a'], [2, 'b']];
    $mock_statement = $this->createMockStatement($rows);
    $connection = $this->createMockDatabaseConnection(['query' => $mock_statement]);

    $statement = $connection->query('SELECT k, v FROM t');
    self::assertInstanceOf(Statement::class, $statement);
    $fetched = $statement->fetchAll(\PDO::FETCH_NUM);
    $keyed = [];
    foreach ($fetched as $row) {
      $keyed[(int) $row[0]] = $row[1];
    }
    self::assertSame([1 => 'a', 2 => 'b'], $keyed);
  }

  /**
   * FetchColumn() returns the value of the requested column index.
   */
  #[Test]
  public function fetchColumnReturnsSingleColumn(): void {
    $mock_statement = $this->createMockStatement([], [0 => 42]);
    $connection = $this->createMockDatabaseConnection(['query' => $mock_statement]);

    $statement = $connection->query('SELECT id FROM t');
    self::assertInstanceOf(Statement::class, $statement);
    self::assertSame(42, $statement->fetchColumn(0));
  }

  /**
   * Mock Statement returns keyed rows from fetchAllAssoc().
   */
  #[Test]
  public function mockStatementFetchAllAssocReturnsKeyedRows(): void {
    $rows = [
      ['id' => 1, 'name' => 'one'],
      ['id' => 2, 'name' => 'two'],
    ];
    $mock_statement = $this->createMockStatement($rows);
    $mock_statement->expects(self::once())
      ->method('fetchAllAssoc')
      ->with('id')
      ->willReturn([1 => $rows[0], 2 => $rows[1]]);
    $connection = $this->createMockDatabaseConnection(['query' => $mock_statement]);

    $statement = $connection->query('SELECT id, name FROM t');
    self::assertInstanceOf(Statement::class, $statement);
    $keyed = $statement->fetchAllAssoc('id');
    self::assertSame('one', $keyed[1]['name'] ?? NULL);
    self::assertSame('two', $keyed[2]['name'] ?? NULL);
  }

  /**
   * Mock Statement returns key-value pairs from fetchAllKeyed().
   */
  #[Test]
  public function mockStatementFetchAllKeyedReturnsKeyValuePairs(): void {
    $rows = [[10, 'ten'], [20, 'twenty']];
    $mock_statement = $this->createMockStatement($rows);
    $mock_statement->expects(self::once())
      ->method('fetchAllKeyed')
      ->with(0, 1)
      ->willReturn([10 => 'ten', 20 => 'twenty']);
    $connection = $this->createMockDatabaseConnection(['query' => $mock_statement]);

    $statement = $connection->query('SELECT k, v FROM t');
    self::assertInstanceOf(Statement::class, $statement);
    $keyed = $statement->fetchAllKeyed(0, 1);
    self::assertSame([10 => 'ten', 20 => 'twenty'], $keyed);
  }

  /**
   * Mock Statement fetchField() returns single field.
   *
   * Statement::fetchField delegates to fetchColumn.
   */
  #[Test]
  public function mockStatementFetchFieldReturnsSingleField(): void {
    $mock_statement = $this->createMockStatement([], [0 => 99]);
    $connection = $this->createMockDatabaseConnection(['query' => $mock_statement]);

    $statement = $connection->query('SELECT id FROM t');
    self::assertInstanceOf(Statement::class, $statement);
    self::assertSame(99, $statement->fetchField(0));
  }

  /**
   * Mock Statement fetchCol() returns column array.
   */
  #[Test]
  public function mockStatementFetchColReturnsColumnArray(): void {
    $mock_statement = $this->createMockStatement([[1], [2], [3]]);
    $mock_statement->expects(self::once())
      ->method('fetchCol')
      ->with(0)
      ->willReturn([1, 2, 3]);
    $connection = $this->createMockDatabaseConnection(['query' => $mock_statement]);

    $statement = $connection->query('SELECT id FROM t');
    self::assertInstanceOf(Statement::class, $statement);
    $col = $statement->fetchCol(0);
    self::assertSame([1, 2, 3], $col);
  }

}
