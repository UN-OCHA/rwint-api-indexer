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
   * ProcessConversion() does not add or modify keys when key is missing.
   */
  #[Test]
  public function processConversionWhenKeyMissingLeavesItemUnchanged(): void {
    $processor = $this->createProcessor();
    $item = [];
    $processor->processConversion(['bool'], $item, 'flag');
    self::assertArrayNotHasKey('flag', $item);
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
   * ProcessConversion() converts value when key is set (e.g. string to int).
   */
  #[Test]
  public function processConversionIntWhenKeySetConvertsValue(): void {
    $processor = $this->createProcessor();
    $item = ['count' => '42'];
    $processor->processConversion(['int'], $item, 'count');
    self::assertSame(42, $item['count']);
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
   * ProcessConversion() converts value when key is set (e.g. string to float).
   */
  #[Test]
  public function processConversionFloatWhenKeySetConvertsValue(): void {
    $processor = $this->createProcessor();
    $item = ['score' => '3.14'];
    $processor->processConversion(['float'], $item, 'score');
    self::assertSame(3.14, $item['score']);
  }

  /**
   * ProcessConversion() converts value when key is set (e.g. to bool).
   */
  #[Test]
  public function processConversionBoolWhenKeySetConvertsValue(): void {
    $processor = $this->createProcessor();
    $item = ['flag' => '1'];
    $processor->processConversion(['bool'], $item, 'flag');
    self::assertTrue($item['flag']);
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
   * ProcessConversion() time converts numeric seconds to milliseconds.
   */
  #[Test]
  public function processConversionTimeNumericConvertsToMilliseconds(): void {
    $processor = $this->createProcessor();
    $item = ['updated' => 1234567890];
    $processor->processConversion(['time'], $item, 'updated');
    self::assertSame(1234567890000, $item['updated']);
  }

  /**
   * ProcessConversion() time converts date string to timestamp in milliseconds.
   */
  #[Test]
  public function processConversionTimeDateStringConvertsToMilliseconds(): void {
    $processor = $this->createProcessor();
    $item = ['date' => '2020-01-01 00:00:00'];
    $processor->processConversion(['time'], $item, 'date');
    self::assertIsInt($item['date']);
    self::assertGreaterThan(1000000000000, $item['date']);
  }

  /**
   * ProcessConversion() unsets key if value is not numeric or date string.
   */
  #[Test]
  public function processConversionTimeInvalidUnsetsKey(): void {
    $processor = $this->createProcessor();
    $item = ['date' => []];
    $processor->processConversion(['time'], $item, 'date');
    self::assertArrayNotHasKey('date', $item);
  }

  /**
   * ProcessConversion() links converts string via processLinks.
   */
  #[Test]
  public function processConversionLinksConvertsToAbsoluteLinks(): void {
    $processor = $this->createProcessor();
    $item = ['body' => 'See ](/) and href="/path".'];
    $processor->processConversion(['links'], $item, 'body');
    self::assertStringContainsString('https://reliefweb.int/', $item['body']);
    self::assertStringContainsString('href="https://reliefweb.int/path"', $item['body']);
  }

  /**
   * ProcessConversion() links unsets key when value is not a string.
   */
  #[Test]
  public function processConversionLinksNonStringUnsetsKey(): void {
    $processor = $this->createProcessor();
    $item = ['body' => 123];
    $processor->processConversion(['links'], $item, 'body');
    self::assertArrayNotHasKey('body', $item);
  }

  /**
   * ProcessConversion() html sets key-html with converted HTML from markdown.
   */
  #[Test]
  public function processConversionHtmlSetsHtmlKey(): void {
    $processor = $this->createProcessor();
    $item = ['description' => '**Bold** text'];
    $processor->processConversion(['html'], $item, 'description');
    self::assertArrayHasKey('description-html', $item);
    self::assertStringContainsString('Bold', $item['description-html']);
  }

  /**
   * ProcessConversion() html unsets key when value is not a string.
   */
  #[Test]
  public function processConversionHtmlNonStringUnsetsKey(): void {
    $processor = $this->createProcessor();
    $item = ['description' => []];
    $processor->processConversion(['html'], $item, 'description');
    self::assertArrayNotHasKey('description-html', $item);
  }

  /**
   * ProcessConversion() html_iframe sets key-html after iframe and markdown.
   */
  #[Test]
  public function processConversionHtmlIframeSetsHtmlKey(): void {
    $processor = $this->createProcessor();
    $item = ['overview' => 'Plain text'];
    $processor->processConversion(['html_iframe'], $item, 'overview');
    self::assertArrayHasKey('overview-html', $item);
  }

  /**
   * ProcessConversion() html_strict sets key-html with converted HTML.
   */
  #[Test]
  public function processConversionHtmlStrictSetsHtmlKey(): void {
    $processor = $this->createProcessor();
    $item = ['body' => 'Content'];
    $processor->processConversion(['html_strict'], $item, 'body');
    self::assertArrayHasKey('body-html', $item);
  }

  /**
   * ProcessConversion() html_iframe_disaster_map sets key-html.
   */
  #[Test]
  public function processConversionHtmlIframeDisasterMapSetsHtmlKey(): void {
    $processor = $this->createProcessor();
    $item = ['field' => 'Plain text'];
    $processor->processConversion(['html_iframe_disaster_map'], $item, 'field');
    self::assertArrayHasKey('field-html', $item);
  }

  /**
   * ProcessConversion() multi_int converts to int array.
   */
  #[Test]
  public function processConversionMultiIntConvertsToIntArray(): void {
    $processor = $this->createProcessor();
    $item = ['ids' => '1%%%2%%%3'];
    $processor->processConversion(['multi_int'], $item, 'ids');
    self::assertSame([1, 2, 3], $item['ids']);
  }

  /**
   * ProcessConversion() multi_int drops non-numeric segments.
   */
  #[Test]
  public function processConversionMultiIntDropsNonNumeric(): void {
    $processor = $this->createProcessor();
    $item = ['ids' => '1%%%x%%%3'];
    $processor->processConversion(['multi_int'], $item, 'ids');
    self::assertSame([1, 3], $item['ids']);
  }

  /**
   * ProcessConversion() multi_int unsets key when value is not a string.
   */
  #[Test]
  public function processConversionMultiIntNonStringUnsetsKey(): void {
    $processor = $this->createProcessor();
    $item = ['ids' => []];
    $processor->processConversion(['multi_int'], $item, 'ids');
    self::assertArrayNotHasKey('ids', $item);
  }

  /**
   * ProcessConversion() single takes first element of array.
   */
  #[Test]
  public function processConversionSingleTakesFirstElement(): void {
    $processor = $this->createProcessor();
    $item = ['value' => ['first', 'second']];
    $processor->processConversion(['single'], $item, 'value');
    self::assertSame('first', $item['value']);
  }

  /**
   * ProcessConversion() single unsets key when not array or no index 0.
   */
  #[Test]
  public function processConversionSingleInvalidUnsetsKey(): void {
    $processor = $this->createProcessor();
    $item = ['value' => 'not-array'];
    $processor->processConversion(['single'], $item, 'value');
    self::assertArrayNotHasKey('value', $item);
  }

  /**
   * ProcessConversion() multi_string splits by whitespace and commas.
   */
  #[Test]
  public function processConversionMultiStringSplitsToArray(): void {
    $processor = $this->createProcessor();
    $item = ['tags' => 'a b,c d'];
    $processor->processConversion(['multi_string'], $item, 'tags');
    self::assertSame(['a', 'b', 'c', 'd'], $item['tags']);
  }

  /**
   * ProcessConversion() multi_string unsets key when value is not a string.
   */
  #[Test]
  public function processConversionMultiStringNonStringUnsetsKey(): void {
    $processor = $this->createProcessor();
    $item = ['tags' => 123];
    $processor->processConversion(['multi_string'], $item, 'tags');
    self::assertArrayNotHasKey('tags', $item);
  }

  /**
   * ProcessConversion() primary sets primary flag on matching sub-item.
   */
  #[Test]
  public function processConversionPrimarySetsFlagOnMatchingItem(): void {
    $processor = $this->createProcessor();
    $item = [
      'country' => [
        ['id' => 1, 'name' => 'A'],
        ['id' => 2, 'name' => 'B'],
      ],
      'primary_country' => ['id' => 1],
    ];
    $processor->processConversion(['primary'], $item, 'country');
    self::assertTrue($item['country'][0]['primary']);
    self::assertArrayNotHasKey('primary', $item['country'][1]);
  }

  /**
   * ProcessConversion() int removes key when value is non-numeric.
   */
  #[Test]
  public function processConversionIntNonNumericUnsetsKey(): void {
    $processor = $this->createProcessor();
    $item = ['count' => 'not-a-number'];
    $processor->processConversion(['int'], $item, 'count');
    self::assertArrayNotHasKey('count', $item);
  }

  /**
   * ProcessConversion() float removes key when value is non-numeric.
   */
  #[Test]
  public function processConversionFloatNonNumericUnsetsKey(): void {
    $processor = $this->createProcessor();
    $item = ['score' => 'nope'];
    $processor->processConversion(['float'], $item, 'score');
    self::assertArrayNotHasKey('score', $item);
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
   * ProcessFile() includes pagecount as integer when present and numeric.
   */
  #[Test]
  public function processFileWithPagecountIncludesPagecountAsInteger(): void {
    $processor = $this->createProcessor();
    // 14 segments: delta, id, uuid, filename, filehash, pagecount, description,
    // langcode, preview_*, uri, mimetype, filesize.
    $item = [
      'file' => '0###1###abc-uuid###doc.pdf###h1###42###Description###en############public://attachments/abc-uuid/doc.pdf###application/pdf###1000',
    ];
    $result = $processor->processFile($item, 'file');
    self::assertTrue($result);
    self::assertIsArray($item['file']);
    self::assertCount(1, $item['file']);
    self::assertArrayHasKey('pagecount', $item['file'][0]);
    self::assertSame(42, $item['file'][0]['pagecount']);
  }

  /**
   * ProcessFile() omits pagecount when segment is empty.
   */
  #[Test]
  public function processFileWithoutPagecountOmitsPagecount(): void {
    $processor = $this->createProcessor();
    $item = [
      'file' => '0###1###abc-uuid###doc.pdf###h1#######Description###en############public://attachments/abc-uuid/doc.pdf###application/pdf###1000',
    ];
    $result = $processor->processFile($item, 'file');
    self::assertTrue($result);
    self::assertArrayNotHasKey('pagecount', $item['file'][0]);
  }

  /**
   * ProcessFile() omits pagecount when value is non-numeric.
   */
  #[Test]
  public function processFileWithNonNumericPagecountOmitsPagecount(): void {
    $processor = $this->createProcessor();
    $item = [
      'file' => '0###1###abc-uuid###doc.pdf###h1###n/a###Description###en############public://attachments/abc-uuid/doc.pdf###application/pdf###1000',
    ];
    $result = $processor->processFile($item, 'file');
    self::assertTrue($result);
    self::assertArrayNotHasKey('pagecount', $item['file'][0]);
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
