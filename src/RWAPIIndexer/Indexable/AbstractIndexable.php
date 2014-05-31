<?php

namespace RWAPIIndexer\Indexable;

/**
 * Base indexable class.
 */
abstract class AbstractIndexable extends \RWAPIIndexer\Taxonomies {
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

  // Base public scheme for URLs.
  private $public_scheme_url = '';

  /**
   * Construct the indexable based on the given website.
   */
  public function __construct($options) {
    $this->options = $options;
    $this->public_scheme_url = $options['website'] . '/sites/reliefweb.int/files/';
    parent::__construct();
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
   *
   * @param integer $limit
   *   Maximum number of items.
   * @param  integer $offset
   *   ID of the index from which to start getting items to index.
   * @return array
   *   Items to index.
   */
  public function getItems($limit, $offset) {
    return array();
  }

  /**
   * Bulk index the given items.
   *
   * @param array $items
   *   Items to index.
   * @return integer
   *   ID of the last indexed item.
   */
  public function indexItems(&$items) {
    $data = '';

    $index = $this->getIndexName();
    $type = $this->getIndexType();
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
   * Index an individual item.
   *
   * @param  array $item
   *   Item to index.
   */
  public function indexItem($item) {

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
    $index = $this->getIndexName();
    $type = $this->getIndexType();

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
              'stopwords' => array('_english_', '_french_', '_spanish_'),
            ),
          ),
        ),
      ),
    );

    $index = $this->getIndexName();
    $type = $this->getIndexType();

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
          '_all' => array('enabled' => FALSE),
          '_timestamp' => array('enabled' => TRUE, 'store' => TRUE, 'index' => 'no'),
          'properties' => $this->getMapping(),
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
   * Get Elasticsearch index name.
   */
  public function getIndexName() {
    return $this->options['base-index-name'] . '_' . $this->entity_type;
  }

  /**
   * Get Elasticsearch index type.
   */
  public function getIndexType() {
    return $this->entity_bundle;
  }

  /**
   * Return the mapping for the current indexable.
   *
   * @return array
   *   Mapping.
   */
  public function getMapping() {
    return array();
  }
}
