<?php

namespace RWAPIIndexer\Tests;

use ReflectionClass;
use RWAPIIndexer\Mapping;
use PHPUnit\Framework\TestCase;

/**
 * Test Mapping.
 */
class MappingTest extends TestCase {

  /**
   * Set up access to private 'mapping' property.
   */
  protected function setUp(): void {
    $reflector = new ReflectionClass('RWAPIIndexer\Mapping');
    $this->property = $reflector->getProperty('mapping');
    $this->property->setAccessible(TRUE);
  }

  /**
   * Test taxonomy mapping.
   */
  public function testCanAddTaxonomyMapping() {
    $mapping = new Mapping();
    $this->assertArrayNotHasKey('test', $this->property->getValue($mapping));
    $mapping->addTaxonomy('test');
    $this->assertArrayHasKey('test', $this->property->getValue($mapping));
  }

  /**
   * Test image mapping.
   */
  public function testCanAddImageMapping() {
    $mapping = new Mapping();
    $this->assertArrayNotHasKey('test_image', $this->property->getValue($mapping));
    $mapping->addImage('test_image');
    $mappingValue = $this->property->getValue($mapping);
    $this->assertArrayHasKey('test_image', $mappingValue);
    $this->assertArrayHasKey('properties', $mappingValue['test_image']);
    $this->assertArrayHasKey('copyright', $mappingValue['test_image']['properties']);
    $this->assertEquals('text', $mappingValue['test_image']['properties']['copyright']['type']);
  }

  /**
   * Test file mapping.
   */
  public function testCanAddFileMapping() {
    $mapping = new Mapping();
    $this->assertArrayNotHasKey('test_file', $this->property->getValue($mapping));
    $mapping->addFile('test_file');
    $mappingValue = $this->property->getValue($mapping);
    $this->assertArrayHasKey('test_file', $mappingValue);
    $this->assertArrayHasKey('properties', $mappingValue['test_file']);
    $this->assertArrayHasKey('filesize', $mappingValue['test_file']['properties']);
    $this->assertEquals('integer', $mappingValue['test_file']['properties']['filesize']['type']);
  }

  /**
   * Test profile mapping.
   */
  public function testCanAddProfileMapping() {
    $mapping = new Mapping();
    $this->assertArrayNotHasKey('profile', $this->property->getValue($mapping));
    $sections = array(
      'test_section1' => array(
        'label' => 'First Section',
      ),
      'test_section2' => array(
        'label' => 'Second Section',
      ),
    );
    $mapping->addProfile($sections);
    $mappingValue = $this->property->getValue($mapping);
    $this->assertArrayHasKey('profile', $mappingValue);
    $this->assertArrayHasKey('properties', $mappingValue['profile']);
    $this->assertArrayHasKey('test_section1', $mappingValue['profile']['properties']);
    $this->assertEquals('text', $mappingValue['profile']['properties']['overview']['type']);
  }

  /**
   * Test date mapping with common field.
   */
  public function testCanCreateCommonFieldMapping() {
    $mapping = new Mapping();
    $mapping->addDates('test_date', array('test'));
    $mappingValue = $this->property->getValue($mapping);
    $this->assertArrayHasKey('test_date', $mappingValue);
    $this->assertArrayHasKey('properties', $mappingValue['test_date']);
    $this->assertArrayHasKey('test', $mappingValue['test_date']['properties']);
    $this->assertArrayHasKey('copy_to', $mappingValue['test_date']['properties']['test']);
    $this->assertEquals('common_test_date', $mappingValue['test_date']['properties']['test']['copy_to'][0]);
  }

}
