<?php

namespace Drupal\facets_hardcode;

class FacetsHardcodeMetatagHelper {
  public static function updateCanonicalUrls(array &$build) {
    if (isset($build['#attached']['html_head_link'])) {
      foreach ($build['#attached']['html_head_link'] as $key => $item) {
        if (isset($item[0]['rel']) && in_array($item[0]['rel'], array('canonical', 'shortlink'))) {
          $path = $build['#attached']['html_head_link'][$key][0]['href'];

          list($source_id, $base_path) = self::getFacetSourceInfo($path);
          if (!is_null($source_id)) {
            $path = self::updateCanonicalUrl($path, $source_id, $base_path);
            $build['#attached']['html_head_link'][$key][0]['href'] = $path;
          }
        }
      }
    }
  }

  public static function setRobotsMetatag(&$build, $url) {
    list($source_id, $base_path) = self::getFacetSourceInfo($url);

    if (!is_null($source_id) && !self::isCanonical($url, $source_id, $base_path)) {
      $robots = [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'robots',
          'content' => 'noindex, nofollow',
        ]
      ];

      $build['#attached']['html_head'][] = [$robots, 'robots'];
    }
  }

  public static function getFacetSourceInfo($path) {
    $config = \Drupal::config('facets_hardcode.settings');
    $new_lines = '/(\r\n|\r|\n)/';
    $base_paths = preg_split($new_lines, $config->get('facet_source_base_paths'));
    $faceted_path = FacetsHardcodePathHelper::getFacetedPath($path);
    $source_id = NULL;
    $base_path = NULL;

    foreach ($base_paths as $base_path_item) {
      if (strpos($base_path_item, '|') === FALSE) {
        continue;
      }

      list($facet_source_id, $base_path) = explode('|', $base_path_item);

      if (strpos($faceted_path, $base_path) === 0) {
        $source_id = $facet_source_id;
        break;
      }
    }

    return [$source_id, $base_path];
  }

  public static function isCanonical($url, $facet_source_id, $base_path) {
    $faceted_path = FacetsHardcodePathHelper::getFacetedPath($url);
    $filter_string = substr($faceted_path, strlen($base_path));

    $config = \Drupal::config('facets_hardcode.settings');
    $newLines = '/(\r\n|\r|\n)/';
    $canonical_facets = preg_split($newLines, $config->get('canonical_facets'));

    $canonical = TRUE;

    if (!empty($filter_string)) {
      $filters = FacetsHardcodePathHelper::explodeFilterString($filter_string, $facet_source_id);

      $canonical_level = $config->get('canonical_level') ?: 2;
      $count = 0;

      foreach (['hardcoded', 'dynamic'] as $type) {
        if (array_key_exists($type, $filters)) {
          foreach ($filters[$type] as $key => $values) {
            if (!in_array($key, $canonical_facets) || empty($values)) {
              $canonical = FALSE;
              break;
            }

            foreach ($values as $index => $value) {
              $count++;

              if ($count > $canonical_level) {
                $canonical = FALSE;
                break;
              }
            }
          }
        }
      }
    }

    return $canonical;
  }

  public static function updateCanonicalUrl($url, $facet_source_id, $base_path) {
    $faceted_path = FacetsHardcodePathHelper::getFacetedPath();
    $filter_string = substr($faceted_path, strlen($base_path));

    $config = \Drupal::config('facets_hardcode.settings');
    $newLines = '/(\r\n|\r|\n)/';
    $canonical_facets = preg_split($newLines, $config->get('canonical_facets'));

    if (!empty($filter_string)) {
      $filters = FacetsHardcodePathHelper::explodeFilterString($filter_string, $facet_source_id);

      $canonical_level = $config->get('canonical_level') ?: 2;
      $count = 0;

      foreach (['hardcoded', 'dynamic'] as $type) {
        if (array_key_exists($type, $filters)) {
          foreach ($filters[$type] as $key => $values) {
            if (!in_array($key, $canonical_facets)) {
              unset($filters[$type][$key]);
            }

            foreach ($values as $index => $value) {
              $count++;

              if ($count > $canonical_level) {
                unset($filters[$type][$key][$index]);

                if (empty($filters[$type][$key])) {
                  unset($filters[$type][$key]);
                }
              }
            }
          }
        }
      }

      $url = $base_path . FacetsHardcodePathHelper::implodeFilterString($filters, $facet_source_id);
    }

    return $url;
  }
}
