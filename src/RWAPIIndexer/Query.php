<?php

namespace RWAPIIndexer;

use RWAPIIndexer\Database\DatabaseConnection;
use RWAPIIndexer\Database\Query as DatabaseQuery;

/**
 * Query handler class.
 */
class Query {

  /**
   * Database connection.
   *
   * @var \RWAPIIndexer\Database\DatabaseConnection
   */
  protected $connection = NULL;

  /**
   * Entity type.
   *
   * @var string
   */
  protected $entityType = '';

  /**
   * Entity bundle.
   *
   * @var string
   */
  protected $bundle = '';

  /**
   * Base table.
   *
   * @var string
   */
  protected $baseTable = '';

  /**
   * Field for getting the items to index.
   *
   * @var string
   */
  protected $baseField = '';

  /**
   * Query options for the current entity type/bundle.
   *
   * @var array
   */
  protected $options = [];

  /**
   * Construct the query handler.
   *
   * @param \RWAPIIndexer\Database\DatabaseConnection $connection
   *   Database connection.
   * @param string $entity_type
   *   Type of the entity on which to perform the queries.
   * @param string $bundle
   *   Bundle of the entity on which to perform the queries.
   * @param array $options
   *   Query options.
   */
  public function __construct(DatabaseConnection $connection, $entity_type, $bundle, array $options = []) {
    $this->connection = $connection;
    $this->entityType = $entity_type;
    $this->bundle = $bundle;
    $this->options = $options;

    if ($this->entityType === 'node') {
      $this->baseTable = 'node';
      $this->baseField = 'nid';
    }
    elseif ($entity_type === 'taxonomy_term') {
      $this->baseTable = 'taxonomy_term_data';
      $this->baseField = 'tid';
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
    return new DatabaseQuery($this->baseTable, $this->baseTable, $this->connection);
  }

  /**
   * Add the entity id field to the list of fields returned by the query.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the id field.
   */
  public function addIdField(DatabaseQuery $query) {
    $query->addField($this->baseTable, $this->baseField, 'id');
  }

  /**
   * Add a condition on the entity bundle to the query.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the bundle condition.
   */
  public function setBundle(DatabaseQuery $query) {
    if ($this->entityType === 'node') {
      $query->condition($this->baseTable . '.type', $this->bundle);
    }
    elseif ($this->entityType === 'taxonomy_term') {
      $query->addField($this->baseTable, 'name', 'name');
      $query->innerJoin('taxonomy_vocabulary', 'taxonomy_vocabulary', "taxonomy_vocabulary.vid = {$this->baseTable}.vid");
      $query->condition('taxonomy_vocabulary.machine_name', $this->bundle, '=');
    }
  }

  /**
   * Add a condition on the ID field, specifying from where to start indexing.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the offset condition.
   * @param sting|int $offset
   *   Entity id from which to start the indexing.
   */
  public function setOffset(DatabaseQuery $query, $offset = NULL) {
    if (!empty($offset)) {
      $query->condition($this->baseTable . '.' . $this->baseField, $offset, '<=');
    }
  }

  /**
   * Set the maximum number of items to fetch.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which set limit.
   * @param int $limit
   *   Maximum number of document to index.
   */
  public function setLimit(DatabaseQuery $query, $limit = NULL) {
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
  public function setGroupBy(DatabaseQuery $query) {
    $query->groupBy($this->baseTable . '.' . $this->baseField);
  }

  /**
   * Sort the results by entity ID.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the order by statement.
   * @param string $direction
   *   Ordering direction, either DESC or ASC.
   */
  public function setOrderBy(DatabaseQuery $query, $direction = 'DESC') {
    $query->orderBy($this->baseTable . '.' . $this->baseField, $direction);
  }

  /**
   * Modify the query to count the number of returnable items.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to modify.
   */
  public function setCount(DatabaseQuery $query) {
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
  public function setIds(DatabaseQuery $query, array $ids = NULL) {
    if (!empty($ids)) {
      $query->condition($this->baseTable . '.' . $this->baseField, $ids, 'IN');
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
  public function setFilters(DatabaseQuery $query, array $conditions) {
    $entity_type = $this->entityType;
    $base_table = $this->baseTable;
    $base_field = $this->baseField;

    foreach ($conditions as $category => $fields) {
      foreach ($fields as $field => $values) {
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
   * @param int $limit
   *   Maximum number of items to index.
   * @param int $offset
   *   Id of the entity from which to start the indexing.
   *
   * @return int
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

    $result = $query->execute();
    if (!empty($result)) {
      $count = (int) $result->fetchField();
      return $limit <= 0 ? $count : min($limit, $count);
    }
    return 0;
  }

  /**
   * Get the offset from which to start the indexing.
   *
   * @param int $offset
   *   Id of the entity from which to start the indexing.
   *
   * @return int
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

      $result = $query->execute();
      $offset = !empty($result) ? (int) $result->fetchField() : 0;
    }
    return $offset;
  }

  /**
   * Get ids of the entities to index.
   *
   * @param int $limit
   *   Maximum number of items to index.
   * @param int $offset
   *   Id of the entity from which to start the indexing.
   * @param array $ids
   *   Ids of the entities to index.
   *
   * @return ids
   *   array.
   */
  public function getIds($limit = NULL, $offset = NULL, array $ids = NULL) {
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
    $result = $query->execute();
    return !empty($result) ? $result->fetchCol() : [];
  }

  /**
   * Process a query and return the resulting items.
   *
   * @param int $limit
   *   Maximum number of items to index.
   * @param int $offset
   *   Entity ID from which to start fetching the items.
   * @param array $ids
   *   Ids of the entities to index.
   *
   * @return array
   *   Items returned by the query.
   */
  public function getItems($limit = NULL, $offset = NULL, array $ids = NULL) {
    $entity_type = $this->entityType;
    $base_table = $this->baseTable;
    $base_field = $this->baseField;

    // Get the id of the entities to retrieve.
    $ids = $this->getIds($limit, $offset, $ids);
    if (empty($ids)) {
      return [];
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
    $result = $query->execute();
    if (empty($result)) {
      return [];
    }
    $items = $result->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

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
  public function loadReferences(array &$items) {
    if (!empty($this->options['references']) && !empty($items)) {
      $ids = array_keys($items);
      $queries = [];
      foreach ($this->options['references'] as $field_name => $alias) {
        $field_table = 'field_data_' . $field_name;
        $query = new DatabaseQuery($field_table, $field_table, $this->connection);
        $query->addField($field_table, 'entity_id', 'id');
        $query->addField($field_table, "{$field_name}_tid", 'value');
        $query->addExpression($this->connection->quote($alias), 'alias');
        $query->condition("{$field_table}.entity_type", $this->entityType, '=');
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
