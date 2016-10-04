<?php

namespace RWAPIIndexer;

/**
 * Elasticsearch handler class.
 */
class Elasticsearch {
  // Elasticsearch server.
  protected $server = 'http://localhost:9200';

  // Base index name.
  protected $base = '';

  // Index tag.
  protected $tag = '';

  // Default index settings.
  protected $default_settings = array(
    'settings' => array(
      'number_of_shards' => 1,
      'number_of_replicas' => 1,
      'analysis' => array(
        'analyzer' => array(
          'default_index' => array(
            'type' => 'custom',
            'tokenizer' => 'standard',
            'filter' => array('standard', 'lowercase', 'asciifolding', 'elision', 'filter_stop', 'filter_stemmer_possessive', 'filter_word_delimiter', 'filter_shingle'),
            'char_filter' => array('html_strip'),
          ),
          'default_search' => array(
            'type' => 'custom',
            'tokenizer' => 'standard',
            'filter' => array('standard', 'lowercase', 'asciifolding', 'elision', 'filter_stop', 'filter_stemmer_possessive', 'filter_word_delimiter', 'filter_shingle'),
          ),
        ),
        'filter' => array(
          'filter_stemmer_possessive' => array(
            'type' => 'stemmer',
            'name' => 'possessive_english',
          ),
          'filter_shingle' => array(
            'type' => 'shingle',
            'min_shingle_size' => 2,
            'max_shingle_size' => 4,
            'output_unigrams' => TRUE,
          ),
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
            'stopwords' => array('_english_'),
          ),
        ),
      ),
    ),
  );

  /**
   * Construct the elasticsearch handler for the given server.
   *
   * @param string $server
   *   Address of the elasticsearch server.
   * @param string $base
   *   Base index name.
   * @param string $tag
   *   Index tag.
   */
  public function __construct($server, $base, $tag = '') {
    $this->server = $server;
    $this->base = $base . '_';
    $this->tag = !empty($tag) ? '_' . $tag : '';
  }

  /**
   * Get the index path (with or without the type).
   *
   * @param string $index
   *   Index name.
   * @param boolean $type
   *   Whether to append the index type or not.
   * @return string
   *   Index path.
   */
  public function getIndexPath($index, $type = FALSE) {
    return $this->base . $index . '_index' . $this->tag . ($type ? '/' . $index : '');
  }

  /**
   * Get the index alias.
   *
   * @param string $index
   *   Index name.
   * @return string
   *   Index alias.
   */
  public function getIndexAlias($index, $type = FALSE) {
    return $this->base . $index;
  }

  /**
   * Create an index and index type with the given mapping if it doesn't exist.
   *
   * @param string $index
   *   Elasticsearch index name.
   * @param array $mapping
   *   Index mapping.
   */
  public function create($index, $mapping) {
    $this->createIndex($index);
    $this->createType($index, $mapping);
  }

  /**
   * Create an elasticsearch index if doesn't already exists.
   *
   * @param string $index
   *   Index to create.
   */
  public function createIndex($index) {
    $path = $this->getIndexPath($index);

    // Try to create the elasticsearch index.
    try {
      $this->request('POST', $path, $this->default_settings);
    }
    catch (\Exception $exception) {
      $message = $exception->getMessage();
      // Exception not because index already exists, rethrow.
      if (strpos($message, 'IndexAlreadyExistsException') === FALSE) {
        throw $exception;
      }
    }
  }

  /**
   * Create an index type if doesn't already exist and set its mapping.
   *
   * @param string $index
   *   Index name.
   * @param array $mapping
   *   Mapping for the given index type.
   */
  public function createType($index, $mapping) {
    $path = $this->getIndexPath($index, TRUE) . '/_mapping';

    $mapping = array(
      $index => array(
        '_all' => array('enabled' => FALSE),
        'properties' => $mapping,
      ),
    );

    // Try to set up the mapping of the type.
    try {
      $this->request('PUT', $path, $mapping);
    }
    catch (\Exception $exception) {
      $message = $exception->getMessage();
      // Exception not because mapping already set, rethrow.
      if (strpos($message, 'IndexAlreadyExistsException') === FALSE) {
        throw $exception;
      }
    }
  }

  /**
   * Remove an elasticsearch index.
   *
   * @param string $index
   *   Index name.
   */
  public function remove($index) {
    $path = $this->getIndexPath($index);

    // Try to delete the elasticsearch index.
    try {
      $this->request('DELETE', $path);
    }
    catch (\Exception $exception) {
      $message = $exception->getMessage();
      // Exception other than index missing, rethrow.
      if (strpos($message, 'IndexMissingException') === FALSE) {
        throw $exception;
      }
    }
  }


  /**
   * Create an alias for the index pointing to the type.
   *
   * @param string $index
   *   Index name.
   * @param boolean $remove
   *   Whether to remove the index or add it.
   * @param string $alias
   *   Index alias.
   */
  public function addAlias($index) {
    $alias = $this->getIndexAlias($index);

    $data = array(
      'actions' => array(
        array(
          'remove' => array(
            'index' => '*',
            'alias' => $alias,
          ),
        ),
        array(
          'add' => array(
            'index' => $this->getIndexPath($index),
            'alias' => $alias,
          ),
        ),
      ),
    );

    // Try to create the index alias.
    try {
      $this->request('POST', '_aliases', $data);
    }
    catch (\Exception $exception) {
      $message = $exception->getMessage();
      if (strpos($message, 'InvalidAliasNameException') !== FALSE) {
        throw new \Exception('Invalid alias name "' . $alias . '", an index exists with the same name as the alias.');
      }
      elseif (strpos($message, 'IndexMissingException') !== FALSE) {
        throw new \Exception('Index "' . $this->getIndexPath($index) . '" does not exist.');
      }
      else {
        throw $exception;
      }
    }
  }

  /**
   * Remove the alias for the given index.
   *
   * @param string $index
   *   Index name.
   */
  public function removeAlias($index) {
    $alias = $this->getIndexAlias($index);

    $data = array(
      'actions' => array(
        array(
          'remove' => array(
            'index' => $this->getIndexPath($index),
            'alias' => $alias,
          ),
        ),
      ),
    );

    // Try to remove the index alias.
    try {
      $this->request('POST', '_aliases', $data);
    }
    catch (\Exception $exception) {
      $message = $exception->getMessage();
      // Exception other than alias missing, rethrow.
      if (strpos($message, 'AliasesMissingException') === FALSE && strpos($message, 'IndexMissingException') === FALSE) {
        throw $exception;
      }
    }
  }

  /**
   * Bulk index the given items.
   *
   * @param string $index
   *   Index name.
   * @param array $items
   *   Items to index.
   * @return integer
   *   ID of the last indexed item.
   */
  public function indexItems($index, &$items) {
    $data = '';
    $path = $this->getIndexPath($index, TRUE) . '/_bulk';

    // Get last id.
    end($items);
    $offset = key($items) - 1;
    reset($items);

    // Prepare bulk indexing.
    foreach ($items as $item) {
      // Add the document to the bulk indexing data.
      $action = array('index' => array(
        '_index' => $this->getIndexPath($index),
        '_type' => $index,
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
   * Index an item.
   *
   * @param string $index
   *   Index name.
   * @param array $item
   *   Item to index.
   */
  public function indexItem($index, $item) {
    $path = $this->getIndexPath($index, TRUE) . '/' . $item['id'];
    $data = json_encode($item);
    $this->request('POST', $path, $data);
  }

  /**
   * Remove an item.
   *
   * @param string $index
   *   Index name.
   * @param array $id
   *   Id of the item to remove.
   */
  public function removeItem($index, $id) {
    $path = $this->getIndexPath($index, TRUE) . "/" . $id;
    $this->request('DELETE', $path);
  }

  /**
   * Send a request to elasticsearch.
   *
   * @param string $method
   *   Request method (GET, POST, PUT or DELETE).
   * @param string $path
   *   Elasticsearch resource path.
   * @param array|string $data
   *   Optional data to convey with the request.
   */
  public function request($method, $path, &$data = NULL) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $this->server . '/' . $path);
    curl_setopt($curl, CURLOPT_TIMEOUT, 200);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

    // Send data if defined.
    if (isset($data)) {
      if (!is_string($data)) {
        $data = json_encode($data) . "\n";
      }
      curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    // Elasticsearch error.
    if ($status != 200) {
      if (!empty($response)) {
        $response = json_decode($response);
        if (isset($response->error->type)) {
          $message = str_replace(' ', '', ucwords(str_replace('_', ' ', $response->error->type)));
          if (isset($response->error->reason)) {
            $message .= ' [reason: ' . $response->error->reason . ']';
          }
          if (isset($response->error->index)) {
            $message .= ' [index: ' . $response->error->index . ']';
          }
          throw new \Exception($message);
        }
      }
      throw new \Exception("Unknown error");
    }

    return $response;
  }
}
