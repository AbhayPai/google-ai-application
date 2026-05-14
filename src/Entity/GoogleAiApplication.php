<?php

namespace Drupal\google_ai_application\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines the Google AI Application config entity.
 *
 * @ConfigEntityType(
 *   id = "google_ai_application",
 *   label = @Translation("Google AI Application"),
 *   handlers = {
 *     "list_builder" = "Drupal\google_ai_application\GoogleAiApplicationListBuilder",
 *     "view_builder" = "Drupal\google_ai_application\GoogleAiApplicationViewBuilder",
 *     "form" = {
 *       "add" = "Drupal\google_ai_application\Form\ConfigEntityForm",
 *       "edit" = "Drupal\google_ai_application\Form\ConfigEntityForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *       "search_config" = "Drupal\google_ai_application\Form\SearchConfigForm"
 *     }
 *   },
 *   admin_permission = "administer site configuration",
 *   config_prefix = "google_ai_application",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/google-ai/application/{google_ai_application}",
 *     "collection" = "/admin/config/search/google-ai/application",
 *     "add-form" = "/admin/config/search/google-ai/application/add",
 *     "edit-form" = "/admin/config/search/google-ai/application/{google_ai_application}/edit",
 *     "delete-form" = "/admin/config/search/google-ai/application/{google_ai_application}/delete"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "content_type",
 *     "app_name",
 *     "project_name",
 *     "data_store_name",
 *     "collection_name",
 *     "schema_name",
 *     "struct_schema",
 *     "location",
 *     "branch_name",
 *     "serving_config",
 *     "field_title",
 *     "field_description_caption",
 *     "field_enable_prefilters",
 *     "field_show_camera_icon",
 *     "field_hero_search_cta_links",
 *     "footer_cards",
 *     "page_path",
 *   }
 * )
 */
class GoogleAiApplication extends ConfigEntityBase {

  protected string $id;
  protected string $label;

  protected string $app_name;
  protected string $project_name;
  protected string $data_store_name;

  protected string $collection_name = 'default_collection';
  protected string $schema_name = 'default_schema';

  /**
   * JSON schema string (NOT array for config safety)
   */
  protected string $struct_schema = '{}';

  protected string $location = 'global';
  protected string $branch_name = 'default_branch';
  protected string $serving_config = 'default_search';

  /**
   * Content types
   */
  protected array $content_type = [];

  /**
   * Hero title.
   */
  protected string $field_title = '';

  /**
   * Hero description caption.
   */
  protected string $field_description_caption = '';

  /**
   * Enable prefilters.
   */
  protected bool $field_enable_prefilters = FALSE;

  /**
   * Show camera icon.
   */
  protected bool $field_show_camera_icon = FALSE;

  /**
   * CTA links.
   */
  protected array $field_hero_search_cta_links = [];

  /**
   * Footer cards.
   */
  protected array $footer_cards = [];

  /**
   * Public route path.
   */
  protected string $page_path = '';

  /**
   * Check if entity supports a content type.
   */
  public function hasContentType(string $bundle): bool {
    return in_array(
      $bundle,
      $this->getContentTypes(),
      TRUE
    );
  }

  /**
   * Get content types.
   */
  public function getContentTypes(): array {
    return $this->content_type ?? [];
  }

}
