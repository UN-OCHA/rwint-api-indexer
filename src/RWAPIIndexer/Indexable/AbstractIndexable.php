<?php

namespace RWAPIIndexer\Indexable;

/**
 * Base indexable class.
 */
abstract class AbstractIndexable {
  // Elasticsearch index type and name.
  protected $entity_type = '';
  protected $entity_bundle = '';

  // Global options.
  protected $options = NULL;

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

  // Whether or not Markdown is available.
  private $markdown = FALSE;

  /**
   * Construct the indexable based on the given website.
   */
  public function __construct($options) {
    $this->options = $options;
    $this->public_scheme_url = $options['website'] . '/sites/reliefweb.int/files/';
    $this->markdown = function_exists('Markdown');
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

            case 'multi_int':
              $values = array();
              foreach (explode('%%%', $item[$key]) as $data) {
                $values[] = (int) $data;
              }
              $item[$key] = $values;
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
          /*if (module_exists('transliteration')) {
            $filename = transliteration_clean_filename($filename);
          }*/
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

  /**
   * Get the maximum number of items to index.
   */
  public function getLimit() {
    if ($this->entity_type === 'node') {
      $query = db_select('node', 'node');
      $query->condition('node.type', $this->entity_bundle);
      $query->count();
      $count = (int) $query->execute()->fetchField();
    }
    else {
      $count = count($this->taxonomies[$this->entity_bundle]);
    }
    $limit = $this->options['limit'];
    return $limit <= 0 ? $count : min($limit, $count);
  }

  /**
   * Get the offset from which to start the indexing.
   */
  public function getOffset() {
    $offset = $this->options['offset'];
    if ($offset <= 0) {
      if ($this->entity_type === 'node') {
        $query = db_select('node', 'node');
        $query->addField('node', 'nid', 'id');
        $query->condition('node.type', $this->entity_bundle);
        $query->orderBy('node.nid', 'DESC');
        $query->range(0, 1);
        $offset = (int) $query->execute()->fetchField();
      }
      else {
        $taxonomy = &$this->taxonomies[$this->entity_bundle];
        end($taxonomy);
        $offset = key($taxonomy);
        reset($taxonomy);
      }
    }
    return $offset;
  }

  /**
   * Get entities to index.
   */
  public function getItems($limit, $offset) {
    return array();
  }

  /**
   * Bulk index the given items.
   */
  public function indexItems(&$items) {
    $data = '';

    $index = $this->options['database'] . '_' . $this->entity_type;
    $type = $this->entity_bundle;
    $path = "{$index}/{$type}/_bulk";

    // Get last id.
    end($items);
    $offset = key($items) - 1;
    reset($items);

    // Prepare bulk indexing.
    foreach ($items as &$item) {
      // Add the document to the bulk indexing data.
      $action = array('index' => array(
        '_index' => $index,
        '_type' => $type,
        '_id' => $item['id'],
      ));

      $data .= json_encode($action, JSON_FORCE_OBJECT) . "\n";
      $data .= json_encode($item) . "\n";
    }

    // Bulk index the documents.
    $this->request('POST', $path, $data);

    return $offset;
  }

  /**
   * Index entities.
   */
  public function index() {
    // Create the index and set up the mapping for the entity bundle.
    $this->createIndex();

    // Make sure we can return all the concatenated data.
    db_query('SET SESSION group_concat_max_len = 100000');

    // Prepare the indexing by loading all the taxonomies.
    echo "Loading taxonomies...\n";
    $this->loadTaxonomies();

    $offset = $this->getOffset();
    $limit = $this->getLimit();
    $chunk_size = $this->options['chunk-size'];
    $total_count = 0;

    if ($offset === 0) {
      throw new Exception("No entity to index.");
    }

    // Main indexing loop.
    echo "Indexing entities...\n";
    while ($offset > 0 && $total_count < $limit) {
      // Get $chunk_size items starting from the last indexed item.
      $items = $this->getItems($chunk_size, $offset);

      $count = count($items);
      $total_count += $count;

      if ($count > 0) {
        $offset = $this->indexItems($items);
      }
      else {
        break;
      }

      // Clear the memory.
      unset($items);

      echo "Indexed {$total_count}/{$limit} entities.                 \r";
    }

    // Last indexed item.
    $offset += 1;

    echo "\nSuccessfully indexed {$total_count}/{$limit} entities.\n";
    echo "Last indexed entity is {$offset}.\n";
  }

  /**
   * Remove the elasticsearch type of this entity bundle.
   */
  public function remove() {
    $index = $this->options['database'] . '_' . $this->entity_type;
    $type = $this->entity_bundle;

    // Try to create the elasticsearch index.
    try {
      $this->request('DELETE', "{$index}/{$type}");
    }
    catch (\Exception $exception) {
      $message = $exception->getMessage();
      // Exception other than type missing, rethrow.
      if (strpos($message, 'TypeMissingException') !== 0) {
        throw $exception;
      }
    }

    echo "Index successfully removed.\n";
  }

  /**
   * Send a request to elasticsearch (type can be one of POST, PUT, DELETE).
   */
  public function request($type, $path, &$data = NULL) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $this->options['elasticsearch'] . '/' . $path);
    curl_setopt($curl, CURLOPT_TIMEOUT, 200);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $type);

    // Send data if defined.
    if (isset($data)) {
      if (!is_string($data)) {
        $data = json_encode($data, JSON_FORCE_OBJECT) . "\n";
      }
      curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    // Elasticsearch error.
    if ($status != 200) {
      $response = json_decode($response);
      if (isset($response->error)) {
        throw new \Exception($response->error);
      }
    }
  }

  /**
   * Set the mapping of the index type if it doesn't already exist.
   */
  public function createIndex() {
    $settings = array(
      'settings' => array(
        'number_of_shards' => 1,
        'number_of_replicas' => 1,
        'analysis' => array(
          'analyzer' => array(
            'default_index' => array(
              'type' => 'custom',
              'tokenizer' => 'standard',
              'filter' => array('standard', 'lowercase', 'asciifolding', 'elision', 'filter_stop', 'filter_word_delimiter', 'kstem', 'filter_edge_ngram'),
              'char_filter' => array('html_strip'),
            ),
            'default_search' => array(
              'type' => 'custom',
              'tokenizer' => 'standard',
              'filter' => array('standard', 'lowercase', 'asciifolding', 'elision', 'filter_stop', 'filter_word_delimiter', 'kstem'),
            ),
          ),
          'filter' => array(
            'filter_edge_ngram' => array(
              'type' => 'edgeNGram',
              'min_gram' => 1,
              'max_gram' => 20,
            ),
            'filter_word_delimiter' => array(
              'type' => 'word_delimiter',
              'preserve_original' => TRUE,
            ),
            'filter_stop' => array(
              'type' => 'stop',
              'stopwords' => array("_english_", "_french_", "_spanish_"),
            ),
          ),
        ),
      ),
    );

    $index = $this->options['database'] . '_' . $this->entity_type;
    $type = $this->entity_bundle;

    // Try to create the elasticsearch index.
    try {
      $this->request('POST', $index, $settings);
    }
    catch (\Exception $exception) {
      $message = $exception->getMessage();
      // Exception not because index already exists, rethrow.
      if (strpos($message, 'IndexAlreadyExistsException') !== 0) {
        throw $exception;
      }
    }

    // Try to set up the mapping of the type.
    try {
      $mapping = array(
        $type => array(
          'properties' => $this->getMapping()
        ),
      );
      $this->request('PUT', "{$index}/{$type}/_mapping", $mapping);
    }
    catch (\Exception $exception) {
      $message = $exception->getMessage();
      // Exception not because mapping already set, rethrow.
      if (strpos($message, 'IndexAlreadyExistsException') !== 0) {
        throw $exception;
      }
    }
  }

  /**
   * Get the mapping of the index type if it doesn't already exist.
   */
  public function getMapping() {
    return array();
  }

  /**
   * Get the mapping for a 'multi_field'.
   */
  public function getMultiFieldMapping($field, $properties = array('name'), $extra_properties = array(), $disabled = FALSE) {
    $mapping = array(
      'properties' => array(
        'id' => array('type' => 'integer', 'index_name' => $field . '.id'),
      ),
    );
    foreach ($properties as $property) {
      $mapping['properties'][$property] = array(
        'type' => 'multi_field',
        'path' => 'just_name',
        'fields' => array(
          $property => array(
            'type' => 'string',
            'omit_norms' => TRUE,
            'index_name' => $field . '.' . $property,
          ),
          'exact' => array(
            'type' => 'string',
            'index' => 'not_analyzed',
            'omit_norms' => TRUE,
            'index_name' => $field . '.' . $property . '.exact',
          ),
          'common' => array(
            'type' => 'string',
            'omit_norms' => TRUE,
            'index_name' => $field . '.common',
          ),
          'common_exact' => array(
            'type' => 'string',
            'index' => 'not_analyzed',
            'omit_norms' => TRUE,
            'index_name' => $field . '.common.exact',
          ),
        ),
      );
    }
    foreach ($extra_properties as $property => $data) {
      $mapping['properties'][$property] = $data;
    }
    if ($disabled === TRUE) {
      $mapping['enabled'] = FALSE;
    }
    return $mapping;
  }

  /**
   * Get the mapping for an image field.
   */
  public function getImageFieldMapping($disabled =  FALSE) {
    $mapping = array(
      'properties' => array(
        'id' => array('type' => 'integer'),
        'mimetype' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        'filename' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        'caption' => array('type' => 'string', 'omit_norms' => TRUE),
        'copyright' => array('type' => 'string', 'omit_norms' => TRUE),
        'url' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        'url-large' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
        'url-small' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
        'url-thumb' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
      ),
    );
    if ($disabled === TRUE) {
      $mapping['enabled'] = FALSE;
    }
    return $mapping;
  }

  /**
   * Get the mapping for a field field.
   */
  public function getFileFieldMapping($disabled = FALSE) {
    $mapping = array(
      'properties' => array(
        'id' => array('type' => 'integer'),
        'mimetype' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        'filename' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        'description' => array('type' => 'string', 'omit_norms' => TRUE),
        'url' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
        'preview' => array(
          'properties' => array(
            'url' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'not_analyzed'),
            'url-large' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
            'url-small' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
            'url-thumb' => array('type' => 'string', 'omit_norms' => TRUE, 'index' => 'no'),
          ),
        ),
      ),
    );
    if ($disabled === TRUE) {
      $mapping['enabled'] = FALSE;
    }
    return $mapping;
  }
}
