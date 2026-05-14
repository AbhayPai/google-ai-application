<?php

namespace Drupal\google_ai_application\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Clean GET-only search form.
 */
class SearchForm extends FormBase {

  public function getFormId(): string {
    return 'google_ai_application_search_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {

    $request = \Drupal::request();
    $query = trim($request->query->get('search_text', ''));

    // Force GET behavior.
    $form['#method'] = 'GET';

    // IMPORTANT: prevent Drupal POST submit system.
    $form['#action'] = $request->getRequestUri();
    $form['#submit'] = [];
    $form['#validate'] = [];

    $form['search_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#default_value' => $query,
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Search support articles...'),
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    return $form;
  }

  /**
   * No submit logic needed anymore.
   * (Intentionally empty)
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Intentionally empty.
  }

}
