<?php

namespace Drupal\google_ai_application;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the view for Google AI Application entities.
 */
class GoogleAiApplicationViewBuilder extends EntityViewBuilder {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function createInstance(ContainerInterface $container, $entity_type) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {

    $content_types = (array) $entity->get('content_type');

    $storage = $this->entityTypeManager->getStorage('node');

    $total_nodes = 0;
    $content_type_counts = [];

    foreach ($content_types as $bundle) {

      if (empty($bundle)) {
        continue;
      }

      $count = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $bundle)
        ->count()
        ->execute();

      $content_type_counts[] = ucfirst($bundle) . ': ' . $count;
      $total_nodes += $count;
    }

    return [
      '#theme' => 'google_ai_application',

      // Core config
      '#label' => $entity->label(),
      '#project_name' => $entity->get('project_name'),
      '#data_store_name' => $entity->get('data_store_name'),
      '#schema_name' => $entity->get('schema_name'),
      '#location' => $entity->get('location'),
      '#branch_name' => $entity->get('branch_name'),
      '#app_name' => $entity->get('app_name'),

      '#content_types' => implode(', ', array_filter($content_types)),

      // Computed stats
      '#total_nodes' => $total_nodes,
      '#content_type_counts' => $content_type_counts,
    ];
  }
}
