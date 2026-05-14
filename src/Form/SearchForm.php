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
    $entity = $this->application;

    if ($entity && !empty($query)) {
      // This resets the session query and page tokens
      $this->stateService->reset($entity->id(), $query);

      // Explicitly save the session to ensure the redirect picks it up
      \Drupal::service('session_manager')->save();
    }

    $form_state->setRedirect('google_ai_application.page', [
      'google_ai_application' => $entity?->id(),
    ]);
  }
}
