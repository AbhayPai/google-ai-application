<?php

namespace Drupal\google_ai_application\EventSubscriber;

use Drupal\core_event_dispatcher\EntityHookEvents;
use Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityDeleteEvent;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class EntityEventSubscriber implements EventSubscriberInterface, ContainerInjectionInterface {

  protected QueueFactory $queueFactory;

  /**
   * Batch buffer.
   */
  private array $batch = [];

  public function __construct(
    QueueFactory $queueFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->queueFactory = $queueFactory;
  }

  /**
   * DI factory.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('queue'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Event subscriptions.
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityHookEvents::ENTITY_INSERT => 'onInsert',
      EntityHookEvents::ENTITY_UPDATE => 'onUpdate',
      EntityHookEvents::ENTITY_DELETE => 'onDelete',
      KernelEvents::TERMINATE => 'onTerminate',
    ];
  }

  /**
   * INSERT event.
   */
  public function onInsert(EntityInsertEvent $event): void {
    $this->handle($event->getEntity(), 'insert');
  }

  /**
   * UPDATE event.
   */
  public function onUpdate(EntityUpdateEvent $event): void {
    $this->handle($event->getEntity(), 'update');
  }

  /**
   * DELETE event.
   */
  public function onDelete(EntityDeleteEvent $event): void {
    // $this->handle($event->getEntity(), 'delete');
  }

  /**
   * Check target bundle.
   */
  private function isNode(EntityInterface $entity): bool {
    return $entity->getEntityTypeId() === 'node'
      && $entity->bundle() === 'support_hub';
  }

  /**
   * Central handler.
   */
  private function handle(EntityInterface $entity, string $operation): void {
    if (!$this->isNode($entity)) {
      return;
    }

    $configStorage = $this->entityTypeManager
      ->getStorage('google_ai_application');

    $configEntities = $configStorage->loadMultiple();

    foreach ($configEntities as $configEntity) {
      if ($configEntity->hasContentType('support_hub')) {
        $this->batch[] = [
          'nid' => $entity->id(),
          'project_id' => $configEntity->get('project_name'),
          'location' => $configEntity->get('location'),
          'data_store_id' => $configEntity->get('data_store_name'),
          'branch' => $configEntity->get('branch_name'),
          'operation' => $operation,
          'attempts' => 0,
        ];
      }
    }

    // Flush when batch limit reached.
    if (count($this->batch) >= 10) {
      $this->flushBatch();
    }
  }

  /**
   * Flush batch into queue.
   */
  public function flushBatch(): void {
    if (empty($this->batch)) {
      return;
    }

    $this->queueFactory
      ->get('entity_vertex_document_queue')
      ->createItem([
        'items' => $this->batch,
      ]);

    $this->batch = [];
  }

  /**
   * Flush remaining batch at end of request.
   */
  public function onTerminate(TerminateEvent $event): void {
    $this->flushBatch();
  }

}
