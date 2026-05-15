<?php

namespace Drupal\google_ai_application\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Google AI Application configuration form.
 */
class ConfigEntityForm extends EntityForm {

  protected EntityFieldManagerInterface $fieldManager;

  public function __construct(
    EntityFieldManagerInterface $fieldManager
  ) {
    $this->fieldManager = $fieldManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_field.manager'),
    );
  }

  public function form(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\google_ai_application\Entity\GoogleAiApplication $entity */
    $entity = $this->entity;

    $content_types = [];
    foreach (NodeType::loadMultiple() as $type) {
      $content_types[$type->id()] = $type->label();
    }

    $form['config'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-config',
    ];

    // =========================
    // APPLICATION CONFIG
    // =========================
    $form['application'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuration'),
      '#group' => 'config',
    ];

    $form['application']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#required' => TRUE,
      '#default_value' => $entity->label(),
      '#disabled' => !$entity->isNew(),
    ];

    $form['application']['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\google_ai_application\Entity\GoogleAiApplication::load',
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['application']['project_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project ID'),
      '#required' => TRUE,
      '#default_value' => $entity->get('project_name'),
      '#disabled' => !$entity->isNew(),
    ];

    $form['application']['app_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App Name'),
      '#required' => TRUE,
      '#default_value' => $entity->get('app_name'),
      '#disabled' => !$entity->isNew(),
    ];

    $form['application']['data_store_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data Store Name'),
      '#required' => TRUE,
      '#default_value' => $entity->get('data_store_name'),
      '#disabled' => !$entity->isNew(),
    ];

    $form['application']['schema_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Schema Name'),
      '#required' => TRUE,
      '#default_value' => $entity->get('schema_name'),
      '#disabled' => !$entity->isNew(),
    ];

    $form['application']['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#required' => TRUE,
      '#default_value' => $entity->get('location'),
      '#disabled' => !$entity->isNew(),
    ];

    $form['application']['branch_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Branch Name'),
      '#required' => TRUE,
      '#default_value' => $entity->get('branch_name'),
      '#disabled' => !$entity->isNew(),
    ];

    $form['application']['serving_config'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Serving Config'),
      '#required' => TRUE,
      '#default_value' => $entity->get('serving_config'),
      '#disabled' => !$entity->isNew(),
    ];

    // =========================
    // CONTENT TYPES
    // =========================
    $form['schema'] = [
      '#type' => 'details',
      '#title' => $this->t('Schema'),
      '#group' => 'config',
    ];

    $form['schema']['content_type'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content Types'),
      '#options' => $content_types,
      '#required' => TRUE,
      '#default_value' => array_values((array) $entity->get('content_type')),
      '#disabled' => !$entity->isNew(),
    ];

    return parent::form($form, $form_state);
  }

  public function save(array $form, FormStateInterface $form_state): int {

    /** @var \Drupal\google_ai_application\Entity\GoogleAiApplication $entity */
    $entity = $this->entity;

    $content_types = array_values(array_filter(
      $form_state->getValue('content_type') ?? []
    ));

    $entity->set('content_type', $content_types);
    $entity->set('project_name', $form_state->getValue('project_name'));
    $entity->set('data_store_name', $form_state->getValue('data_store_name'));
    $entity->set('schema_name', $form_state->getValue('schema_name'));
    $entity->set('location', $form_state->getValue('location'));
    $entity->set('branch_name', $form_state->getValue('branch_name'));
    $entity->set('serving_config', $form_state->getValue('serving_config'));
    $entity->set('app_name', $form_state->getValue('app_name'));

    $status = $entity->save();

    $this->messenger()->addStatus(
      $this->t('Google AI Application configuration saved.')
    );

    $form_state->setRedirect('entity.google_ai_application.collection');

    return $status;
  }

}
