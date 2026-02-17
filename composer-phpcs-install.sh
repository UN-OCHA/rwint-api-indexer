#!/bin/sh

PHPCS=./vendor/bin/phpcs

if [ -f "$PHPCS" ]; then
  # Drupal Coder references Slevomat sniffs; both standards must be in installed_paths
  $PHPCS --config-set installed_paths "$(pwd)/vendor/drupal/coder/coder_sniffer,$(pwd)/vendor/slevomat/coding-standard"
fi
