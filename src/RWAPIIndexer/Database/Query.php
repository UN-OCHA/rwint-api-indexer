<?php

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
  private $table = '';

  /**
   * Query base table alias.
   *
   * @var string
   */
  private $alias = '';

  /**
   * Database connection.
   *
   * @var \RWAPIIndexer\Database\DatabaseConnection
   */
  private $connection = NULL;

  /**
   * Query parts.
   *
   * @var array
   */
  private $parts = [];

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
  public function __construct($table, $alias, DatabaseConnection $connection) {
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
  public function addField($table, $field, $alias) {
    $this->parts['fields'][] = "{$table}.{$field} AS {$alias}";
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
  public function addExpression($expression, $alias) {
    $this->parts['fields'][] = "{$expression} AS {$alias}";
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
  public function leftJoin($table, $alias, $condition) {
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
  public function innerJoin($table, $alias, $condition) {
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
  public function join($type, $table, $alias, $condition) {
    $this->parts['joins'][] = "{$type} JOIN {$table} AS {$alias} ON {$condition}";
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
  public function condition($field, $value, $operator = '=') {
    switch ($operator) {
      case 'IN':
        $values = [];
        foreach ((array) $value as $data) {
          $values[] = $this->connection->quote($data);
        }
        $this->parts['where'][] = "{$field} IN (" . implode(",", $values) . ")";
        break;

      default:
        $value = $this->connection->quote($value);
        $this->parts['where'][] = "{$field} {$operator} {$value}";
    }
    return $this;
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
  public function groupBy($field) {
    $this->parts['group by'][] = $field;
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
  public function orderBy($field, $direction) {
    $this->parts['order by'][] = "{$field} {$direction}";
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
  public function range($offset, $limit) {
    $this->parts['limit'] = "{$offset}, {$limit}";
    return $this;
  }

  /**
   * Mark the query as being a count query.
   *
   * @return \RWAPIIndexer\Database\Query
   *   This query instance.
   */
  public function count() {
    $this->parts['count'] = TRUE;
    return $this;
  }

  /**
   * Build the query.
   *
   * @return string
   *   Query string.
   */
  public function build() {
    // Select.
    $query = ['SELECT'];

    // Fields.
    if (!empty($this->parts['count'])) {
      $query[] = "COUNT(*)";
    }
    elseif (!empty($this->parts['fields'])) {
      $query[] = implode(', ', $this->parts['fields']);
    }
    else {
      $query[] = '*';
    }

    // From.
    $query[] = 'FROM ' . $this->table . ' AS ' . $this->alias;

    // Joins.
    if (!empty($this->parts['joins'])) {
      $query[] = implode(' ', $this->parts['joins']);
    }

    // Where.
    if (!empty($this->parts['where'])) {
      $query[] = 'WHERE ' . implode(' AND ', $this->parts['where']);
    }

    // Group By.
    if (!empty($this->parts['group by'])) {
      $query[] = 'GROUP BY ' . implode(', ', $this->parts['group by']);
    }

    // Order By.
    if (!empty($this->parts['order by'])) {
      $query[] = 'ORDER BY ' . implode(', ', $this->parts['order by']);
    }

    // Limit.
    if (isset($this->parts['limit'])) {
      $query[] = 'LIMIT ' . $this->parts['limit'];
    }

    $query = implode(' ', $query);

    return $query;
  }

  /**
   * Execute the query.
   *
   * @return \RWAPIIndexer\Database\Statement
   *   Database statement.
   */
  public function execute() {
    $query = $this->build();
    return $this->connection->query($query);
  }

  /**
   * Get query string.
   */
  public function __tostring() {
    return $this->build();
  }

}
