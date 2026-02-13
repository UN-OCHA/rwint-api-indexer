<?php

declare(strict_types=1);

namespace RWAPIIndexer;

/**
 * Indexing options handler class.
 *
 * Immutable value object. Create via Options::fromArray().
 *
 * @phpstan-type IndexingOptions array{
 *   bundle?: string,
 *   elasticsearch?: string,
 *   mysql-host?: string,
 *   mysql-port?: int,
 *   mysql-user?: string,
 *   mysql-pass?: string,
 *   database?: string,
 *   base-index-name?: string,
 *   tag?: string,
 *   website?: string,
 *   limit?: int,
 *   offset?: int,
 *   filter?: string,
 *   chunk-size?: int,
 *   id?: int,
 *   remove?: bool,
 *   alias?: bool,
 *   alias-only?: bool,
 *   simulate?: bool,
 *   replicas?: int,
 *   shards?: int,
 *   log?: string,
 *   post-process-item-hook?: mixed
 * }
 */
readonly class Options {

  /**
   * Constructor.
   *
   * Use Options::fromArray() to create instances from kebab-case arrays.
   * Validation runs on construction so every instance is guaranteed valid.
   *
   * @param string $bundle
   *   Entity bundle to index (e.g. report, job, country).
   * @param string $elasticsearch
   *   Elasticsearch base URL.
   * @param string $mysqlHost
   *   MySQL hostname or IP address.
   * @param int $mysqlPort
   *   MySQL port (1-65535).
   * @param string $mysqlUser
   *   MySQL username.
   * @param string $mysqlPass
   *   MySQL password.
   * @param string $database
   *   Database name.
   * @param string $baseIndexName
   *   Base name for the Elasticsearch index.
   * @param string $tag
   *   Optional tag appended to the index name.
   * @param string $website
   *   Website base URL (e.g. https://reliefweb.int).
   * @param int $limit
   *   Maximum number of entities to index (0 = all).
   * @param int $offset
   *   Entity ID from which to start indexing.
   * @param string $filter
   *   Filter expression (field:value,value+field2:value).
   * @param int $chunkSize
   *   Number of entities to index per batch (1-1000).
   * @param int $id
   *   Single entity ID to index (0 = none).
   * @param bool $remove
   *   TRUE to remove an entity or the index.
   * @param bool $alias
   *   TRUE to set the index alias after indexing.
   * @param bool $aliasOnly
   *   TRUE to set the alias without indexing.
   * @param bool $simulate
   *   TRUE to only output the number of indexable entities.
   * @param int $replicas
   *   Number of index replicas (0 or more).
   * @param int $shards
   *   Number of index shards (1-8).
   * @param string $log
   *   Log callback name (e.g. 'echo') or empty.
   * @param mixed $postProcessItemHook
   *   Optional post-process hook (callable, empty string or NULL).
   */
  public function __construct(
    public string $bundle = '',
    public string $elasticsearch = 'http://127.0.0.1:9200',
    public string $mysqlHost = 'localhost',
    public int $mysqlPort = 3306,
    public string $mysqlUser = 'root',
    public string $mysqlPass = '',
    public string $database = 'reliefwebint_0',
    public string $baseIndexName = 'reliefwebint_0',
    public string $tag = '',
    public string $website = 'https://reliefweb.int',
    public int $limit = 0,
    public int $offset = 0,
    public string $filter = '',
    public int $chunkSize = 500,
    public int $id = 0,
    public bool $remove = FALSE,
    public bool $alias = FALSE,
    public bool $aliasOnly = FALSE,
    public bool $simulate = FALSE,
    public int $replicas = 1,
    public int $shards = 1,
    public string $log = '',
    public mixed $postProcessItemHook = NULL,
  ) {
    $this->validateOptions();
  }

  /**
   * Create Options from an associative array (e.g. from CLI).
   *
   * Maps kebab-case array keys to constructor parameter names and unpacks
   * the result into the constructor.
   *
   * @param IndexingOptions $options
   *   Indexing options (kebab-case keys).
   *
   * @return self
   *   Options instance.
   */
  public static function fromArray(array $options = []): self {
    if (php_sapi_name() === 'cli' && !isset($options['log'])) {
      $options['log'] = 'echo';
    }

    // Map kebab-case array keys to constructor parameter names.
    $mapping = [
      'bundle' => 'bundle',
      'elasticsearch' => 'elasticsearch',
      'mysql-host' => 'mysqlHost',
      'mysql-port' => 'mysqlPort',
      'mysql-user' => 'mysqlUser',
      'mysql-pass' => 'mysqlPass',
      'database' => 'database',
      'base-index-name' => 'baseIndexName',
      'tag' => 'tag',
      'website' => 'website',
      'limit' => 'limit',
      'offset' => 'offset',
      'filter' => 'filter',
      'chunk-size' => 'chunkSize',
      'id' => 'id',
      'remove' => 'remove',
      'alias' => 'alias',
      'alias-only' => 'aliasOnly',
      'simulate' => 'simulate',
      'replicas' => 'replicas',
      'shards' => 'shards',
      'log' => 'log',
      'post-process-item-hook' => 'postProcessItemHook',
    ];

    $parameters = [];
    foreach ($mapping as $option => $parameter) {
      if (isset($options[$option])) {
        $parameters[$parameter] = $options[$option];
      }
    }

    /** @var IndexingOptions $parameters */
    return new self(...$parameters);
  }

  /**
   * Parse the options from the command line.
   *
   * @return IndexingOptions
   *   Parsed options (kebab-case keys).
   */
  public static function parseArguments(): array {
    global $argv;

    array_shift($argv);

    if (empty($argv)) {
      self::displayUsage();
    }

    $options = [];
    while (($arg = array_shift($argv)) !== NULL) {
      switch ($arg) {
        case '--elasticsearch':
        case '-e':
          $options['elasticsearch'] = array_shift($argv);
          break;

        case '--mysql-host':
        case '-H':
          $options['mysql-host'] = array_shift($argv);
          break;

        case '--mysql-port':
        case '-P':
          $options['mysql-port'] = (int) array_shift($argv);
          break;

        case '--mysql-user':
        case '-u':
          $options['mysql-user'] = array_shift($argv);
          break;

        case '--mysql-pass':
        case '-p':
          $options['mysql-pass'] = array_shift($argv);
          break;

        case '--database':
        case '-d':
          $options['database'] = array_shift($argv);
          break;

        case '--base-index-name':
        case '-b':
          $options['base-index-name'] = array_shift($argv);
          break;

        case '--tag':
        case '-t':
          $options['tag'] = array_shift($argv);
          break;

        case '--website':
        case '-w':
          $options['website'] = array_shift($argv);
          break;

        case '--limit':
        case '-l':
          $options['limit'] = (int) array_shift($argv);
          break;

        case '--offset':
        case '-o':
          $options['offset'] = (int) array_shift($argv);
          break;

        case '--filter':
        case '-f':
          $options['filter'] = array_shift($argv);
          break;

        case '--chunk-size':
        case '-c':
          $options['chunk-size'] = (int) array_shift($argv);
          break;

        case '--id':
        case '-i':
          $options['id'] = (int) array_shift($argv);
          break;

        case '--remove':
        case '-r':
          $options['remove'] = TRUE;
          break;

        case '--alias':
        case '-a':
          $options['alias'] = TRUE;
          break;

        case '--alias-only':
        case '-A':
          $options['alias-only'] = TRUE;
          break;

        case '--simulate':
        case '-s':
          $options['simulate'] = TRUE;
          break;

        case '--replicas':
        case '-R':
          $options['replicas'] = (int) array_shift($argv);
          break;

        case '--shards':
        case '-S':
          $options['shards'] = (int) array_shift($argv);
          break;

        case '--help':
        case '-h':
          self::displayUsage();
          break;

        default:
          if (str_starts_with($arg, '-')) {
            self::displayUsage("Invalid argument '{$arg}'.");
          }
          else {
            $options['bundle'] = $arg;
          }
          break;
      }
    }

    if (empty($options['bundle'])) {
      self::displayUsage("No entity bundle provided.");
    }

    return $options;
  }

  /**
   * Validate entity bundle parameter.
   */
  public static function validateBundle(string $bundle): string|FALSE {
    return Bundles::has($bundle) ? $bundle : FALSE;
  }

  /**
   * Validate MySQL host.
   */
  public static function validateMysqlHost(string $host): string|FALSE {
    if (filter_var($host, FILTER_VALIDATE_IP) || preg_match('/^\S+$/', $host) === 1) {
      return $host;
    }
    return FALSE;
  }

  /**
   * Validate post process item hook.
   *
   * @param mixed $hook
   *   Post-process item hook to validate.
   *
   * @return callable|string|null|false
   *   Validated post-process item hook (callable, empty string) or FALSE if
   *   invalid.
   */
  public static function validatePostProcessItemHook(mixed $hook): callable|string|null|false {
    if ($hook === NULL || $hook === '' || is_callable($hook)) {
      return $hook;
    }
    return FALSE;
  }

  /**
   * Validate this instance's properties. Called from constructor.
   */
  private function validateOptions(): void {
    // Validate bundle.
    if (self::validateBundle($this->bundle) === FALSE) {
      $known = implode(', ', array_keys(Bundles::BUNDLES));
      throw new \InvalidArgumentException("Invalid bundle '{$this->bundle}'. Known bundles: {$known}.");
    }
    // Validate Elasticsearch URL.
    if (filter_var($this->elasticsearch, FILTER_VALIDATE_URL) === FALSE) {
      throw new \InvalidArgumentException('Invalid Elasticsearch option, it must be a valid URL.');
    }
    // Validate MySQL host.
    if (self::validateMysqlHost($this->mysqlHost) === FALSE) {
      throw new \InvalidArgumentException('Invalid MySQL host. It must be a valid hostname or IP address.');
    }
    // Validate MySQL port.
    if (filter_var($this->mysqlPort, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === FALSE) {
      throw new \InvalidArgumentException('Invalid MySQL port. It must be between 1 and 65535.');
    }
    // Validate MySQL user.
    if (filter_var($this->mysqlUser, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^\S+$/']]) === FALSE) {
      throw new \InvalidArgumentException('Invalid MySQL user. It must be a non-empty string without spaces.');
    }
    // Validate MySQL password.
    if (filter_var($this->mysqlPass, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^\S*$/']]) === FALSE) {
      throw new \InvalidArgumentException('Invalid MySQL password. It must not contain spaces.');
    }
    // Validate database name.
    if (filter_var($this->database, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[a-zA-Z0-9_-]*$/']]) === FALSE) {
      throw new \InvalidArgumentException('Invalid database name. Only letters, numbers, underscores and hyphens are allowed.');
    }
    // Validate base index name.
    if (filter_var($this->baseIndexName, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[a-zA-Z0-9_-]*$/']]) === FALSE) {
      throw new \InvalidArgumentException('Invalid base index name. Only letters, numbers, underscores and hyphens are allowed.');
    }
    // Validate tag.
    if (filter_var($this->tag, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[a-zA-Z0-9_-]*$/']]) === FALSE) {
      throw new \InvalidArgumentException('Invalid tag. Only letters, numbers, underscores and hyphens are allowed.');
    }
    // Validate website URL.
    if (filter_var($this->website, FILTER_VALIDATE_URL) === FALSE) {
      throw new \InvalidArgumentException('Invalid website option, it must be a valid URL.');
    }
    // Validate limit.
    if (filter_var($this->limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === FALSE) {
      throw new \InvalidArgumentException('Invalid limit. It must be a non-negative integer.');
    }
    // Validate offset.
    if (filter_var($this->offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === FALSE) {
      throw new \InvalidArgumentException('Invalid offset. It must be a non-negative integer.');
    }
    // Validate filter.
    if (filter_var($this->filter, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^(([a-zA-Z0-9_-]+:[a-zA-Z0-9_*-]+(,[a-zA-Z0-9_*-]+)*)([+][a-zA-Z0-9_-]+:[a-zA-Z0-9_*-]+(,[a-zA-Z0-9_*-]+)*)*)*$/']]) === FALSE) {
      throw new \InvalidArgumentException("Invalid filter. Format: field1:value1,value2+field2:value1 (e.g. status:current+theme:123).");
    }
    // Validate chunk-size.
    if (filter_var($this->chunkSize, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]) === FALSE) {
      throw new \InvalidArgumentException('Invalid chunk-size. It must be between 1 and 1000.');
    }
    // Validate id.
    if (filter_var($this->id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === FALSE) {
      throw new \InvalidArgumentException('Invalid id. It must be a non-negative integer.');
    }
    // Validate replicas.
    if (filter_var($this->replicas, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === FALSE) {
      throw new \InvalidArgumentException('Invalid replicas. It must be 0 or greater.');
    }
    // Validate shards.
    if (filter_var($this->shards, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 8]]) === FALSE) {
      throw new \InvalidArgumentException('Invalid shards. It must be between 1 and 8.');
    }
    // Validate post-process-item-hook.
    if (self::validatePostProcessItemHook($this->postProcessItemHook) === FALSE) {
      throw new \InvalidArgumentException('Invalid post-process-item-hook. It must be callable or empty.');
    }
  }

  /**
   * Display the script usage and indexing options.
   *
   * @param ?string $error
   *   Error message to display, if any.
   */
  public static function displayUsage(?string $error = NULL): void {
    if ($error !== NULL && $error !== '') {
      echo "[ERROR] " . $error . "\n\n";
    }

    echo "Usage: php PATH/TO/Indexer.php [options] <entity-bundle>\n" .
          "     -h, --help Display this help message \n" .
          "     -e, --elasticsearch <arg> Elasticsearch URL, defaults to http://127.0.0.1:9200 \n" .
          "     -H, --mysql-host <arg> Mysql host, defaults to localhost \n" .
          "     -P, --mysql-port <arg> Mysql port, defaults to 3306 \n" .
          "     -u, --mysql-user <arg> Mysql user, defaults to root \n" .
          "     -p, --mysql-pass <arg> Mysql pass, defaults to none \n" .
          "     -d, --database <arg> Database name, deaults to reliefwebint_0 \n" .
          "     -b, --base-index-name <arg> Base index name, deaults to reliefwebint_0 \n" .
          "     -t, --tag <arg> Tag appended to the index name, defaults to empty string \n" .
          "     -w, --website <arg> Website URL, deaults to https://reliefweb.int \n" .
          "     -l, --limit <arg> Maximum number of entities to index, defaults to 0 (all) \n" .
          "     -o, --offset <arg> ID of the entity from which to start the indexing, defaults to the most recent one \n" .
          "     -f, --filter <arg> Filter documents to index. Format: 'field1:value1,value2+field2:value1,value2' \n" .
          "     -c, --chunk-size <arg> Number of entities to index at one time, defaults to 500 \n" .
          "     -i, --id Id of an entity item to index, defaults to 0 (none) \n" .
          "     -r, --remove Removes an entity if 'id' is provided or the index for the given entity bundle \n" .
          "     -a, --alias Set up the alias for the index after the indexing, ignored if id is provided \n" .
          "     -A, --alias-only Set up the alias for the index without indexing, ignored if id is provided \n" .
          "     -s, --simulate Return the number of indexable entities based on the provided limit and offset \n" .
          "     -R, --replicas Create indices with this number of replicas, defaults to 1. Allowed: 0 or more \n" .
          "     -S, --shards Create indices with this number of shards, defaults to 1. Allowed: 1-8) \n\n";
    exit();
  }

}
