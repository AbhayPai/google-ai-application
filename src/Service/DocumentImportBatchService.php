<?php

namespace Drupal\google_ai_application\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles batch document imports.
 */
class DocumentImportBatchService {

  /**
   * Drupal logger.
   */
  protected LoggerInterface $logger;

  /**
   * Constructor.
   */
  public function __construct(
    protected DocumentTransformerService $transformer,
    protected DocumentService $documentService,
    protected EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory
  ) {
    $this->logger = $loggerFactory->get('google_ai_application');
  }

  /**
   * Start batch import.
   */
  public function start(
    array $bundles,
    array $config
  ): void {
    $batch = [
      'title' => t('Importing documents to Google Discovery Engine'),
      'operations' => [],
      'finished' => [static::class, 'finished'],
    ];

    $chunkSize = $config['chunk_size'] ?? 20;

    foreach ($bundles as $bundle) {
      $query = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery()
        ->condition('type', $bundle)
        ->condition('status', 1)
        ->accessCheck(TRUE);

      $nids = $query->execute();

      if (empty($nids)) {
        continue;
      }

      $chunks = array_chunk($nids, $chunkSize);

      foreach ($chunks as $chunk) {
        $batch['operations'][] = [
          [static::class, 'processChunk'],
          [$chunk, $config],
        ];
      }
    }

    batch_set($batch);
  }

  /**
   * Process batch chunk.
   */
  public static function processChunk(
    array $nids,
    array $config,
    array &$context
  ): void {
    try {
      if (!isset($context['results'])) {
        $context['results'] = [
          'processed' => [],
          'success' => TRUE,
          'failures' => [],
          'entity_id' => $config['entity_id'] ?? NULL,
        ];
      }

      $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);

      $transformer = \Drupal::service(
        'google_ai_application.document_transformer'
      );

      $documentService = \Drupal::service(
        'google_ai_application.document'
      );

      $documents = $transformer->transformNodes($nodes);

      $result = $documentService->importDocuments(
        $config['project_id'],
        $config['location'],
        $config['data_store_id'],
        $config['branch'],
        $documents
      );

      if (empty($result['success'])) {
        $context['results']['success'] = FALSE;

        $context['results']['failures'][] = [
          'chunk' => $nids,
          'error' => $result['error'] ?? 'Unknown error',
        ];
      }

      $context['results']['processed'][] = count($nids);

      $context['message'] = t(
        'Processed @count nodes',
        ['@count' => count($nids)]
      );
    }
    catch (\Throwable $e) {
      $context['results']['success'] = FALSE;

      $context['results']['failures'][] = [
        'chunk' => $nids,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Batch finished callback.
   */
  public static function finished(
    bool $success,
    array $results,
    array $operations
  ): void {
    $total = array_sum($results['processed'] ?? []);

    $isSuccess = $results['success'] ?? $success;

    $entityId = $results['entity_id'] ?? NULL;

    if ($entityId) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage('google_ai_application')
        ->load($entityId);

      if ($entity) {
        $entity->set('last_import', time());

        $entity->set(
          'last_import_status',
          $isSuccess ? 'Success' : 'Failed'
        );

        $entity->save();
      }
    }

    if ($isSuccess) {
      \Drupal::messenger()->addStatus(t(
        'Import completed successfully. @count nodes processed.',
        ['@count' => $total]
      ));
    }
    else {
      \Drupal::messenger()->addError(t(
        'Import completed with errors. @count nodes processed.',
        ['@count' => $total]
      ));
    }
  }

}
