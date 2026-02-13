<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RWAPIIndexer\Processor;
use RWAPIIndexer\References;

/**
 * Tests for Processor field processor.
 */
#[AllowMockObjectsWithoutExpectations]
final class ProcessorTest extends TestCase {

  use MockDatabaseTrait;

  /**
   * Creates a Processor instance for tests.
   *
   * @return \RWAPIIndexer\Processor
   *   Processor with mock connection and empty references.
   */
  private function createProcessor(): Processor {
    $connection = $this->createMockDatabaseConnection();
    $references = new References();
    return new Processor('https://reliefweb.int', $connection, $references);
  }

  /**
   * ProcessConversion() bool adds FALSE when key is missing.
   */
  #[Test]
  public function processConversionBoolWhenKeyMissingAddsFalse(): void {
    $processor = $this->createProcessor();
    $item = [];
    $processor->processConversion(['bool'], $item, 'flag');
    self::assertFalse($item['flag']);
  }

  /**
   * ProcessConversion() int leaves key unset when key is missing.
   */
  #[Test]
  public function processConversionIntWhenKeyMissingLeavesKeyUnset(): void {
    $processor = $this->createProcessor();
    $item = [];
    $processor->processConversion(['int'], $item, 'count');
    self::assertArrayNotHasKey('count', $item);
  }

  /**
   * ProcessConversion() skips conversion when key is already set.
   */
  #[Test]
  public function processConversionSkipsWhenKeyAlreadySet(): void {
    $processor = $this->createProcessor();
    $item = ['count' => '42'];
    $processor->processConversion(['int'], $item, 'count');
    // Current implementation returns early when key is set, so no conversion.
    self::assertSame('42', $item['count']);
  }

  /**
   * ProcessConversion() float leaves key unset when key is missing.
   */
  #[Test]
  public function processConversionFloatWhenKeyMissingLeavesKeyUnset(): void {
    $processor = $this->createProcessor();
    $item = [];
    $processor->processConversion(['float'], $item, 'score');
    self::assertArrayNotHasKey('score', $item);
  }

  /**
   * ProcessConversion() does not add key when key is missing for int.
   */
  #[Test]
  public function processConversionSkipsWhenKeyNotSet(): void {
    $processor = $this->createProcessor();
    $item = [];
    $processor->processConversion(['int'], $item, 'missing');
    self::assertArrayNotHasKey('missing', $item);
  }

  /**
   * ConvertToHtml() converts markdown to HTML (e.g. bold).
   */
  #[Test]
  public function convertToHtmlConvertsMarkdown(): void {
    $html = Processor::convertToHtml('Hello **world**');
    self::assertStringContainsString('Hello', $html);
    self::assertStringContainsString('strong', $html);
  }

  /**
   * ConvertToHtml() returns empty string for empty input.
   */
  #[Test]
  public function convertToHtmlEmptyReturnsEmpty(): void {
    self::assertSame('', Processor::convertToHtml(''));
  }

  /**
   * HtmlBlockElements lists known block elements including div.
   */
  #[Test]
  public function htmlBlockElementsIsNonEmpty(): void {
    self::assertNotEmpty(Processor::$htmlBlockElements);
    self::assertContains('div', Processor::$htmlBlockElements);
  }

  /**
   * ProcessLinks() prepends website to relative links and substitutes host.
   */
  #[Test]
  public function processLinksPrependsWebsiteAndSubstitutesDomain(): void {
    $processor = $this->createProcessor();
    $text = 'Link ](/) and href="/path"';
    $result = $processor->processLinks($text);
    self::assertStringContainsString('https://reliefweb.int/', $result);
    self::assertStringContainsString('href="https://reliefweb.int/path"', $result);
  }

  /**
   * EncodePath() rawurlencodes and restores slashes.
   */
  #[Test]
  public function encodePathEncodesAndRestoresSlashes(): void {
    $processor = $this->createProcessor();
    self::assertSame('path/to/file', $processor->encodePath('path/to/file'));
    self::assertSame('hello%20world', $processor->encodePath('hello world'));
  }

  /**
   * GetMimeType() returns known mime type by extension.
   */
  #[Test]
  public function getMimeTypeReturnsKnownExtension(): void {
    $processor = $this->createProcessor();
    self::assertSame('application/pdf', $processor->getMimeType('doc.pdf'));
    self::assertSame('image/png', $processor->getMimeType('image.PNG'));
  }

  /**
   * GetMimeType() returns application/octet-stream for unknown extension.
   */
  #[Test]
  public function getMimeTypeReturnsOctetStreamForUnknown(): void {
    $processor = $this->createProcessor();
    self::assertSame('application/octet-stream', $processor->getMimeType('file.xyz'));
  }

  /**
   * ProcessEntityRelativeUrl() prepends website and encodes path.
   */
  #[Test]
  public function processEntityRelativeUrlPrependsWebsite(): void {
    $processor = $this->createProcessor();
    $url = $processor->processEntityRelativeUrl('reports/123');
    self::assertStringStartsWith('https://reliefweb.int/', $url);
    self::assertStringContainsString('reports', $url);
  }

  /**
   * ProcessIframes() converts [iframe:WxH "title"](url) to iframe HTML.
   */
  #[Test]
  public function processIframesConvertsSyntaxToIframe(): void {
    $processor = $this->createProcessor();
    $text = '[iframe:800x600 "Map"](https://example.com/map)';
    $result = $processor->processIframes($text);
    self::assertStringContainsString('<iframe', $result);
    self::assertStringContainsString('width="800"', $result);
    self::assertStringContainsString('height="600"', $result);
    self::assertStringContainsString('Map', $result);
    self::assertStringContainsString('https://example.com/map', $result);
  }

  /**
   * ProcessFilePath() with attachments path uses website base.
   */
  #[Test]
  public function processFilePathWithAttachmentsUsesWebsiteBase(): void {
    $processor = $this->createProcessor();
    $url = $processor->processFilePath('public://attachments/abc/file.pdf');
    self::assertStringStartsWith('https://reliefweb.int/', $url);
    self::assertStringContainsString('attachments', $url);
  }

  /**
   * ProcessFilePath() with style appends styles path.
   */
  #[Test]
  public function processFilePathWithStyleAppendsStylesPath(): void {
    $processor = $this->createProcessor();
    $url = $processor->processFilePath('public://files/doc.pdf', 'large');
    self::assertStringContainsString('styles/large/public/', $url);
  }

}
