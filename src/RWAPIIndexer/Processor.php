<?php

namespace RWAPIIndexer;

use RWAPIIndexer\Database\DatabaseConnection;
use RWAPIIndexer\Database\Query as DatabaseQuery;
use Michelf\Markdown;

/**
 * Entity field processor class.
 */
class Processor {

  /**
   * Website URL.
   *
   * @var string
   */
  protected $website = '';

  /**
   * References handler.
   *
   * @var \RWAPIIndexer\References
   */
  protected $references = NULL;

  /**
   * Markdown converter.
   *
   * @var string
   */
  protected $markdown = 'markdown';

  /**
   * Base public scheme for URLs.
   *
   * @var string
   */
  private $publicSchemeUrl = '';

  /**
   * Default mimetypes.
   *
   * @var array
   */
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
    // Images.
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
    // Archives.
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed',
    'exe' => 'application/x-msdownload',
    'msi' => 'application/x-msdownload',
    'cab' => 'application/vnd.ms-cab-compressed',
    // Audio/video.
    'mp3' => 'audio/mpeg',
    'qt' => 'video/quicktime',
    'mov' => 'video/quicktime',
    // Adobe.
    'pdf' => 'application/pdf',
    'psd' => 'image/vnd.adobe.photoshop',
    'ai' => 'application/postscript',
    'eps' => 'application/postscript',
    'ps' => 'application/postscript',
    // Ms office.
    'doc' => 'application/msword',
    'rtf' => 'application/rtf',
    'xls' => 'application/vnd.ms-excel',
    'ppt' => 'application/vnd.ms-powerpoint',
    // Open office.
    'odt' => 'application/vnd.oasis.opendocument.text',
    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
  );

  /**
   * Check if the Markdown function is available.
   *
   * @param string $website
   *   Website URL.
   * @param \RWAPIIndexer\References $references
   *   References handler.
   */
  public function __construct($website, References $references) {
    $this->website = $website;
    $this->references = $references;
    $this->publicSchemeUrl = $website . '/sites/reliefweb.int/files/';
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
   */
  public function processConversion(array $definition, array &$item, $key) {
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
          $item[$key] = $this->processLinks($item[$key]);
          break;

        // Convert a field value in markdown format to HTML.
        case 'html':
          $html = $this->processMarkdown($item[$key]);
          if (!empty($html)) {
            $html = $this->processHtml($html, TRUE);
            $item[$key . '-html'] = $html;
          }
          break;

        // Convert a field value in markdown format to HTML.
        // Convert iframe special syntax.
        case 'html_iframe':
          $text = $this->processIframes($item[$key]);
          $html = $this->processMarkdown($text);
          if (!empty($html)) {
            $html = $this->processHtml($html, TRUE);
            $item[$key . '-html'] = $html;
          }
          break;

        // Convert a field value in markdown format to HTML.
        // Strip embedded images and iframes.
        case 'html_strict':
          $html = $this->processMarkdown($item[$key]);
          if (!empty($html)) {
            $html = $this->processHtml($html, FALSE);
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
   * Process reference.
   *
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
  public function processReference(array $definition, array &$item, $key) {
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
   * Process links.
   *
   * Process links in text, converting to absolute links
   * and substituting the domain for reliefweb links.
   *
   * @param string $text
   *   Text to process.
   *
   * @return string
   *   Text with processed links.
   */
  public function processLinks($text) {
    // Convert relative links to absolute.
    $text = preg_replace('@((\]\(|((src|href)=))(?![\'"]?https?://)[\'"]?)/*@', '$1' . $this->website . '/', $text);
    // Substitute domain for reliefweb.int links.
    $text = preg_replace('@https?://reliefweb\.int@', $this->website, $text);
    return $text;
  }

  /**
   * Convert text from markdown to HTML.
   *
   * @param string $text
   *   Text to convert.
   *
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
        return Markdown::defaultTransform($text);
    }
    return '';
  }

  /**
   * Convert the special iframe markdown-like syntax to html.
   *
   * Syntax is: [iframe:width:height](link).
   *
   * @param string $text
   *   Markdown text to process.
   *
   * @return string
   *   Processed text.
   */
  public function processIframes($text) {
    return preg_replace_callback("/\[iframe(?:[:](\d+))?(?:[:](\d+))?\]\(([^\)]+)\)/", static function ($data) {
      $width = !empty($data[1]) ? $data[1] : "1000";
      $height = !empty($data[2]) ? $data[2] : "400";
      return '<iframe width="' . $width . '" height="' . $height . '" src="' . $data[3] . '" frameborder="0" allowfullscreen></iframe>';
    }, $text);
  }

  /**
   * Strip unsupported tags.
   *
   * @todo Investigate using HTMLPurifier library.
   * @todo Cleanup markdown text in Drupal instead so that it
   * can safely be converted in the API without having to worry
   * about XSS etc.
   *
   * @param string $html
   *   HTML text to clean.
   * @param bool $embedded
   *   Whether embedded content is allowed or not.
   *
   * @return string
   *   Cleaned-up HTML.
   */
  public function processHtml($html, $embedded = FALSE) {
    $tags = array(
      'div',
      'span',
      'br',
      'a',
      'em',
      'strong',
      'cite',
      'code',
      'strike',
      'ul',
      'ol',
      'li',
      'dl',
      'dt',
      'dd',
      'blockquote',
      'p',
      'pre',
      'h1',
      'h2',
      'h3',
      'h4',
      'h5',
      'h6',
      'table',
      'caption',
      'thead',
      'tbody',
      'th',
      'td',
      'tr',
      'sup',
      'sub',
    );

    // Add iframe and image tags to the list of allowed tags.
    if ($embedded) {
      $tags = array_merge($tags, array('iframe', 'img'));
    }

    // Use Drupal filter_xss function if available.
    if (function_exists('filter_xss')) {
      return filter_xss($html, $tags);
    }
    else {
      return strip_tags($html, '<' . implode('><', $tags) . '>');
    }
  }

  /**
   * Add a "url" field to an entity item based on its entity type.
   *
   * @param string $entity_type
   *   Entity type of the item.
   * @param array $item
   *   Item to which add the URL field.
   * @param string $alias
   *   URL alias for the entity.
   */
  public function processEntityUrl($entity_type, array &$item, $alias) {
    $item['url'] = $this->website . '/' . str_replace('_', '/', $entity_type) . '/' . $item['id'];
    if (!empty($alias)) {
      $item['url_alias'] = $this->website . '/' . $this->encodePath($alias);
    }
  }

  /**
   * Transform a relative URL starting with "/" into an encoded absolute URL.
   *
   * @paran string $url
   *   Relative URL.
   *
   * @return string
   *   Absolute URL.
   */
  public function processEnprocessRelativeUrl($url) {
    return $this->website . $this->encodePath($url);
  }

  /**
   * Process an image field.
   *
   * @param string $field
   *   Image information to convert to image field.
   * @param bool $single
   *   Indicates that the field should could contain a single value.
   * @param bool $meta
   *   Whether to add the copyright and caption.
   * @param bool $styles
   *   Whether to add urls to the derivative images.
   *
   * @return bool
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
   * @param bool $single
   *   Indicates that the field should could contain a single value.
   *
   * @return bool
   *   Processing success.
   */
  public function processFile($field, $single = FALSE) {
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
   *
   * @param string $filename
   *   File name.
   *
   * @return string
   *   Mime type.
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
   *
   * @return string
   *   File URL.
   */
  public function processFilePath($path, $style = '') {
    $base = $this->publicSchemeUrl;
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
   *
   * @return string
   *   Encoded path.
   */
  public function encodePath($path) {
    return str_replace('%2F', '/', rawurlencode($path));
  }

  /**
   * Process the profile of a taxonomy term (country or disaster).
   *
   * @param \RWAPIIndexer\Database\DatabaseConnection $connection
   *   Database connection.
   * @param array $item
   *   Item to process.
   * @param array $sections
   *   Definition of the profile sections.
   */
  public function processProfile(DatabaseConnection $connection, array &$item, array $sections) {
    $description = array();
    $profile = array();

    // The actual description comes first.
    if (!empty($item['description'])) {
      $description[] = $item['description'];
      $profile['overview'] = $item['description'];
    }

    $entity_type = 'taxonomy_term';

    // Process the profile sections.
    foreach ($sections as $id => $info) {
      $label = $info['label'];
      $keep_archives = !empty($info['archives']);
      $use_image = !empty($info['image']);
      $image_field = !empty($info['internal']) ? 'cover' : 'logo';

      $links = array();
      $section = array();
      $table = 'field_data_field_' . $id;

      $query = new DatabaseQuery($table, $table, $connection);
      $query->addField($table, 'field_' . $id . '_url', 'url');
      $query->addField($table, 'field_' . $id . '_title', 'title');
      $query->addField($table, 'field_' . $id . '_image', $image_field);
      $query->addField($table, 'field_' . $id . '_active', 'active');
      $query->condition($table . '.entity_type', $entity_type);
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
            $link['url'] = $this->processEnprocessRelativeUrl($link['url']);
            $internal = TRUE;
          }

          // Remove the image if empty or asked to.
          if (empty($link[$image_field]) || !$use_image) {
            unset($link[$image_field]);
          }
          // Expand internal images.
          elseif ($internal) {
            $link[$image_field] = $this->processFilePath($link[$image_field], 'attachment-small');
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
                // For internal links, display the title after the cover.
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
      $this->processConversion(array('html'), $item, 'description');
    }
    else {
      unset($item['description']);
    }

    // Add the profile.
    if (!empty($profile)) {
      $item['profile'] = $profile;
      // Convert markdown.
      if (!empty($item['profile']['overview'])) {
        $this->processConversion(array('html'), $item['profile'], 'overview');
      }
    }
  }

}
