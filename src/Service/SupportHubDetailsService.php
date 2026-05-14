<?php

namespace Drupal\support_hub\Service;

use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\Core\Entity\EntityInterface;

class SupportHubDetailsService {

  public function getNodeDetails(EntityInterface $entity): ?array {

    if (!$entity instanceof NodeInterface) {
      return NULL;
    }

    return [
      'id' => $entity->uuid(),
      'structData' => [
        'nid' => $entity->id(),
        'title' => $entity->label(),
        'category' => $this->getTaxonomyLabels($entity, 'field_category'),
        'created' => $entity->getCreatedTime(),
        'changed' => $entity->getChangedTime(),
      ],
    ];
  }

  private function getTaxonomyLabels(EntityInterface $entity, string $field): ?array {

    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return NULL;
    }

    $labels = [];

    foreach ($entity->get($field)->referencedEntities() as $term) {
      if ($term instanceof TermInterface) {
        $labels[] = $term->label();
      }
    }

    return !empty($labels) ? $labels : NULL;
  }

}
