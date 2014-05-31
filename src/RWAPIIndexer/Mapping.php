<?php

namespace RWAPIIndexer;

/**
 * Mapping generation class.
 */
class Mapping {
  private $mapping;

  public function __construct() {
    $this->mapping = array();
  }

  /**
   * Add an integer field definition to the mapping.
   * @param string $field
   *   Field Name.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addInteger($field) {
    $this->addFieldMapping($field, array('type' => 'integer'));
    return $this;
  }

  /**
   * Add a float field definition to the mapping.
   * @param string $field
   *   Field Name.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addFloat($field) {
    $this->addFieldMapping($field, array('type' => 'float'));
    return $this;
  }

  /**
   * Add a boolean field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addBoolean($field) {
    $this->addFieldMapping($field, array('type' => 'boolean'));
    return $this;
  }

  /**
   * Add a string field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param boolean $field
   *   Indicates whether indexing or not and if analyzed.
   * @param boolean $exact
   *   Indicates if the string should have an exact not analyzed sufield.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addString($field, $index = TRUE, $exact = FALSE) {
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
    $this->addFieldMapping($field, $mapping);
    return $this;
  }

  /**
   * Add date fields definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param array $subfields
   *   Date subfields.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addDates($field, $subfields = array()) {
    $properties = array();
    // Add subfields.
    foreach ($subfields as $key => $subfield) {
      $properties[$subfield] =  array('type' => 'date');
    }
    // Default when using base field.
    $properties[$subfields[0]]['copy_to'] = $this->getCommonField($field, 'date');

    $this->addFieldMapping($field, array('properties' => $properties));
    return $this;
  }

  /**
   * Add a taxonomy field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param array $subfields
   *   Taxonomy name subfields.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addTaxonomy($field, $subfields = array()) {
    $properties = array(
      'id' => array('type' => 'integer'),
    );

    $subfields = array_merge(array('name'), $subfields);
    foreach ($subfields as $subfield) {
      $properties[$subfield] = $this->getMultiFieldMapping();
      // Copy the field to the common field (default of the base field).
      $properties[$subfield]['copy_to'] = $this->getCommonField($field);
    }
    $this->addFieldMapping($field, array('properties' => $properties));
    return $this;
  }

  /**
   * Add a file field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addFile($field) {
    $mapping = array(
      'properties' => array(
        'id' => array('type' => 'integer'),
        'mimetype' => array('type' => 'string', 'index' => 'not_analyzed'),
        'filename' => array('type' => 'string', 'index' => 'not_analyzed'),
        'caption' => array('type' => 'string', 'norms' => array('enabled' => FALSE)),
        'copyright' => array('type' => 'string'),
        'url' => array('type' => 'string', 'index' => 'not_analyzed'),
        'url-large' => array('type' => 'string', 'index' => 'no'),
        'url-small' => array('type' => 'string', 'index' => 'no'),
        'url-thumb' => array('type' => 'string', 'index' => 'no'),
      ),
    );
    $this->addFieldMapping($field, $mapping);
    return $this;
  }

  /**
   * Add a file field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @return RWAPIIndexer\Mapping
   *   This Mapping instance.
   */
  public function addImage($field) {
    $mapping = array(
      'properties' => array(
        'id' => array('type' => 'integer'),
        'mimetype' => array('type' => 'string', 'index' => 'not_analyzed'),
        'filename' => array('type' => 'string', 'index' => 'not_analyzed'),
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
    $this->addFieldMapping($field, $mapping);
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
   * Get common multifield name
   * and create mapping for common field if it doesn't exist.
   *
   * @param  string $field
   *   Base field.
   * @param  string $type
   *   Type of the common field.
   * @return string
   *   Common field name.
   */
  private function getCommonField($field, $type = 'string') {
    $name = 'common_' . str_replace('.', '_', $field);
    // Create the common field mapping if doesn't exist.
    if (!isset($this->mapping[$name])) {
      if ($type === 'string') {
        $mapping = $this->getMultiFieldMapping();
      }
      else {
        $mapping = array('type' => $type);
      }
      $mapping['index_name'] = $field;
      $this->addFieldMapping($name, $mapping, $field);
    }
    return $name;
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
    if (!empty($alias)) {
      $parent['index_name'] = $alias;
    }
  }
}
