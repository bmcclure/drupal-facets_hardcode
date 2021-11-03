<?php

namespace Drupal\facets_hardcode;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\facets\FacetInterface;

class FacetsHardcodeSlugHelper {

  /**
   * @param $filterKey
   * @return FacetInterface|null
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
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

  /**
   * @param $filterKey
   * @param $value
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public static function getSlugFromValue($filterKey, $value) {
    $config = \Drupal::config('facets_hardcode.settings');

    $slug = FALSE;

    $entity_type = self::getEntityType($filterKey);
    list($entity_type) = explode(':', $entity_type);

    if (!empty($entity_type) && !empty($value)) {
      /** @var ContentEntityInterface $entity */
      $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($value);

      $slugField = $config->get('slug_field');

      if ($entity->hasField($slugField)) {
        $slug = $entity->get($slugField)->value;
      }
    }

    return $slug ? $slug : $value;
  }

  /**
   * @param $filterKey
   * @param $slug
   * @return bool|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public static function getValueFromSlug($filterKey, $slug) {
    $config = \Drupal::config('facets_hardcode.settings');

    $value = FALSE;

    $entity_type = self::getEntityType($filterKey);
    $bundle = NULL;

    if (strpos($entity_type, ':') !== FALSE) {
      list($entity_type, $bundle) = explode(':', $entity_type);
    }

    if (!empty($entity_type) && !empty($slug) && !empty($bundle)) {
      $slugField = $config->get('slug_field');

      $definition = \Drupal::entityTypeManager()->getDefinition($entity_type);

      if ($definition) {
        $bundle_key = $definition->getKey('bundle') ?? 'bundle';

        $results = \Drupal::entityTypeManager()
          ->getStorage($entity_type)
          ->loadByProperties([
            $slugField => $slug,
            $bundle_key => $bundle,
          ]);

        if (!empty($results)) {
          $keys = array_keys($results);
          $value = array_shift($keys);
        }
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
      list($facetId, $entityType, $bundle) = explode('|', $facetEntityType);

      if ($facetUrlAlias == $facetId) {
        $type = $entityType . ':' . $bundle;

        break;
      }
    }

    return $type;
  }

  /**
   * @param $filters
   * @return mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public static function replaceIdsWithSlugs($filters) {
    foreach ($filters as $filterKey => $values) {
      foreach ($values as $index => $value) {
        $filters[$filterKey][$index] = self::getSlugFromValue($filterKey, $value);
      }
    }

    return $filters;
  }

  /**
   * @param $filters
   * @return mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public static function replaceSlugsWithIds($filters) {
    foreach ($filters as $filterKey => $values) {
      foreach ($values as $index => $value) {
        $filters[$filterKey][$index] = self::getValueFromSlug($filterKey, $value);
      }
    }

    return $filters;
  }

}
