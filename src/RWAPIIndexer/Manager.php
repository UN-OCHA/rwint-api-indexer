<?php

declare(strict_types=1);

namespace RWAPIIndexer;

use RWAPIIndexer\Database\DatabaseConnection;

/**
 * Resource manager class.
 *
 * @phpstan-import-type IndexingOptions from Options
 */
class Manager {

  /**
   * Metrics handler.
   *
   * @var \RWAPIIndexer\Metrics
   */
  protected Metrics $metrics;

  /**
   * Indexing options handler.
   *
   * @var \RWAPIIndexer\Options
   */
  protected Options $options;

  /**
   * Database Connection.
   *
   * @var \RWAPIIndexer\Database\DatabaseConnection
   */
  protected DatabaseConnection $connection;

  /**
   * References handler.
   *
   * @var \RWAPIIndexer\References
   */
  protected References $references;

  /**
   * Elasticsearch handler.
   *
   * @var \RWAPIIndexer\Elasticsearch
   */
  protected Elasticsearch $elasticsearch;

  /**
   * Field processor.
   *
   * @var \RWAPIIndexer\Processor
   */
  protected Processor $processor;

  /**
   * Construct the resource manager with the given indexing options.
   *
   * @param IndexingOptions $options
   *   Indexing options.
   * @param ?\RWAPIIndexer\Database\DatabaseConnection $connection
   *   Optional database connection (for testing). If null, a new connection
   *   is created.
   */
  public function __construct(array $options = [], ?DatabaseConnection $connection = NULL) {
    $this->metrics = new Metrics();

    // Indexing options (parse CLI args when empty and running from CLI).
    $options = (empty($options) && php_sapi_name() === 'cli')
      ? Options::parseArguments()
      : $options;
    $this->options = Options::fromArray($options);

    if ($connection !== NULL) {
      $this->connection = $connection;
    }
    else {
      $this->createDatabaseConnection();
    }

    // Create a new reference handler.
    $this->references = new References();

    // Create a new elasticsearch handler.
    $this->elasticsearch = new Elasticsearch(
      $this->options->elasticsearch,
      $this->options->baseIndexName,
      $this->options->tag,
    );

    // Create a new field processor object to prepare items before indexing.
    $this->processor = new Processor($this->options->website, $this->connection, $this->references);
  }

  /**
   * Create a database connection.
   */
  public function createDatabaseConnection(): void {
    $dbname = $this->options->database;
    $host = $this->options->mysqlHost;
    $port = $this->options->mysqlPort;
    $dsn = "mysql:dbname={$dbname};host={$host};port={$port};charset=utf8";
    $user = $this->options->mysqlUser;
    $password = $this->options->mysqlPass;

    $this->connection = new DatabaseConnection($dsn, $user, $password);
  }

  /**
   * Index items or remove index type.
   */
  public function execute(): void {
    $bundle = $this->options->bundle;
    $id = $this->options->id;
    $remove = $this->options->remove;
    $alias = $this->options->alias;
    $aliasOnly = $this->options->aliasOnly;

    // Remove or index a particular entity.
    if (!empty($id)) {
      if (!empty($remove)) {
        $this->removeItem($bundle, $this->options->id);
      }
      else {
        $this->indexItem($bundle, $this->options->id);
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
  public function index(string $bundle): void {
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
   * @param int $id
   *   Id of the entity item.
   */
  public function indexItem(string $bundle, int $id): void {
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
   * @param int $id
   *   Id of the entity item.
   */
  public function removeItem(string $bundle, int $id): void {
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
  public function remove(string $bundle): void {
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
   * @param bool $remove
   *   Remove or set the alias.
   */
  public function setAlias(string $bundle, bool $remove = FALSE): void {
    // Get the resource handler for the bundle.
    $handler = $this->getResourceHandler($bundle);

    // Set the index alias for this resource.
    $handler->setAlias($remove);
  }

  /**
   * Recursively load the references and add them to the references handler.
   *
   * @param string[] $references
   *   List of the reference bundles.
   */
  public function loadReferences(array $references): void {
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
   *
   * @return \RWAPIIndexer\Resource
   *   Resource handler for the given bundle.
   */
  public function getResourceHandler(string $bundle): Resource {
    return Bundles::getResourceHandler($bundle, $this->elasticsearch, $this->connection, $this->processor, $this->references, $this->options);
  }

  /**
   * Get the metrics Handler.
   *
   * @return \RWAPIIndexer\Metrics
   *   Metrics handler.
   */
  public function getMetrics(): Metrics {
    return $this->metrics;
  }

  /**
   * Log indexing messages.
   *
   * @param string $message
   *   Message to log.
   */
  public function log(string $message): void {
    $callback = $this->options->log;
    if ($callback === 'echo') {
      echo $message;
    }
    elseif (!empty($callback) && is_callable($callback)) {
      $callback($message);
    }
  }

}
