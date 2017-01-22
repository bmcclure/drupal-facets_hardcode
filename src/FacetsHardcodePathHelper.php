<?php

namespace Drupal\facets_hardcode;

use Drupal\facets\FacetInterface;
use Drupal\facets\FacetSource\FacetSourcePluginInterface;
use Drupal\facets\Result\ResultInterface;

class FacetsHardcodePathHelper {
  public static function getFacetSourcePath(FacetSourcePluginInterface $facetSource, $facetSourceId) {
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

    $slug = FacetsHardcodeSlugHelper::getSlugFromValue($filter_key, $result->getRawValue());

    if ($result->isActive()) {
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
    }

    foreach ($facets['dynamic'] as $key => $values) {
      foreach ($values as $value) {
        $path .= "/$key/$value";
      }
    }

    return $path;
  }

  public static function filterFacetsFromPath($path) {
    $config = \Drupal::config('facets_hardcode.settings');
    $newLines = '/(\r\n|\r|\n)/';
    $basePaths = preg_split($newLines, $config->get('facet_source_base_paths'));

    foreach ($basePaths as $basePath) {
      list($facetSourceId, $facetSourcePath) = explode('|', $basePath);

      if ($path && strpos($path, $facetSourcePath, 0) === 0) {
        $facetsPath = str_replace($facetSourcePath, '', $path);

        if ($path != $facetSourcePath) {
          $path = $facetSourcePath;

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

    return $path;
  }
}
