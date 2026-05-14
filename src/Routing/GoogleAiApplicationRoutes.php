<?php

namespace Drupal\google_ai_application\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class GoogleAiApplicationRoutes {

  /**
   * Dynamic route callback.
   */
  public static function routes(): RouteCollection {

    $collection = new RouteCollection();

    $storage = \Drupal::entityTypeManager()
      ->getStorage('google_ai_application');

    $entities = $storage->loadMultiple();

    foreach ($entities as $entity) {

      $path = $entity->page_path ?? '';

      if (empty($path)) {
        continue;
      }

      $route = new Route(
        $path,
        [
          '_controller' => '\Drupal\google_ai_application\Controller\SearchPageController::view',
          'google_ai_application' => $entity->id(),
          '_title' => $entity->label(),
        ],
        [
          '_permission' => 'access content',
        ]
      );

      $collection->add(
        'google_ai_application.dynamic.' . $entity->id(),
        $route
      );
    }

    return $collection;
  }

}
