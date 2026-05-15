<?php

namespace Drupal\google_ai_application\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\google_ai_application\Service\ApplicationWorkflowService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Schema configuration form.
 */
class SchemaForm extends FormBase {

  public function __construct(
    protected ApplicationWorkflowService $workflow,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ExtensionPathResolver $extensionPathResolver,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('google_ai_application.workflow'),
      $container->get('entity_type.manager'),
      $container->get('extension.path.resolver')
    );
  }

  public function getFormId(): string {
    return 'google_ai_application_schema_form';
  }

  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $google_ai_application = NULL
  ): array {
    $entity = $this->entityTypeManager
      ->getStorage('google_ai_application')
      ->load($google_ai_application);

    if (!$entity) {
      $this->messenger()->addError($this->t('Application not found.'));
      return [];
    }

    $module_path = $this->extensionPathResolver->getPath(
      'module',
      'google_ai_application'
    );

    $schema_url = base_path() . $module_path . '/examples/data/Initial-Schema.json';

    $form['config'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-config',
    ];

    $form['schema'] = [
      '#type' => 'details',
      '#title' => $this->t('Schema'),
      '#group' => 'config',
    ];

    $form['schema']['struct_schema'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Struct Schema (JSON)'),
      '#default_value' => $entity->get('struct_schema') ?: '',
      '#description' => $this->t(
        'Enter schema JSON.
        <br>
        <a href="https://cloud.google.com/generative-ai-app-builder/docs/provide-schema" target="_blank">Schema docs</a>
        <br>
        <a href=":url" target="_blank">Sample schema</a>',
        [':url' => $schema_url]
      ),
      '#rows' => 20,
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
      '#value' => $this->t('Save & Sync Schema'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $json = trim($form_state->getValue('struct_schema'));

    json_decode($json, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      $form_state->setErrorByName(
        'struct_schema',
        $this->t('Invalid JSON: @msg', [
          '@msg' => json_last_error_msg(),
        ])
      );
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity = $this->loadEntity($form_state);

    if (!$entity) {
      return;
    }

    $schema = json_decode($form_state->getValue('struct_schema'), TRUE);
    $result = $this->workflow->syncSchema($entity, $schema);

    if (!empty($result['success'])) {
      $this->messenger()->addStatus($this->t('Schema synced successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Schema sync failed. @msg', [
        '@msg' => $result['error']
      ]));
    }
  }

  private function loadEntity(FormStateInterface $form_state) {
    return $this->entityTypeManager
      ->getStorage('google_ai_application')
      ->load($form_state->getValue('entity_id'));
  }
}
