<?php

namespace Drupal\google_ai_application\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Google\ApiCore\ApiException;
use Google\Cloud\DiscoveryEngine\V1\Client\SchemaServiceClient;
use Google\Cloud\DiscoveryEngine\V1\Schema;
use Google\Cloud\DiscoveryEngine\V1\UpdateSchemaRequest;
use Psr\Log\LoggerInterface;

/**
 * Handles Google Discovery Engine Schema operations.
 */
class SchemaService {

  protected SchemaServiceClient $client;
  protected LoggerInterface $logger;

  public function __construct(
    SchemaServiceClient $client,
    LoggerChannelFactoryInterface $loggerFactory
  ) {
    $this->client = $client;
    $this->logger = $loggerFactory->get('google_ai_application');
  }

  /**
   * Update schema in Discovery Engine.
   */
  public function updateSchema(
    string $project,
    string $location,
    string $dataStore,
    string $schemaId,
    string $schemaData
  ): array {
    try {
      // Validate JSON schema before sending
      $decoded = json_decode($schemaData, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        return [
          'success' => false,
          'error' => 'Invalid JSON schema: ' . json_last_error_msg(),
        ];
      }

      $schemaName = $this->client->schemaName(
        $project,
        $location,
        $dataStore,
        $schemaId
      );

      $schema = (new Schema())
        ->setName($schemaName)
        ->setJsonSchema($schemaData);

      $request = (new UpdateSchemaRequest())
        ->setSchema($schema);

      /**
       * IMPORTANT:
       * updateSchema is NOT a long-running operation.
       */
      $result = $this->client->updateSchema($request);

      $this->logger->info(
        'Schema updated: @schema',
        ['@schema' => $schemaId]
      );

      return [
        'success' => true,
        'data' => method_exists($result, 'serializeToJsonString')
          ? json_decode($result->serializeToJsonString(), true)
          : [],
      ];
    }
    catch (ApiException $e) {
      $this->logger->error(
        'Schema update failed: @message',
        ['@message' => $e->getMessage()]
      );

      return [
        'success' => false,
        'error' => $e->getMessage(),
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Unexpected schema error: @message',
        ['@message' => $e->getMessage()]
      );

      return [
        'success' => false,
        'error' => $e->getMessage(),
      ];
    }
  }
}
