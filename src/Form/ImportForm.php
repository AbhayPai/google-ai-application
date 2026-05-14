<?php

namespace Drupal\google_ai_application\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\google_ai_application\Service\ApplicationWorkflowService;
use Drupal\google_ai_application\Service\DocumentTransformerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\google_ai_application\Service\DocumentImportBatchService;

class ImportForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DateFormatterInterface $dateFormatter,
    protected DocumentTransformerService $documentTransformer,
    protected ApplicationWorkflowService $workflow,
    protected DocumentImportBatchService $documentImportBatch,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('google_ai_application.document_transformer'),
      $container->get('google_ai_application.workflow'),
      $container->get('google_ai_application.document_import_batch')
    );
  }

  public function getFormId(): string {
    return 'google_ai_application_import_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $google_ai_application = NULL): array {
    $entity = $this->entityTypeManager
      ->getStorage('google_ai_application')
      ->load($google_ai_application);

    if (!$entity) {
      $this->messenger()->addError($this->t('Application not found.'));
      return [];
    }

    $ready = $this->workflow->isReadyForImport($entity);

    $form['info'] = [
      '#markup' => $this->t('<h2>Import: @label</h2>', ['@label' => $entity->label()]),
    ];

    $form['entity_id'] = [
      '#type' => 'hidden',
      '#value' => $entity->id(),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import Documents'),
      '#button_type' => 'primary',
      '#disabled' => !$ready,
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity = $this->loadEntity($form_state);
    if (!$entity) return;

    $this->documentImportBatch->start(
      (array) $entity->get('content_type'),
      [
        'project_id' => $entity->get('project_name'),
        'location' => $entity->get('location'),
        'data_store_id' => $entity->get('data_store_name'),
        'branch' => $entity->get('branch_name'),
        'entity_id' => $entity->id(),
      ]
    );
  }

  private function loadEntity(FormStateInterface $form_state) {
    return $this->entityTypeManager
      ->getStorage('google_ai_application')
      ->load($form_state->getValue('entity_id'));
  }

}
