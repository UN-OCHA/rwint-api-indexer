<?php

namespace RWAPIIndexer;

/**
 * Indexing options handler class.
 */
class Options {

  /**
   * Indexing options.
   *
   * @var array
   */
  protected $options = [
    'bundle' => '',
    'elasticsearch' => 'http://127.0.0.1:9200',
    'mysql-host' => 'localhost',
    'mysql-port' => 3306,
    'mysql-user' => 'root',
    'mysql-pass' => '',
    'database' => 'reliefwebint_0',
    'base-index-name' => 'reliefwebint_0',
    'tag' => '',
    'website' => 'https://reliefweb.int',
    'limit' => 0,
    'offset' => 0,
    'filter' => '',
    'chunk-size' => 500,
    'id' => 0,
    'remove' => FALSE,
    'alias' => FALSE,
    'alias-only' => FALSE,
    'log' => '',
  ];

  /**
   * Construct the Options handler.
   *
   * @param array $options
   *   Indexing options.
   */
  public function __construct(array $options = []) {
    // Called from command line.
    if (php_sapi_name() == 'cli') {
      $this->options['log'] = 'echo';

      // Parse the options from the command line arguments if not set.
      if (empty($options)) {
        $options = static::parseArguments();
      }
    }

    // Replace the default options.
    $this->options = array_replace($this->options, $options);

    // Validate the options.
    $this->validateOptions($this->options);
  }

  /**
   * Parse the options form the command line.
   *
   * @return array
   *   Parsed options.
   */
  public static function parseArguments() {
    global $argv;

    // Remove the name of the executing script.
    array_shift($argv);

    // No parameters, we display the usage.
    if (empty($argv)) {
      static::displayUsage();
    }

    // Parse the arguments.
    while (($arg = array_shift($argv)) !== NULL) {
      switch ($arg) {
        case '--elasticsearch':
        case '-e':
          $options['elasticsearch'] = array_shift($argv);
          break;

        case '--mysql-host':
        case '-H':
          $options['mysql-host'] = array_shift($argv);
          break;

        case '--mysql-port':
        case '-P':
          $options['mysql-port'] = (int) array_shift($argv);
          break;

        case '--mysql-user':
        case '-u':
          $options['mysql-user'] = array_shift($argv);
          break;

        case '--mysql-pass':
        case '-p':
          $options['mysql-pass'] = array_shift($argv);
          break;

        case '--database':
        case '-d':
          $options['database'] = array_shift($argv);
          break;

        case '--base-index-name':
        case '-b':
          $options['base-index-name'] = array_shift($argv);
          break;

        case '--tag':
        case '-t':
          $options['tag'] = array_shift($argv);
          break;

        case '--website':
        case '-w':
          $options['website'] = array_shift($argv);
          break;

        case '--limit':
        case '-l':
          $options['limit'] = (int) array_shift($argv);
          break;

        case '--offset':
        case '-o':
          $options['offset'] = (int) array_shift($argv);
          break;

        case '--filter':
        case '-f':
          $options['filter'] = (int) array_shift($argv);
          break;

        case '--chunk-size':
        case '-c':
          $options['chunk-size'] = (int) array_shift($argv);
          break;

        case '--id':
        case '-i':
          $options['id'] = (int) array_shift($argv);
          break;

        case '--remove':
        case '-r':
          $options['remove'] = TRUE;
          break;

        case '--alias':
        case '-a':
          $options['alias'] = TRUE;
          break;

        case '--alias-only':
        case '-A':
          $options['alias-only'] = TRUE;
          break;

        case '--help':
        case '-h':
          static::displayUsage();
          break;

        default:
          if (strpos($arg, '-') === 0) {
            static::displayUsage("Invalid argument '{$arg}'.");
          }
          else {
            $options['bundle'] = $arg;
          }
          break;
      }
    }

    // Display usage if no entity bundle is provided.
    if (empty($options['bundle'])) {
      static::displayUsage("No entity bundle provided.");
    }

    return $options;
  }

  /**
   * Return the indexing options.
   *
   * @param string $key
   *   Option key.
   *
   * @return int|string|array
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
    return Bundles::has($bundle) ? $bundle : FALSE;
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
   * @param array $options
   *   Options to validate.
   */
  public function validateOptions(array $options) {
    $results = filter_var_array($options, [
      'bundle' => [
        'filter' => FILTER_CALLBACK,
        'options' => [$this, 'validateBundle'],
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'elasticsearch' => [
        'filter' => FILTER_VALIDATE_URL,
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'mysql-host' => [
        'filter' => FILTER_CALLBACK,
        'options' => [$this, 'validateMysqlHost'],
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'mysql-port' => [
        'filter'    => FILTER_VALIDATE_INT,
        'options'   => ['min_range' => 1, 'max_range' => 65535],
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'mysql-user' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^\S+$/'],
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'mysql-pass' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^\S*$/'],
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'database' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^[a-zA-Z0-9_-]*$/'],
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'base-index-name' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^[a-zA-Z0-9_-]*$/'],
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'tag' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^[a-zA-Z0-9_-]*$/'],
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'website' => [
        'filter' => FILTER_VALIDATE_URL,
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'limit' => [
        'filter' => FILTER_VALIDATE_INT,
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'offset' => [
        'filter' => FILTER_VALIDATE_INT,
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'filter' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^(([a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+(,[a-zA-Z0-9_-]+)*)([+][a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+(,[a-zA-Z0-9_-]+)*)*)*$/'],
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'chunk-size' => [
        'filter'    => FILTER_VALIDATE_INT,
        'options'   => ['min_range' => 1, 'max_range' => 1000],
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'id' => [
        'filter'    => FILTER_VALIDATE_INT,
        'options'   => ['min_range' => 0],
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'remove' => [
        'filter' => FILTER_VALIDATE_BOOLEAN,
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'alias' => [
        'filter' => FILTER_VALIDATE_BOOLEAN,
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
      'alias-only' => [
        'filter' => FILTER_VALIDATE_BOOLEAN,
        'flags' => FILTER_NULL_ON_FAILURE,
      ],
    ]);

    foreach ($results as $key => $value) {
      if (is_null($value)) {
        throw new \Exception("Invalid '{$key}' argument value.");
      }
    }
  }

  /**
   * Display the script usage and indexing options.
   *
   * @param string $error
   *   Error message.
   */
  public static function displayUsage($error = '') {
    if (!empty($error)) {
      echo "[ERROR] " . $error . "\n\n";
    }

    echo "Usage: php PATH/TO/Indexer.php [options] <entity-bundle>\n" .
          "     -h, --help Display this help message \n" .
          "     -e, --elasticsearch <arg> Elasticsearch URL, defaults to http://127.0.0.1:9200 \n" .
          "     -H, --mysql-host <arg> Mysql host, defaults to localhost \n" .
          "     -P, --mysql-port <arg> Mysql port, defaults to 3306 \n" .
          "     -u, --mysql-user <arg> Mysql user, defaults to root \n" .
          "     -p, --mysql-pass <arg> Mysql pass, defaults to none \n" .
          "     -d, --database <arg> Database name, deaults to reliefwebint_0 \n" .
          "     -b, --base-index-name <arg> Base index name, deaults to reliefwebint_0 \n" .
          "     -t, --tag <arg> Tag appended to the index name, defaults to empty string \n" .
          "     -w, --website <arg> Website URL, deaults to https://reliefweb.int \n" .
          "     -l, --limit <arg> Maximum number of entities to index, defaults to 0 (all) \n" .
          "     -o, --offset <arg> ID of the entity from which to start the indexing, defaults to the most recent one \n" .
          "     -f, --filter <arg> Filter documents to index. Format: 'field1:value1,value2+field2:value1,value2' \n" .
          "     -c, --chunk-size <arg> Number of entities to index at one time, defaults to 500 \n" .
          "     -i, --id Id of an entity item to index, defaults to 0 (none) \n" .
          "     -r, --remove Removes an entity if 'id' is provided or the index for the given entity bundle \n" .
          "     -a, --alias Set up the alias for the index after the indexing, ignored if id is provided \n" .
          "     -A, --alias-only Set up the alias for the index without indexing, ignored if id is provided \n" .
          "\n";
    exit();
  }

}
