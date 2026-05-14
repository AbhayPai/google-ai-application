<?php

namespace Drupal\google_ai_application\Service;

use Drupal\google_ai_application\Entity\GoogleAiApplication;

/**
 * Centralized state validation for Google AI Application.
 *
 * This service defines all "business rules" for when an application
 * is ready for different operations (create engine, import, purge, etc).
 */
class ApplicationStateService {

  /**
   * Check base configuration completeness.
   */
  public function hasBaseConfiguration(GoogleAiApplication $entity): bool {
    return
      !empty($entity->get('project_name')) &&
      !empty($entity->get('location')) &&
      !empty($entity->get('data_store_name')) &&
      !empty($entity->get('app_name')) &&
      !empty($entity->get('schema_name'));
  }

  /**
   * Ready for DataStore creation.
   *
   * Only requires base configuration.
   */
  public function isReadyForDataStore(GoogleAiApplication $entity): bool {
    return $this->hasBaseConfiguration($entity);
  }

  /**
   * Ready for Engine creation.
   *
   * Requires:
   * - DataStore created flag must be TRUE
   */
  public function isReadyForEngine(GoogleAiApplication $entity): bool {
    return
      $this->hasBaseConfiguration($entity) &&
      (bool) $entity->get('data_store_created');
  }

  /**
   * Fully ready application (Engine + DataStore).
   */
  public function isReadyForUse(GoogleAiApplication $entity): bool {
    return
      $this->isReadyForEngine($entity) &&
      (bool) $entity->get('app_created');
  }

  /**
   * Ready for schema sync.
   *
   * Schema can be synced once datastore exists (logical dependency).
   */
  public function isReadyForSchema(GoogleAiApplication $entity): bool {
    return $this->hasBaseConfiguration($entity);
  }

  /**
   * Ready for document import.
   *
   * Requires full system readiness + branch configured.
   */
  public function isReadyForImport(GoogleAiApplication $entity): bool {
    return
      $this->isReadyForUse($entity) &&
      !empty($entity->get('branch_name')) &&
      !empty($entity->get('content_type'));
  }

  /**
   * Ready for purge operation.
   */
  public function isReadyForPurge(GoogleAiApplication $entity): bool {
    return $this->isReadyForUse($entity);
  }

  /**
   * Check if application is fully configured (no missing fields).
   *
   * Useful for UI warnings.
   */
  public function isConfigurationComplete(GoogleAiApplication $entity): bool {
    return $this->hasBaseConfiguration($entity);
  }

  /**
   * Check if application is in initial state (nothing created yet).
   */
  public function isNewApplication(GoogleAiApplication $entity): bool {
    return
      !$entity->get('data_store_created') &&
      !$entity->get('app_created');
  }

  /**
   * Human-readable state label (optional UI helper).
   */
  public function getStateLabel(GoogleAiApplication $entity): string {

    if (!$this->hasBaseConfiguration($entity)) {
      return 'Incomplete Configuration';
    }

    if (!$entity->get('data_store_created')) {
      return 'DataStore Pending';
    }

    if (!$entity->get('app_created')) {
      return 'Engine Pending';
    }

    return 'Ready';
  }
}
