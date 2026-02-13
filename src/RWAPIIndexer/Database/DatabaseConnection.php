<?php

declare(strict_types=1);

namespace RWAPIIndexer\Database;

/**
 * Database connection handler.
 *
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
  public function __construct(string $dsn, string $user, string $password) {
    parent::__construct($dsn, $user, $password);

    // Set the statement class.
    $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [
      '\RWAPIIndexer\Database\Statement', [$this],
    ]);

    // Make sure we can return all the concatenated data (MySQL only).
    if (strpos($dsn, 'sqlite') === FALSE) {
      $this->query('SET SESSION group_concat_max_len = 100000');
    }
  }

}
