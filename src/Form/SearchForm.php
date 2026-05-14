<?php

namespace Drupal\google_ai_application\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\google_ai_application\Service\GoogleAiSearchStateService;

class SearchForm extends FormBase {

  protected GoogleAiSearchStateService $stateService;
  protected $application;

  public function __construct(GoogleAiSearchStateService $stateService) {
    $this->stateService = $stateService;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('google_ai_application.search_state')
    );
  }

  public function getFormId() {
    return 'google_ai_application_search_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $application = NULL) {
    $this->application = $application;

    $state = $this->stateService->getState($application?->id());
    $current_query = $state['query'] ?? '';

    $form['#method'] = 'get';   // Important

    $form['search_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#required' => TRUE,
      '#default_value' => $current_query,
      '#attributes' => [
        'placeholder' => $this->t('What are you looking for?'),
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $query = trim($form_state->getValue('search_text'));

    if (empty($query)) {
      return;
    }

    $entity = $this->application;

    // Fallback if entity not passed
    if (!$entity) {
      $entity_param = $this->getRouteMatch()->getParameter('google_ai_application');
      if (is_string($entity_param)) {
        $entity = \Drupal::entityTypeManager()
          ->getStorage('google_ai_application')
          ->load($entity_param);
      }
    }

    if ($entity) {
      // 🔥 Reset and save new query (resets pagination to page 1)
      $this->stateService->reset($entity->id(), $query);

      // Save session before redirect to ensure data persists
      \Drupal::service('session_manager')->save();
    }

    // Redirect to page 1 (removes any page parameter)
    $form_state->setRedirect('google_ai_application.page', [
      'google_ai_application' => $entity?->id(),
    ]);

  }
}
