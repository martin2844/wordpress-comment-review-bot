<?php
/**
 * Comment Manager Class
 * Handles all comment-related operations and AI decision tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class WRB_Comment_Manager {

    /**
     * Database table name for AI decisions
     */
    private $decisions_table;

    /**
     * Database table name for plugin logs
     */
    private $logs_table;

    /**
     * Database module instance
     */
    private $database;

    /**
     * AI Decisions module instance
     */
    private $ai_decisions;

    /**
     * Exporter module instance
     */
    private $exporter;

    /**
     * OpenAI client module instance
     */
    private $openai_client;

    /**
     * AJAX handlers module instance
     */
    private $ajax_handlers;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize database module
        require_once plugin_dir_path(__FILE__) . 'class-wrb-database.php';
        $this->database = new WRB_Database();

        // Initialize AI decisions module
        require_once plugin_dir_path(__FILE__) . 'class-wrb-ai-decisions.php';
        $this->ai_decisions = new WRB_AI_Decisions();

        // Initialize exporter module
        require_once plugin_dir_path(__FILE__) . 'class-wrb-exporter.php';
        $this->exporter = new WRB_Exporter();

        // Initialize OpenAI client module
        require_once plugin_dir_path(__FILE__) . 'class-wrb-openai-client.php';
        $this->openai_client = new WRB_OpenAI_Client();

        // Initialize AJAX handlers module
        require_once plugin_dir_path(__FILE__) . 'class-wrb-ajax-handlers.php';
        $this->ajax_handlers = new WRB_AJAX_Handlers($this);

        // Set table names from modules
        $this->decisions_table = $this->ai_decisions->get_decisions_table();

        // Set table names from database module
        $this->decisions_table = $this->database->get_decisions_table();
        $this->logs_table = $this->database->get_logs_table();

        // Initialize cron job
        add_action('init', array($this, 'init_cron_job'));

        // Update cron job when settings are saved
        add_action('update_option_wrb_options', array($this, 'init_cron_job'), 10, 2);

        // AJAX handlers for comment actions (delegate to AJAX handlers module)
        add_action('wp_ajax_wrb_approve_comment', array($this->ajax_handlers, 'ajax_approve_comment'));
        add_action('wp_ajax_wrb_spam_comment', array($this->ajax_handlers, 'ajax_spam_comment'));
        add_action('wp_ajax_wrb_trash_comment', array($this->ajax_handlers, 'ajax_trash_comment'));
        add_action('wp_ajax_wrb_bulk_action_comments', array($this->ajax_handlers, 'ajax_bulk_action_comments'));

        // AJAX handlers for AI decisions (delegate to AJAX handlers module)
        add_action('wp_ajax_wrb_get_decisions', array($this->ajax_handlers, 'ajax_get_decisions'));
        add_action('wp_ajax_wrb_override_decision', array($this->ajax_handlers, 'ajax_override_decision'));
        add_action('wp_ajax_wrb_export_decisions', array($this->ajax_handlers, 'ajax_export_decisions'));
        add_action('wp_ajax_wrb_clear_decisions', array($this->ajax_handlers, 'ajax_clear_decisions'));
        add_action('wp_ajax_wrb_generate_sample_data', array($this->ajax_handlers, 'ajax_generate_sample_data'));

        // AJAX handlers for OpenAI testing (delegate to AJAX handlers module)
        add_action('wp_ajax_wrb_test_openai_connection', array($this->ajax_handlers, 'ajax_test_openai_connection'));
        add_action('wp_ajax_wrb_test_ai_moderation', array($this->ajax_handlers, 'ajax_test_ai_moderation'));

        // AJAX handler for processing existing pending comments (delegate to AJAX handlers module)
        add_action('wp_ajax_wrb_process_pending_comments', array($this->ajax_handlers, 'ajax_process_pending_comments'));

    // Ensure comments are held (fast, no external calls)
    add_filter('pre_comment_approved', array($this, 'hold_comment_for_ai_review'), 100, 2);

    // Immediately trigger async moderation after a comment is saved (non-blocking)
    add_action('comment_post', array($this, 'maybe_trigger_async_after_comment'), 20, 2);

    // Track when comments are manually moderated outside the plugin
    add_action('transition_comment_status', array($this, 'track_manual_moderation'), 10, 3);

    // Don't interfere with comment saving beyond setting to hold
    // Cron/async will process held comments later

        // Add custom cron schedule for frequent processing
        add_filter('cron_schedules', array($this, 'add_wrb_cron_schedules'));

        // Scheduled processing for held comments
        add_action('wrb_process_held_comments', array($this, 'process_held_comments_cron'));

        // Fallback: Auto-trigger cron processing if it hasn't run recently
        // This handles cases where WordPress cron doesn't fire (Docker, DISABLE_WP_CRON, etc)
        add_action('wp_footer', array($this, 'maybe_auto_trigger_cron'));

    // Single comment moderation fallback event
    add_action('wrb_single_moderate_comment', array($this, 'handle_single_moderate_event'));

    // Safety tick to process due moderation events when WP-Cron loopback fails (Docker etc)
    add_action('init', array($this, 'process_due_moderation_events'), 50);

    // Allow unauthenticated async moderation dispatch (loopback non-blocking) - delegate to AJAX handlers module
    add_action('wp_ajax_nopriv_wrb_async_moderate_now', array($this->ajax_handlers, 'ajax_async_moderate_now'));
    add_action('wp_ajax_nopriv_wrb_async_process_held', array($this->ajax_handlers, 'ajax_async_process_held'));

        // Background AJAX endpoint for non-blocking fallback - delegate to AJAX handlers module
    add_action('wp_ajax_wrb_async_moderate_now', array($this->ajax_handlers, 'ajax_async_moderate_now'));
    add_action('wp_ajax_wrb_async_process_held', array($this->ajax_handlers, 'ajax_async_process_held'));
    }

    /**
     * Log an event using the database module
     *
     * @param string $level Log level (error, warning, info, debug)
     * @param string $message Log message
     * @param mixed $context Additional context (array or string)
     * @param int $comment_id Optional comment ID
     */
    private function log_event($level, $message, $context = null, $comment_id = null) {
        $this->database->log_event($level, $message, $context, $comment_id);
    }

    /**
     * Save an AI decision to the database (delegates to AI decisions module)
     *
     * @param array $decision_data Decision data
     * @return int|false Decision ID or false on failure
     */
    public function save_ai_decision($decision_data) {
        return $this->ai_decisions->save_ai_decision($decision_data);
    }

    /**
     * Get AI decisions from the database (delegates to AI decisions module)
     *
     * @param array $args Query arguments
     * @return array Array of decision objects
     */
    public function get_ai_decisions($args = array()) {
        return $this->ai_decisions->get_ai_decisions($args);
    }

    /**
     * Get AI decisions count (delegates to AI decisions module)
     *
     * @param array $args Query arguments
     * @return int Total count
     */
    public function get_ai_decisions_count($args = array()) {
        return $this->ai_decisions->get_ai_decisions_count($args);
    }

    /**
     * Get AI decisions statistics (delegates to AI decisions module)
     *
     * @return array Statistics data
     */
    public function get_ai_decisions_stats() {
        return $this->ai_decisions->get_ai_decisions_stats();
    }

    /**
     * Override an AI decision (delegates to AI decisions module)
     *
     * @param int $decision_id Decision ID
     * @param string $reason Override reason
     * @return bool Success status
     */
    public function override_ai_decision($decision_id, $reason) {
        return $this->ai_decisions->override_ai_decision($decision_id, $reason);
    }

    /**
     * Clear all AI decisions (delegates to AI decisions module)
     *
     * @return int Number of rows deleted
     */
    public function clear_all_decisions() {
        return $this->ai_decisions->clear_all_decisions();
    }

    /**
     * Generate sample AI decisions for testing (delegates to AI decisions module)
     *
     * @param int $count Number of decisions to generate
     * @return int Number of decisions generated
     */
    public function generate_sample_decisions($count = 10) {
        return $this->ai_decisions->generate_sample_decisions($count);
    }

    /**
     * Get comment statistics
     *
     * @return array Comment statistics
     */
    public function get_comment_stats() {
        $stats = array();

        $statuses = array('hold', 'approve', 'spam', 'trash');
        foreach ($statuses as $status) {
            $args = array(
                'status' => $status,
                'count' => true
            );
            $stats[$status] = get_comments($args);
        }

        $stats['total'] = array_sum($stats);

        return $stats;
    }

    /**
     * Export decisions (delegates to exporter module)
     * Called by AJAX handler
     *
     * @param string $format Export format (csv, json, xml)
     * @param array $args Filter arguments
     */
    public function export_decisions($format, $args) {
        $decisions = $this->get_ai_decisions($args);

        if ($format === 'csv') {
            $this->exporter->export_csv($decisions);
        } elseif ($format === 'json') {
            $this->exporter->export_json($decisions);
        } elseif ($format === 'xml') {
            $this->exporter->export_xml($decisions);
        } else {
            wp_send_json_error(array(
                'message' => __('Invalid export format', 'wordpress-review-bot')
            ));
        }
    }

    /**
     * Test OpenAI connection (delegates to OpenAI client module)
     * Called by AJAX handler
     *
     * @param string $api_key OpenAI API key
     * @param string $model Model name
     * @return array Connection test result
     */
    public function test_openai_connection($api_key, $model) {
        return $this->openai_client->test_connection($api_key, $model);
    }

    /**
     * Test AI moderation (delegates to OpenAI client module)
     * Called by AJAX handler
     *
     * @param string $api_key OpenAI API key
     * @param string $model Model name
     * @param array $comment Comment data
     * @param int $max_tokens Maximum tokens
     * @param float $temperature Temperature setting
     * @return array Moderation result
     */
    public function test_ai_moderation($api_key, $model, $comment, $max_tokens, $temperature) {
        $prompt = $this->build_moderation_prompt($comment);
        return $this->openai_client->moderate_comment($api_key, $model, $comment, $prompt, $max_tokens, $temperature);
    }

    /**
     * Process pending comments
     * Called by AJAX handler
     *
     * @return array Processing result
     */
    public function process_pending_comments() {
        $options = get_option('wrb_options', array());
        $api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
        $model = isset($options['openai_model']) ? $options['openai_model'] : 'gpt-5-mini';
        $max_tokens = isset($options['max_tokens']) ? min(8000, intval($options['max_tokens'])) : 800;
        $temperature = isset($options['temperature']) ? $options['temperature'] : 0.1;
        $confidence_threshold = isset($options['confidence_threshold']) ? $options['confidence_threshold'] : 0.5;

        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('OpenAI API key is required. Please configure it in settings.', 'wordpress-review-bot'),
                'code' => 'missing_api_key'
            );
        }

        $args = array(
            'status' => 'hold',
            'number' => 50,
            'orderby' => 'comment_date_gmt',
            'order' => 'ASC'
        );

        $pending_comments = get_comments($args);

        if (empty($pending_comments)) {
            return array(
                'success' => false,
                'message' => __('No pending comments found to process.', 'wordpress-review-bot'),
                'code' => 'no_pending_comments'
            );
        }

        $processed = 0;
        $approved = 0;
        $rejected = 0;
        $spam = 0;
        $errors = 0;
        $results = array();

        foreach ($pending_comments as $comment) {
            try {
                $start_time = microtime(true);

                $comment_data = array(
                    'author' => $comment->comment_author,
                    'content' => $comment->comment_content,
                    'post_title' => get_the_title($comment->comment_post_ID),
                    'email' => $comment->comment_author_email,
                    'url' => $comment->comment_author_url
                );

                $result = $this->test_ai_moderation($api_key, $model, $comment_data, $max_tokens, $temperature);
                $end_time = microtime(true);
                $processing_time = round(($end_time - $start_time) * 1000) / 1000;

                if ($result['success'] && $result['confidence'] >= $confidence_threshold) {
                    $decision = $result['decision'];
                    $reasoning = $result['reasoning'];
                    $confidence = $result['confidence'];

                    $this->save_ai_decision([
                        'comment_id' => $comment->comment_ID,
                        'decision' => $decision,
                        'reasoning' => $reasoning,
                        'confidence' => $confidence,
                        'model_used' => $model,
                        'processing_time' => $processing_time
                    ]);

                    switch ($decision) {
                        case 'approve':
                            wp_set_comment_status($comment->comment_ID, 'approve');
                            $approved++;
                            break;
                        case 'spam':
                            wp_set_comment_status($comment->comment_ID, 'spam');
                            $spam++;
                            break;
                        case 'reject':
                            wp_set_comment_status($comment->comment_ID, 'trash');
                            $rejected++;
                            break;
                    }

                    $processed++;
                    $results[] = array(
                        'comment_id' => $comment->comment_ID,
                        'author' => $comment->comment_author,
                        'decision' => $decision,
                        'confidence' => $confidence,
                        'processing_time' => $processing_time
                    );
                } else {
                    $errors++;
                    $results[] = array(
                        'comment_id' => $comment->comment_ID,
                        'author' => $comment->comment_author,
                        'error' => isset($result['message']) ? $result['message'] : 'Low confidence score',
                        'confidence' => isset($result['confidence']) ? $result['confidence'] : 0
                    );
                }
            } catch (Exception $e) {
                $errors++;
                $results[] = array(
                    'comment_id' => $comment->comment_ID,
                    'author' => $comment->comment_author,
                    'error' => $e->getMessage()
                );
            }
        }

        return array(
            'success' => true,
            'message' => sprintf(
                __('Processed %d pending comments: %d approved, %d rejected, %d marked as spam, %d errors.', 'wordpress-review-bot'),
                count($pending_comments),
                $approved,
                $rejected,
                $spam,
                $errors
            ),
            'data' => array(
                'total_processed' => count($pending_comments),
                'processed' => $processed,
                'approved' => $approved,
                'rejected' => $rejected,
                'spam' => $spam,
                'errors' => $errors,
                'results' => $results
            )
        );
    }

    /**
     * Get all pending comments
     *
     * @param int $limit Number of comments to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of comment objects
     */
    public function get_pending_comments($limit = 50, $offset = 0) {
        $args = array(
            'status' => 'hold',
            'number' => $limit,
            'offset' => $offset,
            'orderby' => 'comment_date_gmt',
            'order' => 'DESC'
        );

        $comments = get_comments($args);

        // Enrich comments with additional data
        foreach ($comments as $comment) {
            $comment->post_title = get_the_title($comment->comment_post_ID);
            $comment->post_permalink = get_permalink($comment->comment_post_ID);
            $comment->author_avatar = get_avatar($comment->comment_author_email, 32);
            $comment->comment_content_preview = $this->get_content_preview($comment->comment_content);
            $comment->comment_date_formatted = $this->format_date($comment->comment_date_gmt);
            $comment->is_spam_candidate = $this->is_spam_candidate($comment);
        }

        return $comments;
    }

    /**
     * Get count of pending comments
     *
     * @return int Number of pending comments
     */
    public function get_pending_comments_count() {
        $args = array(
            'status' => 'hold',
            'count' => true
        );

        return get_comments($args);
    }

    /**
     * Get content preview (first 100 characters)
     *
     * @param string $content Comment content
     * @return string Preview content
     */
    private function get_content_preview($content) {
        $content = strip_tags($content);
        $content = esc_html($content);

        if (strlen($content) > 100) {
            return substr($content, 0, 100) . '...';
        }

        return $content;
    }

    /**
     * Format date for display
     *
     * @param string $date GMT date string
     * @return string Formatted date
     */
    private function format_date($date) {
        $time = strtotime($date);
        $now = current_time('timestamp');
        $diff = $now - $time;

        if ($diff < HOUR_IN_SECONDS) {
            $mins = round($diff / MINUTE_IN_SECONDS);
            return sprintf(_n('%s minute ago', '%s minutes ago', $mins, 'wordpress-review-bot'), $mins);
        } elseif ($diff < DAY_IN_SECONDS) {
            $hours = round($diff / HOUR_IN_SECONDS);
            return sprintf(_n('%s hour ago', '%s hours ago', $hours, 'wordpress-review-bot'), $hours);
        } elseif ($diff < WEEK_IN_SECONDS) {
            $days = round($diff / DAY_IN_SECONDS);
            return sprintf(_n('%s day ago', '%s days ago', $days, 'wordpress-review-bot'), $days);
        } else {
            return date_i18n(get_option('date_format'), $time);
        }
    }

    /**
     * Check if comment is likely spam
     *
     * @param object $comment Comment object
     * @return bool True if likely spam
     */
    private function is_spam_candidate($comment) {
        $spam_indicators = 0;

        // Check for suspicious links
        if (substr_count($comment->comment_content, 'http') > 2) {
            $spam_indicators++;
        }

        // Check for all-caps
        if ($comment->comment_content === strtoupper($comment->comment_content) && strlen($comment->comment_content) > 20) {
            $spam_indicators++;
        }

        // Check for common spam phrases
        $spam_phrases = array('check out my site', 'great blog', 'nice post', 'visit my website');
        foreach ($spam_phrases as $phrase) {
            if (stripos($comment->comment_content, $phrase) !== false) {
                $spam_indicators++;
            }
        }

        // Check author name length
        if (strlen($comment->comment_author) > 50) {
            $spam_indicators++;
        }

        return $spam_indicators >= 2;
    }

    /**
     * Test OpenAI API connection (delegates to OpenAI client module)
     */
    private function test_openai_api_connection($api_key, $model) {
        return $this->openai_client->test_connection($api_key, $model);
    }

    /**
     * Test comment moderation (delegates to OpenAI client module)
     */
    private function test_moderate_comment($api_key, $model, $comment, $max_tokens, $temperature) {
        $prompt = $this->build_moderation_prompt($comment);
        return $this->openai_client->moderate_comment($api_key, $model, $comment, $prompt, $max_tokens, $temperature);
    }

    /**
     * Hold new comments for AI review when auto-moderation is enabled
     *
     * @param mixed $approved The current approval status
     * @param array $commentdata Comment data
     * @return mixed The approval status
     */
    public function hold_comment_for_ai_review($approved, $commentdata) {
        error_log("WRB: hold_comment_for_ai_review called with approved=" . var_export($approved, true));
        $this->log_event('debug', 'Filter pre_comment_approved invoked', array('original_status' => $approved));
        
        $options = get_option('wrb_options', array());
        $auto_moderation_enabled = isset($options['auto_moderation_enabled']) ? $options['auto_moderation_enabled'] : false;

        error_log("WRB: auto_moderation_enabled: " . ($auto_moderation_enabled ? 'true' : 'false'));

        // Only hold comments if auto-moderation is enabled
        if (!$auto_moderation_enabled) {
            error_log("WRB: auto-moderation not enabled, returning original");
            return $approved;
        }

        // Check if we have API key
        $api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
        if (empty($api_key)) {
            error_log("WRB: No API key, returning original");
            return $approved;
        }

        error_log("WRB: API key present, checking post type");

        // Check post type settings
        $post_id = isset($commentdata['comment_post_ID']) ? $commentdata['comment_post_ID'] : 0;
        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post) {
                $moderate_post_comments = isset($options['moderate_post_comments']) ? $options['moderate_post_comments'] : true;
                $moderate_page_comments = isset($options['moderate_page_comments']) ? $options['moderate_page_comments'] : true;
                $moderate_product_comments = isset($options['moderate_product_comments']) ? $options['moderate_product_comments'] : false;

                $post_type = $post->post_type;
                $should_moderate = false;

                if ($post_type === 'post' && $moderate_post_comments) {
                    $should_moderate = true;
                } elseif ($post_type === 'page' && $moderate_page_comments) {
                    $should_moderate = true;
                } elseif ($post_type === 'product' && $moderate_product_comments) {
                    $should_moderate = true;
                }

                if (!$should_moderate) {
                    error_log("WRB: Post type {$post_type} not configured for moderation");
                    return $approved;
                }
            }
        }

    // Force comment to be held for moderation using integer 0 (WordPress pending status)
    error_log("WRB: Holding comment for moderation, returning 0 (pending)");
    $this->log_event('info', 'Comment held for AI moderation', array('comment_post_ID' => $post_id));
    return 0; // Use numeric 0 to avoid edge cases with 'hold' string
    }

    /**
     * DEPRECATED: Comments are now processed only via cron job (wrb_process_held_comments)
     * This function is kept for backward compatibility but does nothing
     *
     * @deprecated Use process_held_comments_cron instead
     */
    public function schedule_async_moderation($comment_id, $comment) {
        // No-op: Cron job handles all comment processing
    }

    /**
     * Process async moderation (runs in background)
     *
     * @param int $comment_id The comment ID to moderate
     */
    public function process_async_moderation($comment_id) {
        error_log("WRB: process_async_moderation (start) for comment #{$comment_id}");
        
        // Log test entry to verify logging is working
    $this->log_event('debug', "Starting async moderation for comment #{$comment_id}", array('test' => true), $comment_id);
        
        // clear fallback transient on success
        delete_transient('wrb_async_fallback_needed');
        // Get settings
        $options = get_option('wrb_options', array());
        $api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';

        if (empty($api_key)) {
            error_log("WRB: Skipping async moderation for comment #{$comment_id} (no API key)");
            return;
        }

        // Get the comment
        $comment = get_comment($comment_id);
        if (!$comment) {
            error_log("WRB: Skipping async moderation for comment #{$comment_id} (comment not found)");
            return;
        }

        // Only process comments that are currently held (pending moderation)
        // WordPress stores hold status as '0', approved as '1', spam as 'spam', trash as 'trash'
        if ($comment->comment_approved !== '0' && $comment->comment_approved !== 0) {
            error_log("WRB: Skipping async moderation for comment #{$comment_id} (comment status is: {$comment->comment_approved})");
            return;
        }

        // Check if already processed
        $existing_decision = $this->get_comment_decision($comment_id);
        if ($existing_decision) {
            error_log("WRB: Skipping async moderation for comment #{$comment_id} (already has decision)");
            return;
        }

        // Check post type settings
        $post = get_post($comment->comment_post_ID);
        if (!$post) {
            error_log("WRB: Skipping async moderation for comment #{$comment_id} (post not found)");
            return;
        }

        $moderate_post_comments = isset($options['moderate_post_comments']) ? $options['moderate_post_comments'] : true;
        $moderate_page_comments = isset($options['moderate_page_comments']) ? $options['moderate_page_comments'] : true;
        $moderate_product_comments = isset($options['moderate_product_comments']) ? $options['moderate_product_comments'] : false;

        $post_type = $post->post_type;
        $should_moderate = false;

        if ($post_type === 'post' && $moderate_post_comments) {
            $should_moderate = true;
        } elseif ($post_type === 'page' && $moderate_page_comments) {
            $should_moderate = true;
        } elseif ($post_type === 'product' && $moderate_product_comments) {
            $should_moderate = true;
        }

        if (!$should_moderate) {
            error_log("WRB: Post type {$post_type} not configured for moderation, skipping comment #{$comment_id}");
            return;
        }

        // Get moderation settings
        $model = isset($options['openai_model']) ? $options['openai_model'] : 'gpt-5-mini';
        $max_tokens = isset($options['max_tokens']) ? intval($options['max_tokens']) : 800;
        $temperature = isset($options['temperature']) ? $options['temperature'] : 0.1;
        $confidence_threshold = isset($options['confidence_threshold']) ? $options['confidence_threshold'] : 0.5;

        // Prepare comment data for AI
        $comment_data = array(
            'author' => $comment->comment_author,
            'content' => $comment->comment_content,
            'post_title' => get_the_title($comment->comment_post_ID),
            'email' => $comment->comment_author_email,
            'url' => $comment->comment_author_url
        );

        try {
            $start_time = microtime(true);

            // Get AI decision
            $result = $this->test_moderate_comment($api_key, $model, $comment_data, $max_tokens, $temperature);
            $end_time = microtime(true);
            $processing_time = round(($end_time - $start_time) * 1000) / 1000;

            if (!$result['success']) {
                // Log the error to the database
                $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
                $error_code = isset($result['code']) ? $result['code'] : 'unknown_error';
                error_log("WRB: Comment #{$comment_id} failed: {$error_code} - {$error_message}");
                
                $this->log_event(
                    'error',
                    "Moderation failed for comment: {$error_message}",
                    array(
                        'error_code' => $error_code,
                        'model' => $model,
                        'processing_time' => $processing_time,
                        'api_response' => isset($result['details']) ? json_encode($result['details']) : 'No details'
                    ),
                    $comment_id
                );
                return;
            }

            if ($result['confidence'] >= $confidence_threshold) {
                // Store AI decision
                $this->save_ai_decision(array(
                    'comment_id' => $comment_id,
                    'decision' => $result['decision'],
                    'reasoning' => $result['reasoning'],
                    'confidence' => $result['confidence'],
                    'model_used' => $model,
                    'processing_time' => $processing_time
                ));

                // Log successful decision (info level, minimal context)
                $this->log_event(
                    'info',
                    "Decision saved",
                    array(
                        'decision' => $result['decision'],
                        'confidence' => $result['confidence'],
                        'threshold' => $confidence_threshold,
                        'model' => $model,
                        'processing_time' => $processing_time
                    ),
                    $comment_id
                );

                // Apply the decision
                switch ($result['decision']) {
                    case 'approve':
                        wp_set_comment_status($comment_id, 'approve');
                        break;
                    case 'spam':
                        wp_set_comment_status($comment_id, 'spam');
                        break;
                    case 'reject':
                        wp_set_comment_status($comment_id, 'trash');
                        break;
                }
            } else {
                // Low confidence: persist as pending_review and do NOT reprocess again
                error_log("WRB: Comment #{$comment_id} low confidence (confidence: " . $result['confidence'] . ", decision: " . $result['decision'] . "). Reasoning: " . $result['reasoning']);

                // Save a 'pending_review' decision so we don't analyze again, and so it's visible in UI
                $this->save_ai_decision(array(
                    'comment_id' => $comment_id,
                    'decision' => 'pending_review',
                    'reasoning' => $result['reasoning'],
                    'confidence' => $result['confidence'],
                    'model_used' => $model,
                    'processing_time' => $processing_time
                ));

                // Keep the comment pending (do not change status)

                // Log low confidence to DB
                $this->log_event(
                    'warning',
                    "Low confidence decision stored for manual review",
                    array(
                        'suggested_decision' => $result['decision'],
                        'confidence' => $result['confidence'],
                        'threshold' => $confidence_threshold,
                        'model' => $model,
                        'processing_time' => $processing_time
                    ),
                    $comment_id
                );
            }

            error_log("WRB: process_async_moderation (end) for comment #{$comment_id}, processing time: " . $processing_time . "s");

        } catch (Exception $e) {
            error_log("WRB: Async moderation exception for comment #{$comment_id}: " . $e->getMessage());
            
            $this->log_event(
                'error',
                "Moderation exception: " . $e->getMessage(),
                array(
                    'exception' => get_class($e),
                    'model' => $model
                ),
                $comment_id
            );
        }
    }

    
    /**
     * Get existing decision for a comment
     *
     * @param int $comment_id The comment ID
     * @return object|null The decision object or null if not found
     */
    private function get_comment_decision($comment_id) {
        global $wpdb;

        $table_name = $this->decisions_table;
        $sql = $wpdb->prepare("SELECT * FROM {$table_name} WHERE comment_id = %d LIMIT 1", $comment_id);

        return $wpdb->get_row($sql);
    }

    /**
     * Build moderation prompt for AI
     */
    private function build_moderation_prompt($comment) {
        $prompt = sprintf(
            "You are a WordPress comment moderator. Analyze the following comment and determine if it's spam, legitimate (approve), or inappropriate (reject).\n\n".
            "COMMENT TO ANALYZE:\n".
            "Author: \"%s\"\n".
            "Content: \"%s\"\n".
            "Post: \"%s\"\n\n".
            "Spam indicators include: external links, promotional language, excessive punctuation, ALL CAPS, generic compliments, bot-like names, or unrelated to the topic.\n\n".
            "Consider the context and content carefully. A legitimate comment should be relevant to the post and contribute to the discussion.\n\n".
            "Respond with JSON format: {\"decision\": \"approve/reject/spam\", \"confidence\": 0.00-1.00, \"reasoning\": \"Your detailed reasoning\"}",
            $comment['author'],
            $comment['content'],
            $comment['post_title']
        );

        return $prompt;
    }

    /**
     * Add custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_wrb_cron_schedules($schedules) {
        $schedules['wrb_every_minute'] = array(
            'interval' => 60, // 60 seconds
            'display' => __('Every Minute (WRB)', 'wordpress-review-bot')
        );

        $schedules['wrb_every_2_minutes'] = array(
            'interval' => 120, // 2 minutes
            'display' => __('Every 2 Minutes (WRB)', 'wordpress-review-bot')
        );

        return $schedules;
    }

    /**
     * Initialize the cron job for processing held comments
     */
    public function init_cron_job() {
        error_log("WRB: init_cron_job called");
        $options = get_option('wrb_options', array());
        $auto_moderation_enabled = isset($options['auto_moderation_enabled']) ? $options['auto_moderation_enabled'] : false;

        error_log("WRB: auto_moderation_enabled: " . ($auto_moderation_enabled ? 'true' : 'false'));

        if ($auto_moderation_enabled) {
            // Schedule the cron job if not already scheduled
            if (!wp_next_scheduled('wrb_process_held_comments')) {
                // Schedule to run every 2 minutes
                wp_schedule_event(time(), 'wrb_every_2_minutes', 'wrb_process_held_comments');
                error_log("WRB: Cron job scheduled for wrb_process_held_comments");
            } else {
                error_log("WRB: Cron job already scheduled");
            }
        } else {
            error_log("WRB: auto-moderation disabled, unscheduling cron");
            // Clear the scheduled cron job when auto-moderation is disabled
            $timestamp = wp_next_scheduled('wrb_process_held_comments');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'wrb_process_held_comments');
            }
        }
    }

    /**
     * Fallback auto-trigger for cron if WordPress cron hasn't fired
     * This runs on wp_footer and checks if processing is needed
     * Useful in Docker environments where WP-Cron may not work reliably
     */
    public function maybe_auto_trigger_cron() {
        // Only run on non-admin pages
        if (is_admin()) {
            return;
        }

        $options = get_option('wrb_options', array());
        $auto_moderation_enabled = isset($options['auto_moderation_enabled']) ? $options['auto_moderation_enabled'] : false;

        if (!$auto_moderation_enabled) {
            return;
        }

        // Check if there are any held comments
        $held_comments = get_comments(array(
            'status' => 'hold',
            'number' => 1,
            'count' => true
        ));

        if ($held_comments <= 0) {
            return;
        }

        // Check last trigger time - don't trigger more than once per minute
        $last_trigger = get_transient('wrb_last_auto_trigger');
        if ($last_trigger) {
            return; // Already triggered recently
        }

        // Set transient to prevent duplicate triggers for 1 minute
        set_transient('wrb_last_auto_trigger', time(), 60);

    error_log("WRB: Auto-triggering async cron dispatch from page footer (held comments detected)");

        // Fire a non-blocking request to admin-ajax to process held comments asynchronously
        $ajax_url = admin_url('admin-ajax.php');
        $ts = time();
        $secret = defined('AUTH_SALT') ? AUTH_SALT : (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : wp_salt());
        $sig = hash_hmac('sha256', 'process_held|' . $ts, $secret);

        $args = array(
            'method' => 'POST',
            'timeout' => 0.5, // allow connection setup
            'redirection' => 0,
            'blocking' => false,
            'body' => array(
                'action' => 'wrb_async_process_held',
                'ts' => $ts,
                'sig' => $sig,
            ),
        );

        $response = wp_remote_post($ajax_url, $args);
        if (is_wp_error($response)) {
            error_log('WRB: Async held dispatcher failed: ' . $response->get_error_message());
            // Fallback: schedule the cron handler directly soon
            if (!wp_next_scheduled('wrb_process_held_comments')) {
                wp_schedule_single_event(time() + 10, 'wrb_process_held_comments');
                error_log('WRB: Scheduled immediate wrb_process_held_comments fallback');
            }
        } else {
            error_log('WRB: Async held dispatcher queued');
        }
    }

    /**
     * Process held comments via cron job
     */
    public function process_held_comments_cron() {
        error_log('WRB: Starting cron job to process held comments');

        $options = get_option('wrb_options', array());
        $api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';

        if (empty($api_key)) {
            error_log('WRB: API key not configured, skipping cron processing');
            return;
        }

        // Get held comments that haven't been processed yet
        $args = array(
            'status' => 'hold',
            'number' => 10, // Process up to 10 comments per cron run
            'orderby' => 'comment_date_gmt',
            'order' => 'ASC'
        );

        $held_comments = get_comments($args);

        if (empty($held_comments)) {
            error_log('WRB: No held comments found to process');
            return;
        }

        $processed = 0;
        foreach ($held_comments as $comment) {
            // Check if already processed
            $existing_decision = $this->get_comment_decision($comment->comment_ID);
            if ($existing_decision) {
                continue;
            }

            // Process this comment
            error_log("WRB: Processing held comment #{$comment->comment_ID} via cron");
            $this->process_async_moderation($comment->comment_ID);
            $processed++;

            // Add a small delay between API calls to avoid rate limiting
            if ($processed < count($held_comments)) {
                sleep(1);
            }
        }

        error_log("WRB: Cron job processed {$processed} held comments");
    }

    /**
     * Handle scheduled single comment moderation fallback
     *
     * @param int $comment_id
     */
    public function handle_single_moderate_event($comment_id) {
        error_log("WRB: handle_single_moderate_event invoked for comment #{$comment_id}");
        $this->log_event('debug', 'Single comment moderation event invoked', array('comment_id' => $comment_id), $comment_id);
        // Safety: ensure no decision yet
        if ($this->get_comment_decision($comment_id)) {
            error_log("WRB: handle_single_moderate_event abort - decision already exists for comment #{$comment_id}");
            $this->log_event('debug', 'Abort single-event (decision exists)', null, $comment_id);
            return;
        }
        $this->process_async_moderation($comment_id);
    }

    /**
     * Manual cron substitute: process our due events if WP-Cron loopback fails.
     */
    public function process_due_moderation_events() {
        // Avoid running too frequently
        if (get_transient('wrb_last_manual_cron_run')) {
            return;
        }
        set_transient('wrb_last_manual_cron_run', time(), 8); // small cooldown

        if (!function_exists('_get_cron_array')) {
            return; // Core function missing (unlikely)
        }
        $cron = _get_cron_array();
        if (empty($cron)) {
            return;
        }
        $now = time();
    $processed_any = false;
    $processed_comments = array();

        foreach ($cron as $timestamp => $hooks) {
            if ($timestamp > $now) {
                continue; // Not due yet
            }
            // Single comment events
            if (!empty($hooks['wrb_single_moderate_comment'])) {
                foreach ($hooks['wrb_single_moderate_comment'] as $sig => $event) {
                    $args = isset($event['args']) ? $event['args'] : array();
                    $comment_id = isset($args[0]) ? intval($args[0]) : 0;
                    if ($comment_id > 0) {
                        error_log("WRB: Manual tick processing single comment event for #{$comment_id}");
                        $this->log_event('debug', 'Manual tick processing single comment event', array('comment_id' => $comment_id), $comment_id);
                        $this->handle_single_moderate_event($comment_id);
                        wp_unschedule_event($timestamp, 'wrb_single_moderate_comment', $args);
                        $processed_any = true;
                        $processed_comments[] = $comment_id;
                    }
                }
            }
            // Batch held comments event
            if (!empty($hooks['wrb_process_held_comments'])) {
                foreach ($hooks['wrb_process_held_comments'] as $sig => $event) {
                    error_log("WRB: Manual tick processing held comments batch event");
                    $this->log_event('debug', 'Manual tick processing held comments batch');
                    $this->process_held_comments_cron();
                    wp_unschedule_event($timestamp, 'wrb_process_held_comments', $event['args']);
                    $processed_any = true;
                }
            }
        }

        if ($processed_any) {
            error_log('WRB: Manual async events tick processed one or more events');
            $this->log_event('info', 'Manual async tick processed events', array('comment_ids' => $processed_comments));
        }
    }

    /**
     * After a comment is posted, fire a non-blocking async moderation request for that specific comment
     *
     * @param int $comment_id
     * @param int|string $comment_approved
     */
    public function maybe_trigger_async_after_comment($comment_id, $comment_approved) {
        // Only act if comment is held/pending
        if (!in_array($comment_approved, array(0, '0', 'hold'), true)) {
            error_log("WRB: maybe_trigger_async_after_comment skip (status={$comment_approved}) for #{$comment_id}");
            $this->log_event('debug', 'Skip async trigger (status not pending)', array('status' => $comment_approved), $comment_id);
            return;
        }

        $options = get_option('wrb_options', array());
        $auto_moderation_enabled = !empty($options['auto_moderation_enabled']);
        if (!$auto_moderation_enabled) {
            error_log("WRB: maybe_trigger_async_after_comment auto-moderation disabled for #{$comment_id}");
            $this->log_event('debug', 'Skip async trigger (auto moderation disabled)', null, $comment_id);
            return;
        }

        // Skip if already has a decision
        if ($this->get_comment_decision($comment_id)) {
            error_log("WRB: maybe_trigger_async_after_comment decision already exists for #{$comment_id}");
            $this->log_event('debug', 'Skip async trigger (decision exists)', null, $comment_id);
            return;
        }

        // Build signed payload
        // Always schedule a single-event moderation in short delay (non-blocking)
        if (!wp_next_scheduled('wrb_single_moderate_comment', array($comment_id))) {
            wp_schedule_single_event(time() + 5, 'wrb_single_moderate_comment', array($comment_id));
            error_log("WRB: Scheduled single-event async moderation for comment #{$comment_id}");
            $this->log_event('info', 'Scheduled async moderation', array('delay_seconds' => 5), $comment_id);
        } else {
            error_log("WRB: Single-event already scheduled for comment #{$comment_id}");
            $this->log_event('debug', 'Single-event already scheduled', null, $comment_id);
        }
    }

    /**
     * Track manual moderation of comments outside the plugin
     * Mark AI decisions as overridden when admin manually changes status
     *
     * @param string $new_status New comment status
     * @param string $old_status Old comment status
     * @param WP_Comment $comment Comment object
     */
    public function track_manual_moderation($new_status, $old_status, $comment) {
        // Only process if status actually changed
        if ($new_status === $old_status) {
            return;
        }

        // Skip if this is a new comment (no previous decision to override)
        if ($old_status === 'hold' || $old_status === '0') {
            // Check if there's already a decision
            $decision = $this->get_comment_decision($comment->comment_ID);
            if (!$decision) {
                return; // No decision yet, nothing to override
            }
        }

        // Only track transitions to approved, spam, or trash
        if (!in_array($new_status, array('approved', 'spam', 'trash'), true)) {
            return;
        }

        global $wpdb;

        // Check if there's an AI decision for this comment
        $decision = $this->get_comment_decision($comment->comment_ID);
        
        if ($decision) {
            // Check if this change is coming from our own AJAX handlers
            // to avoid double-marking as overridden
            $is_plugin_action = defined('DOING_AJAX') && 
                                isset($_POST['action']) && 
                                strpos($_POST['action'], 'wrb_') === 0;

            if (!$is_plugin_action && !$decision->overridden) {
                // Get current user
                $current_user = wp_get_current_user();
                $user_display = $current_user->user_login ?: 'system';

                // Mark as overridden
                $wpdb->update(
                    $this->decisions_table,
                    array(
                        'overridden' => 1,
                        'overridden_by' => $user_display,
                        'overridden_at' => current_time('mysql')
                    ),
                    array('id' => $decision->id),
                    array('%d', '%s', '%s'),
                    array('%d')
                );

                $this->log_event(
                    'info',
                    'AI decision manually overridden',
                    array(
                        'comment_id' => $comment->comment_ID,
                        'ai_decision' => $decision->decision,
                        'new_status' => $new_status,
                        'overridden_by' => $user_display
                    ),
                    $comment->comment_ID
                );
            }
        }
    }
}