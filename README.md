api-indexer
===========

Standalone application to index the content of the RW database into the API elasticsearch indexes without Drupal.

```
Usage: php PATH/TO/Indexer.php <entity-bundle> [options]
     -e, --elasticsearch <arg> Elasticsearch URL, defaults to http://127.0.0.1:9200
     -H, --mysql-host <arg> Mysql host, defaults to localhost
     -P, --mysql-port <arg> Mysql port, defaults to 3306
     -u, --mysql-user <arg> Mysql user, defaults to root
     -p, --mysql-pass <arg> Mysql pass, defaults to none
     -d, --database <arg> Database name, deaults to reliefwebint_0
     -b, --base-index-name <arg> Base index name, deaults to reliefwebint_0
     -w, --website <arg> Website URL, deaults to http://reliefweb.int
     -l, --limit <arg> Maximum number of entities to index, defaults to 0 (all)
     -o, --offset <arg> ID of the entity from which to start the indexing, defaults to the most recent one
     -c, --chunk-size <arg> Number of entities to index at one time, defaults to 500
     -r, --remove Indicates that the entity-bundle index should be removed
```
