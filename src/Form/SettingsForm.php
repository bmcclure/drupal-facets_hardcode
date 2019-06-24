<?php

namespace Drupal\facets_hardcode\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'facets_hardcode_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('facets_hardcode.settings')
      ->set('slug_field', $form_state->getValue('slug_field'))
      ->set('dynamic_facets_url_identifier', $form_state->getValue('dynamic_facets_url_identifier'))
      ->set('facet_source_base_paths', $form_state->getValue('facet_source_base_paths'))
      ->set('hardcoded_facets', $form_state->getValue('hardcoded_facets'))
      ->set('facet_entity_types', $form_state->getValue('facet_entity_types'))
      ->set('unspecified_value', $form_state->getValue('unspecified_value'))
      ->set('leave_facets_in_path', (int) $form_state->getValue('leave_facets_in_path'))
      ->set('navigation_facets', $form_state->getValue('navigation_facets'))
      ->set('canonical_facets', $form_state->getValue('canonical_facets'))
      ->set('canonical_level', $form_state->getValue('canonical_level'))
      ->set('remove_page_parameter', $form_state->getValue('remove_page_parameter'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['facets_hardcode.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('facets_hardcode.settings');

    $form['slug_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Slug field'),
      '#description' => $this->t('When the facet is an entity reference, use this field on the destination entity if available to get a URL slug.'),
      '#default_value' => $config->get('slug_field'),
    ];

    $form['dynamic_facets_url_identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dynamic facets URL identifier'),
      '#description' => $this->t('Any facet not hardcoded will be separated from the hardcoded facets by this identifier.'),
      '#default_value' => $config->get('dynamic_facets_url_identifier'),
    ];

    $form['facet_source_base_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Facet source base paths'),
      '#description' => $this->t('Each line should consist of a facet source ID followed by a hardcoded base path to use for the facet source, separated by a pipe, one per line.'),
      '#default_value' => $config->get('facet_source_base_paths'),
    ];

    $form['hardcoded_facets'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Hardcoded facets'),
      '#description' => $this->t('Each line should consist of an internal path or alias, a facet id, and a facet value, all pipe-separated, one per line.'),
      '#default_value' => $config->get('hardcoded_facets'),
    ];

    $form['facet_entity_types'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Facet entity types'),
      '#description' => $this->t('A list of facet URL identifiers to the entity type they are related to.'),
      '#default_value' => $config->get('facet_entity_types'),
    ];

    $form['unspecified_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unspecified value'),
      '#description' => $this->t('If a hardcoded facet value is not present, use this value instead.'),
      '#default_value' => $config->get('unspecified_value'),
    ];

    $form['leave_facets_in_path'] = [
      '#type' => 'number',
      '#title' => $this->t('Leave facets in path'),
      '#description' => $this->t('The number of hardcoded facets to leave in the path (to match up with actual pages in Drupal).'),
      '#default_value' => $config->get('leave_facets_in_path'),
      '#min' => 0,
    ];

    $form['navigation_facets'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Navigation facets'),
      '#description' => $this->t('These facet links will always include the facet, even if already engaged.'),
      '#default_value' => $config->get('navigation_facets'),
    ];

    $form['canonical_facets'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Canonical facets'),
      '#description' => $this->t('Enter the url identifiers of the facets to retain in canonical links, one per line.'),
      '#default_value' => $config->get('canonical_facets'),
    ];

    $form['canonical_level'] = [
      '#type' => 'number',
      '#title' => $this->t('Canonical level'),
      '#description' => $this->t('How many facets should be retained in the canonical URL of the mete tags as you browse deeper?'),
      '#default_value' => $config->get('canonical_level'),
    ];

    $form['remove_page_parameter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove "page" parameter'),
      '#description' => $this->t('Removes any "page" query string that would otherwise be present in a facet link.'),
      '#default_value' => $config->get('remove_page_parameter'),
    ];

    return parent::buildForm($form, $form_state);
  }
}
