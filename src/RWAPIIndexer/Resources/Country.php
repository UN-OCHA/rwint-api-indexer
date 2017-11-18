<?php

namespace RWAPIIndexer\Resources;

/**
 * Country resource handler.
 */
class Country extends \RWAPIIndexer\Resource {
  // Options used for building the query to get the items to index.
  protected $query_options = array(
    'fields' => array(
      'description' => 'description',
    ),
    'field_joins' => array(
      'field_status' => array(
        'status' => 'value',
      ),
      'field_shortname' => array(
        'shortname' => 'value',
      ),
      'field_iso3' => array(
        'iso3' => 'value',
      ),
      'field_profile' => array(
        'show_profile' => 'value',
      ),
      'field_featured' => array(
        'featured' => 'value',
      ),
      'field_location' => array(
        'latitude' => 'lat',
        'longitude' => 'lon',
      ),
      'field_video_playlist' => array(
        'video_playlist' => 'value',
      ),
    ),
  );

  // Options used to process the entity items before indexing.
  protected $processing_options = array(
    'conversion' => array(
      'description' => array('links'),
      'current' => array('bool'),
      'featured' => array('bool'),
      'latitude' => array('float'),
      'longitude' => array('float'),
    ),
  );

  // Profile sections (id => label).
  private $profile_sections = array(
    'key_content' => array(
      'label' => 'Key Content',
      'internal' => TRUE,
      'archives' => TRUE,
      'image' => TRUE,
    ),
    'appeals_response_plans' => array(
      'label' => 'Appeals & Response Plans',
      'internal' => TRUE,
      'archives' => TRUE,
      'image' => TRUE,
    ),
    'useful_links' => array(
      'label' => 'Useful Links',
      'internal' => FALSE,
      'archives' => FALSE,
      'image' => TRUE,
    ),
  );

  /**
   * Return the mapping for the current resource.
   *
   * @return array
   *   Elasticsearch index type mapping.
   */
  public function getMapping() {
    $mapping = new \RWAPIIndexer\Mapping();
    $mapping->addInteger('id')
            ->addString('url', FALSE)
            ->addString('url_alias', FALSE)
            ->addString('status', FALSE)
            ->addBoolean('current')
            ->addBoolean('featured')
            // Centroid Coordinates.
            ->addGeoPoint('location')
            // Names.
            ->addString('name', TRUE, TRUE)
            ->addString('shortname', TRUE, TRUE)
            ->addString('iso3', TRUE, TRUE)
            // Description -- legacy.
            ->addString('description')
            ->addString('description-html', NULL)
            // Video playlist.
            ->addString('video_playlist', FALSE);

    // Profile mapping.
    $this->addProfileMapping($mapping);

    return $mapping->export();
  }

  /**
   * Add the profile mapping.
   *
   * @param object $mapping
   *   Elasticsearch index type mapping.
   */
  private function addProfileMapping($mapping) {
    // Only index the overview.
    $mapping->addString('profile.overview');
    $mapping->addString('profile.overview-html', NULL);

    // Add the sections.
    foreach ($this->profile_sections as $id => $info) {
      $base = 'profile.' . $id;
      $image_field = !empty($info['internal']) ? 'cover' : 'logo';

      // Mapping for the active links.
      $mapping->addString($base . '.title', NULL)
              ->addString($base . '.active.url', NULL)
              ->addString($base . '.active.title', NULL)
              ->addString($base . '.active.' . $image_field, NULL);

      // Add the mapping for the archived links.
      if (!empty($info['archives'])) {
        $mapping->addString($base . '.archive.url', NULL)
                ->addString($base . '.archive.title', NULL)
                ->addString($base . '.archive.' . $image_field, NULL);
      }
    }
  }

  /**
   * Process an item, preparing for the indexing.
   *
   * @param array $item
   *   Item to process.
   */
  public function processItem(&$item) {
    // Current.
    $item['current'] = !empty($item['status']) && $item['status'] === 'current';

    // Only keep the description if the profile is checked.
    if (empty($item['show_profile'])) {
      unset($item['description']);
    }
    else {
      $this->processProfile($item);
    }
    unset($item['show_profile']);

    // Centroid coordinates.
    if (isset($item['latitude'], $item['longitude'])) {
      // TODO: fix the coordinates in the main site instead.
      if ($item['latitude'] < -90 || $item['latitude'] > 90) {
        $item['location'] = array('lon' => $item['latitude'], 'lat' => $item['longitude']);
      }
      else {
        $item['location'] = array('lat' => $item['latitude'], 'lon' => $item['longitude']);
      }
      unset($item['longitude']);
      unset($item['latitude']);
    }
  }

  /**
   * Special handling of the profile section for countries.
   */
  private function processProfile(&$item) {
    $description = array();
    $profile = array();

    // The actual description comes first.
    if (!empty($item['description'])) {
      $description[] = $item['description'];
      $profile['overview'] = $item['description'];
    }

    // Process the profile sections.
    foreach ($this->profile_sections as $id => $info) {
      $label = $info['label'];
      $keep_archives = !empty($info['archives']);
      $use_image = !empty($info['image']);
      $image_field = !empty($info['internal']) ? 'cover' : 'logo';

      $links = array();
      $section = array();
      $table = 'field_data_field_' . $id;

      $query = new \RWAPIIndexer\Database\Query($table, $table, $this->connection);
      $query->addField($table, 'field_' . $id . '_url', 'url');
      $query->addField($table, 'field_' . $id . '_title', 'title');
      $query->addField($table, 'field_' . $id . '_image', $image_field);
      $query->addField($table, 'field_' . $id . '_active', 'active');
      $query->condition($table . '.entity_type', $this->entity_type);
      $query->condition($table . '.entity_id', $item['id']);
      // Reverse order so that newer links (higher delta) are first.
      $query->orderBy($table . '.delta', 'DESC');

      $result = $query->execute();
      if (!empty($result)) {
        foreach ($result->fetchAll(\PDO::FETCH_ASSOC) as $link) {
          // Skip links without a url (shouldn't happen).
          if (empty($link['url'])) {
            continue;
          }

          $active = !empty($link['active']);
          $internal = FALSE;
          $title = '';

          // Skip archived items if requested.
          if (!$keep_archives && !$active) {
            continue;
          }

          // Remove the active info.
          unset($link['active']);

          // Transform internal urls to absolute urls.
          if (strpos($link['url'], '/node') === 0) {
            $link['url'] = $this->processor->processRelativeURL($link['url']);
            $internal = TRUE;
          }

          // Remove the image if empty or asked to.
          if (empty($link[$image_field]) || !$use_image) {
            unset($link[$image_field]);
          }
          // Expand internal images.
          elseif ($internal) {
            $link[$image_field] = $this->processor->processFilePath($link[$image_field], 'attachment-small');
          }

          // Set the title or remove it.
          if (!empty($link['title'])) {
            $title = $link['title'];
          }
          else {
            unset($link['title']);
          }

          // Add the link to the appropriate subsection.
          $links[$active ? 'active' : 'archive'][] = $link;

          // Add the link to the description section if active.
          if ($active) {
            // Generate the image link.
            if (!empty($link[$image_field])) {
              $alt = $internal ? 'Cover preview' : 'Logo';

              // If there is a title, we prepend it to the alt default text.
              if (!empty($title)) {
                $image = '![' . $title . ' - ' . $alt . '](' . $link[$image_field] . ')';
                // For internal links, we want to display the title after the cover.
                $title = $internal ? $image . ' ' . $title : $image;
              }
              else {
                $title = '![' . $alt . '](' . $link[$image_field] . ')';
              }
            }

            // Normally there should be either an image or a title
            // but check just in case.
            if (!empty($title)) {
              $section[] = '[' . $title . '](' . $link['url'] . ')';
            }
          }
        }

        // Add the section to the description.
        if (!empty($section)) {
          $description[] = "### " . $label . "\n\n- " . implode("\n- ", $section) . "\n";
        }

        // Add the links to the profile.
        if (!empty($links)) {
          $profile[$id] = array('title' => $label) + $links;
        }
      }
    }

    // Update the item description.
    if (!empty($description)) {
      $item['description'] = trim(implode("\n", $description));
      // Convert markdown.
      $this->processor->processConversion(array('html'), $item, 'description');
    }
    else {
      unset($item['description']);
    }

    // Add the profile.
    if (!empty($profile)) {
      $item['profile'] = $profile;
      // Convert markdown.
      if (!empty($item['profile']['overview'])) {
        $this->processor->processConversion(array('html'), $item['profile'], 'overview');
      }
    }
  }
}
