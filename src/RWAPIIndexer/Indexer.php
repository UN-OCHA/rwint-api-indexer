<?php

// DEBUG.
error_reporting(-1);
ini_set("display_errors", 1);

// Make sure there is enough memory.
ini_set('memory_limit','256M');

// Load the libraries.
require "vendor/autoload.php";

/**
 * Format memory to be more human readable.
 *
 * @param integer $size
 *   Memory size to format.
 * @return string
 *   Formatted memory size.
 */
function format_memory($size) {
  $base = log($size) / log(1024);
  $suffixes = array("", "k", "M", "G", "T");
  return pow(1024, $base - floor($base)) . $suffixes[floor($base)];
}

/**
 * Format memory to be more human readable.
 *
 * @param integer $size
 *   Memory size to format.
 * @return string
 *   Formatted memory size.
 */
function format_time($time) {
  $sec = intval($time);
  $micro = $time - $sec;
  return strftime('%T', mktime(0, 0, $sec)) . str_replace('0.', '.', sprintf('%.3f', $micro));
}

// Can only be run in the console.
if (!(php_sapi_name() == 'cli')) {
  echo 'You need to run this from console.';
  exit();
}

// Get starting time and memory usage.
$time = microtime(TRUE);
$memory = memory_get_usage(TRUE);

// Launch the indexing or index removal.
try {
  // Create a new Indexing manager.
  $manager = new \RWAPIIndexer\Manager();
  // Perform indexing or index removal.
  $manager->execute();
}
catch (\Exception $exception) {
  echo "[ERROR] " . $exception->getMessage() . "\n";
  exit(-1);
}

// Compute execution time and memory usage.
$time = format_time(microtime(TRUE) - $time);
$memory = format_memory(memory_get_peak_usage(TRUE));//memory_get_usage(TRUE) - $memory_usage);

echo "Indexing time: {$time}\n";
echo "Memory usage: {$memory}.\n";







