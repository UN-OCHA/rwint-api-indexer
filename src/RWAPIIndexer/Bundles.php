<?php

namespace RWAPIIndexer;

use RWAPIIndexer\Database\DatabaseConnection;

/**
 * Bundles class manager.
 */
class Bundles {

  /**
   * List of Resources entity bundles and their corresponding class.
   *
   * @var array
   */
  public static $bundles = array(
    'report' => array(
      'class' => '\RWAPIIndexer\Resources\Report',
      'type' => 'node',
      'index' => 'reports',
    ),
    'job' => array(
      'class' => '\RWAPIIndexer\Resources\Job',
      'type' => 'node',
      'index' => 'jobs',
    ),
    'training' => array(
      'class' => '\RWAPIIndexer\Resources\Training',
      'type' => 'node',
      'index' => 'training',
    ),
    'blog_post' => array(
      'class' => '\RWAPIIndexer\Resources\Blog',
      'type' => 'node',
      'index' => 'blog',
    ),
    'book' => array(
      'class' => '\RWAPIIndexer\Resources\Book',
      'type' => 'node',
      'index' => 'book',
    ),
    'faq' => array(
      'class' => '\RWAPIIndexer\Resources\Faq',
      'type' => 'node',
      'index' => 'faq',
    ),
    'country' => array(
      'class' => '\RWAPIIndexer\Resources\Country',
      'type' => 'taxonomy_term',
      'index' => 'countries',
    ),
    'disaster' => array(
      'class' => '\RWAPIIndexer\Resources\Disaster',
      'type' => 'taxonomy_term',
      'index' => 'disasters',
    ),
    'source' => array(
      'class' => '\RWAPIIndexer\Resources\Source',
      'type' => 'taxonomy_term',
      'index' => 'sources',
    ),

    // References.
    'career_categories' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'career_categories',
    ),
    'content_format' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'content_formats',
    ),
    'disaster_type' => array(
      'class' => '\RWAPIIndexer\Resources\DisasterType',
      'type' => 'taxonomy_term',
      'index' => 'disaster_types',
    ),
    'faq_category' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'faq_category',
    ),
    'feature' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'features',
    ),
    'job_type' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'job_types',
    ),
    'language' => array(
      'class' => '\RWAPIIndexer\Resources\Language',
      'type' => 'taxonomy_term',
      'index' => 'languages',
    ),
    'ocha_product' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'ocha_products',
    ),
    'organization_type' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'organization_types',
    ),
    /*'region' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'regions',
    ),*/
    'tags' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'tags',
    ),
    'theme' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'themes',
    ),
    'training_format' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'training_formats',
    ),
    'training_type' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'training_types',
    ),
    'vulnerable_groups' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'vulnerable_groups',
    ),
    'job_experience' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'job_experiences',
    ),
  );

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
  public static function getResourceHandler($bundle, Elasticsearch $elasticsearch, DatabaseConnection $connection, Processor $processor, References $references, Options $options) {
    if (!empty(static::$bundles[$bundle]['class']) && class_exists(static::$bundles[$bundle]['class'])) {
      $class = static::$bundles[$bundle]['class'];
      $index = static::$bundles[$bundle]['index'];
      $type = static::$bundles[$bundle]['type'];
      return new $class($bundle, $type, $index, $elasticsearch, $connection, $processor, $references, $options);
    }
    else {
      $bundles = implode(', ', array_keys(static::$bundles));
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
  public static function has($bundle) {
    return isset(static::$bundles[$bundle]);
  }

}
