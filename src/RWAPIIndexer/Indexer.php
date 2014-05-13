<?php

// DEBUG.
error_reporting(-1);
ini_set("display_errors", 1);

// Make sure there is enough memory.
ini_set('memory_limit','256M');

// Load the libraries.
require "vendor/autoload.php";

/**
 * Convert a text in markdown format to HTML.
 * Compatible with with Drupal markdown module.
 */
function Markdown($text) {
  return \Michelf\Markdown::defaultTransform($text);
}

function get_database_connection() {
  global $options;
  static $pdo;

  if (!isset($pdo)) {
    $dbname = $options['database'];
    $host = $options['mysql-host'];
    $port = $options['mysql-port'];
    $dsn = "mysql:dbname={$dbname};host={$host};port={$port}";
    $user = $options['mysql-user'];
    $password = $options['mysql-pass'];

    try {
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('\RWAPIIndexer\Database\Statement', array($pdo)));
    }
    catch (PDOException $e) {
      echo "Failed to connect to the mysql server.\n";
      exit();
    }
  }
  return $pdo;
}

/**
 * Mimic Drupal db_select function (simplified).
 */
function db_select($table, $alias) {
  return new \RWAPIIndexer\Database\Query($table, $alias);
}

/**
 * Mimic Drupal db_query function (simplified).
 */
function db_query($query) {
  $pdo = get_database_connection();
  return $pdo->query($query);
}

/**
 * Quote a value to be inserted in a query.
 */
function db_quote($value) {
  $pdo = get_database_connection();
  return $pdo->quote($value);
}

/**
 * Get the list of available bundles.
 */
function get_bundles() {
  // TODO: handle other taxonomies.
  return array(
    'report' => '\RWAPIIndexer\Indexable\Report',
    'job' => '\RWAPIIndexer\Indexable\Job',
    'training' => '\RWAPIIndexer\Indexable\Training',
    'country' => '\RWAPIIndexer\Indexable\Country',
    'disaster' => '\RWAPIIndexer\Indexable\Disaster',
    'source' => '\RWAPIIndexer\Indexable\Source',

    // References.
    'career_categories' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    //'city' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    'content_format' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    'disaster_type' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    'feature' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    'job_type' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    'language' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    'ocha_product' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    'organization_type' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    //'region' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    //'tags' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    'theme' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    'training_format' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    'training_type' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    'vulnerable_groups' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
    'job_experience' => '\RWAPIIndexer\Indexable\TaxonomyDefault',
  );
}

/**
 * Get the indexable object for the given entity bundle.
 */
function index($options) {
  $bundles = get_bundles();
  $bundle = $options['bundle'];
  if (isset($bundles[$bundle])){// && class_exists($bundles[$bundle])) {
    $indexable = new $bundles[$bundle]($options);

    try {
      // Remove the index.
      if (!empty($options['remove'])) {
        $indexable->remove();
      }
      // Or index the data.
      else {
        $indexable->index();
      }
    }
    catch (\Exception $exception) {
      echo "[ERROR] " . $exception->getMessage() . "\n";
      exit(-1);
    }
  }
  else {
    echo "Indexable not found.\n";
    exit(-1);
  }
}

/**
 * Display the available parameters.
 */
function display_usage () {
  echo "Usage: php PATH/TO/Indexer.php <entity-bundle> [options]\n";
  echo "     -e, --elasticsearch <arg> Elasticsearch URL, defaults to http://127.0.0.1:9200 \n";
  echo "     -h, --mysql-host <arg> Mysql host, defaults to localhost \n";
  echo "     -P, --mysql-port <arg> Mysql port, defaults to 3306 \n";
  echo "     -u, --mysql-user <arg> Mysql user, defaults to root \n";
  echo "     -p, --mysql-pass <arg> Mysql pass, defaults to none \n";
  echo "     -d, --database <arg> Database name, deaults to reliefwebint_0 \n";
  echo "     -w, --website <arg> Website URL, deaults to http://reliefweb.int \n";
  echo "     -l, --limit <arg> Maximum number of entities to index, defaults to 0 (all) \n";
  echo "     -o, --offset <arg> ID of the entity from which to start the indexing, defaults to the most recent one \n";
  echo "     -c, --chunk-size <arg> Number of entities to index at one time, defaults to 500 \n";
  echo "     -r, --remove Indicates that the entity-bundle index should be removed \n";
  echo "\n";
  exit();
}

/**
 * Get passed entity bundle parameter and check its validity.
 */
function validate_bundle($bundle) {
  $bundles = get_bundles();
  if (in_array($bundle, array_keys($bundles))) {
    return $bundle;
  }
  else {
    echo 'Invalid entity bundle. It must be one of ' . implode(', ', $bundles) . ".\n";
    exit(-1);
  }
}

/**
 * Validate Mysql host.
 */
function validate_mysql_host($host) {
  if (filter_var($host, FILTER_VALIDATE_IP) || preg_match('/^\S+$/', $host) === 1) {
    return $host;
  }
  return FALSE;
}

/**
 * Validate the options passed to the script.
 */
function validate_options($options) {
  $results = filter_var_array($options, array(
    'bundle' => array(
      'filter' => FILTER_CALLBACK,
      'options' => 'validate_bundle',
    ),
    'elasticsearch' => FILTER_VALIDATE_URL,
    'mysql-host' => array(
      'filter' => FILTER_CALLBACK,
      'options' => 'validate_mysql_host',
    ),
    'mysql-port' => array(
      'filter'    => FILTER_VALIDATE_INT,
      'options'   => array('min_range' => 1, 'max_range' => 65535),
    ),
    'mysql-user' => array(
      'filter' => FILTER_VALIDATE_REGEXP,
      'options' => array('regexp' => '/^\S+$/'),
    ),
    'mysql-pass' => array(
      'filter' => FILTER_VALIDATE_REGEXP,
      'options' => array('regexp' => '/^\S*$/'),
    ),
    'database' => array(
      'filter' => FILTER_VALIDATE_REGEXP,
      'options' => array('regexp' => '/^\S*$/'),
    ),
    'website' => FILTER_VALIDATE_URL,
    'limit' => FILTER_VALIDATE_INT,
    'offset' => FILTER_VALIDATE_INT,
    'chunk-size' => array(
      'filter'    => FILTER_VALIDATE_INT,
      'options'   => array('min_range' => 1, 'max_range' => 1000),
    ),
    'remove' => FILTER_VALIDATE_BOOLEAN,
  ));

  foreach ($results as $key => $value) {
    if ($value === FALSE) {
      echo "Invalid '{$key}' argument value.\n";
      exit(-1);
    }
  }
}

// Can only be run in the console.
if (!(php_sapi_name() == 'cli')) {
  echo 'You need to run this from console.';
  exit();
}

// Remove the name of the executing script.
array_shift($argv);

// No parameters, we display the usage.
if (empty($argv)) {
  display_usage();
}

// Display usage if first argument is help.
$first_arg = array_shift($argv);
if ($first_arg === '-h' || $first_arg === '--help') {
  display_usage();
}

// Default options.
$options = array(
  'bundle' => $first_arg,
  'elasticsearch' => 'http://127.0.0.1:9200',
  'mysql-host' => 'localhost',
  'mysql-port' => 3306,
  'mysql-user' => 'root',
  'mysql-pass' => '',
  'database' => 'reliefwebint_0',
  'website' => 'http://reliefweb.int',
  'limit' => 0,
  'offset' => 0,
  'chunk-size' => 500,
);

// Parse the arguments.
while (($arg = array_shift($argv)) !== NULL) {
  switch ($arg) {
    case '--elasticsearch':
    case '-e':
      $options['elasticsearch'] = array_shift($argv);
      break;

    case '--mysql-host':
    case '-h':
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

    case '--chunk-size':
    case '-c':
      $options['chunk-size'] = (int) array_shift($argv);
      break;

    case '--remove':
    case '-r':
      $options['remove'] = TRUE;
      break;

    case '--help':
    case '-h':
    default:
      display_usage();
      break;
  }
}

// Check the validity of the passed options.
validate_options($options);

function format_memory($size) {
  $base = log($size) / log(1024);
  $suffixes = array("", "k", "M", "G", "T");
  return pow(1024, $base - floor($base)) . $suffixes[floor($base)];
}

function format_time($time) {
  $sec = intval($time);
  $micro = $time - $sec;
  return strftime('%T', mktime(0, 0, $sec)) . str_replace('0.', '.', sprintf('%.3f', $micro));
}

$time = microtime(TRUE);
$memory = memory_get_usage(TRUE);

// Launch the indexing.
index($options);

$time = format_time(microtime(TRUE) - $time);
$memory = format_memory(memory_get_peak_usage(TRUE));//memory_get_usage(TRUE) - $memory_usage);

echo "Indexing time: {$time}\n";
echo "Memory usage: {$memory}.\n";
