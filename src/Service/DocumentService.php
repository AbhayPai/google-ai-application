<?php

namespace Drupal\google_ai_application\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Google\ApiCore\ApiException;
use Google\ApiCore\OperationResponse;
use Google\Cloud\DiscoveryEngine\V1\Client\DocumentServiceClient;
use Google\Cloud\DiscoveryEngine\V1\ImportDocumentsRequest;
use Google\Cloud\DiscoveryEngine\V1\ImportDocumentsRequest\InlineSource;
use Google\Cloud\DiscoveryEngine\V1\ListDocumentsRequest;
use Google\Cloud\DiscoveryEngine\V1\PurgeDocumentsRequest;
use Google\Rpc\Status;
use Psr\Log\LoggerInterface;

/**
 * Handles Discovery Engine document operations.
 */
class DocumentService {

  /**
   * Google Discovery Engine document client.
   */
  protected DocumentServiceClient $documentServiceClient;

  /**
   * Drupal logger.
   */
  protected LoggerInterface $logger;

  /**
   * Poll configuration.
   */
  protected array $pollConfig = [
    'initialPollDelayMillis' => 1000,
    'pollDelayMultiplier' => 1.5,
    'maxPollDelayMillis' => 10000,
    'totalPollTimeoutMillis' => 600000,
  ];

  /**
   * Constructor.
   */
  public function __construct(
    DocumentServiceClient $documentServiceClient,
    LoggerChannelFactoryInterface $loggerFactory
  ) {
    $this->documentServiceClient = $documentServiceClient;
    $this->logger = $loggerFactory->get('google_ai_application');
  }

  /**
   * Cleanup gRPC connections.
   */
  public function __destruct() {
    $this->documentServiceClient->close();
  }

  /**
   * Import documents into Discovery Engine.
   *
   * @param string $project
   *   Google Cloud project ID.
   * @param string $location
   *   Discovery Engine location.
   * @param string $dataStore
   *   Datastore ID.
   * @param string $branch
   *   Branch ID.
   * @param array $documents
   *   Array of Document protobuf objects.
   *
   * @return array
   *   Operation result.
   */
  public function importDocuments(
    string $project,
    string $location,
    string $dataStore,
    string $branch,
    array $documents
  ): array {
    try {
      $parent = $this->documentServiceClient->branchName(
        $project,
        $location,
        $dataStore,
        $branch
      );

      $inlineSource = new InlineSource([
        'documents' => $documents,
      ]);

      $request = new ImportDocumentsRequest([
        'parent' => $parent,
        'inline_source' => $inlineSource,
        'reconciliation_mode' => ImportDocumentsRequest\ReconciliationMode::INCREMENTAL,
      ]);

      /** @var \Google\ApiCore\OperationResponse $operation */
      $operation = $this->documentServiceClient->importDocuments($request);

      $operation->pollUntilComplete($this->pollConfig);

      $result = $this->parseOperationResponse($operation);

      if ($result['success']) {
        $this->logger->info(
          'Imported documents into datastore: @datastore',
          ['@datastore' => $dataStore]
        );
      }

      return $result;

    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Document import failed: @message',
        ['@message' => $e->getMessage()]
      );

      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Purge documents from datastore.
   *
   * WARNING:
   * This permanently deletes documents.
   *
   * @param string $project
   *   Google Cloud project ID.
   * @param string $location
   *   Discovery Engine location.
   * @param string $dataStore
   *   Datastore ID.
   * @param string $branch
   *   Branch ID.
   * @param string $filter
   *   Document filter.
   *
   * @return array
   *   Operation result.
   */
  public function purgeDocuments(
    string $project,
    string $location,
    string $dataStore,
    string $branch,
    string $filter = '*'
  ): array {
    try {
      $parent = $this->documentServiceClient->branchName(
        $project,
        $location,
        $dataStore,
        $branch
      );

      $request = (new PurgeDocumentsRequest())
        ->setParent($parent)
        ->setFilter($filter)
        ->setForce(TRUE);

      /** @var \Google\ApiCore\OperationResponse $operation */
      $operation = $this->documentServiceClient->purgeDocuments($request);

      $operation->pollUntilComplete($this->pollConfig);

      $result = $this->parseOperationResponse($operation);

      if ($result['success']) {
        $this->logger->warning(
          'Documents purged from datastore: @datastore',
          ['@datastore' => $dataStore]
        );
      }

      return $result;

    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Document purge failed: @message',
        ['@message' => $e->getMessage()]
      );

      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Count documents.
   *
   * WARNING:
   * This can become expensive for large datastores.
   *
   * @param string $project
   *   Google Cloud project ID.
   * @param string $location
   *   Discovery Engine location.
   * @param string $dataStore
   *   Datastore ID.
   * @param string $branch
   *   Branch ID.
   *
   * @return array
   *   Count result.
   */
  public function countDocuments(
    string $project,
    string $location,
    string $dataStore,
    string $branch
  ): array {
    try {
      $parent = $this->documentServiceClient->branchName(
        $project,
        $location,
        $dataStore,
        $branch
      );

      $request = (new ListDocumentsRequest())
        ->setParent($parent)
        ->setPageSize(100);

      $response = $this->documentServiceClient->listDocuments($request);

      $count = 0;

      foreach ($response->iterateAllElements() as $document) {
        $count++;
      }

      return [
        'success' => TRUE,
        'count' => $count,
      ];

    }
    catch (\Throwable $e) {

      $this->logger->error(
        'Document count failed: @message',
        ['@message' => $e->getMessage()]
      );

      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Parse long-running operation response safely.
   *
   * @param \Google\ApiCore\OperationResponse $operation
   *   Operation response.
   *
   * @return array
   *   Parsed operation result.
   */
  protected function parseOperationResponse(
    OperationResponse $operation
  ): array {
    if ($operation->operationSucceeded()) {

      $result = $operation->getResult();

      return [
        'success' => TRUE,
        'data' => $result
          ? $this->protobufToArray($result)
          : [],
      ];
    }

    $error = $operation->getError();

    if ($error instanceof Status) {
      return [
        'success' => FALSE,
        'error' => $error->getMessage(),
        'code' => $error->getCode(),
      ];
    }

    return [
      'success' => FALSE,
      'error' => 'Unknown operation error.',
    ];
  }

  /**
   * Convert protobuf safely to array.
   *
   * @param mixed $message
   *   Protobuf message.
   *
   * @return array
   *   Converted array.
   */
  protected function protobufToArray($message): array {
    if (method_exists($message, 'serializeToJsonString')) {
      return json_decode(
        $message->serializeToJsonString(),
        TRUE
      ) ?? [];
    }

    return [];
  }

}
