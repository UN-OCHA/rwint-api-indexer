<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\Metrics;

/**
 * Tests for Metrics execution metrics handler.
 */
final class MetricsTest extends TestCase {

  /**
   * Get() returns time and memory usage keys with string values.
   */
  #[Test]
  public function getReturnsTimeAndMemoryKeys(): void {
    $metrics = new Metrics();
    $data = $metrics->get();
    self::assertArrayHasKey('time', $data);
    self::assertArrayHasKey('memory usage', $data);
    self::assertIsString($data['time']);
    self::assertIsString($data['memory usage']);
  }

  /**
   * Reset() resets the start time for subsequent get() calls.
   */
  #[Test]
  public function resetResetsStartTime(): void {
    $metrics = new Metrics();
    usleep(10000);
    $before_reset = $metrics->get();
    usleep(10000);
    $metrics->reset();
    $after_reset = $metrics->get();
    self::assertNotSame($before_reset['time'], $after_reset['time'], 'Time should differ after reset.');
    self::assertStringStartsWith('00:00:00', $after_reset['time']);
  }

  /**
   * FormatTime() formats seconds as HH:MM:SS.mmm.
   */
  #[Test]
  public function formatTimeFormatsCorrectly(): void {
    self::assertSame('00:00:00.000', Metrics::formatTime(0));
    self::assertSame('00:00:01.500', Metrics::formatTime(1.5));
    self::assertSame('01:30:45.123', Metrics::formatTime(5445.123));
  }

  /**
   * FormatMemory() returns a human-readable string for byte size.
   */
  #[Test]
  public function formatMemoryFormatsBytes(): void {
    self::assertNotEmpty(Metrics::formatMemory(1024));
    self::assertNotEmpty(Metrics::formatMemory(1024 * 1024));
    self::assertIsString(Metrics::formatMemory(0));
  }

  /**
   * String representation includes execution time and memory usage labels.
   */
  #[Test]
  public function toStringContainsTimeAndMemory(): void {
    $metrics = new Metrics();
    $string = (string) $metrics;
    self::assertStringContainsString('Execution time:', $string);
    self::assertStringContainsString('Memory usage:', $string);
  }

}
