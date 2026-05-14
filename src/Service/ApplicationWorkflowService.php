<?php

namespace Drupal\google_ai_application\Service;

use Drupal\Core\Entity\EntityInterface;

class ApplicationWorkflowService {

  public function __construct(
    protected DataStoreService $dataStoreService,
    protected EngineService $engineService,
    protected SchemaService $schemaService,
    protected DocumentService $documentService,
  ) {}

  /**
   * Get workflow state.
   */
  public function getStep(EntityInterface $entity): string {
    $datastore_exists = $this->dataStoreExists($entity);
    $engine_exists = $this->engineExists($entity);

    // Nothing exists.
    if (!$datastore_exists) {
      return 'create_datastore';
    }

    // Datastore exists but engine does not.
    if ($datastore_exists && !$engine_exists) {
      return 'create_engine';
    }

    // Everything exists.
    return 'delete_app';
  }

  /**
   * Check datastore existence.
   */
  public function dataStoreExists(EntityInterface $entity): bool {
    return $this->dataStoreService->dataStoreExists(
      $entity->get('project_name'),
      $entity->get('location'),
      $entity->get('data_store_name')
    );
  }

  /**
   * Create datastore.
   */
  public function createDataStore(EntityInterface $entity): array {
    return $this->dataStoreService->createDataStore(
      $entity->get('project_name'),
      $entity->get('location'),
      $entity->get('collection_name'),
      $entity->get('data_store_name'),
      $entity->get('data_store_name')
    );
  }

  /**
   * Delete datastore.
   */
  public function deleteDataStore(EntityInterface $entity): array {
    return $this->dataStoreService->deleteDataStore(
      $entity->get('project_name'),
      $entity->get('location'),
      $entity->get('data_store_name')
    );
  }

  /**
   * Check engine existence.
   */
  public function engineExists(EntityInterface $entity): bool {
    return $this->engineService->engineExists(
      $entity->get('project_name'),
      $entity->get('location'),
      $entity->get('collection_name'),
      $entity->get('app_name')
    );
  }

  /**
   * Create engine.
   */
  public function createEngine(EntityInterface $entity): array {
    return $this->engineService->createEngine(
      $entity->get('project_name'),
      $entity->get('location'),
      $entity->get('collection_name'),
      $entity->get('app_name'),
      $entity->get('app_name'),
      $entity->get('data_store_name')
    );
  }

  /**
   * Delete engine.
   */
  public function deleteEngine(EntityInterface $entity): array {
    return $this->engineService->deleteEngine(
      $entity->get('project_name'),
      $entity->get('location'),
      $entity->get('collection_name'),
      $entity->get('app_name')
    );
  }

  /**
   * Delete full application.
   */
  public function deleteApplication(EntityInterface $entity): void {
    if ($this->engineExists($entity)) {
      $this->deleteEngine($entity);
    }

    if ($this->dataStoreExists($entity)) {
      $this->deleteDataStore($entity);
    }
  }

  /**
   * Sync schema.
   */
  public function syncSchema(
    EntityInterface $entity,
    array $schema
  ): array {

    if (empty($entity->get('schema_name'))) {
      return [
        'success' => FALSE,
        'error' => 'Schema name is missing.',
      ];
    }

    $result = $this->schemaService->updateSchema(
      $entity->get('project_name'),
      $entity->get('location'),
      $entity->get('data_store_name'),
      $entity->get('schema_name'),
      json_encode($schema)
    );

    // Save locally after successful sync.
    if (!empty($result['success'])) {
      $entity->set(
        'struct_schema',
        json_encode(
          $schema,
          JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        )
      );

      $entity->save();
    }

    return $result;
  }

  /**
   * Delete engine.
   */
  public function purge(EntityInterface $entity): array {
    return $this->documentService->purgeDocuments(
      $entity->get('project_name'),
      $entity->get('location'),
      $entity->get('data_store_name'),
      $entity->get('branch_name')
    );
  }

  /**
   * Check if application is ready for import.
   */
  public function isReadyForImport(
    EntityInterface $entity
  ): bool {
    return
      $this->dataStoreExists($entity) &&
      $this->engineExists($entity) &&
      !empty($entity->get('project_name')) &&
      !empty($entity->get('location')) &&
      !empty($entity->get('data_store_name')) &&
      !empty($entity->get('branch_name'));
  }
}
