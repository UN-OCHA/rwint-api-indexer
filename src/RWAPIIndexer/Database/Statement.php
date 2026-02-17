<?php

declare(strict_types=1);

namespace RWAPIIndexer\Database;

/**
 * Database statement.
 *
 * Simple extension of PDOStatement with additional fetch functions.
 */
class Statement extends \PDOStatement {

  /**
   * Database connection.
   *
   * @var \RWAPIIndexer\Database\DatabaseConnection
   */
  public DatabaseConnection $connection;

  /**
   * Construct the statement.
   *
   * PDO does not allow a public constructor when using ATTR_STATEMENT_CLASS,
   * so we use a protected constructor.
   *
   * @param \RWAPIIndexer\Database\DatabaseConnection $connection
   *   Database connection.
   */
  protected function __construct(DatabaseConnection $connection) {
    $this->connection = $connection;
  }

  /**
   * Returns the result set as an associative array keyed by the given field.
   *
   * @param string $key
   *   Field to use a key.
   * @param int|string|null $fetch
   *   Fetch mode.
   *
   * @return array<string, mixed>
   *   Associative array of field data keyed by the given key.
   */
  public function fetchAllAssoc(string $key, int|string|null $fetch = NULL): array {
    $return = [];
    if (isset($fetch)) {
      if (is_string($fetch)) {
        $this->setFetchMode(\PDO::FETCH_CLASS, $fetch);
      }
      elseif (is_int($fetch)) {
        $this->setFetchMode($fetch);
      }
    }

    foreach ($this as $record) {
      $record_key = match (TRUE) {
        is_object($record) && property_exists($record, $key) => $record->$key,
        is_array($record) && isset($record[$key]) => $record[$key],
        default => throw new \InvalidArgumentException('Record must be an object or array'),
      };
      $return[$record_key] = $record;
    }

    return $return;
  }

  /**
   * Returns the entire result set as a single associative array.
   *
   * @param int $key_index
   *   Index of the field to use as key.
   * @param int $value_index
   *   Index of the field to use as value.
   *
   * @return array<int|string, mixed>
   *   Associative array.
   */
  public function fetchAllKeyed(int $key_index = 0, int $value_index = 1): array {
    $return = [];
    $this->setFetchMode(\PDO::FETCH_NUM);
    foreach ($this as $record) {
      if (!is_array($record)) {
        throw new \InvalidArgumentException('Record must be an array');
      }
      if (!isset($record[$key_index])) {
        throw new \InvalidArgumentException('Key index must be set');
      }
      if (!isset($record[$value_index])) {
        throw new \InvalidArgumentException('Value index must be set');
      }
      if (!is_int($record[$key_index]) && !is_string($record[$key_index])) {
        throw new \InvalidArgumentException('Key index must be an integer or string');
      }
      $return[$record[$key_index]] = $record[$value_index];
    }
    return $return;
  }

  /**
   * Returns a single field from the next record of a result set.
   *
   * @param int $index
   *   Index of the field to fetch.
   *
   * @return mixed
   *   Field data.
   */
  public function fetchField(int $index = 0): mixed {
    return $this->fetchColumn($index);
  }

  /**
   * Returns an entire single column of a result set as an indexed array.
   *
   * @param int $index
   *   Index of the column to fetch.
   *
   * @return array<int, mixed>
   *   Field data.
   */
  public function fetchCol(int $index = 0): array {
    return $this->fetchAll(\PDO::FETCH_COLUMN, $index);
  }

}
