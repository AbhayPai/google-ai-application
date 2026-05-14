<?php

namespace Drupal\google_ai_application\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\google_ai_application\Service\SearchService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple Support Hub Search Form (non-AJAX).
 */
class SearchForm extends FormBase {

  protected SearchService $searchService;

  /**
   * @var \Drupal\google_ai_application\Entity\GoogleAiApplication|null
   */
  protected $application;

  public function __construct(SearchService $searchService) {
    $this->searchService = $searchService;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('google_ai_application.search')
    );
  }

  public function getFormId() {
    return 'google_ai_application_search_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $application = NULL) {

    $this->application = $application;

    $form['search_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('search_text', ''),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    // -----------------------------
    // Results (render after submit)
    // -----------------------------

    $results = $form_state->get('search_results') ?? [];

    $form['results'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['support-hub-results']],
    ];

    if (!empty($results)) {

      $items = [];

      foreach ($results as $item) {
        $items[] = [
          '#markup' => '<div class="search-result">
            <h3>' . ($item['title'] ?? '') . '</h3>
            <p>' . ($item['content'] ?? '') . '</p>
          </div>',
        ];
      }

      $form['results']['items'] = $items;
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

    if ($form_state->isValueEmpty('search_text')) {
      $form_state->setErrorByName(
        'search_text',
        $this->t('Please enter a search term.')
      );
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    $query = $form_state->getValue('search_text');

    $app = $this->application;

    if (!$app) {
      $form_state->set('search_results', []);
      return;
    }

    $results = $this->searchService->search(
      $app->project_name,
      $app->location,
      $app->data_store_name,
      $app->serving_config,
      $query
    );

    // Store results in temp storage for rebuild.
    $form_state->set('search_results', $results);

    // This triggers full page reload.
    $form_state->setRebuild(TRUE);
  }

}
