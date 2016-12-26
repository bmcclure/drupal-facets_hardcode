<?php

namespace Drupal\facets_hardcode;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\facets\FacetInterface;

class FacetsHardcodeSlugHelper {
  /**
   * @param $filterKey
   * @return FacetInterface|null
   */
  public static function getFacetFromFilterKey($filterKey) {
    $facets = \Drupal::entityTypeManager()
      ->getStorage('facets_facet')
      ->loadByProperties(['url_alias' => $filterKey]);

    if (empty($facets)) {
      return NULL;
    }

    return array_shift($facets);
  }

  public static function getSlugFromValue($filterKey, $value) {
    $config = \Drupal::config('facets_hardcode.settings');

    $slug = FALSE;

    $entityType = self::getEntityType($filterKey);

    if (!empty($entityType) && !empty($value)) {
      /** @var ContentEntityInterface $entity */
      $entity = \Drupal::entityTypeManager()->getStorage($entityType)->load($value);

      $slugField = $config->get('slug_field');

      if ($entity->hasField($slugField)) {
        $slug = $entity->get($slugField)->value;
      }
    }

    return $slug ? $slug : $value;
  }

  public static function getValueFromSlug($filterKey, $slug) {
    $config = \Drupal::config('facets_hardcode.settings');

    $value = FALSE;

    $entityType = self::getEntityType($filterKey);

    if (!empty($entityType) && !empty($slug)) {
      $slugField = $config->get('slug_field');

      $results = \Drupal::entityTypeManager()->getStorage($entityType)->loadByProperties([
        $slugField => $slug
      ]);

      if (!empty($results)) {
        $keys = array_keys($results);
        $value = array_shift($keys);
      }
    }

    return $value ? $value : $slug;
  }

  public static function getEntityType($facetUrlAlias) {
    $config = \Drupal::config('facets_hardcode.settings');
    $newLines = '/(\r\n|\r|\n)/';

    $facetEntityTypes = preg_split($newLines, $config->get('facet_entity_types'));

    $type = NULL;

    foreach ($facetEntityTypes as $facetEntityType) {
      list($facetId, $entityType) = explode('|', $facetEntityType);

      if ($facetUrlAlias == $facetId) {
        $type = $entityType;

        break;
      }
    }

    return $type;
  }

  public static function replaceIdsWithSlugs($filters) {
    foreach ($filters as $filterKey => $values) {
      foreach ($values as $index => $value) {
        $filters[$filterKey][$index] = self::getSlugFromValue($filterKey, $value);
      }
    }

    return $filters;
  }

  public static function replaceSlugsWithIds($filters) {
    foreach ($filters as $filterKey => $values) {
      foreach ($values as $index => $value) {
        $filters[$filterKey][$index] = self::getValueFromSlug($filterKey, $value);
      }
    }

    return $filters;
  }
}
