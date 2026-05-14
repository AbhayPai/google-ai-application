<?php

namespace Drupal\google_ai_application\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class GoogleAiSearchStateService {

  protected $session;

  public function __construct(SessionInterface $session) {
    $this->session = $session;
  }

  protected function key($entity_id): string {
    return 'google_ai_search_state_' . $entity_id;
  }

  public function reset($entity_id, $query) {
    $state = [
      'query' => $query,
      'current_page' => 1,
      'page_tokens' => [],     // Store all page tokens for history
      'results' => [],
      'total_pages' => 1,
    ];
    $this->updateState($entity_id, $state);
  }

  public function getState($entity_id): array {
    return $this->session->get($this->key($entity_id), [
      'query' => '',
      'results' => [],
      'current_page' => 1,
      'page_tokens' => [],
      'total_pages' => 1,
      'next_token' => NULL,
    ]);
  }

  public function updateState($entity_id, array $state): void {
    $this->session->set($this->key($entity_id), $state);
  }

  /**
   * Add a new page token for pagination history.
   */
  public function addPageToken($entity_id, $page_token) {
    $state = $this->getState($entity_id);
    if (!in_array($page_token, $state['page_tokens'])) {
      $state['page_tokens'][] = $page_token;
    }
    $state['current_page'] = count($state['page_tokens']) + 1;
    $state['total_pages'] = $state['current_page'];
    $this->updateState($entity_id, $state);
  }

  /**
   * Get the page token for a specific page number.
   */
  public function getPageToken($entity_id, $page_number) {
    $state = $this->getState($entity_id);
    if ($page_number === 1) {
      return NULL; // First page has no token
    }
    $token_index = $page_number - 2; // -1 for 0-based index, -1 more for first page
    return $state['page_tokens'][$token_index] ?? NULL;
  }

  /**
   * Set current page.
   */
  public function setCurrentPage($entity_id, $page_number) {
    $state = $this->getState($entity_id);
    $state['current_page'] = max(1, min($page_number, $state['total_pages']));
    $this->updateState($entity_id, $state);
  }

  /**
   * Update total pages count.
   */
  public function setTotalPages($entity_id, $total) {
    $state = $this->getState($entity_id);
    $state['total_pages'] = $total;
    $this->updateState($entity_id, $state);
  }
}
