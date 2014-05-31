<?php

namespace RWAPIIndexer;

class Processor {
  protected $markdown;

  public function __construct() {
    $this->markdown = function_exists('Markdown');
  }

  /**
   * Process a query.
   *
   * @param RWAPIIndexer\Database\Query $query
   *   Query object to process.
   * @param string $entity_type
   *   Entity type of the items to return.
   * @param string $base_table
   *   Base table of the query.
   * @param string $base_field
   *   Base field of the query (id field for the given entity type).
   * @param string $options
   *   Query options.
   * @return  array
   *   Items returned by the query.
   */
  public function processQuery($query, $entity_type, $base_table, $base_field, array $options = array()) {
    // Add the extra fields.
    if (isset($options['query']['fields'])) {
      foreach ($options['query']['fields'] as $alias => $field) {
        $query->addField($base_table, $field, $alias);
      }
    }

    // Add the joined fields.
    if (isset($options['query']['field_joins'])) {
      foreach ($options['query']['field_joins'] as $field_name => $values) {
        $field_table = 'field_data_' . $field_name;

        $condition = "{$field_table}.entity_id = {$base_table}.{$base_field} AND {$field_table}.entity_type = '{$entity_type}'";
        $query->leftJoin($field_table, $field_table, $condition);

        // Add the expressions.
        foreach ($values as $alias => $value) {
          switch ($value) {
            case 'taxonomy_reference':
              $expression = "GROUP_CONCAT(DISTINCT {$field_table}.{$field_name}_tid SEPARATOR '%%%')";
              $query->addExpression($expression, $alias);
              break;

            case 'multi_value':
              $expression = "GROUP_CONCAT(DISTINCT {$field_table}.{$field_name}_value SEPARATOR '%%%')";
              $query->addExpression($expression, $alias);
              break;

            case 'image_reference':
              $file_managed_table = 'file_managed_' . $field_name;
              $condition = "{$file_managed_table}.fid = {$field_table}.{$field_name}_fid";
              $query->leftJoin('file_managed', $file_managed_table, $condition);

              $expression = "GROUP_CONCAT(DISTINCT IF({$field_table}.{$field_name}_fid, CONCAT_WS('###',
                  {$field_table}.{$field_name}_fid,
                  IFNULL({$field_table}.{$field_name}_alt, ''),
                  IFNULL({$field_table}.{$field_name}_title, ''),
                  IFNULL({$field_table}.{$field_name}_width, ''),
                  IFNULL({$field_table}.{$field_name}_height, ''),
                  IFNULL({$file_managed_table}.uri, ''),
                  IFNULL({$file_managed_table}.filename, ''),
                  IFNULL({$file_managed_table}.filesize, '')
                ), NULL) SEPARATOR '%%%')";
              $query->addExpression($expression, $alias);
              break;

            case 'file_reference':
              $file_managed_table = 'file_managed_' . $field_name;
              $condition = "{$file_managed_table}.fid = {$field_table}.{$field_name}_fid";
              $query->leftJoin('file_managed', $file_managed_table, $condition);

              $expression = "GROUP_CONCAT(DISTINCT IF({$field_table}.{$field_name}_fid, CONCAT_WS('###',
                  {$field_table}.{$field_name}_fid,
                  IFNULL({$field_table}.{$field_name}_description, ''),
                  IFNULL({$file_managed_table}.uri, ''),
                  IFNULL({$file_managed_table}.filename, ''),
                  IFNULL({$file_managed_table}.filesize, '')
                ), NULL) SEPARATOR '%%%')";
              $query->addExpression($expression, $alias);
              break;

            default:
              $query->addField($field_table, $field_name . '_' . $value, $alias);
          }
        }
      }
    }

    // Get the items.
    $items = $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    // Post process the items.
    $this->processItems($items, $options);

    return $items;
  }

  /**
   * Process items (conversion and reference field replacement).
   *
   * @param array $items
   *   Entity items to process.
   * @param array  $options
   *   Processing options.
   */
  public function processItems(&$items, array $options = array()) {
    // Process the returned items;
    foreach ($items as $id => &$item) {
      $item['id'] = (int) $item['id'];

      foreach ($item as $key => $value) {
        // Remove NULL properties.
        if (!isset($value) || $value === '') {
          unset($item[$key]);
        }
        // Convert values.
        elseif (isset($options['process']['conversion'][$key])) {
          $this->processConversion($options['process']['conversion'][$key], $item, $key, $value);
        }
        // Get reference taxonomy terms.
        elseif (isset($options['process']['reference'][$key])) {
          $this->processReference($options['process']['reference'][$key], $item, $key, $value);
        }
      }
    }
  }

  /**
   * Convert the value of a field of an entity item.
   *
   * @param array $definition
   *   Processing definition.
   * @param array $item
   *   Entity item being processed.
   * @param string $key
   *   Field to process.
   * @param string $value
   *   Field value to convert.
   */
  public function processConversion($definition, &$item, $key, $value) {
    switch ($definition) {
      case 'bool':
        $item[$key] = (bool) $value;
        break;

      case 'int':
        $item[$key] = (int) $value;
        break;

      case 'float':
        $item[$key] = (float) $value;
        break;

      case 'time':
        $item[$key] = $value * 1000;
        break;

      case 'html':
        // Absolute links.
        $item[$key] = preg_replace('/(\]\(\/?)(?!http:\/\/)/', '](http://reliefweb.int/', $value);
        if (!empty($this->markdown)) {
          $item[$key . '-html'] = Markdown($value);
        }
        break;

      case 'multi_int':
        $values = array();
        foreach (explode('%%%', $value) as $data) {
          $values[] = (int) $data;
        }
        $item[$key] = $values;
        break;
    }
  }

  /**
   * Process a reference field of an entity item,
   * replacing the ID with the reference taxanomy term.
   *
   * @param array $definition
   *   Processing definition.
   * @param array $item
   *   Entity item being processed.
   * @param string $key
   *   Reference field to process.
   * @param string $value
   *   String containing the list of taxonomy term IDs for the field.
   */
  public function processReference($definition, &$item, $key, $value) {
  }
}
