<?php

namespace RWAPIIndexer\Tests;

use RWAPIIndexer\Mapping;
use PHPUnit\Framework\TestCase;

/**
 * Test Mapping.
 */
class MappingTest extends TestCase {

  /**
   * Test taxonomy mapping.
   */
  public function testCanAddTaxonomyMapping() {
    $mapping = new Mapping();
    $before = $mapping->export();
    $this->assertArrayHasKey('timestamp', $before);
    $this->assertArrayNotHasKey('test', $before);
    $mapping->addTaxonomy('test');
    $after = $mapping->export();
    $this->assertArrayHasKey('test', $after);
    $this->assertArrayHasKey('common_test_exact', $after);
    $this->assertEquals('integer', $after['test']['properties']['id']['type']);
  }

  /**
   * Test image mapping.
   */
  public function testCanAddImageMapping() {
    $mapping = new Mapping();
    $mapping->addImage('test_image');
    $after = $mapping->export();
    $this->assertArrayHasKey('test_image', $after);
    $this->assertArrayHasKey('properties', $after['test_image']);
    $this->assertArrayHasKey('copyright', $after['test_image']['properties']);
    $this->assertEquals('text', $after['test_image']['properties']['copyright']['type']);
  }

  /**
   * Test file mapping.
   */
  public function testCanAddFileMapping() {
    $mapping = new Mapping();
    $mapping->addFile('test_file');
    $after = $mapping->export();
    $this->assertArrayHasKey('test_file', $after);
    $this->assertArrayHasKey('properties', $after['test_file']);
    $this->assertArrayHasKey('filesize', $after['test_file']['properties']);
    $this->assertEquals('integer', $after['test_file']['properties']['filesize']['type']);
  }

  /**
   * Test profile mapping.
   */
  public function testCanAddProfileMapping() {
    $mapping = new Mapping();
    $sections = array(
      'test_section1' => array(
        'label' => 'First Section',
      ),
      'test_section2' => array(
        'label' => 'Second Section',
      ),
    );
    $mapping->addProfile($sections);
    $after = $mapping->export();
    $this->assertArrayHasKey('profile', $after);
    $this->assertArrayHasKey('properties', $after['profile']);
    $this->assertArrayHasKey('test_section1', $after['profile']['properties']);
    $this->assertEquals('text', $after['profile']['properties']['overview']['type']);
  }

  /**
   * Test date mapping with common field.
   */
  public function testCanCreateCommonFieldMapping() {
    $mapping = new Mapping();
    $mapping->addDates('test_date', array('test'));
    $after = $mapping->export();
    $this->assertArrayHasKey('test_date', $after);
    $this->assertArrayHasKey('properties', $after['test_date']);
    $this->assertArrayHasKey('test', $after['test_date']['properties']);
    $this->assertArrayHasKey('copy_to', $after['test_date']['properties']['test']);
    $this->assertEquals('common_test_date', $after['test_date']['properties']['test']['copy_to'][0]);
  }

}
