<?php
/**
 * AJAX Handlers Module
 *
 * Handles all AJAX requests from the WordPress admin interface including:
 * - Comment actions (approve, spam, trash, bulk operations)
 * - AI decision management (get, override, export, clear, generate samples)
 * - OpenAI testing (connection, moderation)
 * - Comment processing (pending comments, async moderation)
 *
 * @package WordPress_Review_Bot
 */

if (!defined('ABSPATH')) {
    exit;
}

class WRB_AJAX_Handlers {

    /**
     * Reference to comment manager for delegation
     */
    private $comment_manager;

    /**
     * Constructor
     *
     * @param WRB_Comment_Manager $comment_manager Comment manager instance
     */
    public function __construct($comment_manager) {
        $this->comment_manager = $comment_manager;
    }

    /**
     * Approve comment via AJAX
     */
    public function ajax_approve_comment() {
        $this->verify_ajax_request();

        $comment_id = intval($_POST['comment_id']);
        $result = wp_set_comment_status($comment_id, 'approve');

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Comment approved successfully', 'wordpress-review-bot'),
                'comment_id' => $comment_id
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to approve comment', 'wordpress-review-bot')
            ));
        }
    }

    /**
     * Mark comment as spam via AJAX
     */
    public function ajax_spam_comment() {
        $this->verify_ajax_request();

        $comment_id = intval($_POST['comment_id']);
        $result = wp_set_comment_status($comment_id, 'spam');

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Comment marked as spam', 'wordpress-review-bot'),
                'comment_id' => $comment_id
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to mark comment as spam', 'wordpress-review-bot')
            ));
        }
    }

    /**
     * Trash comment via AJAX
     */
    public function ajax_trash_comment() {
        $this->verify_ajax_request();

        $comment_id = intval($_POST['comment_id']);
        $result = wp_trash_comment($comment_id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Comment moved to trash', 'wordpress-review-bot'),
                'comment_id' => $comment_id
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to trash comment', 'wordpress-review-bot')
            ));
        }
    }

    /**
     * Handle bulk comment actions via AJAX
     */
    public function ajax_bulk_action_comments() {
        $this->verify_ajax_request();

        $comment_ids = array_map('intval', $_POST['comment_ids']);
        $action = sanitize_text_field($_POST['bulk_action']);

        $success_count = 0;
        $error_count = 0;

        foreach ($comment_ids as $comment_id) {
            $result = false;

            switch ($action) {
                case 'approve':
                    $result = wp_set_comment_status($comment_id, 'approve');
                    break;
                case 'spam':
                    $result = wp_set_comment_status($comment_id, 'spam');
                    break;
                case 'trash':
                    $result = wp_trash_comment($comment_id);
                    break;
            }

            if ($result) {
                $success_count++;
            } else {
                $error_count++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('Bulk action completed: %d successful, %d failed', 'wordpress-review-bot'),
                $success_count,
                $error_count
            ),
            'success_count' => $success_count,
            'error_count' => $error_count
        ));
    }

    /**
     * AJAX handler for getting AI decisions
     */
    public function ajax_get_decisions() {
        $this->verify_ajax_request();

        $args = array(
            'limit' => intval($_POST['per_page'] ?? 25),
            'offset' => intval($_POST['offset'] ?? 0),
            'decision' => sanitize_text_field($_POST['decision'] ?? 'all'),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        );

        $decisions = $this->comment_manager->get_ai_decisions($args);
        $total_count = $this->comment_manager->get_ai_decisions_count($args);

        wp_send_json_success(array(
            'decisions' => $decisions,
            'total_count' => $total_count
        ));
    }

    /**
     * AJAX handler for overriding an AI decision
     */
    public function ajax_override_decision() {
        $this->verify_ajax_request();

        $decision_id = intval($_POST['decision_id']);
        $reason = sanitize_textarea_field($_POST['reason']);

        if ($this->comment_manager->override_ai_decision($decision_id, $reason)) {
            wp_send_json_success(array(
                'message' => __('Decision overridden successfully', 'wordpress-review-bot')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to override decision', 'wordpress-review-bot')
            ));
        }
    }

    /**
     * AJAX handler for exporting decisions
     */
    public function ajax_export_decisions() {
        $this->verify_ajax_request();

        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $args = array(
            'decision' => sanitize_text_field($_POST['decision'] ?? 'all'),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        );

        $this->comment_manager->export_decisions($format, $args);
    }

    /**
     * AJAX handler for clearing decisions
     */
    public function ajax_clear_decisions() {
        $this->verify_ajax_request();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action', 'wordpress-review-bot')
            ));
        }

        $cleared = $this->comment_manager->clear_all_decisions();

        wp_send_json_success(array(
            'message' => sprintf(__('%d decisions cleared successfully', 'wordpress-review-bot'), $cleared)
        ));
    }

    /**
     * AJAX handler for generating sample data
     */
    public function ajax_generate_sample_data() {
        $this->verify_ajax_request();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action', 'wordpress-review-bot')
            ));
        }

        $count = intval($_POST['count'] ?? 10);
        $generated = $this->comment_manager->generate_sample_decisions($count);

        wp_send_json_success(array(
            'message' => sprintf(__('%d sample decisions generated successfully', 'wordpress-review-bot'), $generated),
            'generated' => $generated
        ));
    }

    /**
     * AJAX handler for testing OpenAI connection
     */
    public function ajax_test_openai_connection() {
        $this->verify_ajax_request('manage_options');

        $options = get_option('wrb_options', array());
        $api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
        $model = isset($options['openai_model']) ? $options['openai_model'] : 'gpt-5-mini';

        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => __('OpenAI API key is required. Please configure it in settings.', 'wordpress-review-bot'),
                'code' => 'missing_api_key'
            ));
        }

        $response = $this->comment_manager->test_openai_connection($api_key, $model);

        if ($response['success']) {
            wp_send_json_success(array(
                'message' => __('Connection successful! OpenAI API is working.', 'wordpress-review-bot'),
                'data' => array(
                    'model' => $model,
                    'response_time' => $response['response_time'],
                    'api_info' => $response['api_info']
                )
            ));
        } else {
            wp_send_json_error(array(
                'message' => $response['message'],
                'code' => $response['code'] ?? 'connection_error',
                'details' => $response['details'] ?? null
            ));
        }
    }

    /**
     * AJAX handler for testing AI moderation
     */
    public function ajax_test_ai_moderation() {
        $this->verify_ajax_request('manage_options');

        $options = get_option('wrb_options', array());
        $api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
        $model = isset($options['openai_model']) ? $options['openai_model'] : 'gpt-5-mini';
        $max_tokens = isset($options['max_tokens']) ? min(8000, intval($options['max_tokens'])) : 800;
        $temperature = isset($options['temperature']) ? $options['temperature'] : 0.1;

        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => __('OpenAI API key is required. Please configure it in settings.', 'wordpress-review-bot'),
                'code' => 'missing_api_key'
            ));
        }

        $test_comment = array(
            'author' => 'Marketing Bot',
            'content' => 'Check out my amazing website for cheap products! Best deals ever!!! http://spam-site.com Buy now!!!',
            'post_title' => 'WordPress Security'
        );

        $start_time = microtime(true);
        $result = $this->comment_manager->test_ai_moderation($api_key, $model, $test_comment, $max_tokens, $temperature);
        $end_time = microtime(true);
        $processing_time = round(($end_time - $start_time) * 1000) / 1000;

        if ($result['success']) {
            $decision_text = ucfirst($result['decision']);
            $message = sprintf(
                __('AI moderation test successful! The test comment was correctly identified as: %s', 'wordpress-review-bot'),
                $decision_text
            );

            wp_send_json_success(array(
                'message' => $message,
                'data' => array(
                    'comment' => $test_comment,
                    'decision' => $result['decision'],
                    'confidence' => $result['confidence'],
                    'reasoning' => $result['reasoning'],
                    'model' => $model,
                    'processing_time' => $processing_time,
                    'tokens_used' => $result['tokens_used'] ?? null
                )
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
                'code' => $result['code'] ?? 'moderation_error',
                'details' => $result['details'] ?? null
            ));
        }
    }

    /**
     * AJAX handler for processing existing pending comments
     */
    public function ajax_process_pending_comments() {
        $this->verify_ajax_request('moderate_comments');

        $result = $this->comment_manager->process_pending_comments();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX endpoint for async moderation with signature verification
     */
    public function ajax_async_moderate_now() {
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        error_log("WRB: ajax_async_moderate_now invoked for comment #{$comment_id}");
        error_log('WRB: AJAX _POST: ' . json_encode($_POST));
        
        if ($comment_id <= 0) {
            error_log('WRB: AJAX: Invalid comment_id');
            wp_send_json_error(array('message' => 'Invalid comment ID'));
        }
        
        $ts = isset($_POST['ts']) ? intval($_POST['ts']) : 0;
        $sig = isset($_POST['sig']) ? sanitize_text_field($_POST['sig']) : '';
        
        if ($ts <= 0 || empty($sig)) {
            error_log("WRB: AJAX: Missing signature information for comment #{$comment_id}");
            wp_send_json_error(array('message' => 'Missing signature'));
        }
        
        if (abs(time() - $ts) > 300) {
            error_log("WRB: AJAX: Signature expired for comment #{$comment_id}");
            wp_send_json_error(array('message' => 'Signature expired'));
        }
        
        $secret = defined('AUTH_SALT') ? AUTH_SALT : (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : wp_salt());
        $expected = hash_hmac('sha256', $comment_id . '|' . $ts, $secret);
        
        if (!hash_equals($expected, $sig)) {
            error_log("WRB: AJAX: Signature invalid for comment #{$comment_id}");
            wp_send_json_error(array('message' => 'Invalid signature'));
        }
        
        error_log("WRB: AJAX: Signature verified for comment #{$comment_id}, processing moderation");
        $this->comment_manager->process_async_moderation($comment_id);
        wp_send_json_success(array('message' => 'Async moderation processed'));
    }

    /**
     * AJAX endpoint to process held comments asynchronously
     */
    public function ajax_async_process_held() {
        $ts = isset($_POST['ts']) ? intval($_POST['ts']) : 0;
        $sig = isset($_POST['sig']) ? sanitize_text_field($_POST['sig']) : '';
        
        if ($ts <= 0 || empty($sig)) {
            error_log('WRB: AJAX HELD: Missing signature');
            wp_send_json_error(array('message' => 'Missing signature'));
        }
        
        if (abs(time() - $ts) > 300) {
            error_log('WRB: AJAX HELD: Signature expired');
            wp_send_json_error(array('message' => 'Signature expired'));
        }
        
        $secret = defined('AUTH_SALT') ? AUTH_SALT : (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : wp_salt());
        $expected = hash_hmac('sha256', 'process_held|' . $ts, $secret);
        
        if (!hash_equals($expected, $sig)) {
            error_log('WRB: AJAX HELD: Invalid signature');
            wp_send_json_error(array('message' => 'Invalid signature'));
        }
        
        error_log('WRB: AJAX HELD: Dispatching held comments processing');
        $this->comment_manager->process_held_comments_cron();
        wp_send_json_success(array('message' => 'Held comments processed'));
    }

    /**
     * Verify AJAX request for security
     *
     * @param string $capability Required capability (default: 'moderate_comments')
     */
    private function verify_ajax_request($capability = 'moderate_comments') {
        if (!current_user_can($capability)) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action', 'wordpress-review-bot')
            ));
        }

        if (!wp_verify_nonce($_POST['nonce'], 'wrb_comment_action')) {
            wp_send_json_error(array(
                'message' => __('Security check failed', 'wordpress-review-bot')
            ));
        }
    }
}
