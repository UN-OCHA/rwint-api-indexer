<?php

declare(strict_types=1);

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
  protected string $server = 'http://localhost:9200';

  /**
   * Base index name.
   *
   * @var string
   */
  protected string $base = '';

  /**
   * Index tag.
   *
   * @var string
   */
  protected string $tag = '';

  /**
   * Default index settings.
   *
   * @var array<string, mixed>
   */
  protected array $defaultSettings = [
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
      ],
      'normalizer' => [
        'status' => [
          'type' => 'custom',
          'char_filter' => [
            'status_synonyms',
          ],
          'filter' => [
            'lowercase',
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
      ],
      'char_filter' => [
        'status_synonyms' => [
          'type' => 'mapping',
          'mappings' => [
            'current => ongoing',
            'on_hold => on-hold',
            'to_review => to-review',
            'alert_archive => alert-archive',
            'draft_archive => draft-archive',
            'external_archive => external-archive',
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
  public function __construct(string $server, string $base, string $tag = '') {
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
  public function getIndexPath(string $index): string {
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
  public function getIndexAlias(string $index): string {
    return $this->base . $index;
  }

  /**
   * Create an index and index type with the given mapping if it doesn't exist.
   *
   * @param string $index
   *   Elasticsearch index name.
   * @param array<string, mixed> $mapping
   *   Index mapping.
   * @param int $shards
   *   The number of shards for this index.
   * @param int $replicas
   *   The number of replicas for this index.
   */
  public function create(string $index, array $mapping, int $shards, int $replicas): void {
    if (!$this->indexExists($index)) {
      $this->createIndex($index, $mapping, $shards, $replicas);
    }
  }

  /**
   * Check if an index already exists.
   *
   * @param string $index
   *   Elasticsearch index name.
   */
  public function indexExists(string $index): bool {
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
   * @param array<string, mixed> $mapping
   *   Index mapping.
   * @param int $shards
   *   The number of shards for this index.
   * @param int $replicas
   *   The number of replicas for this index.
   */
  public function createIndex(string $index, array $mapping, int $shards, int $replicas): void {
    $path = $this->getIndexPath($index);

    $settings = $this->defaultSettings;
    $settings['number_of_shards'] = $shards;
    $settings['number_of_replicas'] = $replicas;

    $this->request('PUT', $path, [
      'settings' => $settings,
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
  public function remove(string $index): void {
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
  public function addAlias(string $index): void {
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
  public function removeAlias(string $index): void {
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
   * @param array<int, array<string, mixed>> $items
   *   Items to index.
   *
   * @return int
   *   ID of the last indexed item.
   */
  public function indexItems(string $index, array &$items): int {
    $data = '';
    $path = $this->getIndexPath($index);

    // Get last id.
    end($items);
    $offset = key($items) - 1;
    reset($items);

    // Prepare bulk indexing.
    foreach ($items as $item) {
      if (!isset($item['id']) || !is_numeric($item['id'])) {
        continue;
      }
      // Elasticsearch requires the id to be a string.
      $doc_id = (string) $item['id'];

      // Add the document to the bulk indexing data.
      $action = [
        'index' => [
          '_index' => $path,
          '_id' => $doc_id,
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
  public function removeItem(string $index, int $id): void {
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
   * @param array<string, mixed>|string $data
   *   Optional data to convey with the request.
   * @param bool $bulk
   *   Indicates that this is bulk request so that we can set the appropriate
   *   header.
   *
   * @return string
   *   Response from the request as a string.
   *
   * @throws \Exception
   *   If the request fails.
   */
  public function request(string $method, string $path, mixed $data = NULL, bool $bulk = FALSE): string {
    if (empty($method)) {
      throw new \Exception('Method is required.');
    }
    if (empty($path)) {
      throw new \Exception('Path is required.');
    }
    if (empty($this->server)) {
      throw new \Exception('Server is required.');
    }

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
        $encoded_data = gzencode($data);
        if ($encoded_data !== FALSE) {
          $headers[] = 'Content-Encoding: gzip';
          $data = $encoded_data;
        }

        // Prevent curl from expecting a 100 Continue with data is large.
        $headers[] = 'Expect:';

        $headers[] = 'Content-Length: ' . strlen($data);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
      }
    }

    // We enabled the return transfer option so we can get the response as a
    // string or false if the request fails.
    /** @var string|false $response */
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    $errno = curl_errno($curl);

    // Handle timeout and other errors.
    if (!empty($errno)) {
      throw new \Exception($error, $errno);
    }

    // Elasticsearch error.
    if ($status != 200) {
      $message = "Unknown error";
      if (!empty($response)) {
        $decoded_response = json_decode($response, TRUE);
        if (is_array($decoded_response) && isset($decoded_response['error']) && is_array($decoded_response['error'])) {
          $error = $decoded_response['error'];
          if (isset($error['type']) && is_string($error['type'])) {
            $message = str_replace(' ', '', ucwords(str_replace('_', ' ', $error['type'])));
            if (isset($error['reason']) && is_string($error['reason'])) {
              $message .= ' [reason: ' . $error['reason'] . ']';
            }
            if (isset($error['index']) && is_string($error['index'])) {
              $message .= ' [index: ' . $error['index'] . ']';
            }
          }
        }
      }
      throw new \Exception($message, (int) $status);
    }

    // If the response is empty, throw an exception.
    if ($response === FALSE) {
      throw new \Exception('Empty response from the request.');
    }

    return $response;
  }

}
