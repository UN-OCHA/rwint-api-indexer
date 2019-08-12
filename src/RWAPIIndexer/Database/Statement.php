<?php

namespace RWAPIIndexer\Database;

/**
 * Database statement.
 *
 * Simple extension of PDOStatement with additional fetch functions.
 */
class Statement extends \PDOStatement {

  /**
   * Returns the result set as an associative array keyed by the given field.
   *
   * @param string $key
   *   Field to use a key.
   * @param int $fetch
   *   Fetch mode.
   *
   * @return array
   *   Associative array of field data keyed by the given key.
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
   *
   * @param int $key_index
   *   Index of the field to use as key.
   * @param int $value_index
   *   Index of the field to use as value.
   *
   * @return array
   *   Associative array.
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
   *
   * @param int $index
   *   Index of the field to fetch.
   *
   * @return mixed
   *   Field data.
   */
  public function fetchField($index = 0) {
    return $this->fetchColumn($index);
  }

  /**
   * Returns an entire single column of a result set as an indexed array.
   *
   * @param int $index
   *   Index of the column to fetch.
   *
   * @return array
   *   Field data.
   */
  public function fetchCol($index = 0) {
    return $this->fetchAll(\PDO::FETCH_COLUMN, $index);
  }

}
