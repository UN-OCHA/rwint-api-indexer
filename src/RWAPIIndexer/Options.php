<?php

namespace RWAPIIndexer;

/**
 * Indexing options handler class.
 */
class Options {
  // Valid bundles.
  protected $bundles = array();

  // Indexing options.
  protected $options = array(
    'bundle' => '',
    'elasticsearch' => 'http://127.0.0.1:9200',
    'mysql-host' => 'localhost',
    'mysql-port' => 3306,
    'mysql-user' => 'root',
    'mysql-pass' => '',
    'database' => 'reliefwebint_0',
    'base-index-name' => 'reliefwebint_0',
    'website' => 'http://reliefweb.int',
    'limit' => 0,
    'offset' => 0,
    'chunk-size' => 500,
    'console' => FALSE,
    'remove' => FALSE,
  );

  /**
   * Construct the Options handler.
   *
   * @param array $bundles
   *   Allowed entity bundles.
   * @param array $options
   *   Indexing options.
   */
  public function __construct($bundles = array(), $options = NULL) {
    $this->bundles = $bundles;

    // Set flag indicating the indexing is run from the command line.
    $this->options['console'] = php_sapi_name() == 'cli';

    // Set or parse the options.
    if ($this->options['console'] && !isset($options)) {
      $this->parseArguments();
    }
    else {
      $this->options = array_replace($this->options, $options);
    }

    $this->validateOptions($this->options);
  }

  /**
   * Parse the options form the command line.
   */
  public function parseArguments() {
    global $argv;

    // Remove the name of the executing script.
    array_shift($argv);

    // No parameters, we display the usage.
    if (empty($argv)) {
      $this->displayUsage();
    }

    // Display usage if first argument is help.
    $first_arg = array_shift($argv);
    if ($first_arg === '-h' || $first_arg === '--help') {
      $this->displayUsage();
    }

    // Set the bundle options.
    $this->options['bundle'] = $first_arg;

    // Parse the arguments.
    while (($arg = array_shift($argv)) !== NULL) {
      switch ($arg) {
        case '--elasticsearch':
        case '-e':
          $this->options['elasticsearch'] = array_shift($argv);
          break;

        case '--mysql-host':
        case '-H':
          $this->options['mysql-host'] = array_shift($argv);
          break;

        case '--mysql-port':
        case '-P':
          $this->options['mysql-port'] = (int) array_shift($argv);
          break;

        case '--mysql-user':
        case '-u':
          $this->options['mysql-user'] = array_shift($argv);
          break;

        case '--mysql-pass':
        case '-p':
          $this->options['mysql-pass'] = array_shift($argv);
          break;

        case '--database':
        case '-d':
          $this->options['database'] = array_shift($argv);
          break;

        case '--base-index-name':
        case '-b':
          $this->options['base-index-name'] = array_shift($argv);
          break;

        case '--website':
        case '-w':
          $this->options['website'] = array_shift($argv);
          break;

        case '--limit':
        case '-l':
          $this->options['limit'] = (int) array_shift($argv);
          break;

        case '--offset':
        case '-o':
          $this->options['offset'] = (int) array_shift($argv);
          break;

        case '--chunk-size':
        case '-c':
          $this->options['chunk-size'] = (int) array_shift($argv);
          break;

        case '--remove':
        case '-r':
          $this->options['remove'] = TRUE;
          break;

        case '--help':
        case '-h':
        default:
          $this->displayUsage();
          break;
      }
    }
  }

  /**
   * Return the indexing options.
   *
   * @param string $key
   *   Option key.
   * @return integer|string|array
   *   All indexing options or option value for the given key.
   */
  public function get($key = NULL) {
    if (isset($key)) {
      if (!isset($this->options[$key])) {
        throw new \Exception("Undefined indexing option '{$key}'.");
      }
      else {
        return $this->options[$key];
      }
    }
    return $this->options;
  }

  /**
   * Get passed entity bundle parameter and check its validity.
   *
   * @param string $bundle
   *   Entity bundle to validate.
   */
  public function validateBundle($bundle) {
    return isset($this->bundles[$bundle]) ? $bundle : FALSE;
  }

  /**
   * Validate Mysql host.
   *
   * @param string $host
   *   Mysql host to validate.
   */
  public function validateMysqlHost($host) {
    if (filter_var($host, FILTER_VALIDATE_IP) || preg_match('/^\S+$/', $host) === 1) {
      return $host;
    }
    return FALSE;
  }

  /**
   * Validate the indexing options.
   *
   * @param  array $options
   *   Options to validate.
   */
  public function validateOptions($options) {
    $results = filter_var_array($options, array(
      'bundle' => array(
        'filter' => FILTER_CALLBACK,
        'options' => array($this, 'validateBundle'),
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
      'elasticsearch' => array(
        'filter' => FILTER_VALIDATE_URL,
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
      'mysql-host' => array(
        'filter' => FILTER_CALLBACK,
        'options' => array($this, 'validateMysqlHost'),
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
      'mysql-port' => array(
        'filter'    => FILTER_VALIDATE_INT,
        'options'   => array('min_range' => 1, 'max_range' => 65535),
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
      'mysql-user' => array(
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => array('regexp' => '/^\S+$/'),
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
      'mysql-pass' => array(
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => array('regexp' => '/^\S*$/'),
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
      'database' => array(
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => array('regexp' => '/^\S*$/'),
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
      'base-index-name' => array(
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => array('regexp' => '/^\S*$/'),
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
      'website' => array(
        'filter' => FILTER_VALIDATE_URL,
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
      'limit' => array(
        'filter' => FILTER_VALIDATE_INT,
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
      'offset' => array(
        'filter' => FILTER_VALIDATE_INT,
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
      'chunk-size' => array(
        'filter'    => FILTER_VALIDATE_INT,
        'options'   => array('min_range' => 1, 'max_range' => 1000),
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
      'remove' => array(
        'filter' => FILTER_VALIDATE_BOOLEAN,
        'flags' => FILTER_NULL_ON_FAILURE,
      ),
    ));

    foreach ($results as $key => $value) {
      if (is_null($value)) {
        throw new \Exception("Invalid '{$key}' argument value.");
      }
    }
  }

 /**
  * Display the script usage.
  *
  * @return string
  *   Usage and indexing options.
  */
  static public function displayUsage() {
    echo "Usage: php PATH/TO/Indexer.php <entity-bundle> [options]\n" .
          "     -e, --elasticsearch <arg> Elasticsearch URL, defaults to http://127.0.0.1:9200 \n" .
          "     -h, --mysql-host <arg> Mysql host, defaults to localhost \n" .
          "     -P, --mysql-port <arg> Mysql port, defaults to 3306 \n" .
          "     -u, --mysql-user <arg> Mysql user, defaults to root \n" .
          "     -p, --mysql-pass <arg> Mysql pass, defaults to none \n" .
          "     -d, --database <arg> Database name, deaults to reliefwebint_0 \n" .
          "     -b, --base-index-name <arg> Base index name, deaults to reliefwebint_0 \n" .
          "     -w, --website <arg> Website URL, deaults to http://reliefweb.int \n" .
          "     -l, --limit <arg> Maximum number of entities to index, defaults to 0 (all) \n" .
          "     -o, --offset <arg> ID of the entity from which to start the indexing, defaults to the most recent one \n" .
          "     -c, --chunk-size <arg> Number of entities to index at one time, defaults to 500 \n" .
          "     -r, --remove Indicates that the entity-bundle index should be removed \n" .
          "\n";
    exit();
  }
}