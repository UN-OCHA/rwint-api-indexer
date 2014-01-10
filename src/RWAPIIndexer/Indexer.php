<?php

// DEBUG.
error_reporting(-1);
ini_set("display_errors", 1);

// Make sure there is enough memory.
ini_set('memory_limit','256M');

// Load the libraries.
require "vendor/autoload.php";

// Convert a text in markdown format to HTML.
function convertMarkdown($text) {
  return \Michelf\Markdown::defaultTransform($text);
}

// Display the available parameters.
function displayUsage () {
  echo "Usage: script.php <entity-bundle> [options]\n";
  echo "     -e, --elasticsearch <arg> Elasticsearch URL, defaults to http://127.0.0.1:9200 \n";
  echo "     -h, --mysql-host <arg> Mysql host IP, defaults to 127.0.0.1 \n";
  echo "     -P, --mysql-port <arg> Mysql port, defaults to 3306 \n";
  echo "     -u, --mysql-user <arg> Mysql user, defaults to root \n";
  echo "     -p, --mysql-pass <arg> Mysql pass, defaults to none \n";
  echo "     -d, --database <arg> Database name, deaults to reliefwebint_0 \n";
  echo "     -w, --website <arg> Website URL, deaults to http://reliefweb.int \n";
  echo "     -l, --limit <arg> Maximum number of entities to index, defaults to 1000 \n";
  echo "     -o, --offset <arg> ID of the entity from which to start the indexing, defaults to the most recent one \n";
  echo "\n";
  exit();
}

// Get passed entity bundle parameter and check its validity.
function validateBundle($bundle) {
  // TODO: handle other taxonomies.
  $bundles = array('report', 'job', 'training', 'country', 'disaster', 'source');
  if (in_array($bundle, $bundles)) {
    return $bundle;
  }
  else {
    echo 'Invalid entity bundle. It must be one of ' . implode(', ', $bundles) . ".\n";
    exit();
  }
}

// Validate the options passed to the script.
function validateOptions($options) {
  $results = filter_var_array($options, array(
    'bundle' => array(
      'filter' => FILTER_CALLBACK,
      'options' => 'validateBundle',
    ),
    'elasticsearch' => FILTER_VALIDATE_URL,
    'mysql-host' => FILTER_VALIDATE_IP,
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
    'limit' => array(
      'filter'    => FILTER_VALIDATE_INT,
      'options'   => array('min_range' => 1, 'max_range' => 1000),
    ),
    'offset' => FILTER_VALIDATE_INT,
  ));

  foreach ($results as $key => $value) {
    if ($value === FALSE) {
      echo "Invalid '{$key}' argument value.\n";
      exit();
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
  displayUsage();
}

// Default options.
$options = array(
  'bundle' => array_shift($argv),
  'elasticsearch' => 'http://127.0.0.1:9200',
  'mysql-host' => '127.0.0.1',
  'mysql-port' => 3306,
  'mysql-user' => 'root',
  'mysql-pass' => '',
  'database' => 'reliefwebint_0',
  'website' => 'http://reliefweb.int',
  'limit' => 1000,
  'offset' => 0,
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

    default:
      displayUsage();
      break;
  }
}

// Check the validity of the passed options.
validateOptions($options);

echo convertMarkdown("**test**\n");



