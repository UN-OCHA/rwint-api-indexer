<?php

namespace RWAPIIndexer;

/**
 * Mapping generation class.
 */
class Mapping {

  /**
   * Mapping.
   *
   * @var array
   */
  private $mapping = [
    'timestamp' => ['type' => 'date', 'store' => TRUE, 'index' => FALSE],
  ];

  /**
   * Add an integer field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param string $alias
   *   Field index alias.
   *
   * @return \RWAPIIndexer\Bundles
   *   This Mapping instance.
   */
  public function addInteger($field, $alias = '') {
    $this->addFieldMapping($field, ['type' => 'integer'], $alias);
    return $this;
  }

  /**
   * Add a float field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param string $alias
   *   Field index alias.
   *
   * @return \RWAPIIndexer\Bundles
   *   This Mapping instance.
   */
  public function addFloat($field, $alias = '') {
    $this->addFieldMapping($field, ['type' => 'float'], $alias);
    return $this;
  }

  /**
   * Add a boolean field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param string $alias
   *   Field index alias.
   *
   * @return \RWAPIIndexer\Bundles
   *   This Mapping instance.
   */
  public function addBoolean($field, $alias = '') {
    $this->addFieldMapping($field, ['type' => 'boolean'], $alias);
    return $this;
  }

  /**
   * Add a geo point field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param string $alias
   *   Field index alias.
   *
   * @return \RWAPIIndexer\Bundles
   *   This Mapping instance.
   */
  public function addGeoPoint($field, $alias = '') {
    $this->addFieldMapping($field, ['type' => 'geo_point'], $alias);
    return $this;
  }

  /**
   * Add the status field definition to the mapping.
   *
   * @return \RWAPIIndexer\Bundles
   *   This Mapping instance.
   */
  public function addStatus() {
    $this->addFieldMapping('status', [
      'type' => 'keyword',
      'normalizer' => 'status',
    ]);
    return $this;
  }

  /**
   * Add a string field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param bool $index
   *   Indicates whether the field should be indexed and analyzed or not:
   *   TRUE = indexed and analyzed, FALSE = not analyzed, NULL = not indexed.
   * @param bool $exact
   *   Indicates if the string should have an exact not analyzed sufield.
   * @param string $alias
   *   Field index alias.
   * @param bool $suggest
   *   Whether to also index the string as a suggestion for autocomplete or not.
   *
   * @return \RWAPIIndexer\Bundles
   *   This Mapping instance.
   */
  public function addString($field, $index = TRUE, $exact = FALSE, $alias = '', $suggest = FALSE) {
    $mapping = [
      'type' => 'text',
    ];
    if ($index === NULL) {
      $mapping['index'] = FALSE;
    }
    elseif ($index) {
      $mapping['norms'] = FALSE;
      // @todo Disable shingle, disable stop words in analyzer settings.
      // @todo $mapping['index_prefixes'] = true;
      // @todo $mapping['index_phrases'] = true;
    }
    else {
      $mapping['type'] = 'keyword';
    }
    if ($exact) {
      $mapping['fields']['exact'] = [
        'type' => 'keyword',
      ];
    }
    if ($suggest) {
      $mapping['fields']['suggest'] = [
        'type' => 'search_as_you_type',
        'analyzer' => 'search_as_you_type',
        'search_analyzer' => 'search_as_you_type',
      ];
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
   *
   * @return \RWAPIIndexer\Bundles
   *   This Mapping instance.
   */
  public function addDates($field, array $subfields = [], $alias = '') {
    $properties = [];
    // Add subfields.
    foreach ($subfields as $subfield) {
      $properties[$subfield] = ['type' => 'date'];
    }
    // Default when using base field.
    $properties[$subfields[0]]['copy_to'] = $this->getCommonField($field, 'date');

    $this->addFieldMapping($field, ['properties' => $properties], $alias);
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
   *
   * @return \RWAPIIndexer\Bundles
   *   This Mapping instance.
   */
  public function addTaxonomy($field, array $subfields = [], $alias = '') {
    $properties = [
      'id' => ['type' => 'integer'],
    ];

    $subfields = array_merge(['name'], $subfields);
    foreach ($subfields as $subfield) {
      $properties[$subfield] = $this->getMultiFieldMapping();
      // Copy the field to the common field (default of the base field).
      $properties[$subfield]['copy_to'] = $this->getCommonField($field);
    }
    $this->addFieldMapping($field, ['properties' => $properties], $alias);
    return $this;
  }

  /**
   * Add a river search field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param string $alias
   *   Field index alias.
   *
   * @return \RWAPIIndexer\Bundles
   *   This Mapping instance.
   */
  public function addRiverSearch($field, $alias = '') {
    $properties = [
      'id' => ['type' => 'keyword'],
      'url' => ['type' => 'keyword'],
      'title' => ['type' => 'text', 'norms' => FALSE],
      'override' => ['type' => 'integer'],
    ];
    $this->addFieldMapping($field, ['properties' => $properties], $alias);
    return $this;
  }

  /**
   * Add a file field definition to the mapping.
   *
   * @param string $field
   *   Field Name.
   * @param string $alias
   *   Field index alias.
   *
   * @return \RWAPIIndexer\Bundles
   *   This Mapping instance.
   */
  public function addImage($field, $alias = '') {
    $mapping = [
      'properties' => [
        'id' => ['type' => 'integer'],
        'mimetype' => ['type' => 'keyword'],
        'filename' => ['type' => 'keyword'],
        'filesize' => ['type' => 'integer'],
        'caption' => ['type' => 'text', 'norms' => FALSE],
        'copyright' => ['type' => 'text', 'norms' => FALSE],
        'url' => ['type' => 'keyword'],
        'url-large' => ['type' => 'text', 'index' => FALSE],
        'url-small' => ['type' => 'text', 'index' => FALSE],
        'url-thumb' => ['type' => 'text', 'index' => FALSE],
        'width' => ['type' => 'integer'],
        'height' => ['type' => 'integer'],
      ],
    ];
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
   *
   * @return \RWAPIIndexer\Bundles
   *   This Mapping instance.
   */
  public function addFile($field, $alias = '') {
    $mapping = [
      'properties' => [
        'id' => ['type' => 'integer'],
        'mimetype' => ['type' => 'keyword'],
        'filename' => ['type' => 'keyword'],
        'filesize' => ['type' => 'integer'],
        'description' => ['type' => 'text', 'norms' => FALSE],
        'url' => ['type' => 'keyword'],
        'preview' => [
          'properties' => [
            'url' => ['type' => 'keyword'],
            'url-large' => ['type' => 'text', 'index' => FALSE],
            'url-small' => ['type' => 'text', 'index' => FALSE],
            'url-thumb' => ['type' => 'text', 'index' => FALSE],
            'version' => ['type' => 'keyword'],
          ],
        ],
      ],
    ];
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
    return [
      'type' => 'text',
      'norms' => FALSE,
      'fields' => [
        'exact' => [
          'type' => 'keyword',
        ],
      ],
    ];
  }

  /**
   * Get common fields, creating their mappings if they don't exist.
   *
   * @param string $field
   *   Base field.
   * @param string $type
   *   Type of the common field.
   * @param bool $exact
   *   Indicates if an exact (not analyzed) field should also be created.
   *
   * @return array
   *   Common fields.
   */
  private function getCommonField($field, $type = 'string', $exact = TRUE) {
    // Common field name.
    $name = 'common_' . str_replace('.', '_', $field);

    // Common fields.
    $fields = [$name];

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
        $this->addFieldMapping($name, ['type' => $type], $field);
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
  private function addFieldMapping($field, array $mapping, $alias = '') {
    $path = explode('.', $field);
    $field = array_shift($path);

    if (!isset($this->mapping[$field])) {
      $this->mapping[$field] = [];
    }
    $parent = &$this->mapping[$field];

    foreach ($path as $field) {
      if (!isset($parent['properties'][$field])) {
        $parent['properties'][$field] = [];
      }
      $parent = &$parent['properties'][$field];
    }

    $parent += $mapping;
  }

}
