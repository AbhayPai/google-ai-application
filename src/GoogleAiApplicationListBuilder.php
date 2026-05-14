<?php

namespace Drupal\google_ai_application;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

class GoogleAiApplicationListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {

    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {

    $row['label'] = $entity->label();

    $row['id'] = $entity->id();

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity): array {

    $operations = parent::getOperations($entity);

    $operations['view'] = [
      'title' => $this->t('View'),
      'weight' => 0,
      'url' => $entity->toUrl('canonical'),
    ];

    return $operations;
  }

}
