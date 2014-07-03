<?php

namespace RWAPIIndexer;

/**
 * Bundles class manager.
 */
class Bundles {
  // List of Resources entity bundles and their corresponding class.
  public static $bundles = array(
    'report' => '\RWAPIIndexer\Resources\Report',
    'job' => '\RWAPIIndexer\Resources\Job',
    'training' => '\RWAPIIndexer\Resources\Training',
    'country' => '\RWAPIIndexer\Resources\Country',
    'disaster' => '\RWAPIIndexer\Resources\Disaster',
    'source' => '\RWAPIIndexer\Resources\Source',

    // References.
    'career_categories' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'city' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'content_format' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'disaster_type' => '\RWAPIIndexer\Resources\DisasterType',
    'feature' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'job_type' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'language' => '\RWAPIIndexer\Resources\Language',
    'ocha_product' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'organization_type' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    //'region' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    //'tags' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'theme' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'training_format' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'training_type' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'vulnerable_groups' => '\RWAPIIndexer\Resources\TaxonomyDefault',
    'job_experience' => '\RWAPIIndexer\Resources\TaxonomyDefault',
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
    if (isset(self::$bundles[$bundle])) {
      return new self::$bundles[$bundle]($bundle, $elasticsearch, $connection, $processor, $references, $options);
    }
    else {
      $bundles = implode(', ', array_keys(self::$bundles));
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
    return isset(self::$bundles[$bundle]);
  }
}
