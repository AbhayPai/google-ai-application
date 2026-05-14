<?php

namespace Drupal\google_ai_application\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Google\Cloud\DiscoveryEngine\V1\Client\SearchServiceClient;
use Google\Cloud\DiscoveryEngine\V1\SearchRequest;
use Google\Cloud\DiscoveryEngine\V1\SearchRequest\ContentSearchSpec;
use Psr\Log\LoggerInterface;

/**
 * Handles Google Discovery Engine Search operations.
 */
class SearchService {

  /**
   * Search client.
   */
  protected SearchServiceClient $searchServiceClient;

  /**
   * Logger.
   */
  protected LoggerInterface $logger;

  /**
   * Constructor.
   */
  public function __construct(
    SearchServiceClient $searchServiceClient,
    LoggerChannelFactoryInterface $loggerFactory
  ) {
    $this->searchServiceClient = $searchServiceClient;
    $this->logger = $loggerFactory->get('google_ai_application');
  }

  /**
   * Execute search.
   */
  public function search(
    string $project,
    string $location,
    string $dataStore,
    string $servingConfig,
    string $query,
    int $page = 0,
    int $pageSize = 10
  ): array {

    try {

      $formattedServingConfig = $this->searchServiceClient
        ->servingConfigName(
          $project,
          $location,
          $dataStore,
          $servingConfig
        );

      $offset = $page * $pageSize;

      $contentSearchSpec = (new ContentSearchSpec())
        ->setSnippetSpec(
          new ContentSearchSpec\SnippetSpec([
            'return_snippet' => TRUE,
          ])
        );

      $request = (new SearchRequest())
        ->setServingConfig($formattedServingConfig)
        ->setQuery($query)
        ->setPageSize($pageSize)
        ->setOffset($offset)
        ->setContentSearchSpec($contentSearchSpec);

      $response = $this->searchServiceClient->search($request);

      $results = [];

      foreach ($response->iterateAllElements() as $result) {

        $document = $result->getDocument();

        $fields = $document->getStructData()->getFields();

        $title = '';

        if (isset($fields['title'])) {
          $title = $fields['title']->getStringValue();
        }

        $snippet = '';

        $derivedData = $document->getDerivedStructData();

        if ($derivedData) {

          $derivedFields = $derivedData->getFields();

          if (isset($derivedFields['snippets'])) {

            $snippetItems = $derivedFields['snippets']
              ->getListValue()
              ->getValues();

            if (!empty($snippetItems)) {

              $snippetStruct = $snippetItems[0]
                ->getStructValue()
                ->getFields();

              if (isset($snippetStruct['snippet'])) {
                $snippet = $snippetStruct['snippet']
                  ->getStringValue();
              }
            }
          }
        }

        $results[] = [
          'id' => $document->getId(),
          'title' => $title,
          // 'snippet' => $snippet,
        ];
      }

      return $results;
    }
    catch (\Exception $e) {

      $this->logger->error(
        'Discovery Engine search failed: @message',
        ['@message' => $e->getMessage()]
      );

      return [];
    }
  }

}
