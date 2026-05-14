<?php

namespace Drupal\google_ai_application\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\google_ai_application\Service\SearchService;
use Drupal\google_ai_application\Service\GoogleAiSearchStateService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class GoogleAiSearchAjaxController extends ControllerBase {

  /**
   * @var \Drupal\google_ai_application\Service\SearchService
   */
  protected $searchService;

  /**
   * @var \Drupal\google_ai_application\Service\GoogleAiSearchStateService
   */
  protected $stateService;

  public function __construct(SearchService $searchService, GoogleAiSearchStateService $stateService) {
    $this->searchService = $searchService;
    $this->stateService = $stateService;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('google_ai_application.search'),
      $container->get('google_ai_application.search_state')
    );
  }

  /**
   * AJAX endpoint for loading more results.
   */
  public function ajaxSearch(Request $request, string $entity_id): JsonResponse {
    $entity = $this->entityTypeManager()
      ->getStorage('google_ai_application')
      ->load($entity_id);

    if (!$entity) {
      return new JsonResponse(['error' => 'Application not found'], 404);
    }

    $query = $request->query->get('q') ?: '';
    $page_token = $request->query->get('page_token');
    $page_size = (int) $request->query->get('page_size', 10);

    if (empty($query)) {
      $state = $this->stateService->getState($entity->id());
      $query = $state['query'] ?? '';
    }

    if (empty($query)) {
      return new JsonResponse([
        'results' => [],
        'next_page_token' => NULL,
        'has_more' => FALSE
      ]);
    }

    try {
      $response = $this->searchService->search(
        $entity->project_name,
        $entity->location,
        $entity->data_store_name,
        $entity->serving_config,
        $query,
        $page_token,
        $page_size
      );

      // Store the next page token if available
      if (!empty($response['next_page_token'])) {
        $this->stateService->addPageToken($entity->id(), $response['next_page_token']);
      }

      return new JsonResponse([
        'results' => $response['results'] ?? [],
        'next_page_token' => $response['next_page_token'] ?? NULL,
        'has_more' => !empty($response['next_page_token']),
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('google_ai_application')->error(
        'AJAX search error: @error',
        ['@error' => $e->getMessage()]
      );
      return new JsonResponse([
        'error' => 'Search failed',
        'results' => [],
        'next_page_token' => NULL,
        'has_more' => FALSE
      ], 500);
    }
  }
}
