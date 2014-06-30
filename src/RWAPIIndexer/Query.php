<?php

namespace RWAPIIndexer;

/**
 * Query handler class.
 */
class Query {
  // Database connection.
  protected $connection = NULL;

  // Entity type and bundle.
  protected $entity_type = '';
  protected $bundle = '';

  // Base table and field for getting the items to index.
  protected $base_table = '';
  protected $base_field = '';

  // Query options for the current entity type/bundle.
  protected $options = array();

 /**
  * Construct the query handler.
  *
  * @param \RWAPIIndexer\Database\Connection $connection
  *   Database connection.
  * @param string $entity_type
  *   Type of the entity on which to perform the queries.
  * @param string $bundle
  *   Bundle of the entity on which to perform the queries.
  * @param array $options
  *   Query options.
  */
  public function __construct($connection, $entity_type, $bundle, array $options = array()) {
    $this->connection = $connection;
    $this->entity_type = $entity_type;
    $this->bundle = $bundle;
    $this->options = $options;

    if ($this->entity_type === 'node') {
      $this->base_table = 'node';
      $this->base_field = 'nid';
    }
    elseif ($entity_type === 'taxonomy_term') {
      $this->base_table = 'taxonomy_term_data';
      $this->base_field = 'tid';
    }
    else {
      throw new \Exception('RWAPIIndexer\Query: Unknow entity type');
    }
  }

  /**
   * Create a new database Query.
   *
   * @return \RWAPIIndexer\Database\Query
   *   Return a database query.
   */
  public function newQuery() {
    return new \RWAPIIndexer\Database\Query($this->base_table, $this->base_table, $this->connection);
  }

  /**
   * Add the entity id field to the list of fields returned by the query.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the id field.
   */
  public function addIdField($query) {
    $query->addField($this->base_table, $this->base_field, 'id');
  }

  /**
   * Add a condition on the entity bundle to the query.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the bundle condition.
   */
  public function setBundle($query) {
    if ($this->entity_type === 'node') {
      $query->condition($this->base_table . '.type', $this->bundle);
    }
    elseif ($this->entity_type === 'taxonomy_term') {
      $query->addField($this->base_table, 'name', 'name');
      $query->innerJoin('taxonomy_vocabulary', 'taxonomy_vocabulary', "taxonomy_vocabulary.vid = {$this->base_table}.vid");
      $query->condition('taxonomy_vocabulary.machine_name', $this->bundle, '=');
    }
  }

  /**
   * Add a condition on the ID field, specifying from where to start indexing.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the offset condition.
   */
  public function setOffset($query, $offset = NULL) {
    if (!empty($offset)) {
      $query->condition($this->base_table . '.' . $this->base_field, $offset, '<=');
    }
  }

  /**
   * Set the maximum number of items to fetch.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which set limit.
   */
  public function setLimit($query, $limit = NULL) {
    if (!empty($limit)) {
      $query->range(0, $limit);
    }
  }

  /**
   * Group the results by entity ID.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the group by statement.
   */
  public function setGroupBy($query) {
    $query->groupBy($this->base_table . '.' . $this->base_field);
  }

  /**
   * Sort the results by entity ID.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the order by statement.
   */
  public function setOrderBy($query, $direction = 'DESC') {
    $query->orderBy($this->base_table . '.' . $this->base_field, $direction);
  }

  /**
   * Modify the query to count the number of returnable items.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to modify.
   */
  public function setCount($query) {
    $query->count();
  }

  /**
   * Get the maximum number of items to index.
   *
   * @return integer
   *   Limit.
   */
  public function getLimit($limit = 0) {
    $query = $this->newQuery();

    $this->setBundle($query);
    $this->setCount($query);

    $count = (int) $query->execute()->fetchField();

    return $limit <= 0 ? $count : min($limit, $count);
  }

  /**
   * Get the offset from which to start the indexing.
   *
   * @return integer
   *   Offset.
   */
  public function getOffset($offset = 0) {
    if ($offset <= 0) {
      $query = $this->newQuery();
      $this->addIdField($query);
      $this->setBundle($query);
      $this->setOrderBy($query);
      $this->setLimit($query, 1);

      $offset = (int) $query->execute()->fetchField();
    }
    return $offset;
  }

  /**
   * Process a query and return the resulting items.
   *
   * @param integer $limit
   *   Maximum number of items to index.
   * @param integer $offset
   *   Entity ID from which to start fetching the items.
   * @return  array
   *   Items returned by the query.
   */
  public function getItems($limit, $offset) {
    $entity_type = $this->entity_type;
    $bundle = $this->bundle;
    $base_table = $this->base_table;
    $base_field = $this->base_field;

    // Base query.
    $query = $this->newQuery();

    $this->addIdField($query);
    $this->setBundle($query);
    $this->setOffset($query, $offset);
    $this->setGroupBy($query);
    $this->setOrderBy($query);
    $this->setLimit($query, $limit);

    // Add the extra fields.
    if (isset($this->options['fields'])) {
      foreach ($this->options['fields'] as $alias => $field) {
        $query->addField($base_table, $field, $alias);
      }
    }

    // Add the joined fields.
    if (isset($this->options['field_joins'])) {
      foreach ($this->options['field_joins'] as $field_name => $values) {
        $field_table = 'field_data_' . $field_name;

        $condition = "{$field_table}.entity_id = {$base_table}.{$base_field} AND {$field_table}.entity_type = '{$entity_type}'";
        $query->leftJoin($field_table, $field_table, $condition);

        // Add the expressions.
        foreach ($values as $alias => $value) {
          switch ($value) {
            case 'taxonomy_reference':
              $expression = "GROUP_CONCAT(DISTINCT {$field_table}.{$field_name}_tid SEPARATOR '%%%')";
              $query->addExpression($expression, $alias);
              break;

            case 'multi_value':
              $expression = "GROUP_CONCAT(DISTINCT {$field_table}.{$field_name}_value SEPARATOR '%%%')";
              $query->addExpression($expression, $alias);
              break;

            case 'image_reference':
              $file_managed_table = 'file_managed_' . $field_name;
              $condition = "{$file_managed_table}.fid = {$field_table}.{$field_name}_fid";
              $query->leftJoin('file_managed', $file_managed_table, $condition);

              $expression = "GROUP_CONCAT(DISTINCT IF({$field_table}.{$field_name}_fid, CONCAT_WS('###',
                  {$field_table}.{$field_name}_fid,
                  IFNULL({$field_table}.{$field_name}_alt, ''),
                  IFNULL({$field_table}.{$field_name}_title, ''),
                  IFNULL({$field_table}.{$field_name}_width, ''),
                  IFNULL({$field_table}.{$field_name}_height, ''),
                  IFNULL({$file_managed_table}.uri, ''),
                  IFNULL({$file_managed_table}.filename, ''),
                  IFNULL({$file_managed_table}.filesize, '')
                ), NULL) SEPARATOR '%%%')";
              $query->addExpression($expression, $alias);
              break;

            case 'file_reference':
              $file_managed_table = 'file_managed_' . $field_name;
              $condition = "{$file_managed_table}.fid = {$field_table}.{$field_name}_fid";
              $query->leftJoin('file_managed', $file_managed_table, $condition);

              $expression = "GROUP_CONCAT(DISTINCT IF({$field_table}.{$field_name}_fid, CONCAT_WS('###',
                  {$field_table}.{$field_name}_fid,
                  IFNULL({$field_table}.{$field_name}_description, ''),
                  IFNULL({$file_managed_table}.uri, ''),
                  IFNULL({$file_managed_table}.filename, ''),
                  IFNULL({$file_managed_table}.filesize, '')
                ), NULL) SEPARATOR '%%%')";
              $query->addExpression($expression, $alias);
              break;

            default:
              $query->addField($field_table, $field_name . '_' . $value, $alias);
          }
        }
      }
    }

    // Get the items.
    try {
      return $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    }
    catch (\Exception $exception) {
      echo $exception->getMessage() . "\n";
      echo $query->build() . "\n";
      return array();
    }
  }
}
