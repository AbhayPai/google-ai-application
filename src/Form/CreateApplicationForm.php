<?php

namespace Drupal\google_ai_application\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\google_ai_application\Service\ApplicationWorkflowService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a new application in Google Gen App Builder.
 */
class CreateApplicationForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ApplicationWorkflowService $workflow;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ApplicationWorkflowService $workflow
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->workflow = $workflow;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('google_ai_application.workflow')
    );
  }

  public function getFormId(): string {
    return 'google_ai_application_create_application_form';
  }

  /**
   * Load entity helper.
   */
  private function loadEntity(FormStateInterface $form_state) {
    $id = $form_state->getValue('entity_id');

    return $this->entityTypeManager
      ->getStorage('google_ai_application')
      ->load($id);
  }

  /**
   * Build form.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $google_ai_application = NULL
  ): array {

    $entity = $this->entityTypeManager
      ->getStorage('google_ai_application')
      ->load($google_ai_application);

    if (!$entity) {
      $this->messenger()->addError($this->t('Application entry not found.'));
      return $form;
    }

    $step = $this->workflow->getStep($entity);

    $form['info'] = [
      '#markup' => $this->t('<h2>Application: @label</h2>', [
        '@label' => $entity->label(),
      ]),
    ];

    $form['entity_id'] = [
      '#type' => 'hidden',
      '#value' => $entity->id(),
    ];

    // =========================
    // STEP-BASED UI FLOW
    // =========================

    switch ($step) {

      case 'create_datastore':
        $form['action'] = [
          '#type' => 'submit',
          '#value' => $this->t('Create Data Store'),
          '#submit' => ['::submitCreateDataStore'],
          '#button_type' => 'primary',
        ];
        break;

      case 'create_engine':
        $form['create_engine'] = [
          '#type' => 'submit',
          '#value' => $this->t('Create Engine'),
          '#submit' => ['::submitCreateEngine'],
          '#button_type' => 'primary',
        ];
        $form['delete_datastore'] = [
          '#type' => 'submit',
          '#value' => $this->t('Delete Data Store'),
          '#submit' => ['::submitDeleteDataStore'],
          '#button_type' => 'danger',
        ];
        break;

      case 'delete_app':
        $form['action'] = [
          '#type' => 'submit',
          '#value' => $this->t('Delete Application'),
          '#submit' => ['::submitDeleteApp'],
          '#button_type' => 'danger',
        ];
        break;
    }

    return $form;
  }

  /**
   * STEP 1: Create Data Store
   */
  public function submitCreateDataStore(array &$form, FormStateInterface $form_state): void {

    $entity = $this->loadEntity($form_state);

    if (!$entity) {
      return;
    }

    $result = $this->workflow->createDataStore($entity);

    if (!empty($result['success'])) {
      $this->messenger()->addStatus($this->t('Data store created successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Data store creation failed: @msg', [
        '@msg' => $result['error'] ?? 'Unknown error',
      ]));
    }
  }

  /**
   * Delete datastore.
   */
  public function submitDeleteDataStore(
    array &$form,
    FormStateInterface $form_state
  ): void {

    $entity = $this->loadEntity($form_state);

    if (!$entity) {
      return;
    }

    $result = $this->workflow->deleteDataStore($entity);

    if (!empty($result['success'])) {
      $this->messenger()->addStatus(
        $this->t('Data store deleted successfully.')
      );
    }
    else {
      $this->messenger()->addError(
        $this->t('Data store deletion failed.')
      );
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * STEP 2: Create Engine
   */
  public function submitCreateEngine(array &$form, FormStateInterface $form_state): void {

    $entity = $this->loadEntity($form_state);

    if (!$entity) {
      return;
    }

    $result = $this->workflow->createEngine($entity);

    if (!empty($result['success'])) {
      $this->messenger()->addStatus($this->t('Engine created successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Engine creation failed: @msg', [
        '@msg' => $result['error'] ?? 'Unknown error',
      ]));
    }
  }

  /**
   * STEP 3: Delete Application
   */
  public function submitDeleteApp(array &$form, FormStateInterface $form_state): void {

    $entity = $this->loadEntity($form_state);

    if (!$entity) {
      return;
    }

    $this->workflow->deleteApplication($entity);

    $this->messenger()->addStatus($this->t('Application deleted successfully.'));
  }

  /**
   * Not used.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}
}
