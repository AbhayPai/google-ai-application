<?php

namespace Drupal\google_ai_application\Controller;

use Drupal\Core\Controller\ControllerBase;

class SupportHubPageController extends ControllerBase {

  /**
   * Render support hub page.
   */
  public function view(string $google_ai_application): array {
    $entity = $this->entityTypeManager()
      ->getStorage('google_ai_application')
      ->load($google_ai_application);

    if (!$entity) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    return [
      '#theme' => 'google_ai_application_page',

      '#entity' => $entity,

      '#title' => $entity->field_title ?? '',
      '#description' => $entity->field_description_caption ?? '',
      '#cta_links' => $entity->field_hero_search_cta_links ?? [],
      '#footer_cards' => $entity->footer_cards ?? [],

      '#search_form' => \Drupal::formBuilder()
        ->getForm(
          \Drupal\google_ai_application\Form\SearchForm::class,
          $entity
        ),

      '#cache' => [
        'tags' => $entity->getCacheTags(),
        'contexts' => ['url.path'],
      ],
    ];
  }

}
