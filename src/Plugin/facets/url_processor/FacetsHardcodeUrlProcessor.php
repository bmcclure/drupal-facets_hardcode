<?php

/**
 * @file
 * Contains Drupal\facets_hardcode\Plugin\facets\url_processor\FacetsHardcodeUrlProcessor.
 */

namespace Drupal\facets_hardcode\Plugin\facets\url_processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\facets\FacetInterface;
use Drupal\facets\UrlProcessor\UrlProcessorPluginBase;
use Drupal\facets_hardcode\FacetsHardcodePathHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pretty paths URL processor.
 *
 * @FacetsUrlProcessor(
 *   id = "facets_hardcode",
 *   label = @Translation("Hardcoded facets"),
 *   description = @Translation("Hardcoded facets supports shorter pretty paths with hardcoded path parts."),
 * )
 */
class FacetsHardcodeUrlProcessor extends UrlProcessorPluginBase {

  /**
   * @var array
   *   An array containing the active filters
   */
  protected $active_filters = [];

  /**
   * Constructs a new object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Request $request
   *   The request object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\facets\Exception\InvalidProcessorException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Request $request, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request, $entity_type_manager);

    $this->initializeActiveFilters($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getMasterRequest(),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrls(FacetInterface $facet, array $results) {

    // No results are found for this facet, so don't try to create urls.
    if (empty($results)) {
      return [];
    }

    $path = FacetsHardcodePathHelper::getFacetedPath();
    $path_prefix = FacetsHardcodePathHelper::getFacetedPathPrefix();
    $sourcePath = FacetsHardcodePathHelper::getFacetSourcePath($facet->getFacetSource(), $facet->getFacetSourceId());
    $filters = substr($path, (strlen($sourcePath)));
    $config = \Drupal::config('facets_hardcode.settings');

    /** @var \Drupal\facets\Result\ResultInterface $result */
    foreach ($results as &$result) {
      $filters_current_result = $filters;

      $filters_current_result = FacetsHardcodePathHelper::updateFilterString($facet, $result, $filters_current_result);

      $query = $this->request->query->all();

      if ($config->get('remove_page_parameter')) {
        unset($query['page']);
      }

      $url = Url::fromUri('base:' . $path_prefix . $sourcePath . $filters_current_result)
        ->setOption('query', $query);
      $result->setUrl($url);
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveItems(FacetInterface $facet) {
    // Get the filter key of the facet.
    if (isset($this->active_filters[$facet->getUrlAlias()])) {
      foreach ($this->active_filters[$facet->getUrlAlias()] as $value) {
        $facet->setActiveItem(trim($value, '"'));
      }
    }
  }

  /**
   * Initialize the active filters.
   *
   * Get all the filters that are active. This method only get's all the
   * filters but doesn't assign them to facets. In the processFacet method the
   * active values for a specific facet are added to the facet.
   *
   * @param array $configuration
   *   The configuration array for the processor.
   */
  protected function initializeActiveFilters(array $configuration) {
    if (!$configuration['facet']) {
      return;
    }

    /** @var FacetInterface $facet */
    $facet = $configuration['facet'];
    $facet_source_path = FacetsHardcodePathHelper::getFacetSourcePath($facet->getFacetSource(), $facet->getFacetSourceId());
    $path = FacetsHardcodePathHelper::getFacetedPath();

    if(strpos($path, $facet_source_path, 0) === 0) {
      $activeFilters = FacetsHardcodePathHelper::getActiveFilters($path, $facet->getFacetSource(), $facet->getFacetSourceId());

      foreach ($activeFilters as $key => $values) {
        $this->active_filters[$key] = $values;
      }
    }
  }
}
