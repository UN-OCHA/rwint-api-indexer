<?php

namespace RWAPIIndexer\Tests;

use ReflectionClass;
use RWAPIIndexer\Bundles;
use RWAPIIndexer\Resources\Blog;
use RWAPIIndexer\Elasticsearch;
use RWAPIIndexer\Database\DatabaseConnection;
use RWAPIIndexer\Processor;
use RWAPIIndexer\References;
use RWAPIIndexer\Options;
use PHPUnit\Framework\TestCase;

/**
 * Test Bundles.
 */
class BundlesTest extends TestCase {

  /**
   * Test getResourceHandler().
   *
   * Create a blog resource and check its 'index' property.
   */
  public function testCanCreateResourceHandler() {
    $esStub = $this->createMock(Elasticsearch::class);
    $dbcStub = $this->createMock(DatabaseConnection::class);
    $processorStub = $this->createMock(Processor::class);
    $referencesStub = $this->createMock(References::class);
    $optionsStub = $this->createMock(Options::class);
    $handler = Bundles::getResourceHandler('blog_post', $esStub, $dbcStub, $processorStub, $referencesStub, $optionsStub);

    // Access protected 'index' property.
    $reflector = new ReflectionClass('RWAPIIndexer\Resources\Blog');
    $index = $reflector->getProperty('index');
    $index->setAccessible(TRUE);

    $this->assertEquals('blog', $index->getValue($handler));
  }

}
