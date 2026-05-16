<?php

namespace Drupal\google_ai_application\Plugin\search_api\backend;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Item\Item;
use Drupal\search_api\Query\QueryInterface;

/**
 * @SearchApiBackend(
 *   id = "google_ai_backend",
 *   label = @Translation("Google AI Backend"),
 *   description = @Translation("SearchService-based backend with configuration"),
 * )
 */
class GoogleAiBackend extends BackendPluginBase implements PluginFormInterface {

  public function defaultConfiguration() {
    return [
      'project_name' => '',
      'location' => '',
      'data_store_name' => '',
      'serving_config' => '',
      'branch_name' => '',
      'schema_name' => '',
      'page_size' => 10,
    ] + parent::defaultConfiguration();
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['project_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project name'),
      '#default_value' => $this->configuration['project_name'] ?? '',
    ];

    $form['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#default_value' => $this->configuration['location'] ?? '',
    ];

    $form['data_store_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data store name'),
      '#default_value' => $this->configuration['data_store_name'] ?? '',
    ];

    $form['serving_config'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Serving config'),
      '#default_value' => $this->configuration['serving_config'] ?? '',
    ];

    $form['branch_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Branch name'),
      '#default_value' => $this->configuration['branch_name'] ?? '',
    ];

    $form['schema_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Schema name'),
      '#default_value' => $this->configuration['schema_name'] ?? '',
    ];

    $form['page_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Page size'),
      '#default_value' => $this->configuration['page_size'] ?? 10,
      '#min' => 1,
    ];

    return $form;
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    foreach ([
      'project_name',
      'location',
      'data_store_name',
      'serving_config',
      'branch_name',
      'schema_name',
      'page_size',
    ] as $key) {
      $this->configuration[$key] = $form_state->getValue($key);
    }
  }

  /**
   * =========================
   * SEARCH (Views compatible)
   * =========================
   */
  public function search(QueryInterface $query) {

    $keys = (string) $query->getKeys();
    $offset = $query->getOption('offset', 0);

    /** @var \Drupal\google_ai_application\Service\SearchService $search_service */
    $search_service = \Drupal::service('google_ai_application.search');

    $config = $this->configuration;

    $response = $search_service->search(
      $config['project_name'],
      $config['location'],
      $config['data_store_name'],
      $config['serving_config'],
      $keys,
      $offset
    );

    // ✅ This is the ONLY correct container in your Search API version
    $results = $query->getResults();

    $items = [];
    $count = 0;

    if (is_array($response)) {
      foreach ($response as $row) {

        $id = $row['id'] ?? NULL;
        if (!$id) {
          continue;
        }

        // ✅ Supported in your version
        $item = new Item($query->getIndex(), $id);

        foreach ($row as $field_id => $value) {

          if ($field_id === 'id') {
            continue;
          }

          $field = $item->getField($field_id);

          if ($field) {
            $field->setValues(
              is_array($value) ? $value : [$value]
            );
          }
        }

        $items[$id] = $item;
        $count++;
      }
    }

    // ✅ Correct API for your version
    $results->setResultItems($items);
    $results->setResultCount($count);

    return $results;
  }

  /**
   * =========================
   * INDEXING
   * =========================
   */
  public function indexItems(IndexInterface $index, array $items) {

    /** @var \Drupal\google_ai_application\Service\DocumentService $documentService */
    $documentService = \Drupal::service('google_ai_application.document');

    $documents = $this->transformItems($items);

    $documentService->importDocuments(
      $this->configuration['project_name'],
      $this->configuration['location'],
      $this->configuration['data_store_name'],
      $this->configuration['branch_name'],
      $documents
    );

    return array_keys($items);
  }

  public function deleteItems(IndexInterface $index, array $item_ids) {
    // Optional
  }

  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL) {

    /** @var \Drupal\google_ai_application\Service\DocumentService $documentService */
    $documentService = \Drupal::service('google_ai_application.document');

    $documentService->purgeDocuments(
      $this->configuration['project_name'],
      $this->configuration['location'],
      $this->configuration['data_store_name'],
      $this->configuration['branch_name']
    );
  }

  /**
   * =========================
   * INDEX TRANSFORMATION
   * =========================
   */
  public function transformItems(array $items): array {

    /** @var \Drupal\google_ai_application\Service\DocumentTransformerService $transformer */
    $transformer = \Drupal::service('google_ai_application.document_transformer');

    $google_docs = [];

    foreach ($items as $item) {

      if (!$item instanceof ItemInterface) {
        continue;
      }

      $data = [];

      foreach ($item->getFields() as $field_id => $field) {

        $values = $field->getValues();

        if (empty($values)) {
          continue;
        }

        $data[$field_id] = count($values) === 1
          ? reset($values)
          : array_values($values);
      }

      $google_docs[] = $transformer->buildGoogleDocument(
        $this->getDocumentId($item),
        $data
      );
    }

    return $google_docs;
  }

  private function getDocumentId(ItemInterface $item): string {

    $entity = $item->getOriginalObject()->getValue();

    if ($entity instanceof \Drupal\Core\Entity\EntityInterface) {
      return $entity->uuid();
    }

    return $this->normalizeId((string) $item->getId());
  }

  protected function normalizeId(string $id): string {

    $id = preg_replace('/[^a-zA-Z0-9-_]/', '_', $id);
    $id = preg_replace('/_+/', '_', $id);

    return trim($id, '_-');
  }

}
