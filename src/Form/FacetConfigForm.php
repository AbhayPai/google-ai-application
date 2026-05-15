<?php

namespace Drupal\google_ai_application\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Facet configuration form.
 */
class FacetConfigForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
    );
  }

  public function getFormId(): string {
    return 'google_ai_application_facet_config_form';
  }

  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $google_ai_application = NULL
  ): array {

    $entity = $this->entityTypeManager
      ->getStorage('google_ai_application')
      ->load($google_ai_application);

    if (!$entity) {
      $this->messenger()->addError($this->t('Application not found.'));
      return [];
    }

    /**
     * -----------------------------
     * Bundles
     * -----------------------------
     */
    $bundles = $entity->get('content_type');
    $bundles = is_array($bundles) ? array_map('trim', $bundles) : [];

    /**
     * -----------------------------
     * Saved associative structure
     * -----------------------------
     */
    $saved = $entity->get('selected_facet_fields');
    $saved = is_array($saved) ? $saved : [];
    $selected_keys = array_keys($saved);

    $options = [];

    /**
     * -----------------------------
     * Build options
     * -----------------------------
     */
    foreach ($bundles as $bundle) {

      $fields = $this->entityFieldManager
        ->getFieldDefinitions('node', $bundle);

      foreach ($fields as $field_name => $definition) {

        if ($definition->getFieldStorageDefinition()->isBaseField()) {
          continue;
        }

        $type = $definition->getType();
        $settings = $definition->getSettings();

        $resolved_type = NULL;

        /**
         * Taxonomy detection
         */
        if ($type === 'entity_reference') {
          if (!empty($settings['target_type']) && $settings['target_type'] === 'taxonomy_term') {
            $resolved_type = 'taxonomy_term_reference';
          }
        }

        /**
         * List detection
         */
        if (str_starts_with($type, 'list_')) {
          $resolved_type = $type;
        }

        if ($resolved_type === NULL) {
          continue;
        }

        $options[$field_name] = $definition->getLabel() . " ({$field_name})";
      }
    }

    /**
     * -----------------------------
     * Default values (keys only)
     * -----------------------------
     */
    $default_value = array_values(array_intersect(
      $selected_keys,
      array_keys($options)
    ));

    $form['selected_facet_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Facet Fields (Taxonomy + List only)'),
      '#options' => $options,
      '#default_value' => $default_value,
    ];

    $form['google_ai_application_id'] = [
      '#type' => 'hidden',
      '#value' => $google_ai_application,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $id = $form_state->getValue('google_ai_application_id');

    $entity = $this->entityTypeManager
      ->getStorage('google_ai_application')
      ->load($id);

    if (!$entity) {
      $this->messenger()->addError($this->t('Application not found.'));
      return;
    }

    $selected = $form_state->getValue('selected_facet_fields');
    $selected = is_array($selected) ? array_filter($selected) : [];

    $bundles = $entity->get('content_type');
    $bundles = is_array($bundles) ? $bundles : [];

    $field_map = [];

    /**
     * Build:
     * field_name => resolved_type
     */
    foreach ($bundles as $bundle) {

      $fields = $this->entityFieldManager
        ->getFieldDefinitions('node', $bundle);

      foreach ($fields as $field_name => $definition) {

        if (!in_array($field_name, $selected, TRUE)) {
          continue;
        }

        if ($definition->getFieldStorageDefinition()->isBaseField()) {
          continue;
        }

        $type = $definition->getType();
        $settings = $definition->getSettings();

        $resolved_type = NULL;

        if ($type === 'entity_reference') {
          if (!empty($settings['target_type']) && $settings['target_type'] === 'taxonomy_term') {
            $resolved_type = 'taxonomy_term_reference';
          }
          else {
            $resolved_type = 'entity_reference:' . ($settings['target_type'] ?? 'unknown');
          }
        }

        if (str_starts_with($type, 'list_')) {
          $resolved_type = $type;
        }

        if ($resolved_type === NULL) {
          continue;
        }

        $field_map[$field_name] = $resolved_type;
      }
    }

    /**
     * SAVE ASSOCIATIVE ARRAY
     */
    $entity->set('selected_facet_fields', $field_map);
    $entity->save();

    $this->messenger()->addStatus($this->t('Facet fields saved successfully.'));
  }
}
