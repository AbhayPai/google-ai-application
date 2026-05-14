<?php

namespace Drupal\google_ai_application\Service;

use Google\ApiCore\ApiException;
use Google\ApiCore\OperationResponse;
use Google\Cloud\DiscoveryEngine\V1\Client\DataStoreServiceClient;
use Google\Cloud\DiscoveryEngine\V1\CreateDataStoreRequest;
use Google\Cloud\DiscoveryEngine\V1\DataStore;
use Google\Cloud\DiscoveryEngine\V1\DeleteDataStoreRequest;
use Google\Cloud\DiscoveryEngine\V1\GetDataStoreRequest;
use Google\Cloud\DiscoveryEngine\V1\IndustryVertical;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles Datastore operations.
 */
class DataStoreService {

  protected DataStoreServiceClient $client;
  protected LoggerInterface $logger;

  public function __construct(
    DataStoreServiceClient $client,
    LoggerChannelFactoryInterface $loggerFactory
  ) {
    $this->client = $client;
    $this->logger = $loggerFactory->get('google_ai_application');
  }

  /**
   * CREATE DATASTORE
   */
  public function createDataStore(
    string $project,
    string $location,
    string $collection,
    string $displayName,
    string $dataStoreId
  ): array {

    try {
      $parent = $this->client->collectionName(
        $project,
        $location,
        $collection
      );

      $dataStore = (new DataStore())
        ->setDisplayName($displayName)
        ->setIndustryVertical(IndustryVertical::GENERIC);

      $request = (new CreateDataStoreRequest())
        ->setParent($parent)
        ->setDataStore($dataStore)
        ->setDataStoreId($this->normalize($dataStoreId));

      $operation = $this->client->createDataStore($request);
      $operation->pollUntilComplete();

      $this->logger->info(
        'Datastore created: @datastore',
        ['@datastore' => $displayName]
      );

      return $this->parse($operation);

    } catch (ApiException $e) {
      $this->logger->error(
        'Datastore create failed: @message',
        ['@message' => $e->getMessage()]
      );

      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * DELETE DATASTORE
   */
  public function deleteDataStore(
    string $project,
    string $location,
    string $dataStoreId
  ): array {
    try {
      $name = $this->client->dataStoreName(
        $project,
        $location,
        $this->normalize($dataStoreId)
      );

      $request = (new DeleteDataStoreRequest())
        ->setName($name);

      $operation = $this->client->deleteDataStore($request);
      $operation->pollUntilComplete();

      $this->logger->info(
        'Datastore deleted: @dataStoreId',
        ['@dataStoreId' => $dataStoreId]
      );

      return $this->parse($operation);

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
   * CHECK IF DATASTORE EXISTS
   */
  public function dataStoreExists(
    string $project,
    string $location,
    string $dataStoreId
  ): bool {

    try {
      $name = $this->client->dataStoreName(
        $project,
        $location,
        $this->normalize($dataStoreId)
      );

      $request = (new GetDataStoreRequest())
        ->setName($name);

      $this->client->getDataStore($request);

      return TRUE;
    } catch (\Throwable $e) {
      return FALSE;
    }
  }

  /**
   * SAFE PARSER
   */
  private function parse(OperationResponse $operation): array {

    if ($operation->operationSucceeded()) {
      return [
        'success' => TRUE,
        'data' => [],
      ];
    }

    $error = $operation->getError();

    return [
      'success' => FALSE,
      'error' => $error?->getMessage() ?? 'Unknown error',
    ];
  }

  protected function normalize(string $value): string {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9-_]+/', '-', $value);
    $value = trim($value, '-');

    return $value;
  }
}
