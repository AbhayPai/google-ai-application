<?php

namespace Drupal\google_ai_application\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\google_ai_application\Service\ApplicationWorkflowService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Purge form (entity-based).
 */
class PurgeForm extends FormBase {

  public function __construct(
    protected ApplicationWorkflowService $workflow,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('google_ai_application.workflow'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter')
    );
  }

  public function getFormId(): string {
    return 'google_ai_application_purge_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $google_ai_application = NULL): array {

    $entity = $this->entityTypeManager
      ->getStorage('google_ai_application')
      ->load($google_ai_application);

    if (!$entity) {
      $this->messenger()->addError($this->t('Application not found.'));
      return [];
    }

    $form['config'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-config',
    ];

    $form['purge'] = [
      '#type' => 'details',
      '#title' => $this->t('Purge'),
      '#group' => 'config',
    ];

    $form['purge']['info'] = [
      '#markup' => $this->t('<h2>Purge index: @label</h2>', [
        '@label' => $entity->label(),
      ]),
    ];

    $form['entity_id'] = [
      '#type' => 'hidden',
      '#value' => $entity->id(),
    ];

    $form['purge']['actions'] = [
      '#type' => 'actions',
    ];

    $form['purge']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Purge Index'),
      '#button_type' => 'danger',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $entity = $this->loadEntity($form_state);

    if (!$entity) {
      return;
    }

    $this->workflow->purge($entity);

    $this->messenger()->addStatus($this->t('Purge completed.'));
  }

  private function loadEntity(FormStateInterface $form_state) {
    return $this->entityTypeManager
      ->getStorage('google_ai_application')
      ->load($form_state->getValue('entity_id'));
  }
}
