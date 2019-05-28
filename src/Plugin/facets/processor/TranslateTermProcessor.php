<?php

namespace Drupal\facets_hardcode\Plugin\facets\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transforms the results to show the translated term label.
 *
 * @FacetsProcessor(
 *   id = "translate_term",
 *   label = @Translation("Transform term id into label"),
 *   description = @Translation("Show term name instead of entity id."),
 *   stages = {
 *     "build" = 5
 *   }
 * )
 */
class TranslateTermProcessor extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    $language_interface = $this->languageManager->getCurrentLanguage();

    $ids = [];

    /** @var \Drupal\facets\Result\ResultInterface $result */
    foreach ($results as $delta => $result) {
      $ids[$delta] = $result->getRawValue();
    }

    $entity_type = 'taxonomy_term';

    // Load all indexed entities of this type.
    $entities = $this->entityTypeManager
      ->getStorage($entity_type)
      ->loadMultiple($ids);

    // Loop over all results.
    foreach ($results as $i => $result) {
      if (!isset($entities[$ids[$i]])) {
        unset($results[$i]);
        continue;
      }

      /** @var \Drupal\Core\Entity\ContentEntityBase $entity */
      $entity = $entities[$ids[$i]];

      // Check for a translation of the entity and load that instead if one's
      // found.
      if ($entity instanceof TranslatableInterface && $entity->hasTranslation($language_interface->getId())) {
        $entity = $entity->getTranslation($language_interface->getId());
      }

      // Overwrite the result's display value.
      $results[$i]->setDisplayValue($entity->label());
    }

    // Return the results with the new display values.
    return $results;
  }

}
