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
   */
  public function __construct($server, $base) {
    $this->server = $server;
    $this->base = $base . '_';
  }

  /**
   * Create the index type with the given mapping if it doesn't exist.
   *
   * @param string $index
   *   Elasticsearch index name.
   * @param string $type
   *   Elasticsearch index type.
   */
  public function create($index, $type, $mapping) {
    $this->createIndex($index);
    $this->createType($index, $type, $mapping);
  }

  /**
   * Create the elasticsearch index if doesn't already exists.
   *
   * @param string $index
   *   Index to create.
   */
  public function createIndex($index) {
    $path = $this->base . $index;

    // Try to create the elasticsearch index.
    try {
      $this->request('POST', $path, $this->default_settings);
    }
    catch (\Exception $exception) {
      $message = $exception->getMessage();
      // Exception not because index already exists, rethrow.
      if (strpos($message, 'IndexAlreadyExistsException') !== 0) {
        throw $exception;
      }
    }
  }

  /**
   * Create the index type if doesn't already exist and set its mapping.
   *
   * @param string $index
   *   Index name.
   * @param string $type
   *   Index type.
   * @param array $mapping
   *   Mapping for the given index type.
   */
  public function createType($index, $type, $mapping) {
    $path = $this->base . $index . "/" . $type . "/_mapping";

    // Try to set up the mapping of the type.
    try {
      $mapping = array(
        $type => array(
          '_all' => array('enabled' => FALSE),
          '_timestamp' => array('enabled' => TRUE, 'store' => TRUE, 'index' => 'no'),
          'properties' => $mapping,
        ),
      );
      $this->request('PUT', $path, $mapping);
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
   * Remove the elasticsearch index type of this entity bundle.
   *
   * @param string $index
   *   Index name.
   * @param string $type
   *   Index type.
   */
  public function removeType($index, $type) {
    $path = $this->base . $index . "/" . $type;

    // Try to create the elasticsearch index.
    try {
      $this->request('DELETE', $path);
    }
    catch (\Exception $exception) {
      $message = $exception->getMessage();
      // Exception other than type missing, rethrow.
      if (strpos($message, 'TypeMissingException') !== 0) {
        throw $exception;
      }
    }
  }

  /**
   * Bulk index the given items.
   *
   * @param string $index
   *   Index name.
   * @param string $type
   *   Index type.
   * @param array $items
   *   Items to index.
   * @return integer
   *   ID of the last indexed item.
   */
  public function indexItems($index, $type, &$items) {
    $data = '';
    $path = $this->base . $index . "/" . $type . "/_bulk";

    // Get last id.
    end($items);
    $offset = key($items) - 1;
    reset($items);

    // Prepare bulk indexing.
    foreach ($items as &$item) {
      // Add the document to the bulk indexing data.
      $action = array('index' => array(
        '_index' => $this->base . $index,
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
   * Index an item.
   *
   * @param string $index
   *   Index name.
   * @param string $type
   *   Index type.
   * @param array $item
   *   Item to index.
   */
  public function indexItem($index, $type, &$item) {
    $path = $this->base . $index . "/" . $type . "/" . $item['id'];
    $data = json_encode($item);
    $this->request('POST', $path, $data);
  }

  /**
   * Remove an item.
   *
   * @param string $index
   *   Index name.
   * @param string $type
   *   Index type.
   * @param array $id
   *   Id of the item to remove.
   */
  public function removeItem($index, $type, $id) {
    $path = $this->base . $index . "/" . $type . "/" . $id;
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
      $response = json_decode($response);
      if (isset($response->error)) {
        throw new \Exception($response->error);
      }
    }

    return $response;
  }
}
