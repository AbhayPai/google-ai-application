<?php

namespace Drupal\google_ai_application\Service;

use Google\Cloud\DiscoveryEngine\V1\Client\SearchServiceClient;
use Google\Cloud\DiscoveryEngine\V1\SearchRequest;
use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

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
   * Search with pagination using Google's pageToken.
   */
  public function search(
    string $project,
    string $location,
    string $dataStore,
    string $servingConfig,
    string $query,
    ?string $pageToken = NULL,
    int $pageSize = 10
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
        ->setPageSize($pageSize);

      if (!empty($pageToken)) {
        $request->setPageToken($pageToken);
      }

      /** @var \Google\ApiCore\PagedListResponse $response */
      $response = $this->searchServiceClient->search($request);

      $results = [];

      foreach ($response->iterateAllElements() as $result) {
        $document = $result->getDocument();
        $fields = $document->getStructData()->getFields();

        $results[] = [
          'id'    => $document->getId(),
          'title' => $fields['title']->getStringValue() ?? '',
          // Add more fields here as needed (e.g. snippet, link, etc.)
        ];
      }

      // ✅ CORRECT WAY to get next page token
      $page = $response->getPage();
      $nextPageToken = $page ? $page->getNextPageToken() : NULL;

      return [
        'results' => $results,
        'next_page_token' => $nextPageToken ?: NULL,
      ];

    } catch (\Google\ApiCore\ApiException $e) {
      $this->logger->error('Search failed: @msg', ['@msg' => $e->getMessage()]);

      return [
        'results' => [],
        'next_page_token' => NULL,
      ];
    }
  }
}
