/**
 * WordPress Review Bot Frontend JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('WordPress Review Bot Frontend loaded');

        // Initialize frontend functionality
        initFrontend();
    });

    function initFrontend() {
        // Add any frontend-specific JavaScript here
        initReviewCards();
        initFormValidation();
    }

    function initReviewCards() {
        $('.wrb-review-card').each(function() {
            const $card = $(this);

            // Add click handlers for review interactions
            $card.on('click', '.wrb-helpful-btn', function(e) {
                e.preventDefault();
                handleHelpfulClick($(this));
            });
        });
    }

    function handleHelpfulClick($button) {
        const reviewId = $button.data('review-id');
        const isHelpful = $button.data('helpful');

        // Show loading state
        $button.prop('disabled', true).text('Submitting...');

        // Simulate API call
        $.ajax({
            url: wrb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wrb_mark_helpful',
                review_id: reviewId,
                helpful: isHelpful,
                nonce: wrb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $button.text('Thanks!');
                    $button.prop('disabled', true);
                } else {
                    $button.prop('disabled', false).text('Error');
                }
            },
            error: function() {
                $button.prop('disabled', false).text('Error');
            }
        });
    }

    function initFormValidation() {
        $('.wrb-review-form').on('submit', function(e) {
            e.preventDefault();

            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');

            // Basic validation
            const title = $form.find('#wrb-review-title').val().trim();
            const content = $form.find('#wrb-review-content').val().trim();

            if (!title || !content) {
                alert('Please fill in all required fields.');
                return;
            }

            // Show loading state
            $submitBtn.prop('disabled', true).text('Submitting...');

            // Simulate form submission
            setTimeout(() => {
                $form[0].reset();
                $submitBtn.prop('disabled', false).text('Submit Review');
                alert('Review submitted successfully!');
            }, 1500);
        });
    }

})(jQuery);