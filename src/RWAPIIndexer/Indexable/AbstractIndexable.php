<?php

namespace RWAPIIndexer\Indexable;

/**
 * Base indexable class.
 */
abstract class AbstractIndexable {
  // Elasticsearch index type and name.
  protected $index_type = '';
  protected $index_name = '';

  // Default mimetypes.
  protected $mimeTypes = array(
    'txt' => 'text/plain',
    'htm' => 'text/html',
    'html' => 'text/html',
    'php' => 'text/html',
    'css' => 'text/css',
    'js' => 'application/javascript',
    'json' => 'application/json',
    'xml' => 'application/xml',
    'swf' => 'application/x-shockwave-flash',
    'flv' => 'video/x-flv',
    // images.
    'png' => 'image/png',
    'jpe' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'jpg' => 'image/jpeg',
    'gif' => 'image/gif',
    'bmp' => 'image/bmp',
    'ico' => 'image/vnd.microsoft.icon',
    'tiff' => 'image/tiff',
    'tif' => 'image/tiff',
    'svg' => 'image/svg+xml',
    'svgz' => 'image/svg+xml',
    // archives.
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed',
    'exe' => 'application/x-msdownload',
    'msi' => 'application/x-msdownload',
    'cab' => 'application/vnd.ms-cab-compressed',
    // audio/video.
    'mp3' => 'audio/mpeg',
    'qt' => 'video/quicktime',
    'mov' => 'video/quicktime',
    // adobe.
    'pdf' => 'application/pdf',
    'psd' => 'image/vnd.adobe.photoshop',
    'ai' => 'application/postscript',
    'eps' => 'application/postscript',
    'ps' => 'application/postscript',
    // ms office.
    'doc' => 'application/msword',
    'rtf' => 'application/rtf',
    'xls' => 'application/vnd.ms-excel',
    'ppt' => 'application/vnd.ms-powerpoint',
    // open office.
    'odt' => 'application/vnd.oasis.opendocument.text',
    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
  );

  // Loaded taxonomy terms.
  public $taxonomies = array();

  // Base public scheme for URLs.
  private $public_scheme_url = '';

  /**
   * Construct the indexable based on the given website.
   */
  public function __construct($website) {
    $this->public_scheme_url = $website . 'sites/reliefweb.int/files/'
  }

  /**
   * Set the mapping of the index type if it doesn't already exist.
   */
  public function setMapping() {
    return array();
  }

  /**
   * Run a query.
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

    // DEBUG
    if ($entity_type === 'node') {
      if (method_exists($query, 'preExecute')) {
        $query->preExecute();
      }
      $sql = (string) $query;
      $quoted = array();
      $connection = Database::getConnection();
      foreach ((array) $query->arguments() as $key => $val) {
        $quoted[$key] = $connection->quote($val);
      }
      $sql = strtr($sql, $quoted);
      watchdog('query', $sql);
    }

    // Get the items.
    $items = $query->execute()->fetchAllAssoc('id', PDO::FETCH_ASSOC);

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
          switch ($options['process']['conversion'][$key]) {
            case 'bool':
              $item[$key] = (bool) $item[$key];
              break;

            case 'int':
              $item[$key] = (int) $item[$key];
              break;

            case 'float':
              $item[$key] = (float) $item[$key];
              break;

            case 'time':
              $item[$key] = $item[$key] * 1000;
              break;

            case 'html':
              if ($this->markdown) {
                $item[$key . '-html'] = Markdown($item[$key]);
              }
              break;
          }
        }
        // Get reference taxonomy terms.
        elseif (isset($options['process']['reference'][$key])) {
          $vocabulary = key($options['process']['reference'][$key]);
          if (isset($this->taxonomies[$vocabulary])) {
            $fields = $options['process']['reference'][$key][$vocabulary];

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
    }

    return $items;
  }

  public function getTaxonomyTerm($vocabulary, $id, $fields = array('id', 'name')) {
    if (isset($this->taxonomies[$vocabulary][$id])) {
      if (!empty($fields)) {
        return array_intersect_key($this->taxonomies[$vocabulary][$id], array_flip($fields));
      }
      return $this->taxonomies[$vocabulary][$id];
    }
    return NULL;
  }

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

  public function loadTaxonomies() {
    $taxnomies = array(
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
            'field_published' => array(
              'published' => 'value',
            ),
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
              'country' => array('id', 'name', 'shortname', 'iso3', 'latitude', 'longitude'),
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
          'field_joins' => array(
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
          ),
        ),
        'process' => array(
          'reference' => array(
            'type' => array(
              'organization_type' => array('id', 'name'),
            ),
          ),
        ),
      ),
    );

    foreach ($taxnomies as $vocabulary => $options) {
      $this->loadTaxonomyTerms($vocabulary, $options);
    }
  }

  /**
   * Format an image field.
   */
  public function handleImageField(&$field) {
    if (isset($field) && !empty($field)) {
      $items = array();
      foreach (explode('%%%', $field) as $item) {
        $parts = explode('###', $item);

        $array = array(
          'id' => $parts[0],
          'copyright' => preg_replace('/^@+/', '', $parts[1]),
          'caption' => $parts[2],
          'width' => $parts[3],
          'height' => $parts[4],
          'url' => str_replace('public://', $this->public_scheme_url, $parts[5]),
          'url-large' => str_replace('public://', $this->public_scheme_url . 'styles/attachment-large/public/', $parts[5]),
          'url-small' => str_replace('public://', $this->public_scheme_url . 'styles/attachment-small/public/', $parts[5]),
          'url-thumb' => str_replace('public://', $this->public_scheme_url . 'styles/m/public/', $parts[5]),
          'filename' => $parts[6],
          'filemime' => $this->getMimeType($parts[6]),
          'filesize' => $parts[7],
        );

        foreach ($array as $key => $value) {
          if (empty($value)) {
            unset($key);
          }
        }

        $items[] = $array;
      }
      $count = count($items);
      if ($count > 0) {
        $field = $count > 1 ? $items : $items[0];
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Format a file field.
   */
  public function handleFileField(&$field) {
    if (isset($field) && !empty($field)) {
      $items = array();
      foreach (explode('%%%', $field) as $item) {
        $parts = explode('###', $item);

        $array = array(
          'id' => $parts[0],
          'description' => preg_replace('/\|(\d+)\|(0|90|-90)$/', '', $parts[1]),
          'url' => str_replace('public://', $this->public_scheme_url, $parts[2]),
          'filename' => $parts[3],
          'filemime' => $this->getMimeType($parts[3]),
          'filesize' => $parts[4],
        );

        // PDF attachment.
        if ($array['filemime'] === 'application/pdf' && preg_match('/\|(\d+)\|(0|90|-90)$/', $parts[1]) === 1) {
          $directory = dirname($parts[2]) . '-pdf-previews';
          $filename = basename(urldecode($parts[3]), '.pdf');
          if (module_exists('transliteration')) {
            $filename = transliteration_clean_filename($filename);
          }
          $filename = $directory . '/' . $parts[0] . '-' . $filename . '.png';
          $array['preview'] = array(
            'url' =>  str_replace('public://', $this->public_scheme_url, $filename),
            'url-large' => str_replace('public://', $this->public_scheme_url . 'styles/attachment-large/public/', $filename),
            'url-small' => str_replace('public://', $this->public_scheme_url . 'styles/attachment-small/public/', $filename),
            'url-thumb' => str_replace('public://', $this->public_scheme_url . 'styles/m/public/', $filename),
          );
        }

        foreach ($array as $key => $value) {
          if (empty($value)) {
            unset($key);
          }
        }

        $items[] = $array;
      }
      $count = count($items);
      if ($count > 0) {
        $field = $count > 1 ? $items : $items[0];
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Return a file mime type.
   */
  public function getMimeType($filename) {
    $extension = explode('.', $filename);
    $extension = array_pop($extension);
    $extension = strtolower($extension);
    return isset($this->mimeTypes[$extension]) ? $this->mimeTypes[$extension] : 'application/octet-stream';
  }

  public function indexItems($items) {

  }

  public function getItems($limit, $offset) {
    return array();
  }
}
