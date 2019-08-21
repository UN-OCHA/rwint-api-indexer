<?php

namespace RWAPIIndexer\Tests;

use RWAPIIndexer\Processor;
use RWAPIIndexer\References;
use Michelf\Markdown;
use PHPUnit\Framework\TestCase;

/**
 * Test Processor.
 */
class ProcessorTest extends TestCase {

  /**
   * Test the converter is working.
   */
  public function testCanConvertBooleans() {
    $item = array(
      'string' => 'test',
    );
    Processor::processConversion(array('bool'), $item, 'string');
    $this->assertTrue(
      $item['string']
    );
    $item = array(
      'int' => 0,
    );
    Processor::processConversion(array('bool'), $item, 'int');
    $this->assertFalse(
      $item['int']
    );
    $item = array(
      'float' => 1.234,
    );
    Processor::processConversion(array('bool'), $item, 'float');
    $this->assertIsBool(
      $item['float']
    );
  }

  /**
   * Test substitution of appropriate host for links.
   */
  public function testCanConvertLinks() {
    $referenceStub = new References();
    $processor = new Processor('https://rw.test', $referenceStub);
    $item = array(
      'links' => 'http://reliefweb.int/taxonomy/term/13',
    );
    $processor->processConversion(array('links'), $item, 'links');
    $this->assertEquals(
      'https://rw.test/taxonomy/term/13',
      $item['links']
    );
  }

  /**
   * Test converting a string from markdown to html.
   */
  public function testCanConvertMarkdown() {
    $referenceStub = new References();
    $processor = new Processor('https://rw.test', $referenceStub);
    $item = array(
      'html' => '_Emphasis_',
    );
    $processor->processConversion(array('html'), $item, 'html');
    $this->assertEquals(
      "<p><em>Emphasis</em></p>\n",
      $item['html-html']
    );
  }

  /**
   * Test marking a resource as primary when appropriate.
   */
  public function testCanFlagResourceAsPrimary() {
    $item = array(
      'resource' => array('id' => 1),
      'primary_resource' => array('id' => 2),
    );
    Processor::processConversion(array('primary'), $item, 'resource');
    $this->assertEquals(
      $item,
      $item
    );
    $item = array(
      'resource' => array('id' => 2),
      'primary_resource' => array('id' => 2),
    );
    Processor::processConversion(array('primary'), $item, 'resource');
    $this->assertEquals(
      TRUE,
      $item['resource']['primary']
    );
  }

  /**
   * Test that we can process a reference, replacing an id with a term.
   */
  public function testCanProcessReference() {
    $referenceStub = new References();
    $referenceStub->set('referenceTest', array(
      2 => array(
        'id' => 2,
        'name' => 'processed',
      ),
    ));
    $processor = new Processor('https://rw.test', $referenceStub);
    $definition = array(
      'referenceTest' => array('id', 'name'),
    );
    $item = array(
      2 => array(
        'id' => 2,
      ),
    );
    $processor->processReference($definition, $item, 2);
    $this->assertEquals(
      array(
        '2' => array(
          array(
            'id' => 2,
            'name' => 'processed',
          ),
        )
      ),
      $item
    );
  }

  /**
   * Test stripping tags.
   * Note: this will use filter_xss function when Drupal is bootstrapped.
   */
  public function testCanProcessHtml() {
    $html = '<h1>Allowed title</h1>&lt;&&gt;<h7>Disallowed title</h7>';
    $processed = Processor::processHtml($html);
    $this->assertEquals(
      '<h1>Allowed title</h1>&lt;&&gt;Disallowed title',
      $processed
    );
  }

  /**
   * Test processing images.
   */
  public function testCanProcessImage() {
    $referenceStub = new References();
    $processor = new Processor('https://rw.test', $referenceStub);
    $field = 'id###copy###caption###width###height###public://resources/image.jpg###image.jpg###filesize';
    $processor->processImage($field, TRUE, FALSE, FALSE);
    $this->assertArrayHasKey('url', $field);
    $this->assertEquals($field['id'], 'id');
    $this->assertArrayNotHasKey('caption', $field);
    $this->assertEquals($field['width'], 'width');
    $this->assertEquals($field['filesize'], 'filesize');
    // Try again with meta.
    $field = 'id###copy###caption###width###height###public://resources/ocha.jpg###ocha.jpg###filesize';
    $processor->processImage($field, TRUE, TRUE, FALSE);
    $this->assertEquals($field['caption'], 'caption');
  }

  /**
   * Test processing files.
   */
  public function testCanProcessFile() {
    $referenceStub = new References();
    $processor = new Processor('https://rw.test', $referenceStub);
    $field = 'id###|1|0###public://resources/doc.pdf###doc.pdf###filesize';
    $processor->processFile($field, TRUE);
    $this->assertArrayHasKey('url', $field);
    $this->assertEquals($field['id'], 'id');
    $this->assertArrayHasKey('url-large', $field['preview']);
    $this->assertEquals($field['filesize'], 'filesize');
    // Try again without a .pdf extension on the filename.
    $field = 'id###|1|0###public://resources/doc.pdf###doc###filesize';
    $processor->processFile($field, TRUE);
    $this->assertArrayNotHasKey('preview', $field);
  }

}
