<?php

namespace RWAPIIndexer;

use RWAPIIndexer\Database\DatabaseConnection;
use RWAPIIndexer\Database\Query as DatabaseQuery;

/**
 * Base resource class.
 */
abstract class Resource {

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
   * Index name for this resource.
   *
   * @var string
   */
  protected $index;

  /**
   * Options used for building the query to get the items to index.
   *
   * @var array
   */
  protected $queryOptions = [];

  /**
   * Options used to process the entity items before indexing.
   *
   * @var array
   */
  protected $processingOptions = [];

  /**
   * Elasticsearch handler.
   *
   * @var \RWAPIIndexer\Elasticsearch
   */
  protected $elasticsearch = NULL;

  /**
   * Connection to the database.
   *
   * @var \RWAPIIndexer\Database\DatabaseConnection
   */
  protected $connection = NULL;

  /**
   * Field processor.
   *
   * @var \RWAPIIndexer\Processor
   */
  protected $processor = NULL;

  /**
   * References handler.
   *
   * @var \RWAPIIndexer\References
   */
  protected $references = NULL;

  /**
   * Global options.
   *
   * @var \RWAPIIndexer\Options
   */
  protected $options = NULL;

  /**
   * Construct the resource handler.
   *
   * @param string $bundle
   *   Bundle of this resource.
   * @param string $entity_type
   *   Entity type of this resource.
   * @param string $index
   *   Index name for this resource.
   * @param \RWAPIIndexer\Elasticsearch $elasticsearch
   *   Elasticsearch handler.
   * @param \RWAPIIndexer\Database\DatabaseConnection $connection
   *   Database connection.
   * @param \RWAPIIndexer\Processor $processor
   *   Processor.
   * @param \RWAPIIndexer\References $references
   *   References handler.
   * @param \RWAPIIndexer\Options $options
   *   Indexing options.
   */
  public function __construct($bundle, $entity_type, $index, Elasticsearch $elasticsearch, DatabaseConnection $connection, Processor $processor, References $references, Options $options) {
    $this->bundle = $bundle;
    $this->entityType = $entity_type;
    $this->index = $index;
    $this->elasticsearch = $elasticsearch;
    $this->connection = $connection;
    $this->processor = $processor;
    $this->references = $references;
    $this->options = $options;

    $query_options = $this->queryOptions;

    // Only apply filter to the resource being indexed (not to references).
    if ($this->bundle === $options->get('bundle')) {
      $query_options['filters'] = $this->parseFilters($options->get('filter'));
    }

    // Create a new Query object to get the items to index.
    $this->query = new Query($connection, $this->entityType, $this->bundle, $query_options);
  }

  /**
   * Parse argument filters and return conditions to apply to the query.
   *
   * @param string $filters
   *   Document filters.
   *
   * @return array
   *   Query conditions.
   */
  public function parseFilters($filters) {
    $conditions = [];
    if (!empty($filters)) {
      foreach (explode('+', $filters) as $filter) {
        list($field, $value) = explode(':', $filter, 2);
        $values = explode(',', $value);
        if (isset($this->queryOptions['fields'][$field])) {
          $conditions['fields'][$field] = $values;
        }
        elseif (isset($this->queryOptions['field_joins']['field_' . $field])) {
          if (reset($this->queryOptions['field_joins']['field_' . $field]) === 'value') {
            $conditions['field_joins'][$field] = $values;
          }
        }
        elseif (isset($this->queryOptions['references']['field_' . $field])) {
          $conditions['references'][$field] = $values;
        }
      }
    }
    return $conditions;
  }

  /**
   * Return the references for this resource.
   *
   * @return array
   *   Bundles of the references for this entity type/bundle.
   */
  public function getReferences() {
    $references = [];
    if (isset($this->processingOptions['references'])) {
      foreach ($this->processingOptions['references'] as $reference) {
        $references[] = key($reference);
      }
    }
    return $references;
  }

  /**
   * Get entities to index.
   *
   * @param int $limit
   *   Maximum number of items.
   * @param int $offset
   *   ID of the index from which to start getting items to index.
   * @param array $ids
   *   Ids of the the entities to index.
   *
   * @return array
   *   Items to index.
   */
  public function getItems($limit = NULL, $offset = NULL, array $ids = NULL) {
    $items = $this->query->getItems($limit, $offset, $ids);

    // If entity ids are provided then we want to lazily load the references.
    if (!empty($ids)) {
      $this->loadReferences($items);
    }

    $this->processItems($items);

    return $items;
  }

  /**
   * Load the references for the given entity items.
   *
   * @param array $items
   *   Entity items from which to extract the references to load.
   */
  public function loadReferences(array &$items) {
    if (!empty($this->processingOptions['references'])) {
      $references = [];

      // Extract the reference ids from the given entity items.
      foreach ($this->processingOptions['references'] as $field => $info) {
        $bundle = key($info);

        if (!isset($references[$bundle])) {
          $references[$bundle] = [];
        }

        foreach ($items as &$item) {
          if (!empty($item[$field])) {
            // Check which reference items haven't been loaded yet.
            $ids = $this->references->getNotLoaded($bundle, $item[$field]);
            $references[$bundle] = array_merge($references[$bundle], $ids);
          }
        }
      }

      // Load all the references that haven't been already loaded.
      foreach ($references as $bundle => $ids) {
        if (!empty($ids)) {
          $ids = array_unique($ids);
          // Get the resources handler for the bundle.
          $handler = $this->getResourceHandler($bundle);
          // Set the reference items.
          $this->references->setItems($bundle, $handler->getItems(count($ids), NULL, $ids));
        }
      }
    }
  }

  /**
   * Retrieve the URL aliases for the given entity ids.
   *
   * @param array $ids
   *   Entity Ids.
   *
   * @return array
   *   Associative array with entity ids as keys and url aliases as values.
   */
  public function fetchUrlAliases(array $ids) {
    $base = $this->entityType === 'taxonomy_term' ? 'taxonomy/term/' : 'node/';
    $map = [];
    foreach ($ids as $id) {
      $map[$id] = $base . $id;
    }
    $query = new DatabaseQuery('url_alias', 'url_alias', $this->connection);
    $query->addField('url_alias', 'source', 'source');
    $query->addField('url_alias', 'alias', 'alias');
    $query->condition('url_alias.source', $map, 'IN');
    $query->orderBy('url_alias.pid', 'ASC');
    $result = $aliases = $query->execute();
    if (!empty($result)) {
      $aliases = $result->fetchAllKeyed();
      foreach ($map as $id => $source) {
        if (isset($aliases[$source])) {
          $map[$id] = $aliases[$source];
        }
      }
    }
    return $map;
  }

  /**
   * Get the resource handler for the given entity bundle.
   *
   * @param string $bundle
   *   Bundle of the resource.
   *
   * @return \RWAPIIndexer\Resource
   *   Resource handler for the given bundle.
   */
  public function getResourceHandler($bundle) {
    return Bundles::getResourceHandler($bundle, $this->elasticsearch, $this->connection, $this->processor, $this->references, $this->options);
  }

  /**
   * Process items returned by the query.
   *
   * @param array $items
   *   Items to process.
   */
  public function processItems(array &$items) {
    $options = $this->processingOptions;

    $url_aliases = $this->fetchUrlAliases(array_keys($items));

    foreach ($items as $id => &$item) {
      // Add the entity link to the main website.
      $this->processor->processEntityUrl($this->entityType, $item, $url_aliases[$id]);

      // Convert ID to integer.
      $item['id'] = (int) $item['id'];

      // Add timestamp.
      $date = new \DateTime('now', new \DateTimeZone('UTC'));
      $item['timestamp'] = $date->format(\DateTime::ATOM);

      // Generic conversion and reference handling.
      foreach ($item as $key => $value) {
        // Remove NULL properties.
        if (!isset($value) || $value === '') {
          unset($item[$key]);
        }
        else {
          // Get reference taxonomy terms.
          if (isset($options['references'][$key])) {
            $this->processor->processReference($options['references'][$key], $item, $key);
          }
          // Convert values.
          if (isset($options['conversion'][$key])) {
            $this->processor->processConversion($options['conversion'][$key], $item, $key);
          }
        }
      }

      $this->processItem($item);
    }
  }

  /**
   * Process an item, preparing for the indexing.
   *
   * @param array $item
   *   Item to process.
   */
  public function processItem(array &$item) {
  }

  /**
   * Index entities.
   */
  public function index() {
    $this->log("Indexing {$this->bundle} entities.\n");

    // Create the index and set up the mapping for the entity bundle.
    $this->elasticsearch->create($this->index, $this->getMapping());

    // Get the offset from which to start indexing.
    $offset = $this->query->getOffset($this->options->get('offset'));
    // Get the maximum number of items to index.
    $limit = $this->query->getLimit($this->options->get('limit'), $offset);
    // Number of items to process per batch run.
    $chunk_size = $this->options->get('chunk-size');

    // Counter of indexed items.
    $count = 0;

    // If the offset is 0 or negative then nothing to index.
    if ($offset <= 0) {
      throw new \Exception("No entity to index.");
    }

    $this->log("Indexing entities...\n");

    // Main indexing loop.
    while ($offset > 0 && $count < $limit) {
      // Get $chunk_size items starting from the last indexed item.
      $items = $this->getItems(min($limit - $count, $chunk_size), $offset);

      if (!empty($items)) {
        $offset = $this->elasticsearch->indexItems($this->index, $items);
      }
      else {
        // Nothing else index.
        break;
      }

      $count += count($items);

      // Clear the memory.
      unset($items);

      $this->log("Indexed {$count}/{$limit} entities.                 \r");
    }

    // Last indexed item.
    $offset += 1;

    $this->log("\n[OK] Successfully indexed {$count}/{$limit} entities.\n");
    $this->log("Last indexed entity is {$offset}.\n");
  }

  /**
   * Index the entity with the given id.
   *
   * @param int $id
   *   Id of the entity to index.
   */
  public function indexItem($id) {
    $items = $this->getItems(1, 0, [$id]);
    if (!empty($items)) {
      $this->elasticsearch->indexItems($this->index, $items);
      $this->log("[OK] Successfully indexed the entity with the id {$id}.\n");
    }
    else {
      $this->log("The entity with the id {$id} was not found and thus not indexed.\n");
    }
  }

  /**
   * Remove the entity with the provided id.
   *
   * @param int $id
   *   Id of the entity to remove.
   */
  public function removeItem($id) {
    $this->elasticsearch->removeItem($this->index, $id);
    $this->log("[OK] Successfully removed the entity with the id {$id}.\n");
  }

  /**
   * Remove the index.
   */
  public function remove() {
    $this->elasticsearch->remove($this->index);
    $this->log("[OK] Successfully removed index.\n");
  }

  /**
   * Set or remove the alias for the index.
   *
   * @param bool $remove
   *   Remove or set alias.
   */
  public function setAlias($remove = FALSE) {
    if (empty($remove)) {
      $this->elasticsearch->addAlias($this->index);
      $this->log("[OK] Successfully added index alias.\n");
    }
    else {
      $this->elasticsearch->removeAlias($this->index);
      $this->log("[OK] Successfully removed index alias.\n");
    }
  }

  /**
   * Return the mapping for the resource.
   *
   * @return array
   *   Elasticsearch index type mapping.
   */
  public function getMapping() {
    return [];
  }

  /**
   * Log indexing messages.
   *
   * @param string $message
   *   Message to log.
   */
  public function log($message) {
    $callback = $this->options->get('log');
    if ($callback === 'echo') {
      echo $message;
    }
    elseif (!empty($callback) && is_callable($callback)) {
      $callback($message);
    }
  }

}
