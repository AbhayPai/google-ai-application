<?php

namespace Drupal\google_ai_application\Service;

use Drupal\node\NodeInterface;
use Google\Cloud\DiscoveryEngine\V1\Document;
use Google\Protobuf\ListValue;
use Google\Protobuf\Struct;
use Google\Protobuf\Value;

/**
 * Transforms Drupal nodes into Discovery Engine documents.
 */
class DocumentTransformerService {

  /**
   * Transform Drupal nodes into Google documents.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   Loaded Drupal nodes.
   *
   * @return \Google\Cloud\DiscoveryEngine\V1\Document[]
   *   Google Discovery Engine documents.
   */
  public function transformNodes(array $nodes): array {
    $googleDocs = [];

    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }

      $category = '';
      if ($node->hasField('field_support_hub_category')) {
        $category_field = $node->get('field_support_hub_category');

        if (!$category_field->isEmpty()) {
          foreach ($category_field->referencedEntities() as $term) {
            $category = $term->label();
          }
        }
      }

      $usertype_array = [];
      if ($node->hasField('field_support_hub_user_type')) {
        $field_support_hub_user_type = $node->get('field_support_hub_user_type');

        if (!$field_support_hub_user_type->isEmpty()) {
          foreach ($field_support_hub_user_type->referencedEntities() as $term) {
            $usertype_array[] = $term->label();
          }
        }
      }

      $videourl = '';
      if ($node->hasField('field_video')) {
        $field_video = $node->get('field_video');

        if (!$field_video->isEmpty()) {
          $videourl = $field_video->entity->get('field_media_oembed_video')->getValue()[0]['value'];
        }
      }

      // @TODO: Hardcoded fields at the moment, next upgrade must have fields mapped with schema.
      $doc = [
        'id' => (string) $node->id(),
        'title' => $node->label(),
        'category' => $category,
        'usertype_array' => $usertype_array,
        'videourl' => $videourl,
        'langcode' => $node->language()->getId(),
        'created' => $node->getCreatedTime(),
        'changed' => $node->getChangedTime(),
        'url' => $node->toUrl(
          'canonical',
          ['absolute' => TRUE]
        )->toString(),
      ];

      $googleDocs[] = $this->buildGoogleDocument(
        (string) $node->id(),
        $doc
      );
    }

    return $googleDocs;
  }

  /**
   * Build Google Discovery document.
   */
  public function buildGoogleDocument(
    string $id,
    array $data
  ): Document {
    return new Document([
      'id' => $id,
      'struct_data' => $this->buildStruct($data),
    ]);
  }

  /**
   * Convert associative array into protobuf Struct.
   */
  protected function buildStruct(
    array $data
  ): Struct {

    $fields = [];

    foreach ($data as $key => $value) {
      $fields[$key] = $this->toValue($value);
    }

    return new Struct([
      'fields' => $fields,
    ]);
  }

  /**
   * Convert PHP values into protobuf Value objects.
   */
  protected function toValue(
    mixed $value
  ): Value {

    if ($value === NULL) {
      return new Value([
        'null_value' => 0,
      ]);
    }

    if (is_string($value)) {
      return new Value([
        'string_value' => $value,
      ]);
    }

    if (is_int($value) || is_float($value)) {
      return new Value([
        'number_value' => $value,
      ]);
    }

    if (is_bool($value)) {
      return new Value([
        'bool_value' => $value,
      ]);
    }

    if (is_array($value)) {
      // Associative array → Struct.
      if ($this->isAssoc($value)) {
        return new Value([
          'struct_value' => $this->buildStruct($value),
        ]);
      }

      // Indexed array → ListValue.
      $values = [];

      foreach ($value as $item) {
        $values[] = $this->toValue($item);
      }

      return new Value([
        'list_value' => new ListValue([
          'values' => $values,
        ]),
      ]);
    }

    return new Value([
      'string_value' => (string) $value,
    ]);
  }

  /**
   * Determine whether array is associative.
   */
  protected function isAssoc(
    array $array
  ): bool {
    return array_keys($array)
      !== range(0, count($array) - 1);
  }

}
