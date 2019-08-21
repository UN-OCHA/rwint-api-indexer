<?php

namespace RWAPIIndexer\Tests;

use RWAPIIndexer\Resources\Blog;
use RWAPIIndexer\Elasticsearch;
use RWAPIIndexer\Database\DatabaseConnection;
use RWAPIIndexer\Processor;
use RWAPIIndexer\References;
use RWAPIIndexer\Options;
use PHPUnit\Framework\TestCase;

/**
 * Test Resource Abstract Class.
 *
 * Most of the methods are dependent on elasticsearch queries. These aren't
 * tested.
 */
class ResourceTest extends TestCase {

  protected $blog;

  /**
   * Set up a blog resource to test against.
   */
  protected function setUp(): void {
    $esStub = $this->createMock(Elasticsearch::class);
    $dbcStub = $this->createMock(DatabaseConnection::class);
    $processorStub = $this->createMock(Processor::class);
    $referencesStub = $this->createMock(References::class);
    $optionsStub = $this->createMock(Options::class);
    $this->blog = new Blog('blog_post', 'node', 'blog', $esStub, $dbcStub, $processorStub, $referencesStub, $optionsStub);
  }

  /**
   * Test parsing filters.
   */
  public function testCanParseResourceFilters() {
    $filter = 'date_created:2019';
    $conditions = $this->blog->parseFilters($filter);
    $this->assertEquals(
      array(
        'fields' => array(
          'date_created' => array(2019),
        ),
      ),
      $conditions
    );
    $filter = 'status:passing';
    $conditions = $this->blog->parseFilters($filter);
    $this->assertEquals(
      array(
        'field_joins' => array(
          'status' => array('passing'),
        ),
      ),
      $conditions
    );
    $filter = 'tags:123';
    $conditions = $this->blog->parseFilters($filter);
    $this->assertEquals(
      array(
        'references' => array(
          'tags' => array(123),
        ),
      ),
      $conditions
    );
  }

  /**
   * Test getting references.
   */
  public function testCanGetReferences() {
    $references = $this->blog->getReferences();
    $this->assertContains('tags', $references);
  }

}
