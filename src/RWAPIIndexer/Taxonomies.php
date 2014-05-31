<?php

namespace RWAPIIndexer;

class Taxonomies extends \RWAPIIndexer\Processor {
  protected $taxonomies = array();

  /**
   * Load all taxonomies.
   */
  public function loadTaxonomies() {
    // Taxonomies definition.
    $taxonomies = array(
      'city' => array(),
      'job_type' => array(),
      'job_experience' => array(),
      'career_categories' => array(),
      'training_type' => array(),
      'training_format' => array(),
      'organization_type' => array(),
      'language' => array(),
      'feature' => array(),
      'theme' => array(),
      'vulnerable_groups' => array(),
      'content_format' => array(),
      'ocha_product' => array(),
      'disaster_type' => array(
        'query' => array(
          'field_joins' => array(
            'field_abbreviation' => array(
              'abbreviation' => 'value',
            ),
          ),
        ),
      ),
      'country' => array(
        'query' => array(
          'fields' => array(
            'description' => 'description',
          ),
          'field_joins' => array(
            'field_status' => array(
              'status' => 'value',
            ),
            'field_shortname' => array(
              'shortname' => 'value',
            ),
            'field_iso3' => array(
              'iso3' => 'value',
            ),
            'field_current_disaster' => array(
              'current' => 'value',
            ),
            'field_featured' => array(
              'featured' => 'value',
            ),
            'field_location' => array(
              'latitude' => 'lat',
              'longitude' => 'lon',
            ),
          ),
        ),
        'process' => array(
          'conversion' => array(
            'description' => 'html',
            'current' => 'bool',
            'featured' => 'bool',
            'latitude' => 'float',
            'longitude' => 'float',
          ),
        ),
      ),
      'disaster' => array(
        'query' => array(
          'fields' => array(
            'description' => 'description',
          ),
          'field_joins' => array(
            'field_status' => array(
              'status' => 'value',
            ),
            'field_disaster_date' => array(
              'date' => 'value',
            ),
            'field_glide' => array(
              'glide' => 'value',
            ),
            'field_current_disaster' => array(
              'current' => 'value',
            ),
            'field_featured' => array(
              'featured' => 'value',
            ),
            /*'field_published' => array(
              'published' => 'value',
            ),*/
            'field_primary_country' => array(
              'primary_country' => 'tid',
            ),
            'field_primary_disaster_type' => array(
              'primary_disaster_type' => 'tid',
            ),
            'field_country' => array(
              'country' => 'taxonomy_reference',
            ),
            'field_disaster_type' => array(
              'type' => 'taxonomy_reference',
            ),
          ),
        ),
        'process' => array(
          'conversion' => array(
            'description' => 'html',
            'date' => 'time',
            'current' => 'bool',
            'featured' => 'bool',
            'published' => 'bool',
            'latitude' => 'float',
            'longitude' => 'float',
          ),
          'reference' => array(
            'primary_country' => array(
              'country' => array('id', 'name', 'shortname', 'iso3'),//, 'latitude', 'longitude'),
            ),
            'primary_disaster_type' => array(
              'disaster_type' => array('id', 'name'),
            ),
            'country' => array(
              'country' => array('id', 'name', 'shortname', 'iso3'),
            ),
            'type' => array(
              'disaster_type' => array('id', 'name'),
            ),
          ),
        ),
      ),
      'source' => array(
        'query' => array(
          'fields' => array(
            'description' => 'description',
          ),
          'field_joins' => array(
            'field_status' => array(
              'status' => 'value',
            ),
            'field_shortname' => array(
              'shortname' => 'value',
            ),
            'field_longname' => array(
              'longname' => 'value',
            ),
            'field_organization_type' => array(
              'type' => 'tid',
            ),
            'field_homepage' => array(
              'homepage' => 'url',
            ),
            'field_country' => array(
              'country' => 'taxonomy_reference',
            ),
            'field_allowed_content_types' => array(
              'content_type' => 'multi_value',
            ),
          ),
        ),
        'process' => array(
          'conversion' => array(
            'description' => 'html',
            'content_type' => 'multi_int',
          ),
          'reference' => array(
            'type' => array(
              'organization_type' => array('id', 'name'),
            ),
            'country' => array(
              'country' => array('id', 'name', 'shortname', 'iso3'),
            ),
          ),
        ),
      ),
    );

    // Load taxonomies.
    foreach ($taxonomies as $vocabulary => $options) {
      $this->loadTaxonomyTerms($vocabulary, $options);
    }
  }

  /**
   * Load taxonomy terms belonging to the given vocabulary.
   *
   * @param string $vocabulary
   *   Vocabulary.
   * @param  array  $options
   *   Options to pass to the query processing function.
   */
  public function loadTaxonomyTerms($vocabulary, array $options = array()) {
    $entity_type = 'taxonomy_term';
    $base_table = 'taxonomy_term_data';
    $base_field = 'tid';

    // Prepare the base query.
    $query = db_select($base_table, $base_table);
    $query->innerJoin('taxonomy_vocabulary', 'taxonomy_vocabulary', "taxonomy_vocabulary.vid = {$base_table}.vid");
    $query->addField($base_table, $base_field, 'id');
    $query->addField($base_table, 'name', 'name');
    $query->condition('taxonomy_vocabulary.machine_name', $vocabulary, '=');
    $query->groupBy($base_table . '.' . $base_field);
    $query->orderBy($base_table . '.' . $base_field, 'ASC');

    $this->taxonomies[$vocabulary] = $this->processQuery($query, $entity_type, $base_table, $base_field, $options);
  }

  /**
   * Get a loaded taxonomy term with the specified fields.
   *
   * @param string $vocabulary
   *   Vocubulary the taxonomy term belongs to.
   * @param integer  $id
   *   ID of the taxonomy term.
   * @param array  $fields
   *   Fields of the taxonomy term to include.
   * @return array
   *   Taxonomy term.
   */
  public function getTaxonomyTerm($vocabulary, $id, $fields = array('id', 'name')) {
    if (isset($this->taxonomies[$vocabulary][$id])) {
      if (!empty($fields)) {
        return array_intersect_key($this->taxonomies[$vocabulary][$id], array_flip($fields));
      }
      return $this->taxonomies[$vocabulary][$id];
    }
    return NULL;
  }

  /**
   * Process a reference field of an entity item,
   * replacing the ID with the reference taxanomy term.
   *
   * @param array $definition
   *   Processing definition.
   * @param array $item
   *   Entity being processed.
   * @param string $key
   *   Reference field to process.
   * @param string $value
   *   String containing the list of taxonomy term IDs for the field.
   */
  public function processReference($definition, &$item, $key, $value) {
    $vocabulary = key($definition);

    if (isset($this->taxonomies[$vocabulary])) {
      $fields = $definition[$vocabulary];

      $array = array();
      foreach (explode('%%%', $value) as $id) {
        $term = $this->getTaxonomyTerm($vocabulary, $id, $fields);
        if (isset($term)) {
          $array[] = $term;
        }
      }

      $count = count($array);
      if ($count > 0) {
         $item[$key] = $count > 1 ? $array : $array[0];
      }
      else {
        unset($item[$key]);
      }
    }
  }
}
