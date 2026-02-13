<?php

declare(strict_types=1);

namespace RWAPIIndexer\Database;

/**
 * Query handler.
 *
 * Really basic database abstraction layer
 * that mimics Drupal's interface for compatibility.
 */
class Query {

  /**
   * Query base table.
   *
   * @var string
   */
  private string $table = '';

  /**
   * Query base table alias.
   *
   * @var string
   */
  private string $alias = '';

  /**
   * Database connection.
   *
   * @var \RWAPIIndexer\Database\DatabaseConnection
   */
  private DatabaseConnection $connection;

  /**
   * Fields to select.
   *
   * @var string[]
   */
  private array $queryFields = [];

  /**
   * Join clauses.
   *
   * @var string[]
   */
  private array $queryJoins = [];

  /**
   * Where conditions.
   *
   * @var string[]
   */
  private array $queryWhere = [];

  /**
   * Group by clauses.
   *
   * @var string[]
   */
  private array $queryGroupBy = [];

  /**
   * Order by clauses.
   *
   * @var string[]
   */
  private array $queryOrderBy = [];

  /**
   * Limit clause (e.g. "0, 10").
   *
   * @var string|null
   */
  private ?string $queryLimit = NULL;

  /**
   * Whether this is a count query.
   *
   * @var bool
   */
  private bool $queryCount = FALSE;

  /**
   * Construct a query object.
   *
   * @param string $table
   *   Table name.
   * @param string $alias
   *   Table alias.
   * @param \RWAPIIndexer\Database\DatabaseConnection $connection
   *   Database connection.
   */
  public function __construct(string $table, string $alias, DatabaseConnection $connection) {
    $this->table = $table;
    $this->alias = $alias;
    $this->connection = $connection;
  }

  /**
   * Add a field to return in the resultset.
   *
   * @param string $table
   *   Table name.
   * @param string $field
   *   Field name.
   * @param string $alias
   *   Field alias.
   *
   * @return \RWAPIIndexer\Database\Query
   *   This query instance.
   */
  public function addField(string $table, string $field, string $alias): self {
    $this->queryFields[] = "{$table}.{$field} AS {$alias}";
    return $this;
  }

  /**
   * Add an expression to return in the resultset.
   *
   * @param string $expression
   *   Expression.
   * @param string $alias
   *   Expression alias.
   *
   * @return \RWAPIIndexer\Database\Query
   *   This query instance.
   */
  public function addExpression(string $expression, string $alias): self {
    $this->queryFields[] = "{$expression} AS {$alias}";
    return $this;
  }

  /**
   * Left join a table to the base table.
   *
   * @param string $table
   *   Table name to join.
   * @param string $alias
   *   Table alias.
   * @param string $condition
   *   Join condition.
   *
   * @return string
   *   Alias of the joined table.
   */
  public function leftJoin(string $table, string $alias, string $condition): string {
    return $this->join('LEFT', $table, $alias, $condition);
  }

  /**
   * Inner join a table to the base table.
   *
   * @param string $table
   *   Table name to join.
   * @param string $alias
   *   Table alias.
   * @param string $condition
   *   Join condition.
   *
   * @return string
   *   Alias of the joined table.
   */
  public function innerJoin(string $table, string $alias, string $condition): string {
    return $this->join('INNER', $table, $alias, $condition);
  }

  /**
   * Join a table to the base table.
   *
   * @param string $type
   *   Join type (INNER, LEFT).
   * @param string $table
   *   Table name to join.
   * @param string $alias
   *   Table alias.
   * @param string $condition
   *   Join condition.
   *
   * @return string
   *   Alias of the joined table.
   */
  public function join(string $type, string $table, string $alias, string $condition): string {
    $this->queryJoins[] = "{$type} JOIN {$table} AS {$alias} ON {$condition}";
    return $alias;
  }

  /**
   * Add a condition on a field.
   *
   * @param string $field
   *   Field name.
   * @param mixed $value
   *   Condition value.
   * @param string $operator
   *   Condition operator type.
   *
   * @return \RWAPIIndexer\Database\Query
   *   This query instance.
   */
  public function condition(string $field, mixed $value, string $operator = '='): self {
    switch ($operator) {
      case 'IN':
        $values = [];
        foreach ((array) $value as $data) {
          $values[] = $this->quote($data);
        }
        $this->queryWhere[] = "{$field} IN (" . implode(",", $values) . ")";
        break;

      case 'IS NOT NULL':
        $this->queryWhere[] = "{$field} {$operator}";
        break;

      default:
        $this->queryWhere[] = "{$field} {$operator} " . $this->quote($value);
    }
    return $this;
  }

  /**
   * Quote a value for safe use in SQL.
   *
   * PDO::quote() expects a string; this wrapper normalizes mixed types
   * before quoting.
   *
   * @param mixed $value
   *   The value to quote (string, int, float, bool, or null).
   *
   * @return string
   *   Quoted value, or the literal NULL for null input.
   */
  private function quote(mixed $value): string {
    if (!is_scalar($value)) {
      throw new \InvalidArgumentException('Value must be a scalar type');
    }
    else {
      return $this->connection->quote((string) $value);
    }
  }

  /**
   * Add a group by clause.
   *
   * @param string $field
   *   Field name.
   *
   * @return \RWAPIIndexer\Database\Query
   *   This query instance.
   */
  public function groupBy(string $field): self {
    $this->queryGroupBy[] = $field;
    return $this;
  }

  /**
   * Add an order by clause.
   *
   * @param string $field
   *   Field name.
   * @param string $direction
   *   Sort direction (asc or desc).
   *
   * @return \RWAPIIndexer\Database\Query
   *   This query instance.
   */
  public function orderBy(string $field, string $direction): self {
    $this->queryOrderBy[] = "{$field} {$direction}";
    return $this;
  }

  /**
   * Add an offset and limit.
   *
   * @param int $offset
   *   Offset.
   * @param int $limit
   *   Limit.
   *
   * @return \RWAPIIndexer\Database\Query
   *   This query instance.
   */
  public function range(int $offset, int $limit): self {
    $this->queryLimit = "{$offset}, {$limit}";
    return $this;
  }

  /**
   * Mark the query as being a count query.
   *
   * @return \RWAPIIndexer\Database\Query
   *   This query instance.
   */
  public function count(): self {
    $this->queryCount = TRUE;
    return $this;
  }

  /**
   * Build the query.
   *
   * @return string
   *   Query string.
   */
  public function build(): string {
    // Select.
    $query = ['SELECT'];

    // Fields.
    if ($this->queryCount) {
      $query[] = "COUNT(*)";
    }
    elseif ($this->queryFields !== []) {
      $query[] = implode(', ', $this->queryFields);
    }
    else {
      $query[] = '*';
    }

    // From.
    $query[] = 'FROM ' . $this->table . ' AS ' . $this->alias;

    // Joins.
    if ($this->queryJoins !== []) {
      $query[] = implode(' ', $this->queryJoins);
    }

    // Where.
    if ($this->queryWhere !== []) {
      $query[] = 'WHERE ' . implode(' AND ', $this->queryWhere);
    }

    // Group By.
    if ($this->queryGroupBy !== []) {
      $query[] = 'GROUP BY ' . implode(', ', $this->queryGroupBy);
    }

    // Order By.
    if ($this->queryOrderBy !== []) {
      $query[] = 'ORDER BY ' . implode(', ', $this->queryOrderBy);
    }

    // Limit.
    if ($this->queryLimit !== NULL) {
      $query[] = 'LIMIT ' . $this->queryLimit;
    }

    $query = implode(' ', $query);

    return $query;
  }

  /**
   * Execute the query.
   *
   * @return \RWAPIIndexer\Database\Statement|null
   *   Database statement.
   */
  public function execute(): Statement|null {
    $query = $this->build();
    /** @var \RWAPIIndexer\Database\Statement|null $statement */
    $statement = $this->connection->query($query) ?: NULL;
    return $statement;
  }

  /**
   * Get query string.
   */
  public function __tostring(): string {
    return $this->build();
  }

}
