<?php

namespace Drupal\google_ai_application\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

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
 *       "search_component" = "Drupal\google_ai_application\Form\SearchComponentForm"
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
 *     "selected_facet_fields",
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
 *     "page_path"
 *   }
 * )
 */
class GoogleAiApplication extends ConfigEntityBase {

  /**
   * ID of config entity.
   */
  protected string $id;

  /**
   * Label.
   */
  protected string $label;

  /**
   * Selected content types (node bundles).
   *
   * @var string[]
   */
  protected array $content_type = [];

  /**
   * Selected fields per content type.
   *
   * Format:
   * [
   *   0 => "field_support_hub_category",
   *   1 => "field_support_hub_user_type"
   * ]
   *
   * @var array
   */
  protected array $selected_facet_fields = [];

  /**
   * App name.
   */
  protected string $app_name = '';

  /**
   * Project name.
   */
  protected string $project_name = '';

  /**
   * Data store name.
   */
  protected string $data_store_name = '';

  /**
   * Collection name.
   */
  protected string $collection_name = 'default_collection';

  /**
   * Schema name.
   */
  protected string $schema_name = 'default_schema';

  /**
   * JSON schema string.
   */
  protected string $struct_schema = '{}';

  /**
   * Location.
   */
  protected string $location = 'global';

  /**
   * Branch name.
   */
  protected string $branch_name = 'default_branch';

  /**
   * Serving config.
   */
  protected string $serving_config = 'default_search';

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
   *
   * @var array
   */
  protected array $field_hero_search_cta_links = [];

  /**
   * Footer cards.
   *
   * @var array
   */
  protected array $footer_cards = [];

  /**
   * Page path.
   */
  protected string $page_path = '';

  /**
   * Get content types.
   */
  public function getContentTypes(): array {
    return $this->content_type ?? [];
  }

  /**
   * Check if entity supports a content type.
   */
  public function hasContentType(string $bundle): bool {
    return in_array($bundle, $this->getContentTypes(), TRUE);
  }

}
