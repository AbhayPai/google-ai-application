// js/search-ajax.js
(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.googleAiAjaxSearch = {
    attach: function (context, settings) {
      const config = drupalSettings.googleAi || {};
      if (!config.ajaxUrl) return;

      let nextPageToken = config.nextPageToken || null;
      const $resultsContainer = $('#google-ai-results', context);
      const $loadMoreBtn = $('#load-more-btn', context);
      let isLoading = false;

      // Handle Load More button for infinite scroll style pagination
      if ($loadMoreBtn.length) {
        $loadMoreBtn.once('google-ai-loadmore').on('click', function () {
          if (!nextPageToken || isLoading) return;

          const $btn = $(this);
          isLoading = true;
          $btn.prop('disabled', true).text('Loading...');

          $.ajax({
            url: config.ajaxUrl,
            data: {
              q: config.initialQuery,
              page_token: nextPageToken,
              page_size: 10
            },
            success: function (response) {
              if (response.results && response.results.length > 0) {
                response.results.forEach(function (result) {
                  const $resultDiv = $('<div class=\"search-result\">')
                    .append($('<h3>').text(result.title))
                    .append($('<small>').text('ID: ' + Drupal.checkPlain(result.id)));
                  $resultsContainer.append($resultDiv);
                });
              }

              nextPageToken = response.next_page_token || null;

              if (!nextPageToken) {
                $btn.hide(); // Correctly hides the button when the list is exhausted
              } else {
                $btn.prop('disabled', false).text('Load More Results');
              }

              isLoading = false;
            },
            error: function (xhr, status, error) {
              console.error('AJAX search failed:', error);
              $btn.text('Error - Try again');
              $btn.prop('disabled', false);
              isLoading = false;
            }
          });
        });
      }

      // Handle URL-based pagination if present (page parameter in URL)
      const urlParams = new URLSearchParams(window.location.search);
      const page = parseInt(urlParams.get('page')) || 1;
      if (page > 1) {
        // Page already loaded by server, no additional action needed
        // but we could add analytics or scroll here
        window.scrollTo(0, 0);
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
