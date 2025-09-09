<?php

namespace RWAPIIndexer;

/**
 * Metrics handling class.
 */
class Metrics {
  /**
   * Starting time.
   *
   * @var int
   */
  protected $time = 0;

  /**
   * Set up the initial time and memory.
   */
  public function __construct() {
    $this->reset();
  }

  /**
   * Reset the metrics.
   */
  public function reset() {
    $this->time = microtime(TRUE);
  }

  /**
   * Get the metrics.
   *
   * @return array
   *   Time and memory metrics.
   */
  public function get() {
    return [
      'time' => static::formatTime(microtime(TRUE) - $this->time),
      'memory usage' => static::formatMemory(memory_get_peak_usage(TRUE)),
    ];
  }

  /**
   * Print the metrics.
   */
  public function __toString() {
    $metrics = $this->get();

    return "Execution time: {$metrics['time']}\n" .
           "Memory usage: {$metrics['memory usage']}\n";
  }

  /**
   * Format duration time to be more human readable.
   *
   * @param int $time
   *   Time to format.
   *
   * @return string
   *   Formatted memory size.
   */
  public static function formatTime($time) {
    $sec = intval($time);
    $micro = $time - $sec;

    $hours = floor($sec / 3600);
    $minutes = floor(($sec % 3600) / 60);
    $seconds = $sec % 60;

    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds) .
           str_replace('0.', '.', sprintf('%.3f', $micro));
  }

  /**
   * Format memory to be more human readable.
   *
   * @param int $size
   *   Memory size to format.
   *
   * @return string
   *   Formatted memory size.
   */
  public static function formatMemory($size) {
    $base = log($size) / log(1024);
    $suffixes = ["", "k", "M", "G", "T"];
    return pow(1024, $base - floor($base)) . $suffixes[floor($base)];
  }

}
