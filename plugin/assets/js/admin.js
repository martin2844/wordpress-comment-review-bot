/**
 * WordPress Review Bot Admin JavaScript
 */
(function($) {
    'use strict';

    let selectedComments = [];

    $(document).ready(function() {
        console.log('WordPress Review Bot Admin loaded');

        // Initialize admin functionality
        initAdmin();
    });

    function initAdmin() {
        initCommentActions();
        initBulkActions();
        initKeyboardShortcuts();
        initRefreshButton();
        initSettings();
        initDecisionsPage();
        initProcessPendingComments();
    }

    // Helper function to get decision colors
    function getDecisionColor(decision) {
        switch(decision) {
            case 'approve': return 'background-color: #00a32a; color: white;';
            case 'reject': return 'background-color: #d63638; color: white;';
            case 'spam': return 'background-color: #ffb900; color: #2c3338;';
            default: return 'background-color: #666; color: white;';
        }
    }

    /**
     * Initialize individual comment actions
     */
    function initCommentActions() {
        // Comment action buttons
        $(document).on('click', '.wrb-action-approve', function(e) {
            e.preventDefault();
            const commentId = $(this).data('comment-id');
            performCommentAction('approve', commentId);
        });

        $(document).on('click', '.wrb-action-spam', function(e) {
            e.preventDefault();
            const commentId = $(this).data('comment-id');
            performCommentAction('spam', commentId);
        });

        $(document).on('click', '.wrb-action-trash', function(e) {
            e.preventDefault();
            const commentId = $(this).data('comment-id');
            performCommentAction('trash', commentId);
        });

        $(document).on('click', '.wrb-action-edit', function(e) {
            e.preventDefault();
            const commentId = $(this).data('comment-id');
            window.open(wrb_ajax.ajax_url + '?action=editcomment&c=' + commentId, '_blank');
        });

        // Comment selection
        $(document).on('change', '.wrb-comment-checkbox', function() {
            updateSelectedComments();
        });

        // Select all checkbox
        $(document).on('change', '#wrb-select-all', function() {
            const isChecked = $(this).prop('checked');
            $('.wrb-comment-checkbox').prop('checked', isChecked);
            updateSelectedComments();
        });
    }

    /**
     * Initialize bulk actions
     */
    function initBulkActions() {
        $('#wrb-bulk-action-apply').on('click', function(e) {
            e.preventDefault();
            const action = $('#wrb-bulk-action-selector').val();

            if (action === '-1') {
                showNotice('Please select an action to perform.', 'error');
                return;
            }

            if (selectedComments.length === 0) {
                showNotice(wrb_ajax.strings.no_comments_selected, 'error');
                return;
            }

            if (confirm(wrb_ajax.strings.confirm_bulk)) {
                performBulkAction(action, selectedComments);
            }
        });
    }

    /**
     * Initialize keyboard shortcuts
     */
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Only handle shortcuts when not in input fields
            if ($(e.target).is('input, textarea, select')) {
                return;
            }

            const key = e.key.toUpperCase();

            switch (key) {
                case 'A':
                    e.preventDefault();
                    if (selectedComments.length > 0) {
                        performBulkAction('approve', selectedComments);
                    }
                    break;
                case 'S':
                    e.preventDefault();
                    if (selectedComments.length > 0) {
                        performBulkAction('spam', selectedComments);
                    }
                    break;
                case 'T':
                    e.preventDefault();
                    if (selectedComments.length > 0) {
                        performBulkAction('trash', selectedComments);
                    }
                    break;
                case 'R':
                    e.preventDefault();
                    refreshCommentsList();
                    break;
                case ' ':
                    e.preventDefault();
                    toggleCurrentCommentSelection();
                    break;
                case 'ARROWUP':
                case 'ARROWDOWN':
                    e.preventDefault();
                    navigateComments(e.key === 'ARROWUP' ? -1 : 1);
                    break;
            }
        });
    }

    /**
     * Initialize refresh button
     */
    function initRefreshButton() {
        $('.wrb-refresh-btn').on('click', function() {
            refreshCommentsList();
        });
    }

    /**
     * Initialize settings page functionality
     */
    function initSettings() {
        // Clear cache button
        $('#wrb-clear-cache').on('click', function() {
            const $button = $(this);
            $button.prop('disabled', true).html('<span class="wrb-spinner"></span> Clearing...');

            $.ajax({
                url: wrb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wrb_clear_cache',
                    nonce: wrb_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('Cache cleared successfully!', 'success');
                    } else {
                        showNotice('Failed to clear cache.', 'error');
                    }
                },
                error: function() {
                    showNotice('An error occurred while clearing cache.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Clear Cache');
                }
            });
        });

        // Reset settings button
        $('#wrb-reset-settings').on('click', function() {
            if (confirm('Are you sure you want to reset all settings to their defaults? This cannot be undone.')) {
                const $button = $(this);
                $button.prop('disabled', true).text('Resetting...');

                $.ajax({
                    url: wrb_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wrb_reset_settings',
                        nonce: wrb_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice('Settings reset successfully! Reloading page...', 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showNotice('Failed to reset settings.', 'error');
                        }
                    },
                    error: function() {
                        showNotice('An error occurred while resetting settings.', 'error');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Reset to Defaults');
                    }
                });
            }
        });

        // Test OpenAI connection button
        $('#wrb-test-connection').on('click', function() {
            const $button = $(this);
            const originalText = $button.text();
            $button.prop('disabled', true).html('<span class="wrb-spinner"></span> Testing...');

            $.ajax({
                url: wrb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wrb_test_openai_connection',
                    nonce: wrb_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');

                        // Show connection details
                        if (response.data.data) {
                            const details = response.data.data;
                            const detailsHtml = `
                                <div class="wrb-connection-details">
                                    <strong>Connection Successful!</strong><br>
                                    Model: ${details.model}<br>
                                    Response Time: ${details.response_time}s<br>
                                    API Status: Working
                                </div>
                            `;
                            showNotice(detailsHtml, 'success', 10000);
                        }
                    } else {
                        showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'An error occurred while testing connection.';
                    let errorDetails = '';

                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = xhr.responseJSON.data.message;

                        // Show additional error details for API errors
                        if (xhr.responseJSON.data.details && xhr.responseJSON.data.details.error) {
                            const apiError = xhr.responseJSON.data.details.error;
                            if (apiError.type === 'invalid_request_error') {
                                if (apiError.code === 'invalid_api_key') {
                                    errorDetails = '<br><strong>Suggestion:</strong> Please check your OpenAI API key in settings.';
                                } else if (apiError.code === 'insufficient_quota') {
                                    errorDetails = '<br><strong>Suggestion:</strong> Your OpenAI account may have insufficient credits or quota.';
                                }
                            }
                        }
                    }

                    const fullMessage = errorMessage + errorDetails;
                    showNotice(fullMessage, 'error', 15000);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Test AI moderation button
        $('#wrb-test-moderation').on('click', function() {
            const $button = $(this);
            const originalText = $button.text();
            $button.prop('disabled', true).html('<span class="wrb-spinner"></span> Testing...');

            $.ajax({
                url: wrb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wrb_test_ai_moderation',
                    nonce: wrb_ajax.nonce
                },
                success: function(response) {
                    console.log('Test moderation success response:', response); // Debug log

                    if (response.success) {
                        // Show brief success notice at top
                        showNotice(response.data.message, 'success', 3000);

                        // Show detailed results below buttons
                        if (response.data.data) {
                            const results = response.data.data;
                            const decisionClass = results.decision;
                            const decisionText = results.decision.charAt(0).toUpperCase() + results.decision.slice(1);

                            let parameterInfo = '';
                            if (results.parameter_notes && results.parameter_notes.length > 0) {
                                parameterInfo = '<br><strong>Parameter Adjustments:</strong><br>';
                                results.parameter_notes.forEach(note => {
                                    parameterInfo += `• ${note}<br>`;
                                });
                            }

                            const resultsHtml = `
                                <div class="wrb-test-results-inline" style="margin-top: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
                                    <h4 style="margin: 0 0 10px 0; color: #23282d;">AI Moderation Test Results</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div>
                                            <strong>Comment:</strong><br>
                                            <div style="font-style: italic; color: #666; margin: 5px 0;">"${results.comment.content}"</div>
                                            <strong>Author:</strong> ${results.comment.author}<br>
                                            <strong>Post:</strong> ${results.comment.post_title}
                                        </div>
                                        <div>
                                            <strong>Decision:</strong>
                                            <span class="wrb-test-decision ${decisionClass}" style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-left: 5px; ${getDecisionColor(results.decision)}">${decisionText}</span><br><br>
                                            <strong>Confidence:</strong> ${Math.round(results.confidence * 100)}%<br>
                                            <strong>Processing Time:</strong> ${results.processing_time}s<br>
                                            <strong>Model:</strong> ${results.model}
                                        </div>
                                    </div>
                                    ${results.reasoning ? `<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;"><strong>AI Reasoning:</strong> ${results.reasoning}</div>` : ''}
                                    ${results.tokens_used ? `<div style="margin-top: 5px;"><strong>Tokens Used:</strong> ${results.tokens_used}</div>` : ''}
                                    ${parameterInfo}
                                </div>
                            `;

                            // Insert results after the test buttons
                            $('#wrb-test-moderation').closest('td').find('.wrb-test-results-inline').remove();
                            $('#wrb-test-moderation').closest('td').append(resultsHtml);
                        }
                    } else {
                        // Inline error display below the test button
                        $('#wrb-test-moderation').closest('td').find('.wrb-test-results-inline, .wrb-test-error-inline').remove();
                        const errorHtml = `
                            <div class="wrb-test-error-inline" style="margin-top: 15px; padding: 15px; border: 1px solid #c33; border-radius: 4px; background: #fff4f4; color: #a33;">
                                <strong>AI Moderation Test Error:</strong><br>
                                <span>${response.data && response.data.message ? response.data.message : 'An unknown error occurred during moderation test.'}</span>
                            </div>
                        `;
                        $('#wrb-test-moderation').closest('td').append(errorHtml);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'An error occurred while testing AI moderation.';
                    let errorDetails = '';

                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = xhr.responseJSON.data.message;

                        // Handle AI response format errors specifically
                        if (xhr.responseJSON.data.code === 'invalid_ai_response') {
                            errorDetails = '<br><strong>Debugging Information:</strong><br>';

                            if (xhr.responseJSON.data.details) {
                                const details = xhr.responseJSON.data.details;

                                if (details.raw_response) {
                                    errorDetails += `<br><strong>AI Raw Response:</strong><br><div style="background: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto;">${details.raw_response}</div>`;
                                }

                                if (details.model) {
                                    errorDetails += `<br><strong>Model Used:</strong> ${details.model}`;
                                }

                                if (details.supports_json !== undefined) {
                                    errorDetails += `<br><strong>JSON Format Support:</strong> ${details.supports_json ? 'Yes' : 'No'}`;
                                }

                                if (details.temperature_used !== undefined) {
                                    errorDetails += `<br><strong>Temperature Used:</strong> ${details.temperature_used}`;
                                }

                                errorDetails += '<br><br><strong>Suggestion:</strong> The AI response format doesn\'t match expectations. This might indicate an issue with the model or the prompt. Try switching to a different model like gpt-4o or gpt-4-turbo.';
                            }
                        }
                        // Show additional error details for API errors
                        else if (xhr.responseJSON.data.details && xhr.responseJSON.data.details.error) {
                            const apiError = xhr.responseJSON.data.details.error;
                            if (apiError.type === 'invalid_request_error' && apiError.param) {
                                errorDetails = `<br><strong>Parameter:</strong> ${apiError.param}`;
                                if (apiError.code === 'unsupported_parameter') {
                                    errorDetails += '<br><strong>Suggestion:</strong> This model may require different parameters. Try selecting a different model in settings.';
                                } else if (apiError.code === 'unsupported_value') {
                                    if (apiError.param === 'temperature') {
                                        errorDetails += '<br><strong>Suggestion:</strong> This model uses fixed temperature settings. The plugin will automatically adjust this parameter.';
                                    } else {
                                        errorDetails += '<br><strong>Suggestion:</strong> This parameter value is not supported by this model. Try selecting a different model.';
                                    }
                                }
                            }
                        }
                    }

                    const fullMessage = errorMessage + errorDetails;
                    showNotice(fullMessage, 'error', 15000);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Clear decisions button
        $('#wrb-clear-decisions').on('click', function() {
            if (confirm('Are you sure you want to clear all AI decisions? This cannot be undone.')) {
                const $button = $(this);
                $button.prop('disabled', true).text('Clearing...');

                $.ajax({
                    url: wrb_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wrb_clear_decisions',
                        nonce: wrb_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice(response.data.message, 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            showNotice(response.data.message || 'Failed to clear decisions', 'error');
                        }
                    },
                    error: function() {
                        showNotice('An error occurred while clearing decisions.', 'error');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Clear Decision History');
                    }
                });
            }
        });

        // Update range inputs to show values
        $('#wrb_confidence_threshold').on('input', function() {
            $('#confidence-value').text($(this).val());
        });

        $('#wrb_temperature').on('input', function() {
            $('#temperature-value').text($(this).val());
        });
    }

    /**
     * Initialize decisions page functionality
     */
    function initDecisionsPage() {
        // Generate sample data button
        $('#wrb-generate-sample-btn').on('click', function() {
            const $button = $(this);
            $button.prop('disabled', true).html('<span class="wrb-spinner"></span> Generating...');

            $.ajax({
                url: wrb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wrb_generate_sample_data',
                    count: 10,
                    nonce: wrb_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotice(response.data.message || 'Failed to generate sample data', 'error');
                    }
                },
                error: function() {
                    showNotice('An error occurred while generating sample data.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> Generate Sample Data');
                }
            });
        });

        // Override decision buttons
        $(document).on('click', '.wrb-override-decision', function() {
            const decisionId = $(this).data('decision-id');
            const reason = prompt('Please provide a reason for overriding this AI decision:');

            if (reason) {
                const $button = $(this);
                $button.prop('disabled', true);

                $.ajax({
                    url: wrb_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wrb_override_decision',
                        decision_id: decisionId,
                        reason: reason,
                        nonce: wrb_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice(response.data.message, 'success');
                            // Reload to show updated state
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            showNotice(response.data.message || 'Failed to override decision', 'error');
                        }
                    },
                    error: function() {
                        showNotice('An error occurred while overriding decision.', 'error');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            }
        });

        // Export buttons
        $('#wrb-export-csv').on('click', function() {
            exportDecisions('csv');
        });

        $('#wrb-export-json').on('click', function() {
            exportDecisions('json');
        });

        // Clear decisions button
        $('#wrb-clear-decisions').on('click', function() {
            if (confirm('Are you sure you want to clear all AI decisions? This cannot be undone.')) {
                const $button = $(this);
                $button.prop('disabled', true).text('Clearing...');

                $.ajax({
                    url: wrb_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wrb_clear_decisions',
                        nonce: wrb_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice(response.data.message, 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            showNotice(response.data.message || 'Failed to clear decisions', 'error');
                        }
                    },
                    error: function() {
                        showNotice('An error occurred while clearing decisions.', 'error');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Clear Decision History');
                    }
                });
            }
        });

        // Update range inputs to show values
        $('#wrb_confidence_threshold').on('input', function() {
            $('#confidence-value').text($(this).val());
        });

        $('#wrb_temperature').on('input', function() {
            $('#temperature-value').text($(this).val());
        });
    }

    /**
     * Initialize process pending comments functionality
     */
    function initProcessPendingComments() {
        // Process pending comments button
        $('#wrb-process-pending-btn').on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const originalContent = $button.html();

            // Show confirmation dialog
            if (!confirm('This will process all pending comments with AI moderation. The AI will approve, reject, or mark comments as spam based on their content. Do you want to continue?')) {
                return;
            }

            // Show loading state
            $button.css('pointer-events', 'none').html('<span class="wrb-spinner"></span> Processing...');
            $button.find('.wrb-badge').text('Processing...');

            $.ajax({
                url: wrb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wrb_process_pending_comments',
                    nonce: wrb_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success', 10000);

                        // Show detailed results
                        if (response.data.data && response.data.data.results) {
                            const results = response.data.data;
                            let resultsHtml = '<div class="wrb-test-results">';
                            resultsHtml += '<h4>Processing Results:</h4>';
                            resultsHtml += `<p><strong>Total:</strong> ${results.total_processed} comments</p>`;
                            resultsHtml += `<p><strong>Approved:</strong> ${results.approved}</p>`;
                            resultsHtml += `<p><strong>Rejected:</strong> ${results.rejected}</p>`;
                            resultsHtml += `<p><strong>Marked as Spam:</strong> ${results.spam}</p>`;
                            resultsHtml += `<p><strong>Errors:</strong> ${results.errors}</p>`;

                            if (results.errors > 0) {
                                resultsHtml += '<p><strong>Error Details:</strong></p><ul>';
                                results.results.forEach(function(result) {
                                    if (result.error) {
                                        resultsHtml += `<li>Comment #${result.comment_id} by ${result.author}: ${result.error}</li>`;
                                    }
                                });
                                resultsHtml += '</ul>';
                            }

                            resultsHtml += '</div>';
                            showNotice(resultsHtml, 'success', 15000);
                        }

                        // Refresh the page after a delay to show updated stats
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        showNotice(response.data.message, 'error');

                        // Reset button on error
                        $button.css('pointer-events', 'auto').html(originalContent);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'An error occurred while processing comments.';
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = xhr.responseJSON.data.message;
                    }
                    showNotice(errorMessage, 'error');

                    // Reset button on error
                    $button.css('pointer-events', 'auto').html(originalContent);
                }
            });
        });
    }

    /**
     * Export decisions data
     */
    function exportDecisions(format) {
        // Get current filter values
        const decision = $('select[name="decision"]').val();
        const date = $('select[name="date"]').val();

        // Create a form and submit it for download
        const form = $('<form>', {
            method: 'POST',
            action: wrb_ajax.ajax_url,
            target: '_blank'
        });

        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'wrb_export_decisions'
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'format',
            value: format
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'decision',
            value: decision
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'date',
            value: date
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: wrb_ajax.nonce
        }));

        $('body').append(form);
        form.submit();
        form.remove();
    }

    /**
     * Perform action on a single comment
     */
    function performCommentAction(action, commentId) {
        const $card = $(`.wrb-comment-card[data-comment-id="${commentId}"]`);
        const $actionButton = $card.find(`.wrb-action-${action}`);

        // Show loading state
        $actionButton.prop('disabled', true);
        $card.addClass('wrb-loading');

        $.ajax({
            url: wrb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: `wrb_${action}_comment`,
                comment_id: commentId,
                nonce: wrb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    removeCommentCard(commentId);
                } else {
                    showNotice(response.data.message || 'Action failed', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while performing the action.', 'error');
            },
            complete: function() {
                $actionButton.prop('disabled', false);
                $card.removeClass('wrb-loading');
            }
        });
    }

    /**
     * Perform bulk action on multiple comments
     */
    function performBulkAction(action, commentIds) {
        // Show loading state
        const $buttons = $('.wrb-comment-actions button');
        $buttons.prop('disabled', true);
        $('.wrb-comments-list').addClass('wrb-loading');

        $.ajax({
            url: wrb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wrb_bulk_action_comments',
                bulk_action: action,
                comment_ids: commentIds,
                nonce: wrb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');

                    // Remove processed comments from the list
                    commentIds.forEach(commentId => {
                        removeCommentCard(commentId);
                    });

                    // Clear selection
                    selectedComments = [];
                    $('#wrb-select-all').prop('checked', false);
                } else {
                    showNotice(response.data.message || 'Bulk action failed', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while performing the bulk action.', 'error');
            },
            complete: function() {
                $buttons.prop('disabled', false);
                $('.wrb-comments-list').removeClass('wrb-loading');
            }
        });
    }

    /**
     * Remove comment card with animation
     */
    function removeCommentCard(commentId) {
        const $card = $(`.wrb-comment-card[data-comment-id="${commentId}"]`);

        $card.fadeOut(300, function() {
            $(this).remove();

            // Check if there are no more comments
            if ($('.wrb-comment-card').length === 0) {
                location.reload(); // Reload to show empty state
            }
        });
    }

    /**
     * Update selected comments array
     */
    function updateSelectedComments() {
        selectedComments = [];
        $('.wrb-comment-checkbox:checked').each(function() {
            selectedComments.push(parseInt($(this).val()));
        });
    }

    /**
     * Toggle selection of current comment (for keyboard navigation)
     */
    function toggleCurrentCommentSelection() {
        const $focusedCard = $('.wrb-comment-card:focus');
        if ($focusedCard.length) {
            const checkbox = $focusedCard.find('.wrb-comment-checkbox');
            checkbox.prop('checked', !checkbox.prop('checked'));
            updateSelectedComments();
        }
    }

    /**
     * Navigate between comments with arrow keys
     */
    function navigateComments(direction) {
        const $cards = $('.wrb-comment-card');
        const $focused = $('.wrb-comment-card:focus');
        let currentIndex = $cards.index($focused);
        let newIndex = currentIndex + direction;

        if (newIndex >= 0 && newIndex < $cards.length) {
            $cards.eq(newIndex).focus();
        }
    }

    /**
     * Refresh the comments list
     */
    function refreshCommentsList() {
        const $refreshBtn = $('.wrb-refresh-btn');
        const originalText = $refreshBtn.html();

        $refreshBtn.prop('disabled', true).html('<span class="wrb-spinner"></span> ' + wrb_ajax.strings.loading);

        setTimeout(() => {
            location.reload();
        }, 1000);
    }

    /**
     * Show admin notice
     */
    function showNotice(message, type = 'success', timeout = 5000) {
        console.log('showNotice called:', { message, type, timeout }); // Debug log

        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const $notice = $(`
            <div class="notice ${noticeClass} is-dismissible">
                <div class="notice-content">${message}</div>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);

        // Try multiple selectors to find the right container
        const $container = $('.wrap').length ? $('.wrap') : $('body').find('.wrap, .wrap *, body').first();

        if ($container.length) {
            $container.prepend($notice);
            console.log('Notice added to container:', $container);
        } else {
            // Fallback: add to body if no wrap found
            $('body').prepend($notice);
            console.log('Notice added to body (fallback)');
        }

        // Handle dismiss
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        });

        // Auto-dismiss after timeout
        if (timeout > 0) {
            setTimeout(() => {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, timeout);
        }
    }

    /**
     * Initialize AJAX error handling
     */
    $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
        if (settings.url && settings.url.includes('wrb_')) {
            showNotice('An unexpected error occurred. Please try again.', 'error');
        }
    });

})(jQuery);