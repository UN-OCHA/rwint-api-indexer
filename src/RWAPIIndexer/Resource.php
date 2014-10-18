<?php

namespace RWAPIIndexer;

/**
 * Base resource class.
 */
abstract class Resource {
  // Entity type and bundle.
  protected $entity_type = '';
  protected $bundle = '';

  // Index name for this resource.
  protected $index;

  // Options used for building the query to get the items to index.
  protected $query_options = array();

  // Options used to process the entity items before indexing.
  protected $processing_options = array();

  // Elasticsearch handler.
  protected $elasticsearch = NULL;

  // Connection to the database.
  protected $connection = NULL;

  // Field processor.
  protected $processor = NULL;

  // References handler.
  protected $references = NULL;

  // Global options.
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
   * @param \RWAPIIndexer\Database\Connection $connection
   *   Database connection.
   * @param \RWAPIIndexer\Options $options
   *   Indexing options.
   */
  public function __construct($bundle, $entity_type, $index, $elasticsearch, $connection, $processor, $references, $options) {
    $this->bundle = $bundle;
    $this->entity_type = $entity_type;
    $this->index = $index;
    $this->elasticsearch = $elasticsearch;
    $this->connection = $connection;
    $this->processor = $processor;
    $this->references = $references;
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
  public function getItems($limit = NULL, $offset = NULL, $ids = NULL) {
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
  public function loadReferences(&$items) {
    if (!empty($this->processing_options['references'])) {
      $references = array();

      // Extract the reference ids from the given entity items.
      foreach ($this->processing_options['references'] as $field => $info) {
        $bundle = key($info);

        if (!isset($references[$bundle])) {
          $references[$bundle] = array();
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
   * Get the resource handler for the given entity bundle.
   *
   * @param string $bundle
   *   Bundle of the resource.
   * @return \RWAPIIndexer\Resource
   *   Resource handler for the given bundle.
   */
  public function getResourceHandler($bundle) {
    return \RWAPIIndexer\Bundles::getResourceHandler($bundle, $this->elasticsearch, $this->connection, $this->processor, $this->references, $this->options);
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
    $this->elasticsearch->create($this->index, $this->getMapping());

    // Get the offset from which to start indexing.
    $offset = $this->query->getOffset($this->options->get('offset'));
    // Get the maximum number of items to index.
    $limit = $this->query->getLimit($this->options->get('limit'));
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

    $this->log("\nSuccessfully indexed {$count}/{$limit} entities.\n");
    $this->log("Last indexed entity is {$offset}.\n");
  }

  /**
   * Index the entity with the given id.
   *
   * @param integer $id
   *   Id of the entity to index.
   */
  public function indexItem($id) {
    $items = $this->getItems(1, 0, array($id));
    if (!empty($items)) {
      $this->elasticsearch->indexItems($this->index, $items);
      $this->log("Successfully indexed the entity with the id {$id}.\n");
    }
    else {
      $this->log("The entity with the id {$id} was not found and thus not indexed.\n");
    }
  }

  /**
   * Remove the entity with the provided id.
   * @param integer $id
   *   Id of the entity to remove.
   */
  public function removeItem($id) {
    $this->elasticsearch->removeItem($this->index, $id);
    $this->log("Successfully removed the entity with the id {$id}.\n");
  }

  /**
   * Remove the index.
   */
  public function remove() {
    $this->elasticsearch->remove($this->index);
    $this->log("Successfully removed index.\n");
  }

  /**
   * Set or remove the alias for the index.
   *
   * @param boolean $remove
   *   Remove or set alias.
   */
  public function setAlias($remove = FALSE) {
    if (empty($remove)) {
      $this->elasticsearch->addAlias($this->index);
      $this->log("Successfully added index alias.\n");
    }
    else {
      $this->elasticsearch->removeAlias($this->index);
      $this->log("Successfully removed index alias.\n");
    }
  }

  /**
   * Return the mapping for the resource.
   *
   * @return array
   *   Elasticsearch index type mapping.
   */
  public function getMapping() {
    return array();
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
