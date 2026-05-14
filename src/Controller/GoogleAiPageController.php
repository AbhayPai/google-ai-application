<?php

namespace Drupal\google_ai_application\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\google_ai_application\Service\SearchService;
use Drupal\google_ai_application\Service\GoogleAiSearchStateService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GoogleAiPageController extends ControllerBase {

  protected $searchService;
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

  public function view(string $google_ai_application, Request $request): array {
    $entity = $this->entityTypeManager()
      ->getStorage('google_ai_application')
      ->load($google_ai_application);

    if (!$entity) {
      throw new NotFoundHttpException();
    }

    $state = $this->stateService->getState($entity->id());
    $query = $state['query'] ?? '';
    $current_page = (int) $request->query->get('page', 1);

    $results = [];
    $next_page_token = NULL;
    $search_error = NULL;
    $pagination_info = [];

    if (!empty($query)) {
      try {
        if (empty($entity->project_name) || empty($entity->location) ||
            empty($entity->data_store_name) || empty($entity->serving_config)) {
          $search_error = 'Configuration incomplete.';
        } else {
          $page_token = $current_page > 1 ? $this->stateService->getPageToken($entity->id(), $current_page) : NULL;

          $response = $this->searchService->search(
            $entity->project_name,
            $entity->location,
            $entity->data_store_name,
            $entity->serving_config,
            $query,
            $page_token,
            10
          );

          $results = $response['results'] ?? [];
          $next_page_token = $response['next_page_token'] ?? NULL;

          if (!empty($next_page_token)) {
            $this->stateService->addPageToken($entity->id(), $next_page_token);
          }

          $this->stateService->setCurrentPage($entity->id(), $current_page);

          $pagination_info = [
            'current_page' => $current_page,
            'has_next' => !empty($next_page_token),
            'has_prev' => $current_page > 1,
          ];
        }
      } catch (\Exception $e) {
        $search_error = 'Search failed.';
      }
    }

    // Pass the form as a render array, NOT pre-rendered HTML
    $search_form = $this->formBuilder()->getForm(
      \Drupal\google_ai_application\Form\SearchForm::class,
      $entity
    );

    return [
      '#theme' => 'google_ai_application_page',
      '#title' => $entity->label() ?? 'AI Search',
      '#description' => $entity->field_description_caption->value ?? '',
      '#search_query' => $query,
      '#results' => $results,
      '#next_page_token' => $next_page_token,
      '#search_error' => $search_error,
      '#pagination_info' => $pagination_info,
      '#current_page' => $current_page,
      '#cta_links' => $entity->field_hero_search_cta_links ?? [],
      '#footer_cards' => $entity->footer_cards ?? [],
      '#search_form' => $search_form,
      '#attached' => [
        'library' => ['google_ai_application/search-ajax'],
        'drupalSettings' => [
          'googleAi' => [
            'entityId' => $entity->id(),
            'ajaxUrl' => '/google-ai/ajax/search/' . $entity->id(),
            'initialQuery' => $query,
            'nextPageToken' => $next_page_token,
            'currentPage' => $current_page,
          ],
        ],
      ],
    ];
  }
}
