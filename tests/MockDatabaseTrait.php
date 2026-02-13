<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use RWAPIIndexer\Database\DatabaseConnection;
use RWAPIIndexer\Database\Statement;

/**
 * Trait for tests that use a mock database connection instead of SQLite.
 */
trait MockDatabaseTrait {

  /**
   * Creates a mock DatabaseConnection with optional query and quote stubs.
   *
   * @param array{query?: \RWAPIIndexer\Database\Statement|null, quote?: callable} $options
   *   Optional.
   *   - 'query' => Statement to return from query(), or null.
   *   - 'quote' => callable(string): string for quote(); default wraps in
   *      single quotes.
   *
   * @return \RWAPIIndexer\Database\DatabaseConnection
   *   Mock connection (never a real PDO).
   */
  protected function createMockDatabaseConnection(array $options = []): DatabaseConnection {
    $mock_connection = $this->getMockBuilder(DatabaseConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['query', 'quote'])
      ->getMock();

    $mock_connection->method('quote')
      ->willReturnCallback($options['quote'] ?? static function (string $value): string {
        return "'" . addslashes($value) . "'";
      });

    $mock_connection->method('query')
      ->willReturn($options['query'] ?? FALSE);

    return $mock_connection;
  }

  /**
   * Creates a mock Statement that returns the given rows for fetch/fetchAll.
   *
   * @param array<int, array<string|int, mixed>> $rows
   *   Rows to return (associative or numeric).
   * @param array<int, mixed> $fetch_column_map
   *   Optional. Map of (index => value) for fetchColumn($index) calls.
   *
   * @return \RWAPIIndexer\Database\Statement
   *   Mock statement.
   */
  protected function createMockStatement(array $rows = [], array $fetch_column_map = []): Statement {
    $mock_statement = $this->getMockBuilder(Statement::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['fetch', 'fetchColumn', 'fetchAll', 'fetchAllAssoc', 'fetchAllKeyed', 'fetchField', 'fetchCol'])
      ->getMock();

    if ($rows !== []) {
      $index = 0;
      $mock_statement->method('fetch')
        ->willReturnCallback(static function () use ($rows, &$index) {
          if ($index < count($rows)) {
            return $rows[$index++];
          }
          return FALSE;
        });
      $mock_statement->method('fetchAll')
        ->willReturn($rows);
    }

    if ($fetch_column_map !== []) {
      $mock_statement->method('fetchColumn')
        ->willReturnCallback(static function (int $index = 0) use ($fetch_column_map) {
          return $fetch_column_map[$index] ?? $fetch_column_map[0] ?? NULL;
        });
      $mock_statement->method('fetchField')
        ->willReturnCallback(static function (int $index = 0) use ($fetch_column_map) {
          return $fetch_column_map[$index] ?? $fetch_column_map[0] ?? NULL;
        });
    }

    return $mock_statement;
  }

}
