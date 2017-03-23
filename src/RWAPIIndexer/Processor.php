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
  protected $markdown = 'markdown';

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
    $this->public_scheme_url = $website . '/sites/reliefweb.int/files/';
    // Markdown library.
    if (class_exists('\Hoedown')) {
      $this->markdown = 'hoedown';
    }
    elseif (class_exists('\Sundown')) {
      $this->markdown = 'sundown';
    }
    else {
      $this->markdown = 'markdown';
    }
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
          $item[$key] = preg_replace('/(\]\(\/?)(?!http[s]?:\/\/)/', '](' . $this->website . '/', $item[$key]);
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

        // Check if a field has a primary counter part field,
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

        // Convert to single value field.
        case 'single':
          if (isset($item[$key][0])) {
            $item[$key] = $item[$key][0];
          }
          break;

        // Explode a list of strings seperated by spaces or commas into array.
        // Includes " ", \r, \t, \n and \f.
        case 'multi_string':
          $item[$key] = preg_split("/[\s,]+/", $item[$key]);
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

      if (!empty($array)) {
         $item[$key] = $array;
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
      case 'hoedown':
        static $hoedown;
        if (!isset($hoedown)) {
          $hoedown = new \Hoedown();
        }
        return $hoedown->parse($text);

      case 'sundown':
        $sundown = new \Sundown($text, array(
            'tables' => TRUE,
            'no_intra_emphasis' => TRUE,
            'fenced_code_blocks' => TRUE,
            'autolink' => TRUE,
            'safe_links_only' => TRUE,
          ));
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
   * @param string $alias
   *   URL alias for the entity.
   */
  public function processURL($entity_type, &$item, $alias) {
    $item['url'] = $this->website . '/' . str_replace('_', '/', $entity_type) . '/' . $item['id'];
    $item['url_alias'] = $this->website . '/' . $alias;
  }

  /**
   * Process an image field.
   *
   * @param string $field
   *   Image information to convert to image field.
   * @param boolean $single
   *   Indicates that the field should could contain a single value.
   * @return boolean
   *   Processing success.
   */
  public function processImage(&$field, $single = FALSE, $meta = TRUE, $styles = TRUE) {
    if (isset($field) && !empty($field)) {
      $items = array();
      foreach (explode('%%%', $field) as $item) {
        $parts = explode('###', $item);

        $array = array(
          'id' => $parts[0],
          'width' => $parts[3],
          'height' => $parts[4],
          'url' => $this->processFilePath($parts[5]),
          'filename' => $parts[6],
          'mimetype' => $this->getMimeType($parts[6]),
          'filesize' => $parts[7],
        );

        if (!empty($meta)) {
          $array['copyright'] = preg_replace('/^@+/', '', $parts[1]);
          $array['caption'] = $parts[2];
        }

        if (!empty($styles)) {
          $array['url-large'] = $this->processFilePath($parts[5], 'attachment-large');
          $array['url-small'] = $this->processFilePath($parts[5], 'attachment-small');
          $array['url-thumb'] = $this->processFilePath($parts[5], 'm');
        }

        foreach ($array as $key => $value) {
          if (empty($value)) {
            unset($key);
          }
        }

        $items[] = $array;
      }
      if (!empty($items)) {
        $field = $single ? $items[0] : $items;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Process a file field.
   *
   * @param string $field
   *   File information to convert to file field.
   * @param boolean $single
   *   Indicates that the field should could contain a single value.
   * @return boolean
   *   Processing success.
   */
  public function processFile(&$field, $single = FALSE) {
    if (isset($field) && !empty($field)) {
      $items = array();
      foreach (explode('%%%', $field) as $item) {
        $parts = explode('###', $item);

        $array = array(
          'id' => $parts[0],
          'description' => preg_replace('/\|(\d+)\|(0|90|-90)$/', '', $parts[1]),
          'url' => $this->processFilePath($parts[2]),
          'filename' => $parts[3],
          'mimetype' => $this->getMimeType($parts[3]),
          'filesize' => $parts[4],
        );

        // PDF attachment.
        if ($array['mimetype'] === 'application/pdf' && preg_match('/\|(\d+)\|(0|90|-90)$/', $parts[1]) === 1) {
          $directory = dirname($parts[2]) . '-pdf-previews';
          $filename = basename(urldecode($parts[3]), '.pdf');
          /*if (module_exists('transliteration')) {
            $filename = transliteration_clean_filename($filename);
          }*/
          $filename = $directory . '/' . $parts[0] . '-' . $filename . '.png';
          $array['preview'] = array(
            'url' => $this->processFilePath($filename),
            'url-large' => $this->processFilePath($filename, 'attachment-large'),
            'url-small' => $this->processFilePath($filename, 'attachment-small'),
            'url-thumb' => $this->processFilePath($filename, 'm'),
          );
        }

        foreach ($array as $key => $value) {
          if (empty($value)) {
            unset($key);
          }
        }

        $items[] = $array;
      }
      if (!empty($items)) {
        $field = $single ? $items[0] : $items;
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

  /**
   * Return the URL from a public file path.
   *
   * @param string $path
   *   File path.
   * @param string $style
   *   Image style.
   * @return string
   *   File URL.
   */
  public function processFilePath($path, $style = '') {
    $base = $this->public_scheme_url;
    if (!empty($style)) {
      $base .= 'styles/' . $style . '/public/';
    }
    return $base . $this->encodePath(str_replace('public://', '', $path));
  }

  /**
   * Encode url path.
   *
   * @param string $path
   *   Path to encode.
   * @return string
   *   Encoded path.
   */
  public function encodePath($path) {
    return str_replace('%2F', '/', rawurlencode($path));
  }
}
