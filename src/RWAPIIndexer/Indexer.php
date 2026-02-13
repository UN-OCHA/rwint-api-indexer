<?php

/**
 * @file
 * Handle indexing using command line arguments.
 */

declare(strict_types=1);

use RWAPIIndexer\Manager;

// DEBUG.
error_reporting(-1);
ini_set("display_errors", 1);

// Make sure there is enough memory.
ini_set('memory_limit', '256M');

// Load the libraries.
require "vendor/autoload.php";

// Can only be run in the console.
if (!(php_sapi_name() == 'cli')) {
  echo 'You need to run this from console.';
  exit();
}

// Launch the indexing or index removal.
try {
  // Create a new Indexing manager.
  $manager = new Manager();
  // Perform indexing or index removal.
  $manager->execute();

  // Print the time and memory usage.
  echo $manager->getMetrics();
}
catch (\Exception $exception) {
  echo "[ERROR] " . $exception->getMessage() . "\n";
  exit(-1);
}
