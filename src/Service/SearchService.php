<?php

namespace Drupal\google_ai_application\Service;

use Google\ApiCore\ApiException;
use Google\ApiCore\PagedListResponse;
use Google\Cloud\DiscoveryEngine\V1\Client\SearchServiceClient;
use Google\Cloud\DiscoveryEngine\V1\SearchRequest;
use Google\Cloud\DiscoveryEngine\V1\SearchResponse\SearchResult;
use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Handles Google Discovery Engine Search operations.
 */
class SearchService {

  protected SearchServiceClient $searchServiceClient;
  protected LoggerInterface $logger;

  public function __construct(
    SearchServiceClient $searchServiceClient,
    LoggerChannelFactoryInterface $loggerFactory
  ) {
    $this->searchServiceClient = $searchServiceClient;
    $this->logger = $loggerFactory->get('google_ai_application');
  }

  /**
   * Update schema in Discovery Engine.
   */
  public function search(
    string $project,
    string $location,
    string $dataStore,
    string $servingConfig,
    string $query
  ): array {

    try {

      $formattedServingConfig = $this->searchServiceClient->servingConfigName(
        $project,
        $location,
        $dataStore,
        $servingConfig
      );

      $request = (new SearchRequest())
        ->setServingConfig($formattedServingConfig)
        ->setQuery($query)
        ->setPageSize(10);

      /** @var \Google\ApiCore\PagedListResponse $response */
      $response = $this->searchServiceClient->search($request);

      $results = [];
      foreach ($response as $result) {

        $document = $result->getDocument();
        $fields = $document->getStructData()->getFields();

        $title = isset($fields['title'])
          ? $fields['title']->getStringValue()
          : '';

        $results[] = [
          'id' => $document->getId(),
          'title' => $title,
        ];
      }

      return $results;
    }
    catch (\Google\ApiCore\ApiException $e) {

      $this->logger->error('Search failed: @msg', [
        '@msg' => $e->getMessage(),
      ]);

      return [];
    }
  }

}
