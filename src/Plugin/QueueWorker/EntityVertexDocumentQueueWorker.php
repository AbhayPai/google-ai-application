<?php

namespace Drupal\google_ai_application\Plugin\QueueWorker;

use Psr\Log\LoggerInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\google_ai_application\Service\DocumentTransformerService;
use Drupal\google_ai_application\Service\DocumentService;

/**
 * @QueueWorker(
 *   id = "entity_vertex_document_queue",
 *   title = @Translation("Support Hub Queue Worker"),
 *   cron = {"time" = 60}
 * )
 */
class EntityVertexDocumentQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  protected LoggerInterface $logger;
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    LoggerChannelFactoryInterface $loggerFactory,
    EntityTypeManagerInterface $entityTypeManager,
    protected DocumentTransformerService $transformer,
    protected DocumentService $documentService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->logger = $loggerFactory->get('google_ai_application');
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('google_ai_application.document_transformer'),
      $container->get('google_ai_application.document')
    );
  }

  /**
   * Process queue item (batch of nodes).
   */
  public function processItem($data): void {
    // Expect batch: array of items.
    if (empty($data['items']) || !is_array($data['items'])) {
      return;
    }

    $failed = [];

    foreach ($data['items'] as $item) {
      try {
        $nid = $item['nid'] ?? NULL;
        $operation = $item['operation'] ?? 'unknown';

        if (!$nid) {
          throw new \Exception('Missing node ID');
        }

        // Reload entity safely (queue-safe pattern).
        $node = $this->entityTypeManager
          ->getStorage('node')
          ->load($nid);

        if (!$node) {
          throw new \Exception("Node $nid not found");
        }

        $document = $this->transformer->transformNodes([$node]);

        $result = $this->documentService->importDocuments(
          $item['project_id'],
          $item['location'],
          $item['data_store_id'],
          $item['branch'],
          $document
        );

        if (!empty($result['success'])) {
          $this->logger->info('Processed node @nid (@op)', [
            '@nid' => $nid,
            '@op' => $operation,
          ]);
        } else {
          $this->logger->error('Processed node @nid (@op)', [
            '@nid' => $nid,
            '@op' => $operation,
          ]);
        }
      }
      catch (\Throwable $e) {
        $failed[] = [
          'nid' => $item['nid'] ?? NULL,
          'error' => $e->getMessage(),
          'attempts' => ($item['attempts'] ?? 0) + 1,
        ];

        $this->logger->error('Queue item failed for node @nid: @msg', [
          '@nid' => $item['nid'] ?? 'unknown',
          '@msg' => $e->getMessage(),
        ]);
      }
    }

    // Retry failed items
    if (!empty($failed)) {
      foreach ($failed as $fail) {
        // Max retry limit
        if ($fail['attempts'] > 3) {
          $this->logger->critical('Dropping node @nid after max retries', [
            '@nid' => $fail['nid'],
          ]);
          continue;
        }

        // Re-queue failed items
        \Drupal::service('queue')
          ->get('support_hub_queue')
          ->createItem([
            'items' => [
              [
                'nid' => $fail['nid'],
                'operation' => 'retry',
                'attempts' => $fail['attempts'],
              ],
            ],
          ]);
      }
    }
  }

}
