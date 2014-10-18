<?php

namespace RWAPIIndexer;

/**
 * Resource manager class.
 */
class Manager {
  // Metrics handler.
  protected $metrics = NULL;

  // Indexing options handler.
  protected $options = NULL;

  // Database Connection.
  protected $connection = NULL;

  // References handler.
  protected $references = NULL;

  // Elasticsearch handler.
  protected $elasticsearch = NULL;

  // Field processor.
  protected $processor = NULL;

  /**
   * Construct the resource manager with the given indexing options.
   *
   * @param array $options
   *   Indexing options.
   */
  public function __construct($options = array()) {
    $this->metrics = new \RWAPIIndexer\Metrics();

    // Indexing options.
    $this->options = new \RWAPIIndexer\Options($options);

    // Create a new database connection.
    $this->createDatabaseConnection();

    // Create a new reference handler.
    $this->references = new \RWAPIIndexer\References();

    // Create a new elasticsearch handler.
    $this->elasticsearch = new \RWAPIIndexer\Elasticsearch($this->options->get('elasticsearch'), $this->options->get('base-index-name'), $this->options->get('tag'));

    // Create a new field processor object to prepare items before indexing.
    $this->processor = new \RWAPIIndexer\Processor($this->options->get('website'), $this->references);
  }

  /**
   * Create a database connection.
   */
  public function createDatabaseConnection() {
    $dbname = $this->options->get('database');
    $host = $this->options->get('mysql-host');
    $port = $this->options->get('mysql-port');
    $dsn = "mysql:dbname={$dbname};host={$host};port={$port}";
    $user = $this->options->get('mysql-user');
    $password = $this->options->get('mysql-pass');

    $this->connection = new \RWAPIIndexer\Database\DatabaseConnection($dsn, $user, $password);
  }

  /**
   * Index items or remove index type.
   */
  public function execute() {
    $bundle = $this->options->get('bundle');
    $id = $this->options->get('id');
    $remove = $this->options->get('remove');
    $alias = $this->options->get('alias');
    $aliasOnly = $this->options->get('alias-only');

    // Remove or index a particular entity.
    if (!empty($id)) {
      if (!empty($remove)) {
        $this->removeItem($bundle, $this->options->get('id'));
      }
      else {
        $this->indexItem($bundle, $this->options->get('id'));
      }
    }
    // Or remove the index or index entities.
    elseif (empty($aliasOnly)) {
      if (!empty($remove)) {
        $this->remove($bundle);
      }
      else {
        $this->index($bundle);
      }
      // Set or remove the alias.
      if (!empty($alias)) {
        $this->setAlias($bundle, !empty($remove));
      }
    }
    // Only set up or remove the alias.
    else {
      $this->setAlias($bundle, !empty($remove));
    }
  }

  /**
   * Index the entities for the given entity bundle.
   *
   * @param string $bundle
   *   Bundle for the entities to index.
   */
  public function index($bundle) {
    // Get the resource handler for the bundle.
    $handler = $this->getResourceHandler($bundle);

    // Load all references for the given resource.
    $this->loadReferences($handler->getReferences());

    // Index the resource items.
    $handler->index();
  }

  /**
   * Index a particular entity item og the given bundle.
   *
   * @param string $bundle
   *   Bundle of the entity item.
   * @param integer $id
   *   Id of the entity item.
   */
  public function indexItem($bundle, $id) {
    // Get the resource handler for the bundle.
    $handler = $this->getResourceHandler($bundle);

    // Index the item.
    $handler->indexItem($id);
  }

  /**
   * Index a particular entity item of the given bundle.
   *
   * @param string $bundle
   *   Bundle of the entity item.
   * @param integer $id
   *   Id of the entity item.
   */
  public function removeItem($bundle, $id) {
    // Get the resource handler for the bundle.
    $handler = $this->getResourceHandler($bundle);

    // Index the item.
    $handler->removeItem($id);
  }

  /**
   * Remove the elasticsearch index for this resource.
   *
   * @param string $bundle
   *   Bundle of the resource to remove.
   */
  public function remove($bundle) {
    // Get the resource handler for the bundle.
    $handler = $this->getResourceHandler($bundle);

    // Remove the elasticsearch index for this resource.
    $handler->remove();
  }

  /**
   * Set or remove the alias for the index corresponding to the given bundle.
   *
   * @param string $bundle
   *   Bundle of the entity item.
   * @param boolean $remove
   *   Remove or set the alias.
   */
  public function setAlias($bundle, $remove = FALSE) {
    // Get the resource handler for the bundle.
    $handler = $this->getResourceHandler($bundle);

    // Set the index alias for this resource.
    $handler->setAlias($remove);
  }

  /**
   * Recursively load the references and add them to the references handler.
   *
   * @param array $references
   *   List of the reference bundles.
   */
  public function loadReferences($references) {
    foreach ($references as $bundle) {
      // If not already set, load the reference items for this bundle.
      if (!$this->references->has($bundle)) {
        $this->log("Preloading '{$bundle}' reference items.\n");
        // Get the resource handler for this bundle.
        $handler = $this->getResourceHandler($bundle);
        // Recursively load references.
        $this->loadReferences($handler->getReferences());
        // Add current reference items to the list of references.
        $this->references->set($bundle, $handler->getItems());
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
   * Get the metrics Handler.
   *
   * @return \RWAPIIndexer\Metrics
   *   Metrics handler.
   */
  public function getMetrics() {
    return $this->metrics;
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
