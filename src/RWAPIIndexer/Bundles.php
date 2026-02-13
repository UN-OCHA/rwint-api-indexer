<?php

declare(strict_types=1);

namespace RWAPIIndexer;

use RWAPIIndexer\Database\DatabaseConnection;

/**
 * Bundles class manager.
 *
 * @phpstan-type Bundle array{
 *   class: class-string<\RWAPIIndexer\Resource>,
 *   type: string,
 *   index: string
 * }
 */
class Bundles {

  /**
   * List of Resources entity bundles and their corresponding class.
   *
   * @var Bundle[]
   */
  public const BUNDLES = [
    'report' => [
      'class' => '\RWAPIIndexer\Resources\Report',
      'type' => 'node',
      'index' => 'reports',
    ],
    'job' => [
      'class' => '\RWAPIIndexer\Resources\Job',
      'type' => 'node',
      'index' => 'jobs',
    ],
    'training' => [
      'class' => '\RWAPIIndexer\Resources\Training',
      'type' => 'node',
      'index' => 'training',
    ],
    'blog_post' => [
      'class' => '\RWAPIIndexer\Resources\Blog',
      'type' => 'node',
      'index' => 'blog',
    ],
    'book' => [
      'class' => '\RWAPIIndexer\Resources\Book',
      'type' => 'node',
      'index' => 'book',
    ],
    'topic' => [
      'class' => '\RWAPIIndexer\Resources\Topic',
      'type' => 'node',
      'index' => 'topics',
    ],
    'country' => [
      'class' => '\RWAPIIndexer\Resources\Country',
      'type' => 'taxonomy_term',
      'index' => 'countries',
    ],
    'disaster' => [
      'class' => '\RWAPIIndexer\Resources\Disaster',
      'type' => 'taxonomy_term',
      'index' => 'disasters',
    ],
    'source' => [
      'class' => '\RWAPIIndexer\Resources\Source',
      'type' => 'taxonomy_term',
      'index' => 'sources',
    ],

    // References.
    'career_category' => [
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'career_categories',
    ],
    'content_format' => [
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'content_formats',
    ],
    'disaster_type' => [
      'class' => '\RWAPIIndexer\Resources\DisasterType',
      'type' => 'taxonomy_term',
      'index' => 'disaster_types',
    ],
    'feature' => [
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'features',
    ],
    'job_type' => [
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'job_types',
    ],
    'language' => [
      'class' => '\RWAPIIndexer\Resources\Language',
      'type' => 'taxonomy_term',
      'index' => 'languages',
    ],
    'ocha_product' => [
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'ocha_products',
    ],
    'organization_type' => [
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'organization_types',
    ],
    'tag' => [
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'tags',
    ],
    'theme' => [
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'themes',
    ],
    'training_format' => [
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'training_formats',
    ],
    'training_type' => [
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'training_types',
    ],
    'vulnerable_group' => [
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'vulnerable_groups',
    ],
    'job_experience' => [
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'job_experiences',
    ],
  ];

  /**
   * List of Resources entity bundles and their corresponding class.
   *
   * Kept for backward compatibility.
   *
   * @var Bundle[]
   */
  public static array $bundles = self::BUNDLES;

  /**
   * Get the resource handler for the given entity bundle.
   *
   * @param string $bundle
   *   Bundle of the resource.
   * @param \RWAPIIndexer\Elasticsearch $elasticsearch
   *   Elasticsearch handler.
   * @param \RWAPIIndexer\Database\DatabaseConnection $connection
   *   Database connection.
   * @param \RWAPIIndexer\Processor $processor
   *   Field processor.
   * @param \RWAPIIndexer\References $references
   *   References handler.
   * @param \RWAPIIndexer\Options $options
   *   Options.
   *
   * @return \RWAPIIndexer\Resource
   *   Resource handler for the given bundle.
   */
  public static function getResourceHandler(
    string $bundle,
    Elasticsearch $elasticsearch,
    DatabaseConnection $connection,
    Processor $processor,
    References $references,
    Options $options,
  ): Resource {
    if (!empty(self::BUNDLES[$bundle]['class']) && class_exists(self::BUNDLES[$bundle]['class'])) {
      $class = self::BUNDLES[$bundle]['class'];
      $index = self::BUNDLES[$bundle]['index'];
      $type = self::BUNDLES[$bundle]['type'];
      $resource = new $class($bundle, $type, $index, $elasticsearch, $connection, $processor, $references, $options);
      return $resource;
    }
    else {
      $bundles = implode(', ', array_keys(self::BUNDLES));
      throw new \Exception("No resource handler for the bundle '$bundle'. Valid ones are: " . $bundles . "\n");
    }
  }

  /**
   * Check if the given bundle is supported.
   *
   * @param string $bundle
   *   Bundle to check.
   *
   * @return bool
   *   Return TRUE if the bundle exists.
   */
  public static function has(string $bundle): bool {
    return isset(self::BUNDLES[$bundle]);
  }

}
