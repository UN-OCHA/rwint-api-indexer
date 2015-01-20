<?php

namespace RWAPIIndexer\Database;

/**
 * Really basic database abstraction layer
 * that mimics Drupal's interface for compatibility.
 */
class Query {
  private $table = '';
  private $alias = '';
  private $connection = NULL;
  private $parts = array();

  public function __construct($table, $alias, $connection) {
    $this->table = $table;
    $this->alias = $alias;
    $this->connection = $connection;
  }

  public function addField($table, $field, $alias) {
    $this->parts['fields'][] = "{$table}.{$field} AS {$alias}";
    return $this;
  }

  public function addExpression($expression, $alias) {
    $this->parts['fields'][] = "{$expression} AS {$alias}";
    return $this;
  }

  public function leftJoin($table, $alias, $condition) {
    return $this->join('LEFT', $table, $alias, $condition);
  }

  public function innerJoin($table, $alias, $condition) {
    return $this->join('INNER', $table, $alias, $condition);
  }

  public function join($type, $table, $alias, $condition) {
    $this->parts['joins'][] = "{$type} JOIN {$table} AS {$alias} ON {$condition}";
    return $alias;
  }

  public function condition($field, $value, $operator = '=') {
    switch ($operator) {
      case 'IN':
        $values = array();
        foreach ($value as $data) {
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

  public function groupBy($field) {
    $this->parts['group by'][] = $field;
    return $this;
  }

  public function orderBy($field, $direction) {
    $this->parts['order by'][] = "{$field} {$direction}";
    return $this;
  }

  public function range($offset, $limit) {
    $this->parts['limit'] = "{$offset}, {$limit}";
    return $this;
  }

  public function count() {
    $this->parts['count'] = TRUE;
  }

  public function build() {
        // Select.
    $query = array('SELECT');

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
      $query[] = 'GROUP BY ' .  implode(', ', $this->parts['group by']);
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

  public function execute() {
    $query = $this->build();
    return $this->connection->query($query);
  }

  public function __tostring() {
    return $this->build();
  }
}
