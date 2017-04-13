<?php

/**
 * @file
 * Contains \Drupal\url_alter_test\PathProcessorTest.
 */

namespace Drupal\facets_hardcode;

use Drupal\Core\Path\AliasManager;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Path processor for facets_hardcode.
 */
class FacetsHardcodePathProcessor implements InboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $alias = \Drupal::service('path.alias_storage')->load([
      'alias' => $path,
    ]);

    if (!is_array($alias)) {
      $new_path = FacetsHardcodePathHelper::filterFacetsFromPath($path);

      if ($new_path != $path) {
        \Drupal::request()->attributes->set('_disable_route_normalizer', TRUE);

        $path = $new_path;
      }
    }

    return $path;
  }
}
