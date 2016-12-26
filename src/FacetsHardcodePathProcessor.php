<?php

/**
 * @file
 * Contains \Drupal\url_alter_test\PathProcessorTest.
 */

namespace Drupal\facets_hardcode;

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
    $path = FacetsHardcodePathHelper::filterFacetsFromPath($path);

    return $path;
  }
}
