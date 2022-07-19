<?php

namespace RWAPIIndexer;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use RWAPIIndexer\Database\DatabaseConnection;
use RWAPIIndexer\Database\Query as DatabaseQuery;

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
   * Host name of the website URL.
   *
   * @var string
   */
  protected $hostname = '';

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
  protected $markdown = '';

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
  protected $mimeTypes = [
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
  ];

  /**
   * List of HTML block level elements.
   *
   * @var array
   */
  public static $htmlBlockElements = [
    "address",
    "article",
    "aside",
    "base",
    "basefont",
    "blockquote",
    "body",
    "caption",
    "center",
    "col",
    "colgroup",
    "dd",
    "details",
    "dialog",
    "dir",
    "div",
    "dl",
    "dt",
    "fieldset",
    "figcaption",
    "figure",
    "footer",
    "form",
    "frame",
    "frameset",
    "h1",
    "h2",
    "h3",
    "h4",
    "h5",
    "h6",
    "head",
    "header",
    "hr",
    "html",
    "iframe",
    "legend",
    "li",
    "link",
    "main",
    "menu",
    "menuitem",
    "nav",
    "noframes",
    "ol",
    "optgroup",
    "option",
    "p",
    "param",
    "section",
    "source",
    "summary",
    "table",
    "tbody",
    "td",
    "tfoot",
    "th",
    "thead",
    "title",
    "tr",
    "track",
    "ul",
    // Extra elements that need to be followed by a blank line for the following
    // text to be converted to markdown.
    "pre",
    "script",
    "style",
    "textarea",
  ];

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
    $this->hostname = preg_replace('#^https?://#', '', $this->website);
    $this->references = $references;
    $this->publicSchemeUrl = $website . '/sites/default/files/';
    // Markdown library.
    if (class_exists('\Hoedown')) {
      $this->markdown = 'hoedown';
    }
    elseif (class_exists('\League\CommonMark\MarkdownConverter')) {
      $this->markdown = 'commonmark';
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
          if (is_numeric($item[$key])) {
            $item[$key] = $item[$key] * 1000;
          }
          else {
            $date = new \DateTime($item[$key], new \DateTimeZone('UTC'));
            $item[$key] = $date->getTimestamp() * 1000;
          }
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
          $values = [];
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

      $array = [];
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

      case 'commonmark':
        return static::convertToHtml($text, [$this->hostname, 'reliefweb.int']);
    }
    return '';
  }

  /**
   * Convert a markdown text to HTML.
   *
   * @param string $text
   *   Markdown text to convert.
   * @param array $internal_hosts
   *   List of internal hosts to determine if a link is external or not.
   *
   * @return string
   *   HTML.
   */
  public static function convertToHtml($text, array $internal_hosts = ['reliefweb.int']) {
    static $pattern;

    // CommonMark specs consider text following an HTML block element without
    // a blank line to seperate them, as part of the HTML block. This is a
    // breaking change compared to what Michel Fortin's PHP markdown or
    // hoedown libraries were doing so use the following regex to ensure there
    // is a blank line.
    // @see https://spec.commonmark.org/0.30/#html-blocks
    // @see https://talk.commonmark.org/t/beyond-markdown/2787/4
    if (!isset($pattern)) {
      $pattern = '#(</' . implode('>|</', static::$htmlBlockElements) . '>)\s*#m';
    }
    $text = preg_replace($pattern, "$1\n\n", $text);

    // Add a space before the heading '#' which is fine as ReliefWeb doesn't use
    // hash tags.
    // @see https://talk.commonmark.org/t/heading-not-working/819/42
    $text = preg_replace('/^(#+)([^# ])/m', '$1 $2', $text);

    // No need for extra blanks.
    $text = trim($text);

    // Environment configuration.
    $config = [
       // Settings to add attributes to external links.
      'external_link' => [
        'internal_hosts' => $internal_hosts,
        'open_in_new_window' => TRUE,
      ],
    ];

    // Create an Environment with all the CommonMark parsers and renderers.
    $environment = new Environment($config);
    $environment->addExtension(new CommonMarkCoreExtension());

    // Add the extension to convert external links.
    $environment->addExtension(new ExternalLinkExtension());

    // Add the extension to convert ID attributes.
    $environment->addExtension(new AttributesExtension());

    // Add the extension to convert links.
    $environment->addExtension(new AutolinkExtension());

    // Add the extension to convert the tables.
    $environment->addExtension(new TableExtension());

    // Create the converter with the extension(s).
    $converter = new MarkdownConverter($environment);

    // Convert to HTML.
    return (string) $converter->convert($text);
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
    $pattern = "/\[iframe(?:[:](?<width>\d+))?(?:[:x](?<height>\d+))?(?:[ ]+\"?(?<title>[^\"\]]+)\"?)?\](\((?<url>[^\)]+)\))?/";
    return preg_replace_callback($pattern, static function ($data) {
      $width = !empty($data['width']) ? $data['width'] : '1000';
      $height = !empty($data['height']) ? $data['height'] : '400';
      $title = !empty($data['title']) ? $data['title'] : 'iframe';
      $url = $data['url'];

      return '<iframe width="' . $width . '" height="' . $height . '" src="' . $data['url'] . '" frameborder="0" allowfullscreen></iframe>';
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
    $tags = [
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
    ];

    // Add iframe and image tags to the list of allowed tags.
    if ($embedded) {
      $tags = array_merge($tags, ['iframe', 'img']);
    }

    return static::filterXss($html, $tags);
  }

  /**
   * Filters HTML to prevent cross-site-scripting (XSS) vulnerabilities.
   *
   * @param string $html
   *   HTML text.
   * @param array $tags
   *   Allowed HTML tags.
   *
   * @return string
   *   Filtered HTML.
   */
  protected static function filterXss($html, array $tags) {
    static $drupal_filter_xss;

    if (!isset($drupal_filter_xss)) {
      $drupal_filter_xss = method_exists('\Drupal\Component\Utility\Xss', 'filter');
    }

    // Use Drupal filter_xss function if available.
    if ($drupal_filter_xss) {
      // phpcs:ignore
      return \Drupal\Component\Utility\Xss::filter($html, $tags);
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
      $item['url_alias'] = $this->website . '/' . ltrim($this->encodePath($alias), '/');
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
  public function processEntityRelativeUrl($url) {
    return $this->website . '/' . ltrim($this->encodePath($url), '/');
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
      $items = [];
      foreach (explode('%%%', $field) as $item) {
        [
          $id,
          $width,
          $height,
          $uri,
          $filename,
          $mimetype,
          $filesize,
          $copyright,
          $caption,
        ] = explode('###', $item);

        // Update the mime type if necessary. Some old content have the wrong
        // one.
        if (empty($mimetype) || $mimetype === 'application/octet-stream') {
          $mimetype = $this->getMimeType($filename);
        }

        $array = [
          'id' => $id,
          'width' => $width,
          'height' => $height,
          'url' => $this->processFilePath($uri),
          'filename' => $filename,
          'mimetype' => $mimetype,
          'filesize' => $filesize,
        ];

        if (!empty($meta)) {
          $array['copyright'] = preg_replace('/^@+/', '', $copyright);
          $array['caption'] = $caption;
        }

        if (!empty($styles)) {
          $array['url-large'] = $this->processFilePath($uri, 'large');
          $array['url-small'] = $this->processFilePath($uri, 'small');
          $array['url-thumb'] = $this->processFilePath($uri, 'thumbnail');
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
  public function processFile(&$field, $single = FALSE) {
    if (isset($field) && !empty($field)) {
      $items = [];
      foreach (explode('%%%', $field) as $item) {
        [
          $id,
          $uuid,
          $filename,
          $description,
          $langcode,
          $preview_uuid,
          $preview_page,
          $preview_rotation,
          $uri,
          $mimetype,
          $filesize,
        ] = explode('###', $item);

        // Skip private files.
        if (strpos($uri, 'private://') === 0) {
          continue;
        }

        $description = trim($description);

        // Add the language version to the description for backward
        // compatibility.
        $language_versions = [
          'ar' => 'Arabic version',
          'en' => 'English version',
          'es' => 'Spanish version',
          'fr' => 'French version',
          'ot' => 'Other version',
          'ru' => 'Russian version',
        ];
        if (isset($language_versions[$langcode])) {
          if (!empty($description)) {
            $description .= ' - ' . $language_versions[$langcode];
          }
          else {
            $description = $language_versions[$langcode];
          }
        }

        // Update the mime type if necessary. Some old content have the wrong
        // one.
        if (empty($mimetype) || $mimetype === 'application/octet-stream') {
          $mimetype = $this->getMimeType($filename);
        }

        // We need to expose the permanent URI not the system one.
        $permanent_uri = 'public://attachments/' . $uuid . '/' . $filename;

        $array = [
          'id' => $id,
          'description' => $description,
          'url' => $this->processFilePath($permanent_uri),
          'filename' => $filename,
          'mimetype' => $mimetype,
          'filesize' => $filesize,
        ];

        // PDF attachment.
        if (!empty($preview_uuid) && !empty($preview_page)) {
          $preview_uri = str_replace('/attachments/', '/previews/', $uri);
          $preview_uri = preg_replace('#\..+$#i', '.png', $preview_uri);
          $array['preview'] = [
            'url' => $this->processFilePath($preview_uri),
            'url-large' => $this->processFilePath($preview_uri, 'large'),
            'url-small' => $this->processFilePath($preview_uri, 'small'),
            'url-thumb' => $this->processFilePath($preview_uri, 'thumbnail'),
            'version' => implode('-', [
              $id,
              $preview_page,
              $preview_rotation,
            ]),
          ];
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
    $extension = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));
    return $this->mimeTypes[$extension] ?? 'application/octet-stream';
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
    if (strpos($path, '/attachments/') !== FALSE) {
      $base = $this->website . '/';
    }
    else {
      $base = $this->publicSchemeUrl;
    }
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
    $description = [];
    $profile = [];

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

      $links = [];
      $section = [];
      $table = $entity_type . '__field_' . $id;

      $query = new DatabaseQuery($table, $table, $connection);
      $query->addField($table, 'field_' . $id . '_url', 'url');
      $query->addField($table, 'field_' . $id . '_title', 'title');
      $query->addField($table, 'field_' . $id . '_image', $image_field);
      $query->addField($table, 'field_' . $id . '_active', 'active');
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
            $link['url'] = $this->processEntityRelativeUrl($link['url']);
            $internal = TRUE;
          }

          // Remove the image if empty or asked to.
          if (empty($link[$image_field]) || !$use_image) {
            unset($link[$image_field]);
          }
          // Expand internal images.
          elseif ($internal) {
            $link[$image_field] = $this->processFilePath($link[$image_field], 'small');
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
          $profile[$id] = ['title' => $label] + $links;
        }
      }
    }

    // Update the item description.
    if (!empty($description)) {
      $item['description'] = trim(implode("\n", $description));
      // Convert markdown.
      $this->processConversion(['html'], $item, 'description');
    }
    else {
      unset($item['description']);
    }

    // Add the profile.
    if (!empty($profile)) {
      $item['profile'] = $profile;
      // Convert markdown.
      if (!empty($item['profile']['overview'])) {
        $this->processConversion(['html'], $item['profile'], 'overview');
      }
    }
  }

}
