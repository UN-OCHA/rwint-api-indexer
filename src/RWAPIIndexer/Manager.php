<?php

namespace RWAPIIndexer;

/**
 * Resource manager class.
 */
class Manager {
  // List of Resources entity bundles and their corresponding class.
  protected $bundles = array(
    'report' => '\RWAPIIndexer\Resources\Report',
    'job' => '\RWAPIIndexer\Resources\Job',
    'training' => '\RWAPIIndexer\Resources\Training',
    'country' => '\RWAPIIndexer\Resources\Country',
    'disaster' => '\RWAPIIndexer\Resources\Disaster',
    'source' => '\RWAPIIndexer\Resources\Source',

    // References.
    'career_categories' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'city' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'content_format' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'disaster_type' => '\RWAPIIndexer\Resources\DisasterType',
    'feature' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'job_type' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'language' => '\RWAPIIndexer\Resources\Language',
    'ocha_product' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'organization_type' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    //'region' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    //'tags' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'theme' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'training_format' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'training_type' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'vulnerable_groups' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'job_experience' => '\RWAPIIndexer\Resources\TaxonomyDefault',
  );

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
  public function __construct($options = NULL) {
    // Indexing options.
    $this->options = new \RWAPIIndexer\Options($this->bundles, $options);

    // Create a new database connection.
    $this->createDatabaseConnection();

    // Create a new reference handler.
    $this->references = new \RWAPIIndexer\References();

    // Create a new elasticsearch handler.
    $this->elasticsearch = new \RWAPIIndexer\Elasticsearch($this->options->get('elasticsearch'), $this->options->get('base-index-name'));

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
    if (!empty($this->options->get('remove'))) {
      $this->remove();
    }
    else {
      $this->index();
    }
  }

  /**
   * Index the entities for the given entity bundle.
   *
   * @param string $bundle
   *   Bundle for the entities to index.
   */
  public function index($bundle = '') {
    $handler = $this->getResourceHandler($bundle);

    // Load references for the given resource.
    $this->loadReferences($handler->getReferences());

    // Index the resource items.
    $handler->index();
  }

  /**
   * Remove the elasticsearch index type for this resource.
   *
   * @param string $bundle
   *   Bundle of the resource to remove.
   */
  public function remove($bundle = '') {
    $handler = $this->getResourceHandler($bundle);

    // Remove the elasticsearch index type for this resource.
    $handler->remove();
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
        $this->log("Loading '{$bundle}' reference items.\n");
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
  public function getResourceHandler($bundle = '') {
    $bundle = !empty($bundle) ? $bundle : $this->options->get('bundle');

    if (isset($this->bundles[$bundle])) {
      return new $this->bundles[$bundle]($bundle, $this->elasticsearch, $this->connection, $this->processor, $this->options);
    }
    else {
      $bundles = implode(', ', array_keys($this->bundles));
      throw new \Exception("No resource handler for the bundle '$bundle'. Valid ones are: " . $bundles . "\n");
    }
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
