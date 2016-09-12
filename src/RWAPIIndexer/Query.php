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
   * Filter the query with the given ids.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to filter.
   * @param array $ids
   *   Entity Ids used to filter the query.
   */
  public function setIds($query, $ids) {
    if (!empty($ids)) {
      $query->condition($this->base_table . '.' . $this->base_field, $ids, 'IN');
    }
  }

  /**
   * Filter the query with given conditions.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to filter.
   * @param array $conditions
   *   Conditions used to filter the query.
   */
  public function setFilters($query, $conditions) {
    $entity_type = $this->entity_type;
    $base_table = $this->base_table;
    $base_field = $this->base_field;

    foreach ($conditions as $category => $fields) {
      foreach ($fields as $field =>  $values) {
        if ($category === 'fields') {
          $query->condition($base_table . '.' . $field, $valus, 'IN');
        }
        else {
          $table = 'field_data_field_' . $field;
          $alias = $table . '_filter';
          $column = $category === 'references' ? 'tid' : 'value';
          $condition = "{$alias}.entity_id = {$base_table}.{$base_field} AND {$alias}.entity_type = '{$entity_type}'";
          $query->innerJoin($table, $alias, $condition);
          $query->condition($alias . '.field_' . $field . '_' . $column, $values, 'IN');
        }
      }
    }
  }

  /**
   * Get the maximum number of items to index.
   *
   * @param integer $limit
   *   Maximum number of items to index.
   * @param integer offset
   *   Id of the entity from which to start the indexing.
   *
   * @return integer
   *   Limit.
   */
  public function getLimit($limit = 0, $offset = 0) {
    $query = $this->newQuery();
    $this->setBundle($query);
    $this->setCount($query);

    // Set the offset.
    if (!empty($offset)) {
      $this->setOffset($query, $offset);
    }

    // Set filters.
    if (!empty($this->options['filters'])) {
      $this->setFilters($query, $this->options['filters']);
    }

    $count = (int) $query->execute()->fetchField();

    return $limit <= 0 ? $count : min($limit, $count);
  }

  /**
   * Get the offset from which to start the indexing.
   *
   * @param integer offset
   *   Id of the entity from which to start the indexing.
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

      // Set filters.
      if (!empty($this->options['filters'])) {
        $this->setFilters($query, $this->options['filters']);
      }

      $offset = (int) $query->execute()->fetchField();
    }
    return $offset;
  }

  /**
   * Get ids of the entities to index.
   *
   * @param integer $limit
   *   Maximum number of items to index.
   * @param integer offset
   *   Id of the entity from which to start the indexing.
   * @param array ids
   *   Ids of the entities to index.
   *
   * @return ids
   *   array.
   */
  public function getIds($limit = NULL, $offset = NULL, $ids = NULL) {
    if (!empty($ids)) {
      return $ids;
    }

    // Base query.
    $query = $this->newQuery();

    $this->addIdField($query);
    $this->setBundle($query);
    $this->setIds($query, $ids);
    $this->setOffset($query, $offset);
    $this->setOrderBy($query);
    $this->setLimit($query, $limit);

    // Set filters.
    if (!empty($this->options['filters'])) {
      $this->setFilters($query, $this->options['filters']);
    }

    // Fetch the ids.
    return $query->execute()->fetchCol();
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
  public function getItems($limit = NULL, $offset = NULL, $ids = NULL) {
    $entity_type = $this->entity_type;
    $bundle = $this->bundle;
    $base_table = $this->base_table;
    $base_field = $this->base_field;

    // Get the id of the entities to retrieve.
    $ids = $this->getIds($limit, $offset, $ids);
    if (empty($ids)) {
      return array();
    }

    // Base query.
    $query = $this->newQuery();
    $this->addIdField($query);
    $this->setBundle($query);
    $this->setIds($query, $ids);
    $this->setGroupBy($query);

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

    // Fetch the items.
    $items = $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    // Sort by ID desc.
    krsort($items);

    // Load the references and attach them to the items.
    $this->loadReferences($items);

    return $items;
  }

  /**
   * Load the references for the given entity items.
   *
   * @param array $items
   *   Items for which to load the references.
   */
  public function loadReferences(&$items) {
    if (!empty($this->options['references']) && !empty($items)) {
      $ids = array_keys($items);
      $queries = array();
      foreach ($this->options['references'] AS $field_name => $alias) {
        $field_table = 'field_data_' . $field_name;
        $query = new \RWAPIIndexer\Database\Query($field_table, $field_table, $this->connection);
        $query->addField($field_table, 'entity_id', 'id');
        $query->addField($field_table, "{$field_name}_tid", 'value');
        $query->addExpression($this->connection->quote($alias), 'alias');
        $query->condition("{$field_table}.entity_type", $this->entity_type, '=');
        $query->condition("{$field_table}.entity_id", $ids, 'IN');
        $queries[] = $query->build();
      }

      $statement = $this->connection->query(implode(' UNION ', $queries));
      while ($row = $statement->fetchObject()) {
        $items[$row->id][$row->alias][] = $row->value;
      }
    }
  }
}
