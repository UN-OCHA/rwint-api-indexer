<?php

namespace RWAPIIndexer;

/**
 * Base resource class.
 */
abstract class Resource {
  // Entity type and bundle.
  protected $entity_type = '';
  protected $bundle = '';

  // Options used for building the query to get the items to index.
  protected $query_options = array();

  // Options used to process the entity items before indexing.
  protected $processing_options = array();

  // Elasticsearch handler.
  protected $elasticsearch = NULL;

  // Field processor.
  protected $processor = NULL;

  // Global options.
  protected $options = NULL;

  /**
   * Construct the resource handler.
   *
   * @param string $bundle
   *   Bundle of this resource.
   * @param \RWAPIIndexer\Elasticsearch $elasticsearch
   *   Elasticsearch handler.
   * @param \RWAPIIndexer\Database\Connection $connection
   *   Database connection.
   * @param \RWAPIIndexer\Options $options
   *   Indexing options.
   */
  public function __construct($bundle, $elasticsearch, $connection, $processor, $options) {
    $this->bundle = $bundle;
    $this->elasticsearch = $elasticsearch;
    $this->processor = $processor;
    $this->options = $options;

    // Create a new Query object to get the items to index.
    $this->query = new \RWAPIIndexer\Query($connection, $this->entity_type, $this->bundle, $this->query_options);
  }

  /**
   * Return the references for this resource.
   *
   * @return array
   *   Bundles of the references for this entity type/bundle.
   */
  public function getReferences() {
    $references = array();
    if (isset($this->processing_options['references'])) {
      foreach ($this->processing_options['references'] as $reference) {
        $references[] = key($reference);
      }
    }
    return $references;
  }

  /**
   * Get entities to index.
   *
   * @param integer $limit
   *   Maximum number of items.
   * @param  integer $offset
   *   ID of the index from which to start getting items to index.
   * @return array
   *   Items to index.
   */
  public function getItems($limit = NULL, $offset = NULL) {
    $items = $this->query->getItems($limit, $offset);

    $this->processItems($items);

    return $items;
  }

  /**
   * Process items returned by the query.
   *
   * @param array $items
   *   Items to process.
   */
  public function processItems(&$items) {
    $options = $this->processing_options;

    foreach ($items as $id => &$item) {
      // Add the entity link to the main website.
      $this->processor->processURL($this->entity_type, $item);

      // Convert ID to integer.
      $item['id'] = (int) $item['id'];

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
  public function processItem(&$item) {
  }

  /**
   * Index entities.
   */
  public function index() {
    $this->log("Indexing {$this->bundle} entities.\n");

    // Create the index and set up the mapping for the entity bundle.
    $this->elasticsearch->create($this->entity_type, $this->bundle, $this->getMapping());

    // Get the offset from which to start indexing.
    $offset = $this->query->getOffset($this->options->get('offset'));
    // Get the maximum number of items to index.
    $limit = $this->query->getLimit($this->options->get('limit'));
    // Number of items to process per batch run.
    $chunk_size = $this->options->get('chunk-size');

    // Total number of indexed items.
    $total_count = 0;

    // If the offset is 0 or negative then nothing to index.
    if ($offset <= 0) {
      throw new \Exception("No entity to index.");
    }

    $this->log("Indexing entities...\n");

    // Main indexing loop.
    while ($offset > 0 && $total_count < $limit) {
      // Get $chunk_size items starting from the last indexed item.
      $items = $this->getItems($chunk_size, $offset);

      $count = count($items);
      $total_count += $count;

      if ($count > 0) {
        $offset = $this->elasticsearch->indexItems($this->entity_type, $this->bundle, $items);
      }
      else {
        // Nothing else index.
        break;
      }

      // Clear the memory.
      unset($items);

      $this->log("Indexed {$total_count}/{$limit} entities.                 \r");
    }

    // Last indexed item.
    $offset += 1;

    $this->log("\nSuccessfully indexed {$total_count}/{$limit} entities.\n");
    $this->log("Last indexed entity is {$offset}.\n");
  }

  /**
   * Remove the index.
   */
  public function remove() {
    $this->elasticsearch->removeType($this->entity_type, $this->bundle);
    $this->log("Index successfully removed.\n");
  }

  /**
   * Return the mapping for the current indexable.
   *
   * @return array
   *   Elasticsearch index type mapping.
   */
  public function getMapping() {
    return array();
  }

  /**
   * Log indexing message if in run from the console.
   *
   * @param string $message
   *   Message to log.
   */
  public function log($message) {
    if ($this->options->get('console')) {
      echo $message;
    }
  }
}
