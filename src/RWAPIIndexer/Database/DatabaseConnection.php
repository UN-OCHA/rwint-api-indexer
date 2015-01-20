<?php

namespace RWAPIIndexer\Database;

/**
 * Really basic database connection layer
 * that mimics Drupal's interface for compatibility.
 */
class DatabaseConnection extends \PDO {

  /**
   * Construct a database connection.
   *
   * @param string $dsn
   *   Data source name.
   * @param string $user
   *   Database username.
   * @param string $password
   *   Database password.
   */
  public function __construct($dsn, $user, $password) {
    parent::__construct($dsn, $user, $password);

    // Set the statement class.
    $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array('\RWAPIIndexer\Database\Statement', array($this)));

    // Make sure we can return all the concatenated data.
    $this->query('SET SESSION group_concat_max_len = 100000');
  }
}
