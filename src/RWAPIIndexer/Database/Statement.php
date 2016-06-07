<?php

namespace RWAPIIndexer\Database;

/**
 * Simple extension of PDOStatement with additional fetch functions.
 */
class Statement extends \PDOStatement {
  public $dbh;

  protected function __construct($dbh) {
    $this->dbh = $dbh;
  }

  /**
   * Returns the result set as an associative array keyed by the given field.
   */
  public function fetchAllAssoc($key, $fetch = NULL) {
    $return = array();
    if (isset($fetch)) {
      if (is_string($fetch)) {
        $this->setFetchMode(\PDO::FETCH_CLASS, $fetch);
      }
      else {
        $this->setFetchMode($fetch);
      }
    }

    foreach ($this as $record) {
      $record_key = is_object($record) ? $record->$key : $record[$key];
      $return[$record_key] = $record;
    }

    return $return;
  }

  /**
   * Returns the entire result set as a single associative array.
   */
  public function fetchAllKeyed($key_index = 0, $value_index = 1) {
    $return = array();
    $this->setFetchMode(\PDO::FETCH_NUM);
    foreach ($this as $record) {
      $return[$record[$key_index]] = $record[$value_index];
    }
    return $return;
  }

  /**
   * Returns a single field from the next record of a result set.
   */
  public function fetchField($index = 0) {
    return $this->fetchColumn($index);
  }
}
