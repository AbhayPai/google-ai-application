<?php

namespace Drupal\google_ai_application\Service;

use Google\ApiCore\ApiException;
use Google\ApiCore\OperationResponse;
use Google\Cloud\DiscoveryEngine\V1\Client\EngineServiceClient;
use Google\Cloud\DiscoveryEngine\V1\Engine;
use Google\Cloud\DiscoveryEngine\V1\CreateEngineRequest;
use Google\Cloud\DiscoveryEngine\V1\DeleteEngineRequest;
use Google\Cloud\DiscoveryEngine\V1\GetEngineRequest;
use Google\Cloud\DiscoveryEngine\V1\SolutionType;
use Google\Rpc\Status;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles Engine operations.
 */
class EngineService {

  protected EngineServiceClient $engineServiceClient;
  protected LoggerInterface $logger;


  public function __construct(
    EngineServiceClient $engineServiceClient,
    LoggerChannelFactoryInterface $loggerFactory
  ) {
    $this->engineServiceClient = new EngineServiceClient();
    $this->logger = $loggerFactory->get('google_ai_application');
  }

  /**
   * CREATE ENGINE
   */
  public function createEngine(
    string $project,
    string $location,
    string $collection,
    string $displayName,
    string $appId,
    string $dataStoreId
  ): array {

    try {
      $parent = $this->engineServiceClient->collectionName(
        $project,
        $location,
        $collection
      );

      $engine = (new Engine())
        ->setDisplayName($this->normalizeId($displayName))
        ->setSolutionType(SolutionType::SOLUTION_TYPE_SEARCH)
        ->setDataStoreIds([
          $this->normalizeId($dataStoreId)
        ]);

      $request = (new CreateEngineRequest())
        ->setParent($parent)
        ->setEngine($engine)
        ->setEngineId($this->normalizeId($appId));

      $operation = $this->engineServiceClient->createEngine($request);
      $operation->pollUntilComplete();

      $this->logger->info(
        'Application created: @displayName',
        ['@displayName' => $displayName]
      );

      return $this->parseOperation($operation);

    } catch (ApiException $e) {
      $this->logger->error(
        'Application create failed: @message',
        ['@message' => $e->getMessage()]
      );

      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * DELETE ENGINE
   */
  public function deleteEngine(
    string $project,
    string $location,
    string $collection,
    string $appId
  ): array {

    try {
      $name = $this->engineServiceClient->engineName(
        $project,
        $location,
        $collection,
        $this->normalizeId($appId)
      );

      $request = (new DeleteEngineRequest())
        ->setName($name);

      $operation = $this->engineServiceClient->deleteEngine($request);
      $operation->pollUntilComplete();

      $this->logger->info(
        'Application deleted: @appId',
        ['@appId' => $appId]
      );

      return $this->parseOperation($operation);

    } catch (ApiException $e) {
      $this->logger->error(
        'Datastore delete failed: @message',
        ['@message' => $e->getMessage()]
      );

      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * ✅ CHECK IF ENGINE EXISTS
   */
  public function engineExists(
    string $project,
    string $location,
    string $collection,
    string $appId
  ): bool {

    try {
      $name = $this->engineServiceClient->engineName(
        $project,
        $location,
        $collection,
        $this->normalizeId($appId)
      );

      $request = (new GetEngineRequest())
        ->setName($name);

      $this->engineServiceClient->getEngine($request);

      return TRUE;

    } catch (\Throwable $e) {
      return FALSE;
    }
  }

  /**
   * SAFE OPERATION PARSER
   */
  private function parseOperation(OperationResponse $operation): array {

    if ($operation->operationSucceeded()) {
      $result = $operation->getResult();

      return [
        'success' => TRUE,
        'data' => $result ? $this->protobufToArray($result) : [],
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
      'error' => 'Unknown engine operation error',
    ];
  }

  /**
   * PROTOBUF SAFE CONVERTER
   */
  private function protobufToArray($message): array {
    if (method_exists($message, 'serializeToJsonString')) {
      return json_decode($message->serializeToJsonString(), TRUE) ?? [];
    }
    return [];
  }

  /**
   * Normalize ID
   */
  protected function normalizeId(string $value): string {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9-_]+/', '-', $value);
    $value = preg_replace('/-+/', '-', $value);
    $value = trim($value, '-');

    if (!preg_match('/^[a-z0-9]/', $value)) {
      $value = 'eng-' . $value;
    }

    return $value;
  }
}
