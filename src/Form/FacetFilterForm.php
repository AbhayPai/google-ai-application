<?php

namespace Drupal\google_ai_application\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Facet Filter Form.
 */
class FacetFilterForm extends FormBase {

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

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'google_ai_application_facet_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $app_id = NULL
  ): array {

    $entity = $this->loadApplication($app_id);

    if (!$entity) {
      return $form;
    }

    $facet_config = $this->getFacetConfig($entity);
    $bundles = $this->getBundles($entity);

    $nodes = $this->loadNodes($bundles);

    $facet_data = $this->buildFacetData(
      $facet_config,
      $bundles,
      $nodes
    );

    $this->buildFacetElements($form, $facet_data);

    $form['actions'] = $this->buildActions();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $values = $form_state->getValues();

    $query = [];

    foreach ($values as $key => $value) {

      if (!is_array($value)) {
        continue;
      }

      $selected = array_filter($value);

      if (!empty($selected)) {
        $query[$key] = implode(',', $selected);
      }
    }

    $request = $this->getRequest();

    if ($request->query->has('search_text')) {
      $query['search_text'] = $request->query->get('search_text');
    }

    $form_state->setRedirect('<current>', [], [
      'query' => $query,
    ]);
  }

  /**
   * Load application entity.
   */
  private function loadApplication(?string $app_id): ?object {

    return $this->entityTypeManager
      ->getStorage('google_ai_application')
      ->load($app_id);
  }

  /**
   * Get configured facet fields.
   */
  private function getFacetConfig(object $entity): array {

    $config = $entity->get('selected_facet_fields') ?? [];

    return is_array($config) ? $config : [];
  }

  /**
   * Get configured bundles.
   */
  private function getBundles(object $entity): array {

    $bundles = $entity->get('content_type') ?? [];

    return is_array($bundles) ? $bundles : [];
  }

  /**
   * Load nodes for bundles.
   */
  private function loadNodes(array $bundles): array {

    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->accessCheck(TRUE);

    if (!empty($bundles)) {
      $query->condition('type', $bundles, 'IN');
    }

    $nids = $query
      ->range(0, 300)
      ->execute();

    return $storage->loadMultiple($nids);
  }

  /**
   * Build complete facet data.
   */
  private function buildFacetData(
    array $facet_config,
    array $bundles,
    array $nodes
  ): array {

    $facet_data = [];

    foreach ($facet_config as $field_name => $stored_type) {

      $definition = $this->getFieldDefinition(
        $field_name,
        $bundles
      );

      if (!$definition) {
        continue;
      }

      $field_type = $definition->getType();

      $facet_data[$field_name] = [
        'label' => $definition->getLabel(),
        'values' => [],
      ];

      /**
       * LIST FIELD
       */
      if (str_starts_with($field_type, 'list_')) {

        $facet_data[$field_name]['values'] = $this->getListFieldValues(
          $definition
        );

        continue;
      }

      /**
       * TAXONOMY FIELD
       */
      if (
        $field_type === 'entity_reference'
        && ($definition->getSetting('target_type') ?? NULL) === 'taxonomy_term'
      ) {

        $facet_data[$field_name]['values'] = $this->getTaxonomyFieldValues(
          $field_name,
          $nodes
        );
      }
    }

    return $facet_data;
  }

  /**
   * Get field definition from bundles.
   */
  private function getFieldDefinition(
    string $field_name,
    array $bundles
  ): mixed {

    foreach ($bundles as $bundle) {

      $definitions = $this->entityFieldManager
        ->getFieldDefinitions('node', $bundle);

      if (isset($definitions[$field_name])) {
        return $definitions[$field_name];
      }
    }

    return NULL;
  }

  /**
   * Get allowed values for list field.
   */
  private function getListFieldValues(mixed $definition): array {

    $allowed_values = $definition
      ->getFieldStorageDefinition()
      ->getSetting('allowed_values');

    return is_array($allowed_values)
      ? $allowed_values
      : [];
  }

  /**
   * Get taxonomy values used in nodes.
   */
  private function getTaxonomyFieldValues(
    string $field_name,
    array $nodes
  ): array {

    $used_tids = [];

    foreach ($nodes as $node) {

      if (!$node instanceof NodeInterface) {
        continue;
      }

      if (!$node->hasField($field_name)) {
        continue;
      }

      $field = $node->get($field_name);

      if ($field->isEmpty()) {
        continue;
      }

      foreach ($field->getValue() as $item) {

        if (!empty($item['target_id'])) {
          $used_tids[$item['target_id']] = $item['target_id'];
        }
      }
    }

    if (empty($used_tids)) {
      return [];
    }

    $terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadMultiple($used_tids);

    $values = [];

    foreach ($terms as $term) {
      $values[$term->id()] = $term->label();
    }

    return $values;
  }

  /**
   * Build facet form elements.
   */
  private function buildFacetElements(
    array &$form,
    array $facet_data
  ): void {

    foreach ($facet_data as $field_name => $data) {

      if (empty($data['values'])) {
        continue;
      }

      $selected = $this->getSelectedValues($field_name);

      $form[$field_name] = [
        '#type' => 'details',
        '#title' => $data['label'],
        '#open' => TRUE,
      ];

      $form[$field_name][$field_name] = [
        '#type' => 'checkboxes',
        '#options' => $data['values'],
        '#default_value' => $selected,
      ];
    }
  }

  /**
   * Get selected query values.
   */
  private function getSelectedValues(string $field_name): array {

    $query_value = $this->getRequest()
      ->query
      ->get($field_name);

    if (empty($query_value)) {
      return [];
    }

    return explode(',', $query_value);
  }

  /**
   * Build actions.
   */
  private function buildActions(): array {

    return [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Apply filters'),
      ],
    ];
  }

}
