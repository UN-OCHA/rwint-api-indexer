<?php

namespace RWAPIIndexer\Tests;

use RWAPIIndexer\Bundles;
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
   * Create a blog resource handler and check its type.
   */
  public function testCanCreateResourceHandler() {
    $esStub = $this->createMock(Elasticsearch::class);
    $dbcStub = $this->createMock(DatabaseConnection::class);
    $processorStub = $this->createMock(Processor::class);
    $referencesStub = $this->createMock(References::class);
    $optionsStub = $this->createMock(Options::class);

    $handler = Bundles::getResourceHandler('blog_post', $esStub, $dbcStub, $processorStub, $referencesStub, $optionsStub);

    $this->assertInstanceOf('RWAPIIndexer\Resources\Blog', $handler);
    $this->assertInstanceOf('RWAPIIndexer\Query', $handler->query);
  }

  /**
   * Test has().
   *
   * Create a blog resource and check it exists.
   */
  public function testBundlesAreSupported() {
    $bundles = new Bundles;

    $this->assertTrue($bundles->has('blog_post'));
    $this->assertFalse($bundles->has('not_a_bundle'));
    $this->assertTrue($bundles->has('disaster_type'));
  }

}
