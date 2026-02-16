<?php

declare(strict_types=1);

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
  protected DatabaseConnection $connection;

  /**
   * Entity type.
   *
   * @var string
   */
  protected string $entityType = '';

  /**
   * Entity bundle.
   *
   * @var string
   */
  protected string $bundle = '';

  /**
   * Base table.
   *
   * @var string
   */
  protected string $baseTable = '';

  /**
   * Field for getting the items to index.
   *
   * @var string
   */
  protected string $baseField = '';

  /**
   * Table containing the UUID.
   *
   * @var string
   */
  protected string $uuidTable = '';

  /**
   * Field containing the UUID.
   *
   * @var string
   */
  protected string $uuidField = 'uuid';

  /**
   * Query options for the current entity type/bundle.
   *
   * @var array{
   *   filters?: array<string, array<string, mixed>>,
   *   fields?: array<string, string>,
   *   field_joins?: array<string, array<string, string>>,
   *   references?: array<string, string>
   * }
   */
  protected array $options = [];

  /**
   * Construct the query handler.
   *
   * @param \RWAPIIndexer\Database\DatabaseConnection $connection
   *   Database connection.
   * @param string $entity_type
   *   Type of the entity on which to perform the queries.
   * @param string $bundle
   *   Bundle of the entity on which to perform the queries.
   * @param array{filters?: array<string, array<string, mixed>>, fields?: array<string, string>, field_joins?: array<string, array<string, string>>, references?: array<string, string>} $options
   *   Query options.
   */
  public function __construct(
    DatabaseConnection $connection,
    string $entity_type,
    string $bundle,
    array $options = [],
  ) {
    $this->connection = $connection;
    $this->entityType = $entity_type;
    $this->bundle = $bundle;
    $this->options = $options;

    if ($this->entityType === 'node') {
      $this->baseTable = 'node_field_data';
      $this->baseField = 'nid';
      $this->uuidTable = 'node';
    }
    elseif ($entity_type === 'taxonomy_term') {
      $this->baseTable = 'taxonomy_term_field_data';
      $this->baseField = 'tid';
      $this->uuidTable = 'taxonomy_term_data';
    }
    else {
      throw new \Exception('RWAPIIndexer\Query: Unknown entity type');
    }
  }

  /**
   * Create a new database Query.
   *
   * @return \RWAPIIndexer\Database\Query
   *   Return a database query.
   */
  public function newQuery(): DatabaseQuery {
    return new DatabaseQuery($this->baseTable, $this->baseTable, $this->connection);
  }

  /**
   * Add the entity id field to the list of fields returned by the query.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the id field.
   */
  public function addIdField(DatabaseQuery $query): void {
    $query->addField($this->baseTable, $this->baseField, 'id');
  }

  /**
   * Add the entity id field to the list of fields returned by the query.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the id field.
   */
  public function addUuidField(DatabaseQuery $query): void {
    $query->innerJoin($this->uuidTable, $this->uuidTable, "{$this->uuidTable}.{$this->baseField} = {$this->baseTable}.{$this->baseField}");
    $query->addField($this->uuidTable, $this->uuidField, 'uuid');
  }

  /**
   * Add a condition on the entity bundle to the query.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the bundle condition.
   */
  public function setBundle(DatabaseQuery $query): void {
    if ($this->entityType === 'node') {
      $query->condition($this->baseTable . '.type', $this->bundle);
    }
    elseif ($this->entityType === 'taxonomy_term') {
      $query->condition($this->baseTable . '.vid', $this->bundle);
    }
  }

  /**
   * Add a condition on the ID field, specifying from where to start indexing.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to which add the offset condition.
   * @param string|int|null $offset
   *   Entity id from which to start the indexing.
   */
  public function setOffset(DatabaseQuery $query, string|int|null $offset = NULL): void {
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
  public function setLimit(DatabaseQuery $query, ?int $limit = NULL): void {
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
  public function setGroupBy(DatabaseQuery $query): void {
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
  public function setOrderBy(DatabaseQuery $query, string $direction = 'DESC'): void {
    $query->orderBy($this->baseTable . '.' . $this->baseField, $direction);
  }

  /**
   * Modify the query to count the number of returnable items.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to modify.
   */
  public function setCount(DatabaseQuery $query): void {
    $query->count();
  }

  /**
   * Filter the query with the given ids.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to filter.
   * @param int[]|null $ids
   *   Entity Ids used to filter the query.
   */
  public function setIds(DatabaseQuery $query, ?array $ids = NULL): void {
    if (!empty($ids)) {
      $query->condition($this->baseTable . '.' . $this->baseField, $ids, 'IN');
    }
  }

  /**
   * Filter the query with given conditions.
   *
   * @param \RWAPIIndexer\Database\Query $query
   *   Query to filter.
   * @param array<string, array<string, mixed>> $conditions
   *   Conditions used to filter the query.
   */
  public function setFilters(DatabaseQuery $query, array $conditions): void {
    $entity_type = $this->entityType;
    $base_table = $this->baseTable;
    $base_field = $this->baseField;

    foreach ($conditions as $category => $fields) {
      foreach ($fields as $field => $values) {
        if ($category === 'fields') {
          if ($values !== '*') {
            $query->condition($base_table . '.' . $field, $values, 'IN');
          }
          else {
            $query->condition($base_table . '.' . $field, NULL, 'IS NOT NULL');
          }
        }
        else {
          $table = $entity_type . '__field_' . $field;
          $alias = $table . '_filter';
          $condition = "{$alias}.entity_id = {$base_table}.{$base_field}";
          $query->innerJoin($table, $alias, $condition);
          // `*` is to check for the existence of the field which is already
          // implied with the innerJoin().
          if ($values !== '*') {
            $column = $category === 'references' ? 'target_id' : 'value';
            $query->condition($alias . '.field_' . $field . '_' . $column, $values, 'IN');
          }
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
  public function getLimit(int $limit = 0, int $offset = 0): int {
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

    $count = $query->execute()?->fetchField();
    if ($count !== FALSE && $count !== NULL && is_numeric($count)) {
      $count = (int) $count;
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
  public function getOffset(int $offset = 0): int {
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

      $field = $query->execute()?->fetchField();
      if ($field !== FALSE && $field !== NULL && is_numeric($field)) {
        $offset = (int) $field;
      }
      else {
        $offset = 0;
      }
    }
    return $offset;
  }

  /**
   * Get ids of the entities to index.
   *
   * @param ?int $limit
   *   Maximum number of items to index.
   * @param ?int $offset
   *   Id of the entity from which to start the indexing.
   * @param int[]|null $ids
   *   Ids of the entities to index.
   *
   * @return int[]
   *   array.
   */
  public function getIds(?int $limit = NULL, ?int $offset = NULL, ?array $ids = NULL): array {
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
    $col = $query->execute()?->fetchCol() ?? [];
    $ids = [];
    foreach ($col as $id) {
      if (is_numeric($id)) {
        $ids[] = (int) $id;
      }
    }
    return $ids;
  }

  /**
   * Process a query and return the resulting items.
   *
   * @param ?int $limit
   *   Maximum number of items to index.
   * @param ?int $offset
   *   Entity ID from which to start fetching the items.
   * @param int[]|null $ids
   *   Ids of the entities to index.
   *
   * @return array<int, array<string, mixed>>
   *   Items returned by the query.
   */
  public function getItems(?int $limit = NULL, ?int $offset = NULL, ?array $ids = NULL): array {
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
    $this->addUuidField($query);
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
        $field_table = $entity_type . '__' . $field_name;

        $condition = "{$field_table}.entity_id = {$base_table}.{$base_field}";
        $query->leftJoin($field_table, $field_table, $condition);

        // Add the expressions.
        foreach ($values as $alias => $value) {
          switch ($value) {
            case 'multi_value':
              $expression = "GROUP_CONCAT(DISTINCT {$field_table}.{$field_name}_value SEPARATOR '%%%')";
              $query->addExpression($expression, $alias);
              break;

            // @todo review performances as there are many tables to join now.
            case 'image_reference':
              // Join the image table.
              $media_image_field = 'field_media_image';
              $media_image_table = 'media__' . $media_image_field;
              $media_image_alias = $media_image_table . '_' . $field_name;
              $query->leftJoin($media_image_table, $media_image_alias, "{$media_image_alias}.entity_id = {$field_table}.{$field_name}_target_id");

              // Join the copyright table.
              $media_copyright_field = 'field_copyright';
              $media_copyright_table = 'media__' . $media_copyright_field;
              $media_copyright_alias = $media_copyright_table . '_' . $field_name;
              $query->leftJoin($media_copyright_table, $media_copyright_alias, "{$media_copyright_alias}.entity_id = {$field_table}.{$field_name}_target_id");

              // Join the description table.
              $media_description_field = 'field_description';
              $media_description_table = 'media__' . $media_description_field;
              $media_description_alias = $media_description_table . '_' . $field_name;
              $query->leftJoin($media_description_table, $media_description_alias, "{$media_description_alias}.entity_id = {$field_table}.{$field_name}_target_id");

              // Join the file managed table.
              $file_managed_table = 'file_managed';
              $file_managed_alias = $file_managed_table . '_' . $field_name;
              $query->leftJoin($file_managed_table, $file_managed_alias, "{$file_managed_alias}.fid = {$media_image_alias}.{$media_image_field}_target_id");

              $expression = "GROUP_CONCAT(DISTINCT IF({$field_table}.{$field_name}_target_id IS NOT NULL, CONCAT_WS('###',
                  {$field_table}.delta,
                  {$field_table}.{$field_name}_target_id,
                  IFNULL({$media_image_alias}.{$media_image_field}_width, ''),
                  IFNULL({$media_image_alias}.{$media_image_field}_height, ''),
                  IFNULL({$file_managed_alias}.uri, ''),
                  IFNULL({$file_managed_alias}.filename, ''),
                  IFNULL({$file_managed_alias}.filemime, ''),
                  IFNULL({$file_managed_alias}.filesize, ''),
                  IFNULL({$media_copyright_alias}.{$media_copyright_field}_value, ''),
                  IFNULL({$media_description_alias}.{$media_description_field}_value, '')
                ), NULL) SEPARATOR '%%%')";
              $query->addExpression($expression, $alias);
              break;

            case 'file_reference':
              // Join the file managed table.
              $file_managed_table = 'file_managed';
              $file_managed_alias = $file_managed_table . '_' . $field_name;
              $query->leftJoin($file_managed_table, $file_managed_alias, "{$file_managed_alias}.uuid = {$field_table}.{$field_name}_file_uuid");

              $expression = "GROUP_CONCAT(DISTINCT IF({$field_table}.{$field_name}_file_uuid IS NOT NULL, CONCAT_WS('###',
                  {$field_table}.delta,
                  IFNULL({$field_table}.{$field_name}_revision_id, ''),
                  IFNULL({$field_table}.{$field_name}_uuid, ''),
                  IFNULL({$field_table}.{$field_name}_file_name, ''),
                  IFNULL({$field_table}.{$field_name}_file_hash, ''),
                  IFNULL({$field_table}.{$field_name}_page_count, ''),
                  IFNULL({$field_table}.{$field_name}_description, ''),
                  IFNULL({$field_table}.{$field_name}_language, ''),
                  IFNULL({$field_table}.{$field_name}_preview_uuid, ''),
                  IFNULL({$field_table}.{$field_name}_preview_page, ''),
                  IFNULL({$field_table}.{$field_name}_preview_rotation, ''),
                  IFNULL({$file_managed_alias}.uri, ''),
                  IFNULL({$file_managed_alias}.filemime, ''),
                  IFNULL({$file_managed_alias}.filesize, '')
                ), NULL) SEPARATOR '%%%')";
              $query->addExpression($expression, $alias);
              break;

            case 'river_search':
              $expression = "GROUP_CONCAT(DISTINCT IF({$field_table}.{$field_name}_url IS NOT NULL, CONCAT_WS('###',
                  {$field_table}.delta,
                  {$field_table}.{$field_name}_url,
                  IFNULL({$field_table}.{$field_name}_title, ''),
                  IFNULL({$field_table}.{$field_name}_override, '')
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
    $rows = $query->execute()?->fetchAllAssoc('id', \PDO::FETCH_ASSOC) ?? [];
    if ($rows === []) {
      return [];
    }
    /** @var array<int, array<string, mixed>> $items */
    $items = [];
    foreach ($rows as $item) {
      if (is_array($item) && isset($item['id']) && is_numeric($item['id'])) {
        $item['id'] = (int) $item['id'];
        $items[$item['id']] = $item;
      }
    }

    // Sort by ID desc.
    krsort($items);

    // Load the references and attach them to the items.
    $this->loadReferences($items);

    return $items;
  }

  /**
   * Load the references for the given entity items.
   *
   * @param array<int, array<string, mixed>> $items
   *   Items for which to load the references.
   */
  public function loadReferences(array &$items): void {
    if (!empty($this->options['references']) && !empty($items)) {
      $ids = array_keys($items);
      $queries = [];
      foreach ($this->options['references'] as $field_name => $alias) {
        $field_table = $this->entityType . '__' . $field_name;
        $query = new DatabaseQuery($field_table, $field_table, $this->connection);
        $query->addField($field_table, 'entity_id', 'id');
        $query->addField($field_table, "{$field_name}_target_id", 'value');
        $query->addExpression($this->connection->quote($alias), 'alias');
        $query->condition("{$field_table}.entity_id", $ids, 'IN');
        $queries[] = $query->build();
      }

      /** @var \RWAPIIndexer\Database\Statement $statement */
      $statement = $this->connection->query(implode(' UNION ', $queries));
      while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
        /** @var array{id: string, alias: string, value: string} $row */
        if (!is_numeric($row['id']) || empty($row['alias']) || !is_numeric($row['value'])) {
          continue;
        }
        $id = (int) $row['id'];
        $alias = $row['alias'];
        $value = (int) $row['value'];
        if (!isset($items[$id][$alias])) {
          $items[$id][$alias] = [];
        }
        if (is_array($items[$id][$alias])) {
          $items[$id][$alias][] = $value;
        }
      }
    }
  }

}
