<?php

namespace RWAPIIndexer;

/**
 * Entity field processor class.
 */
class Processor {
  // Website URL.
  protected $website = '';

  // References handler.
  protected $references = NULL;

  // Markdown converter.
  protected $markdown = 'sundown';

  // Base public scheme for URLs.
  private $public_scheme_url = '';

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

  // Check if the Markdown function is available.
  public function __construct($website, $references) {
    $this->website = $website;
    $this->references = $references;
    $this->markdown = class_exists('\Sundown') ? 'sundown' : 'markdown';
    $this->public_scheme_url = $website . '/sites/reliefweb.int/files/';
  }

  /**
   * Convert the value of a field of an entity item.
   *
   * @param array $definition
   *   Processing definition.
   * @param array $item
   *   Entity item being processed.
   * @param string $key
   *   Field to process.
   * @param string $value
   *   Field value to convert.
   */
  public function processConversion($definition, &$item, $key) {
    foreach ($definition as $conversion) {
      switch ($conversion) {
        // Convert a value to boolean.
        case 'bool':
          $item[$key] = (bool) $item[$key];
          break;

        // Convert a value to integer.
        case 'int':
          $item[$key] = (int) $item[$key];
          break;

        // Convert a value to float.
        case 'float':
          $item[$key] = (float) $item[$key];
          break;

        // Convert time in seconds to milliseconds.
        case 'time':
          $item[$key] = $item[$key] * 1000;
          break;

        // Convert links to absolute links.
        case 'links':
          // TODO: replace reliefweb.int with $website.
          $item[$key] = preg_replace('/(\]\(\/?)(?!http:\/\/)/', '](' . $this->website . '/', $item[$key]);
          break;

        // Convert a field value in markdown format to HTML.
        case 'html':
          $html = $this->processMarkdown($item[$key]);
          if (!empty($html)) {
            $item[$key . '-html'] = $html;
          }
          break;

        // Explode a concatenated mutli integer field.
        case 'multi_int':
          $values = array();
          foreach (explode('%%%', $item[$key]) as $data) {
            $values[] = (int) $data;
          }
          $item[$key] = $values;
          break;

        // Check if a field as aprimary counter part field,
        // and add a 'primary' flag to the field sub item that
        // matches the value of the primary field.
        case 'primary':
          $field = 'primary_' . $key;
          // Check if there is a primary field.
          if (isset($item[$field]['id'])) {
            $primary_id = $item[$field]['id'];

            // Set the primary flag if IDs match.
            if (key($item[$key]) !== 0) {
              $item[$key]['primary'] = TRUE;
            }
            else {
              foreach ($item[$key] as &$term) {
                if ($term['id'] == $primary_id) {
                  $term['primary'] = TRUE;
                }
              }
            }
          }
          break;
      }
    }
  }

  /**
   * Process a reference field of an entity item,
   * replacing the ID with the reference taxanomy term.
   *
   * @param array $definition
   *   Processing definition.
   * @param array $item
   *   Entity being processed.
   * @param string $key
   *   Reference field to process.
   */
  public function processReference($definition, &$item, $key) {
    $bundle = key($definition);

    if ($this->references->has($bundle)) {
      $fields = $definition[$bundle];

      $array = array();
      foreach ($item[$key] as $id) {
        $term = $this->references->getItem($bundle, $id, $fields);
        if (isset($term)) {
          $array[] = $term;
        }
      }

      $count = count($array);
      if ($count > 0) {
         $item[$key] = $count > 1 ? $array : $array[0];
      }
      else {
        unset($item[$key]);
      }
    }
  }

  /**
   * Convert text from markdown to HTML.
   *
   * @param string $text
   *   Text to convert.
   * @return string
   *   Text converted to HTML.
   */
  public function processMarkdown($text) {
    switch ($this->markdown) {
      case 'sundown':
        $sundown = new \Sundown($text);
        return $sundown->toHTML();

      case 'markdown':
        return \Michelf\Markdown::defaultTransform($text);
    }
    return '';
  }

  /**
   * Add a url field to an entity item based on its entity type.
   *
   * @param string $entity_type
   *   Entity type of the item.
   * @param array $item
   *   Item to which add the URL field.
   */
  public function processURL($entity_type, &$item) {
    $item['url'] = $this->website . '/' . str_replace('_', '/', $entity_type) . '/' . $item['id'];
  }

  /**
   * Process a file field.
   *
   * @param string $field
   *   File information to convert to file fields.
   */
  public function processImage(&$field) {
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
   * Process a file field.
   *
   * @param string $field
   *   File information to convert to file fields.
   */
  public function processFile(&$field) {
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
}