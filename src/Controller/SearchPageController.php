<?php

namespace Drupal\google_ai_application\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\google_ai_application\Service\SearchService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Support Hub Page Controller.
 */
class SearchPageController extends ControllerBase {

  public function __construct(
    protected SearchService $searchService,
    protected RequestStack $requestStack
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('google_ai_application.search'),
      $container->get('request_stack')
    );
  }

  public function view(string $google_ai_application): array {

    $entity = $this->entityTypeManager()
      ->getStorage('google_ai_application')
      ->load($google_ai_application);

    if (!$entity) {
      throw new NotFoundHttpException();
    }

    $request = $this->requestStack->getCurrentRequest();

    $query = trim((string) $request->query->get('search_text', ''));
    $page = (int) $request->query->get('page', 0);

    $results = $this->searchService->search(
      $entity->project_name ?? '',
      $entity->location ?? '',
      $entity->data_store_name ?? '',
      $entity->serving_config ?? '',
      $query,
      $page
    );

    return [
      '#theme' => 'google_ai_application_page',

      '#title' => $entity->field_title ?? '',
      '#description' => $entity->field_description_caption ?? '',

      '#cta_links' => $entity->field_hero_search_cta_links ?? [],
      '#footer_cards' => $entity->footer_cards ?? [],

      '#search_query' => $query,
      '#results' => $results,

      /**
       * ALL FACET LOGIC INSIDE FORM
       */
      '#facet_form' => $this->formBuilder()->getForm(
        'Drupal\google_ai_application\Form\FacetFilterForm',
        $google_ai_application
      ),

      '#search_form' => $this->formBuilder()->getForm(
        'Drupal\google_ai_application\Form\SearchForm'
      ),

      '#cache' => [
        'contexts' => [
          'url',
          'url.query_args',
        ],
      ],
    ];
  }
}
