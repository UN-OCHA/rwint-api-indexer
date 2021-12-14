<?php

namespace RWAPIIndexer;

/**
 * Elasticsearch handler class.
 */
class Elasticsearch {
  /**
   * Elasticsearch server.
   *
   * @var string
   */
  protected $server = 'http://localhost:9200';

  /**
   * Base index name.
   *
   * @var string
   */
  protected $base = '';

  /**
   * Index tag.
   *
   * @var string
   */
  protected $tag = '';

  /**
   * Default index settings.
   *
   * @var array
   */
  protected $defaultSettings = [
    'number_of_shards' => 1,
    'number_of_replicas' => 1,
    // For deep pagination. Review "Search After" query when switching to 5.x.
    // 2,000,000 should be enough for several years.
    'max_result_window' => 2000000,
    'analysis' => [
      'analyzer' => [
        'default' => [
          'type' => 'custom',
          'tokenizer' => 'standard',
          'filter' => [
            'lowercase',
            'asciifolding',
            'elision',
            'filter_stop',
            'filter_stemmer_possessive',
            'filter_word_delimiter',
            'filter_shingle',
          ],
          'char_filter' => ['html_strip'],
        ],
        'default_search' => [
          'type' => 'custom',
          'tokenizer' => 'standard',
          'filter' => [
            'lowercase',
            'asciifolding',
            'elision',
            'filter_stop',
            'filter_stemmer_possessive',
            'filter_word_delimiter',
            'filter_shingle',
          ],
        ],
        'search_as_you_type' => [
          'type' => 'custom',
          'tokenizer' => 'standard',
          'filter' => [
            'lowercase',
            'asciifolding',
            'elision',
          ],
        ],
        'status' => [
          'type' => 'custom',
          'tokenizer' => 'whitespace',
          'filter' => [
            'lowercase',
            'filter_status_synonyms',
          ],
        ],
      ],
      'filter' => [
        'filter_stemmer_possessive' => [
          'type' => 'stemmer',
          'name' => 'possessive_english',
        ],
        'filter_shingle' => [
          'type' => 'shingle',
          'min_shingle_size' => 2,
          'max_shingle_size' => 4,
          'output_unigrams' => TRUE,
        ],
        'filter_edge_ngram' => [
          'type' => 'edge_ngram',
          'min_gram' => 1,
          'max_gram' => 20,
        ],
        'filter_word_delimiter' => [
          'type' => 'word_delimiter',
          'preserve_original' => TRUE,
        ],
        'filter_stop' => [
          'type' => 'stop',
          'stopwords' => ['_english_'],
        ],
        'filter_status_synonyms' => [
          'type' => 'synonym',
          'synonyms' => [
            'current' => 'ongoing',
            'on_hold' => 'on-hold',
            'to_review' => 'to-review',
            'alert_archive' => 'alert-archive',
            'draft_archive' => 'draft-archive',
            'external_archive' => 'external-archive',
          ],
        ],
      ],
    ],
  ];

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
   * Get the index path.
   *
   * @param string $index
   *   Index name.
   *
   * @return string
   *   Index path.
   */
  public function getIndexPath($index) {
    return $this->base . $index . '_index' . $this->tag;
  }

  /**
   * Get the index alias.
   *
   * @param string $index
   *   Index name.
   *
   * @return string
   *   Index alias.
   */
  public function getIndexAlias($index) {
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
  public function create($index, array $mapping) {
    if (!$this->indexExists($index)) {
      $this->createIndex($index, $mapping);
    }
  }

  /**
   * Check if an index already exists.
   *
   * @param string $index
   *   Elasticsearch index name.
   */
  public function indexExists($index) {
    $path = $this->getIndexPath($index);

    try {
      $this->request('HEAD', $path);
      return TRUE;
    }
    catch (\Exception $exception) {
      // Exception is not due to the index being missing, rethrow.
      if ($exception->getCode() !== 404) {
        throw $exception;
      }
    }
    return FALSE;
  }

  /**
   * Create an elasticsearch index if doesn't already exists.
   *
   * @param string $index
   *   Index to create.
   * @param array $mapping
   *   Index mapping.
   */
  public function createIndex($index, array $mapping) {
    $path = $this->getIndexPath($index);

    $this->request('PUT', $path, [
      'settings' => $this->defaultSettings,
      'mappings' => [
        'properties' => $mapping,
      ],
    ]);
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
      // Exception other than index missing, rethrow.
      if ($exception->getCode() !== 404) {
        throw $exception;
      }
    }
  }

  /**
   * Create an alias for the index pointing to the type.
   *
   * @param string $index
   *   Index name.
   */
  public function addAlias($index) {
    $alias = $this->getIndexAlias($index);

    $data = [
      'actions' => [
        [
          'remove' => [
            'index' => '*',
            'alias' => $alias,
          ],
        ],
        [
          'add' => [
            'index' => $this->getIndexPath($index),
            'alias' => $alias,
          ],
        ],
      ],
    ];

    // Try to create the index alias.
    try {
      $this->request('POST', '_aliases', $data);
    }
    catch (\Exception $exception) {
      if ($exception->getCode() === 404) {
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

    $data = [
      'actions' => [
        [
          'remove' => [
            'index' => $this->getIndexPath($index),
            'alias' => $alias,
          ],
        ],
      ],
    ];

    // Try to remove the index alias.
    try {
      $this->request('POST', '_aliases', $data);
    }
    catch (\Exception $exception) {
      // Exception other than alias missing, rethrow.
      if ($exception->getCode() !== 404) {
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
   *
   * @return int
   *   ID of the last indexed item.
   */
  public function indexItems($index, array &$items) {
    $data = '';
    $path = $this->getIndexPath($index);

    // Get last id.
    end($items);
    $offset = key($items) - 1;
    reset($items);

    // Prepare bulk indexing.
    foreach ($items as $item) {
      // Add the document to the bulk indexing data.
      $action = [
        'index' => [
          '_index' => $path,
          '_id' => $item['id'],
        ],
      ];

      $data .= json_encode($action, JSON_FORCE_OBJECT) . "\n";
      $data .= json_encode($item) . "\n";
    }

    // Bulk index the documents.
    $this->request('POST', $path . '/_bulk', $data, TRUE);

    return $offset;
  }

  /**
   * Remove an item.
   *
   * @param string $index
   *   Index name.
   * @param int $id
   *   Id of the item to remove.
   */
  public function removeItem($index, $id) {
    $path = $this->getIndexPath($index) . '/_doc/' . $id;
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
   * @param bool $bulk
   *   Indicates that this is bulk request so that we can set the appropriate
   *   header.
   */
  public function request($method, $path, $data = NULL, $bulk = FALSE) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $this->server . '/' . $path);
    curl_setopt($curl, CURLOPT_TIMEOUT, $bulk ? 200 : 20);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

    if ($method === 'HEAD') {
      curl_setopt($curl, CURLOPT_NOBODY, TRUE);
    }
    else {
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

      // Tell ES that we accept compressed responses. CURL will decode them.
      curl_setopt($curl, CURLOPT_ENCODING, '');

      // Send data if defined.
      if (isset($data)) {
        if (!is_string($data)) {
          $data = json_encode($data) . "\n";
        }

        // Request headers.
        $headers = [];

        if ($bulk) {
          $headers[] = 'Content-Type: application/x-ndjson';
        }
        else {
          $headers[] = 'Content-Type: application/json';
        }

        // Compress the data and tell ES that it's compressed.
        $data = gzencode($data);
        $headers[] = 'Content-Encoding: gzip';

        // Prevent curl from expecting a 100 Continue with data is large.
        $headers[] = 'Expect:';

        $headers[] = 'Content-Length: ' . strlen($data);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
      }
    }

    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    $errno = curl_errno($curl);
    curl_close($curl);

    // Handle timeout and other errors.
    if (!empty($errno)) {
      throw new \Exception($error, $errno);
    }

    // Elasticsearch error.
    if ($status != 200) {
      $message = "Unknown error";
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
        }
      }
      throw new \Exception($message, (int) $status);
    }

    return $response;
  }

}
