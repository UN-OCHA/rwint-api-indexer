api-indexer
===========

Standalone application to index the content of the RW database into the API elasticsearch indexes without Drupal.

Command line
------------

```
Usage: php PATH/TO/Indexer.php [options] <entity-bundle>
    -h, --help display this help
    -e, --elasticsearch <arg> Elasticsearch URL, defaults to http://127.0.0.1:9200
    -H, --mysql-host <arg> Mysql host, defaults to localhost
    -P, --mysql-port <arg> Mysql port, defaults to 3306
    -u, --mysql-user <arg> Mysql user, defaults to root
    -p, --mysql-pass <arg> Mysql pass, defaults to none
    -d, --database <arg> Database name, deaults to reliefwebint_0
    -b, --base-index-name <arg> Base index name, deaults to reliefwebint_0
    -t, --tag <arg> Tag appended to the index name, defaults to empty string
    -w, --website <arg> Website URL, deaults to https://reliefweb.int
    -l, --limit <arg> Maximum number of entities to index, defaults to 0 (all)
    -o, --offset <arg> ID of the entity from which to start the indexing, defaults to the most recent one
    -f, --filter <arg> Filter documents to index. Format: 'field1:value1,value2+field2:value1,value2'
    -c, --chunk-size <arg> Number of entities to index at one time, defaults to 500
    -i, --id Id of an entity item to index, defaults to 0 (none)
    -r, --remove Removes an entity if 'id' is provided or the index for the given entity bundle
    -a, --alias Set up the alias for the index after the indexing, ignored if id is provided
    -A, --alias-only Set up the alias for the index without indexing, ignored if id is provided
```

Library
-------

Add the library with composer:

```php
composer require "reliefweb/api-indexer"
```

And then create a new Manager, providing an options array (see above for the options using their fullname):

```php
use RWAPIIndexer\Manager;

// Indexing options to index all reports.
$options = array(
  'bundle' => 'report',
  'elasticsearch' => 'http://127.0.0.1:9200',
  'mysql-host' => 'localhost',
  'mysql-port' => 3306,
  'mysql-user' => 'root',
  'mysql-pass' => '',
  'database' => 'DATABASE_NAME',
  'base-index-name' => 'ELASTICSEARCH_INDEX_PREFIX',
  'tag' => '',
  'website' => 'https://reliefweb.int',
  'limit' => 0,
  'offset' => 0,
  'chunk-size' => 500,
  'id' => 0,
  'remove' => FALSE,
  'alias' => TRUE,
);

// Create the indexing manager.
$manager = new Manager($options);

// Index or delete based on the provided options.
$manager->execute();
```

Markdown
--------

It's recommended to have php-hoedown installed. If not, the indexer will default to the PHP League CommonMark library which is slower.
