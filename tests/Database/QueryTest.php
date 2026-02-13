<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests\Database;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\Database\DatabaseConnection;
use RWAPIIndexer\Database\Query;
use RWAPIIndexer\Tests\MockDatabaseTrait;

/**
 * Tests for Database\Query query builder.
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
   * Build() selects * and FROM table AS alias when no fields added.
   */
  #[Test]
  public function buildSelectsStarByDefault(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $sql = $query->build();
    self::assertStringContainsString('SELECT *', $sql);
    self::assertStringContainsString('FROM users AS u', $sql);
  }

  /**
   * AddField() adds table.field AS alias to the SELECT list.
   */
  #[Test]
  public function addFieldAddsToSelect(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $query->addField('u', 'id', 'id');
    $query->addField('u', 'name', 'name');
    $sql = $query->build();
    self::assertStringContainsString('u.id AS id', $sql);
    self::assertStringContainsString('u.name AS name', $sql);
  }

  /**
   * AddExpression() adds a raw expression with alias to the SELECT list.
   */
  #[Test]
  public function addExpressionAddsToSelect(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $query->addExpression('COUNT(*)', 'cnt');
    $sql = $query->build();
    self::assertStringContainsString('COUNT(*) AS cnt', $sql);
  }

  /**
   * Condition() with default operator adds field = quoted value.
   */
  #[Test]
  public function conditionWithEquals(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $query->addField('u', 'id', 'id');
    $query->condition('u.status', 'active');
    $sql = $query->build();
    self::assertStringContainsString('WHERE u.status = ', $sql);
    self::assertStringContainsString("'active'", $sql);
  }

  /**
   * Condition() with IN operator adds field IN (values).
   */
  #[Test]
  public function conditionWithIn(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $query->condition('u.id', [1, 2, 3], 'IN');
    $sql = $query->build();
    self::assertStringContainsString('u.id IN (', $sql);
  }

  /**
   * Condition() with IS NOT NULL adds the predicate without value.
   */
  #[Test]
  public function conditionWithIsNotNull(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $query->condition('u.deleted', NULL, 'IS NOT NULL');
    $sql = $query->build();
    self::assertStringContainsString('u.deleted IS NOT NULL', $sql);
  }

  /**
   * LeftJoin() adds LEFT JOIN table AS alias ON condition.
   */
  #[Test]
  public function leftJoinAddsJoin(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $query->leftJoin('roles', 'r', 'r.user_id = u.id');
    $sql = $query->build();
    self::assertStringContainsString('LEFT JOIN roles AS r ON r.user_id = u.id', $sql);
  }

  /**
   * InnerJoin() adds INNER JOIN table AS alias ON condition.
   */
  #[Test]
  public function innerJoinAddsJoin(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $query->innerJoin('roles', 'r', 'r.user_id = u.id');
    $sql = $query->build();
    self::assertStringContainsString('INNER JOIN roles AS r ON r.user_id = u.id', $sql);
  }

  /**
   * GroupBy() adds GROUP BY field to the query.
   */
  #[Test]
  public function groupByAddsClause(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $query->addField('u', 'type', 'type');
    $query->groupBy('u.type');
    $sql = $query->build();
    self::assertStringContainsString('GROUP BY u.type', $sql);
  }

  /**
   * OrderBy() adds ORDER BY field direction to the query.
   */
  #[Test]
  public function orderByAddsClause(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $query->orderBy('u.id', 'DESC');
    $sql = $query->build();
    self::assertStringContainsString('ORDER BY u.id DESC', $sql);
  }

  /**
   * Range() adds LIMIT offset, limit to the query.
   */
  #[Test]
  public function rangeAddsLimit(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $query->range(10, 5);
    $sql = $query->build();
    self::assertStringContainsString('LIMIT 10, 5', $sql);
  }

  /**
   * Count() replaces SELECT list with COUNT(*).
   */
  #[Test]
  public function countReplacesSelectWithCount(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $query->addField('u', 'id', 'id');
    $query->count();
    $sql = $query->build();
    self::assertStringContainsString('COUNT(*)', $sql);
    self::assertStringNotContainsString('u.id AS id', $sql);
  }

  /**
   * Returns the same string as build().
   */
  #[Test]
  public function toStringReturnsBuild(): void {
    $connection = $this->createConnection();
    $query = new Query('t', 't', $connection);
    self::assertSame($query->build(), (string) $query);
  }

  /**
   * Execute() calls connection query() and returns a statement with row data.
   */
  #[Test]
  public function executeRunsQueryWhenConnectionReturnsStatement(): void {
    $mock_statement = $this->createMockStatement([['id' => 1]]);
    $mock_connection = $this->createMockDatabaseConnection(['query' => $mock_statement]);

    $query = new Query('t', 't', $mock_connection);
    $query->addField('t', 'id', 'id');
    $statement = $query->execute();
    self::assertNotNull($statement);
    self::assertSame(['id' => 1], $statement->fetch(\PDO::FETCH_ASSOC));
  }

  /**
   * Condition() throws when the value is not scalar.
   */
  #[Test]
  public function conditionWithNonScalarThrows(): void {
    $connection = $this->createConnection();
    $query = new Query('users', 'u', $connection);
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Value must be a scalar type');
    $query->condition('u.id', new \stdClass());
  }

}
