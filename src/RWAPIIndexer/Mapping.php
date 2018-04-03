<?php

namespace RWAPIIndexer;

/**
 * Mapping generation class.
 */
class Mapping {
  // Mapping.
  private $mapping = array(
    'timestamp' => array('type' => 'date', 'store' => TRUE, 'index' => 'no'),
  );

  /**
   * Add an integer field definition to the mapping.
   * @param string $field
   *   Field Name.
   * @param string $alias
   *   Field index alias.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addInteger($field, $alias = '') {
    $this->addFieldMapping($field, array('type' => 'integer'), $alias);
    return $this;
  }

  /**
   * Add a float field definition to the mapping.
   * @param string $field
   *   Field Name.
   * @param string $alias
   *   Field index alias.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addFloat($field, $alias = '') {
    $this->addFieldMapping($field, array('type' => 'float'), $alias);
    return $this;
  }

  /**
   * Add a boolean field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param string $alias
   *   Field index alias.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addBoolean($field, $alias = '') {
    $this->addFieldMapping($field, array('type' => 'boolean'), $alias);
    return $this;
  }

  /**
   * Add a geo point field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param string $alias
   *   Field index alias.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addGeoPoint($field, $alias = '') {
    $this->addFieldMapping($field, array('type' => 'geo_point'), $alias);
    return $this;
  }

  /**
   * Add a string field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param boolean $index
   *   Indicates whether the field should be indexed and analyzed or not:
   *   TRUE = indexed and analyzed, FALSE = not analyzed, NULL = not indexed.
   * @param boolean $exact
   *   Indicates if the string should have an exact not analyzed sufield.
   * @param string $alias
   *   Field index alias.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addString($field, $index = TRUE, $exact = FALSE, $alias = '') {
    $mapping = array(
      'type' => 'string',
    );
    if ($index === NULL) {
      $mapping['index'] = 'no';
    }
    elseif ($index) {
      $mapping['norms'] = array('enabled' => FALSE);
    }
    else {
      $mapping['index'] = 'not_analyzed';
    }
    if ($exact) {
      $mapping['fields'] = array(
        'exact' => array(
          'type' => 'string',
          'index' => 'not_analyzed',
        ),
      );
    }
    $this->addFieldMapping($field, $mapping, $alias);
    return $this;
  }

  /**
   * Add date fields definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param array $subfields
   *   Date subfields.
   * @param string $alias
   *   Field index alias.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addDates($field, $subfields = array(), $alias = '') {
    $properties = array();
    // Add subfields.
    foreach ($subfields as $key => $subfield) {
      $properties[$subfield] =  array('type' => 'date');
    }
    // Default when using base field.
    $properties[$subfields[0]]['copy_to'] = $this->getCommonField($field, 'date');

    $this->addFieldMapping($field, array('properties' => $properties), $alias);
    return $this;
  }

  /**
   * Add a taxonomy field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param array $subfields
   *   Taxonomy name subfields.
   * @param string $alias
   *   Field index alias.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addTaxonomy($field, $subfields = array(), $alias = '') {
    $properties = array(
      'id' => array('type' => 'integer'),
    );

    $subfields = array_merge(array('name'), $subfields);
    foreach ($subfields as $subfield) {
      $properties[$subfield] = $this->getMultiFieldMapping();
      // Copy the field to the common field (default of the base field).
      $properties[$subfield]['copy_to'] = $this->getCommonField($field);
    }
    $this->addFieldMapping($field, array('properties' => $properties), $alias);
    return $this;
  }

  /**
   * Add a file field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param string $alias
   *   Field index alias.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addImage($field, $alias = '') {
    $mapping = array(
      'properties' => array(
        'id' => array('type' => 'integer'),
        'mimetype' => array('type' => 'string', 'index' => 'not_analyzed'),
        'filename' => array('type' => 'string', 'index' => 'not_analyzed'),
        'filesize' => array('type' => 'integer'),
        'caption' => array('type' => 'string', 'norms' => array('enabled' => FALSE)),
        'copyright' => array('type' => 'string'),
        'url' => array('type' => 'string', 'index' => 'not_analyzed'),
        'url-large' => array('type' => 'string', 'index' => 'no'),
        'url-small' => array('type' => 'string', 'index' => 'no'),
        'url-thumb' => array('type' => 'string', 'index' => 'no'),
        'width' => array('type' => 'integer'),
        'height' => array('type' => 'integer'),
      ),
    );
    $this->addFieldMapping($field, $mapping, $alias);
    return $this;
  }

  /**
   * Add a file field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param string $alias
   *   Field index alias.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addFile($field, $alias = '') {
    $mapping = array(
      'properties' => array(
        'id' => array('type' => 'integer'),
        'mimetype' => array('type' => 'string', 'index' => 'not_analyzed'),
        'filename' => array('type' => 'string', 'index' => 'not_analyzed'),
        'filesize' => array('type' => 'integer'),
        'description' => array('type' => 'string', 'norms' => array('enabled' => FALSE)),
        'url' => array('type' => 'string', 'index' => 'not_analyzed'),
        'preview' => array(
          'properties' => array(
            'url' => array('type' => 'string', 'index' => 'not_analyzed'),
            'url-large' => array('type' => 'string', 'index' => 'no'),
            'url-small' => array('type' => 'string', 'index' => 'no'),
            'url-thumb' => array('type' => 'string', 'index' => 'no'),
          ),
        ),
      ),
    );
    $this->addFieldMapping($field, $mapping, $alias);
    return $this;
  }

  /**
   * Add a taxonomy term profile mapping.
   *
   * @param array $sections
   *   Definition of the profile sections.
   */
  public function addProfile(array $sections) {
    // Only index the overview.
    $this->addString('profile.overview');
    $this->addString('profile.overview-html', NULL);

    // Add the sections.
    foreach ($sections as $id => $info) {
      $base = 'profile.' . $id;
      $image_field = !empty($info['internal']) ? 'cover' : 'logo';

      // Mapping for the active links.
      $this->addString($base . '.title', NULL)
              ->addString($base . '.active.url', NULL)
              ->addString($base . '.active.title', NULL)
              ->addString($base . '.active.' . $image_field, NULL);

      // Add the mapping for the archived links.
      if (!empty($info['archives'])) {
        $this->addString($base . '.archive.url', NULL)
                ->addString($base . '.archive.title', NULL)
                ->addString($base . '.archive.' . $image_field, NULL);
      }
    }
    return $this;
  }

  /**
   * Export the mapping.
   *
   * @return array
   *   Mapping.
   */
  public function export() {
    return $this->mapping;
  }

  /**
   * Multifield mapping definition.
   *
   * @return array
   *   Mulitfield Mapping.
   */
  private function getMultiFieldMapping() {
    return array(
      'type' => 'string',
      'norms' => array('enabled' => FALSE),
      'fields' => array(
        'exact' => array(
          'type' => 'string',
          'index' => 'not_analyzed',
        ),
      ),
    );
  }

  /**
   * Get common fields, creating their mappings if they don't exist.
   *
   * @param  string $field
   *   Base field.
   * @param  string $type
   *   Type of the common field.
   * @param  boolean $exact
   *   Indicates if an exact (not analyzed) field should also be created.
   * @return array
   *   Common fields.
   */
  private function getCommonField($field, $type = 'string', $exact = TRUE) {
    // Common field name.
    $name = 'common_' . str_replace('.', '_', $field);

    // Common fields.
    $fields = array($name);

    // Create the common field mapping if doesn't exist.
    if (!isset($this->mapping[$name])) {
      if ($type === 'string') {
        $this->addString($name, TRUE, FALSE, $field);

        // Exact field.
        if ($exact) {
          $this->addString($name . '_exact', FALSE, FALSE, $field . '.exact');
        }
      }
      else {
        $this->addFieldMapping($name, array('type' => $type), $field);
      }
    }

    // Add the exact field to the list of common fields to copy to.
    if ($type === 'string' && $exact) {
      $fields[] = $name . '_exact';
    }

    return $fields;
  }

  /**
   * Add a field mapping to the mapping.
   *
   * @param string $field
   *   Field to add to the mapping.
   * @param array $mapping
   *   Field mapping.
   * @param string $alias
   *   Field index alias.
   */
  private function addFieldMapping($field, $mapping, $alias = '') {
    $path = explode('.', $field);
    $field = array_shift($path);

    if (!isset($this->mapping[$field])) {
      $this->mapping[$field] = array();
    }
    $parent = &$this->mapping[$field];

    foreach ($path as $field) {
      if (!isset($parent['properties'][$field])) {
        $parent['properties'][$field] = array();
      }
      $parent = &$parent['properties'][$field];
    }

    $parent += $mapping;

    // Deprecated in ES 2.x.
    /*if (!empty($alias)) {
      $parent['index_name'] = $alias;
    }*/
  }
}
