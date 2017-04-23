<?php

namespace Drupal\facets_hardcode;

use Drupal\facets\FacetInterface;
use Drupal\facets\FacetSource\FacetSourcePluginInterface;
use Drupal\facets\Result\ResultInterface;

class FacetsHardcodePathHelper {
  public static function isFacetPath($path = NULL) {
    if (is_null($path)) {
      $path = \Drupal::request()->getPathInfo();
    }

    $is_facet_path = FALSE;

    $config = \Drupal::config('facets_hardcode.settings');
    $newLines = '/(\r\n|\r|\n)/';
    $basePaths = preg_split($newLines, $config->get('facet_source_base_paths'));

    foreach ($basePaths as $basePath) {
      list(,$facetSourcePath) = explode('|', $basePath);

      $path = str_replace(self::getFacetedPathPrefix(), '', $path);

      if ($path && strpos($path, $facetSourcePath, 0) === 0) {
        $suffix = str_replace($facetSourcePath, '', $path);
        $parts = explode('/', trim($suffix, '/'));
        if (!isset($parts[1]) || $parts[1] === 'f') {
          $is_facet_path = TRUE;
        }

        break;
      }
    }

    $hmm = 'hmm';

    return $is_facet_path;
  }

  public static function getFacetSourcePath($facetSource, $facetSourceId) {
    $sourcePath = NULL;

    $config = \Drupal::config('facets_hardcode.settings');
    $newLines = '/(\r\n|\r|\n)/';
    $facet_source_base_paths = preg_split($newLines, $config->get('facet_source_base_paths'));

    foreach ($facet_source_base_paths as $facet_source_base_path) {
      list($sourceId, $basePath) = explode('|', $facet_source_base_path);

      if ($sourceId == $facetSourceId) {
        $sourcePath = $basePath;

        break;
      }
    }

    if (is_null($sourcePath)) {
      //$sourcePath = $facetSource->getPath();
    }

    return $sourcePath;
  }

  public static function getHardcodePath($facetSourceId) {
    $config = \Drupal::config('facets_hardcode.settings');
    $newLines = '/(\r\n|\r|\n)/';

    $hardcodedFacets = preg_split($newLines, $config->get('hardcoded_facets'));
    $hardcodePath = [];

    foreach ($hardcodedFacets as $hardcodedFacet) {
      list($hardcodedSourceId, $facetPath) = explode('|', $hardcodedFacet);

      if ($hardcodedSourceId == $facetSourceId) {
        $hardcodePath = explode('/', trim($facetPath, '/'));
        break;
      }
    }

    return $hardcodePath;
  }

  public static function getActiveFilters($path, FacetSourcePluginInterface $facetSource, $facetSourceId) {
    $config = \Drupal::config('facets_hardcode.settings');
    $facetSourcePath = FacetsHardcodePathHelper::getFacetSourcePath($facetSource, $facetSourceId);
    $filters = substr($path, (strlen($facetSourcePath) + 1));
    $parts = explode('/', $filters);

    $dynamicId = $config->get('dynamic_facets_url_identifier');

    $hardcodePath = self::getHardcodePath($facetSourceId);

    $activeFilters = [];
    $hardcodedSection = TRUE;
    $isKey = TRUE;
    $key = '';
    foreach($parts as $index => $part){
      if ($part == $dynamicId) {
        $hardcodedSection = FALSE;

        continue;
      }

      if ($hardcodedSection) {
        if (isset($hardcodePath[$index])) {
          $activeFilters[$hardcodePath[$index]][] = $part;
        }
      } else {
        if ($isKey) {
          $key = $part;
        } else {
          $activeFilters[$key][] = $part;
        }

        $isKey = !$isKey;
      }
    }

    $activeFilters = FacetsHardcodeSlugHelper::replaceSlugsWithIds($activeFilters);

    return $activeFilters;
  }



  public static function updateFilterString(FacetInterface $facet, ResultInterface $result, $filterString) {
    $config = \Drupal::config('facets_hardcode.settings');
    $filter_key = $facet->getUrlAlias();
    $facetSourceId = $facet->getFacetSourceId();

    $filters = self::explodeFilterString($filterString, $facetSourceId);
    $unspecifiedValue = $config->get('unspecified_value');

    $keepFacet = self::keepFacetInUrl($facet);

    $slug = FacetsHardcodeSlugHelper::getSlugFromValue($filter_key, $result->getRawValue());

    if ($result->isActive()) {
      if ($keepFacet) {
        $filters = self::removeFacetsAfterActive($filters, $filter_key, $slug);
      } else {
        if (isset($filters['hardcoded'][$filter_key])) {
          $index = array_search($slug, $filters['hardcoded'][$filter_key]);

          if ($index !== FALSE) {
            $filters['hardcoded'][$filter_key][$index] = $unspecifiedValue;
          }
        }

        if (isset($filters['dynamic'][$filter_key])) {
          $index = array_search($slug, $filters['dynamic'][$filter_key]);

          if ($index !== FALSE) {
            unset($filters['dynamic'][$filter_key][$index]);

            if (empty($filters['dynamic'][$filter_key])) {
              unset($filters['dynamic'][$filter_key]);
            }
          }
        }
      }

      $filterString = self::implodeFilterString($filters, $facetSourceId);
    } else {
      $set = FALSE;

      if (isset($filters['hardcoded'][$filter_key])) {
        $index = array_search($unspecifiedValue, $filters['hardcoded'][$filter_key]);

        if ($index !== FALSE) {
          $filters['hardcoded'][$filter_key][$index] = $slug;

          $set = TRUE;
        }
      }

      if (!$set) {
        $filters['dynamic'][$filter_key][] = $slug;
      }

      $filterString = self::implodeFilterString($filters, $facetSourceId);
    }

    return $filterString;
  }

  public static function removeFacetsAfterActive(array $filters, $key, $slug) {
    if (self::isHardcoded($filters, $key, $slug)) {
      $filters['dynamic'] = [];

      $after = FALSE;
      foreach ($filters['hardcoded'] as $filterKey => $values) {
        if ($key == $filterKey) {
          $after = TRUE;
          continue;
        }

        if ($after) {
          unset($filters['hardcoded'][$filterKey]);
        }
      }

      $afterValue = FALSE;
      foreach ($filters['hardcoded'][$key] as $index => $value) {
        if ($slug == $value) {
          $afterValue = TRUE;
          continue;
        }

        if ($afterValue) {
          unset($filters['hardcoded'][$key][$index]);
        }
      }
    } else {
      $after = FALSE;
      foreach ($filters['dynamic'] as $filterKey => $values) {
        if ($key == $filterKey) {
          $after = TRUE;

          continue;
        }

        if ($after) {
          unset($filters['dynamic'][$filterKey]);
        }
      }

      $afterValue = FALSE;
      foreach ($filters['dynamic'][$key] as $index => $value) {
        if ($slug == $value) {
          $afterValue = TRUE;
          continue;
        }

        if ($afterValue) {
          unset($filters['dynamic'][$key][$index]);
        }
      }
    }

    return $filters;
  }

  public static function isHardcoded(array $filters, $key, $slug) {
    $index = array_search($slug, $filters['hardcoded'][$key]);

    return $index !== FALSE;
  }

  public static function keepFacetInUrl(FacetInterface $facet) {
    $config = \Drupal::config('facets_hardcode.settings');
    $newLines = '/(\r\n|\r|\n)/';
    $navigationFacets = preg_split($newLines, $config->get('navigation_facets'));

    return (in_array($facet->id(), $navigationFacets));
  }

  public static function explodeFilterString($filterString, $facetSourceId) {
    $config = \Drupal::config('facets_hardcode.settings');
    $hardcodePath = self::getHardcodePath($facetSourceId);
    $parts = explode('/', trim($filterString, '/'));
    $dynamicId = $config->get('dynamic_facets_url_identifier');

    $facets = [
      'hardcoded' => [],
      'dynamic' => [],
    ];

    $hardcodedSection = TRUE;
    $isKey = TRUE;
    $key = '';
    foreach($parts as $index => $part) {
      if ($part == $dynamicId) {
        $hardcodedSection = FALSE;

        continue;
      }

      if ($hardcodedSection) {
        if (isset($hardcodePath[$index])) {
          $facets['hardcoded'][$hardcodePath[$index]][] = $part;
        }
      } else {
        if ($isKey) {
          $key = $part;
        } else {
          $facets['dynamic'][$key][] = $part;
        }

        $isKey = !$isKey;
      }
    }

    return $facets;
  }

  public static function implodeFilterString(array $facets, $facetSourceId) {
    $config = \Drupal::config('facets_hardcode.settings');
    $hardcodePath = self::getHardcodePath($facetSourceId);
    $unspecifiedValue = $config->get('unspecified_value');

    $path = '';

    foreach ($hardcodePath as $index => $hardcodePart) {
      if (!empty($facets['hardcoded'][$hardcodePart])) {
        $path .= '/' . array_shift($facets['hardcoded'][$hardcodePart]);
      } else {
        $path .= "/$unspecifiedValue";
      }
    }

    if (!empty($facets['dynamic'])) {
      $path .= '/' . $config->get('dynamic_facets_url_identifier');
      ksort($facets['dynamic']);
    }

    foreach ($facets['dynamic'] as $key => $values) {
      sort($values);
      foreach ($values as $value) {
        $path .= "/$key/$value";
      }
    }

    return $path;
  }

  public static function filterFacetsFromPath($path, $removeLanguagePrefix = TRUE) {
    $config = \Drupal::config('facets_hardcode.settings');
    $newLines = '/(\r\n|\r|\n)/';
    $basePaths = preg_split($newLines, $config->get('facet_source_base_paths'));
    $prefix = self::getFacetedPathPrefix();
    $path = str_replace($prefix, '', $path);

    foreach ($basePaths as $basePath) {
      list($facetSourceId, $facetSourcePath) = explode('|', $basePath);

      if ($path && strpos($path, $facetSourcePath, 0) === 0) {
        $facetsPath = str_replace($facetSourcePath, '', $path);

        if ($path != $facetSourcePath) {
          $path = $prefix . $facetSourcePath;

          if ($config->get('leave_facets_in_path')) {
            $filters = self::explodeFilterString($facetsPath, $facetSourceId);
            $leaveIn = $config->get('leave_facets_in_path');
            $unspecifiedValue = $config->get('unspecified_value');
            $hardcodePath = self::getHardcodePath($facetSourceId);
            $i = 0;
            while ($i < $leaveIn) {
              if (isset($hardcodePath[$i])) {
                $path .= '/' . array_shift($filters['hardcoded'][$hardcodePath[$i]]);
              } else {
                $path .= '/' . $unspecifiedValue;
              }

              $i++;
            }
          }
        }
      }
    }

    if (!$removeLanguagePrefix) {
      $path = $prefix . $path;
    }

    return $path;
  }

  /**
   * Gets the full faceted path from the current request, minus any prefixes.
   *
   * @return string
   */
  public static function getFacetedPath() {
    $path = \Drupal::request()->getPathInfo();

    // Remove any prefix from the path
    $current_path = \Drupal::service('path.current')->getPath();
    $result = \Drupal::service('path.alias_manager')->getAliasByPath($current_path);
    if ($pos = strpos($path, $result)) {
      $path = substr($path, $pos);
    }

    return $path;
  }

  public static function getFacetedPathPrefix() {
    $path = \Drupal::request()->getPathInfo();

    $prefix = '';


    // @todo: Uncomment or remove. If we're not on a real path, this won't help.
    // Remove any prefix from the path
    //$current_path = \Drupal::service('path.current')->getPath();
    //$result = \Drupal::service('path.alias_manager')->getAliasByPath($current_path);
    //if ($pos = strpos($path, $result)) {
    //  $prefix = substr($path, 0, $pos);
    //}

    // Hardcoded for now
    if (strpos($path, '/es/') === 0) {
      $prefix = '/es';
    }

    return $prefix;
  }
}
