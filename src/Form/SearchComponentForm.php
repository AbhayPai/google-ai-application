<?php

namespace Drupal\google_ai_application\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Google AI Application entity search config form.
 */
class SearchComponentForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\google_ai_application\Entity\GoogleAiApplication $entity */
    $entity = $this->entity;

    $form = parent::form($form, $form_state);

    // -------------------------------------------------------------------------
    // Load values from form state or entity.
    // -------------------------------------------------------------------------

    $hero_title = $form_state->getValue(
      'field_title',
      $entity->field_title ?? ''
    );

    $hero_caption = $form_state->getValue(
      'field_description_caption',
      $entity->field_description_caption ?? ''
    );

    $enable_prefilters = $form_state->getValue(
      'field_enable_prefilters',
      $entity->field_enable_prefilters ?? FALSE
    );

    $show_camera_icon = $form_state->getValue(
      'field_show_camera_icon',
      $entity->field_show_camera_icon ?? FALSE
    );

    // -------------------------------------------------------------------------
    // CTA Links.
    // -------------------------------------------------------------------------

    $cta_links = $form_state->getValue('field_hero_search_cta_links');

    if ($cta_links === NULL) {
      $cta_links = $entity->field_hero_search_cta_links ?? [];
    }

    $cta_links = is_array($cta_links)
      ? array_values(array_filter($cta_links, 'is_array'))
      : [];

    if (empty($cta_links)) {
      $cta_links[] = [
        'title' => '',
        'url' => '',
      ];
    }

    // -------------------------------------------------------------------------
    // Footer Cards.
    // -------------------------------------------------------------------------

    $footer_cards = $form_state->getValue('footer_cards');

    if ($footer_cards === NULL) {
      $footer_cards = $entity->footer_cards ?? [];
    }

    $footer_cards = is_array($footer_cards)
      ? array_values(array_filter($footer_cards, 'is_array'))
      : [];

    if (empty($footer_cards)) {
      $footer_cards[] = [
        'field_title' => '',
        'field_content' => '',
        'field_icon' => '',
        'field_link' => '',
      ];
    }

    // -------------------------------------------------------------------------
    // Hero Banner Search Component.
    // -------------------------------------------------------------------------
    $form['config'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-config',
    ];

    $form['hero_banner_search_component'] = [
      '#type' => 'details',
      '#title' => $this->t('Hero Banner Search Component'),
      '#group' => 'config',
    ];

    $form['hero_banner_search_component']['field_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $hero_title,
    ];

    $form['hero_banner_search_component']['field_description_caption'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description caption'),
      '#default_value' => $hero_caption,
    ];

    $form['hero_banner_search_component']['field_enable_prefilters'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable prefilters'),
      '#default_value' => $enable_prefilters,
    ];

    $form['hero_banner_search_component']['field_show_camera_icon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show camera icon'),
      '#default_value' => $show_camera_icon,
    ];

    // -------------------------------------------------------------------------
    // CTA Links.
    // -------------------------------------------------------------------------

    $form['hero_banner_search_component']['field_hero_search_cta_links'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Hero search CTA links'),
      '#tree' => TRUE,
    ];

    foreach ($cta_links as $index => $link) {

      $form['hero_banner_search_component']['field_hero_search_cta_links'][$index] = [
        '#type' => 'fieldset',
        '#title' => $this->t('CTA link @number', [
          '@number' => $index + 1,
        ]),
        '#tree' => TRUE,
      ];

      $form['hero_banner_search_component']['field_hero_search_cta_links'][$index]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Link title'),
        '#default_value' => $link['title'] ?? '',
      ];

      $form['hero_banner_search_component']['field_hero_search_cta_links'][$index]['url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Link URL'),
        '#description' => $this->t('Enter a URL or path.'),
        '#default_value' => $link['url'] ?? '',
      ];

      if (count($cta_links) > 1) {
        $form['hero_banner_search_component']['field_hero_search_cta_links'][$index]['remove'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#submit' => ['::removeCTA'],
          '#limit_validation_errors' => [
            ['field_hero_search_cta_links'],
          ],
          '#cta_index' => $index,
        ];
      }
    }

    $form['hero_banner_search_component']['field_hero_search_cta_links']['add_more_cta_links'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another CTA link'),
      '#submit' => ['::addMoreCTA'],
      '#limit_validation_errors' => [
        ['field_hero_search_cta_links'],
      ],
    ];

    // -------------------------------------------------------------------------
    // Footer Card Component.
    // -------------------------------------------------------------------------

    $form['footer_card_component'] = [
      '#type' => 'details',
      '#title' => $this->t('Footer Card Component'),
      '#group' => 'config',
    ];

    $form['footer_card_component']['footer_cards'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Footer cards'),
      '#tree' => TRUE,
    ];

    foreach ($footer_cards as $index => $card) {
      $form['footer_card_component']['footer_cards'][$index] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Footer card @number', [
          '@number' => $index + 1,
        ]),
        '#tree' => TRUE,
      ];

      $form['footer_card_component']['footer_cards'][$index]['field_title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#default_value' => $card['field_title'] ?? '',
      ];

      $form['footer_card_component']['footer_cards'][$index]['field_content'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Content'),
        '#default_value' => $card['field_content'] ?? '',
      ];

      $form['footer_card_component']['footer_cards'][$index]['field_icon'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Icon'),
        '#default_value' => $card['field_icon'] ?? '',
      ];

      $form['footer_card_component']['footer_cards'][$index]['field_link'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Link'),
        '#description' => $this->t('Enter a URL or path.'),
        '#default_value' => $card['field_link'] ?? '',
      ];

      if (count($footer_cards) > 1) {
        $form['footer_card_component']['footer_cards'][$index]['remove'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#submit' => ['::removeFooterCard'],
          '#limit_validation_errors' => [
            ['footer_cards'],
          ],
          '#card_index' => $index,
        ];
      }
    }

    $form['footer_card_component']['footer_cards']['add_more_footer_cards'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another card'),
      '#submit' => ['::addMoreFooterCards'],
      '#limit_validation_errors' => [
        ['footer_cards'],
      ],
    ];

    $form['entity_id'] = [
      '#type' => 'hidden',
      '#value' => $entity->id(),
    ];

    return $form;
  }

  /**
   * Add CTA row.
   */
  public function addMoreCTA(array &$form, FormStateInterface $form_state) {

    $cta_links = $form_state->getValue('field_hero_search_cta_links') ?: [];

    $cta_links[] = [
      'title' => '',
      'url' => '',
    ];

    $form_state->setValue('field_hero_search_cta_links', $cta_links);

    $form_state->setRebuild(TRUE);
  }

  /**
   * Remove CTA row.
   */
  public function removeCTA(array &$form, FormStateInterface $form_state) {

    $trigger = $form_state->getTriggeringElement();

    $index = $trigger['#cta_index'];

    $cta_links = $form_state->getValue('field_hero_search_cta_links') ?: [];

    if (isset($cta_links[$index])) {
      unset($cta_links[$index]);

      $form_state->setValue(
        'field_hero_search_cta_links',
        array_values($cta_links)
      );
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * Add footer card row.
   */
  public function addMoreFooterCards(array &$form, FormStateInterface $form_state) {

    $footer_cards = $form_state->getValue('footer_cards') ?: [];

    $footer_cards[] = [
      'field_title' => '',
      'field_content' => '',
      'field_icon' => '',
      'field_link' => '',
    ];

    $form_state->setValue('footer_cards', $footer_cards);

    $form_state->setRebuild(TRUE);
  }

  /**
   * Remove footer card row.
   */
  public function removeFooterCard(array &$form, FormStateInterface $form_state) {

    $trigger = $form_state->getTriggeringElement();

    $index = $trigger['#card_index'];

    $footer_cards = $form_state->getValue('footer_cards') ?: [];

    if (isset($footer_cards[$index])) {

      unset($footer_cards[$index]);

      $form_state->setValue(
        'footer_cards',
        array_values($footer_cards)
      );
    }

    $form_state->setRebuild(TRUE);
  }

  private function loadEntity(FormStateInterface $form_state) {
    return $this->entityTypeManager
      ->getStorage('google_ai_application')
      ->load($form_state->getValue('entity_id'));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->loadEntity($form_state);
    if (!$entity) return;

    // -------------------------------------------------------------------------
    // Clean CTA links.
    // -------------------------------------------------------------------------

    $cta_links = array_filter(
      $form_state->getValue('field_hero_search_cta_links') ?: [],
      function ($item) {

        if (!is_array($item)) {
          return FALSE;
        }

        return !empty($item['title'])
          || !empty($item['url']);
      }
    );

    // -------------------------------------------------------------------------
    // Clean footer cards.
    // -------------------------------------------------------------------------

    $footer_cards = array_filter(
      $form_state->getValue('footer_cards') ?: [],
      function ($item) {

        if (!is_array($item)) {
          return FALSE;
        }

        return !empty($item['field_title'])
          || !empty($item['field_content'])
          || !empty($item['field_icon'])
          || !empty($item['field_link']);
      }
    );

    // -------------------------------------------------------------------------
    // Save entity fields.
    // -------------------------------------------------------------------------

    $entity->set('field_title', $form_state->getValue('field_title'));

    $entity->set(
      'field_description_caption',
      $form_state->getValue('field_description_caption')
    );

    $entity->set(
      'field_enable_prefilters',
      (bool) $form_state->getValue('field_enable_prefilters')
    );

    $entity->set(
      'field_show_camera_icon',
      (bool) $form_state->getValue('field_show_camera_icon')
    );

    $entity->set(
      'field_hero_search_cta_links',
      array_values($cta_links)
    );

    $entity->set(
      'footer_cards',
      array_values($footer_cards)
    );

    $status = $entity->save();

    $this->messenger()->addStatus(
      $this->t('Updated components for %label application.', [
        '%label' => $entity->label(),
      ])
    );

    $form_state->setRedirectUrl($entity->toUrl('collection'));
  }

}
