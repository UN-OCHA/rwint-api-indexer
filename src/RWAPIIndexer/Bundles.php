<?php

namespace RWAPIIndexer;

/**
 * Bundles class manager.
 */
class Bundles {
  // List of Resources entity bundles and their corresponding class.
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
    /*'tags' => array(
      'class' => '\RWAPIIndexer\Resources\TaxonomyDefault',
      'type' => 'taxonomy_term',
      'index' => 'tags',
    ),*/
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
   * @return \RWAPIIndexer\Resource
   *   Resource handler for the given bundle.
   */
  public static function getResourceHandler($bundle, $elasticsearch, $connection, $processor, $references, $options) {
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
   * @param string  $bundle
   *   Bundle to check.
   * @return boolean
   *   Return TRUE if the bundle exists.
   */
  public static function has($bundle) {
    return isset(static::$bundles[$bundle]);
  }
}
