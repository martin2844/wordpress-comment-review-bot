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
     * Cron processor module instance
     */
    private $cron_processor;

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

        // Initialize cron processor module
        require_once plugin_dir_path(__FILE__) . 'class-wrb-cron-processor.php';
        $this->cron_processor = new WRB_Cron_Processor($this, $this->ai_decisions, $this->database, $this->openai_client);

        // Set table names from modules
        $this->decisions_table = $this->ai_decisions->get_decisions_table();

        // Set table names from database module
        $this->decisions_table = $this->database->get_decisions_table();
        $this->logs_table = $this->database->get_logs_table();

        // Initialize cron job (delegate to cron processor)
        add_action('init', array($this->cron_processor, 'init_cron_job'));

        // Update cron job when settings are saved (delegate to cron processor)
        add_action('update_option_wrb_options', array($this->cron_processor, 'init_cron_job'), 10, 2);

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

        // Ensure comments are held (fast, no external calls) - delegate to cron processor
        add_filter('pre_comment_approved', array($this->cron_processor, 'hold_comment_for_ai_review'), 100, 2);

        // Immediately trigger async moderation after a comment is saved (non-blocking) - delegate to cron processor
        add_action('comment_post', array($this->cron_processor, 'maybe_trigger_async_after_comment'), 20, 2);

        // Track when comments are manually moderated outside the plugin
        add_action('transition_comment_status', array($this, 'track_manual_moderation'), 10, 3);

        // Don't interfere with comment saving beyond setting to hold
        // Cron/async will process held comments later

        // Add custom cron schedule for frequent processing (delegate to cron processor)
        add_filter('cron_schedules', array($this->cron_processor, 'add_wrb_cron_schedules'));

        // Scheduled processing for held comments (delegate to cron processor)
        add_action('wrb_process_held_comments', array($this->cron_processor, 'process_held_comments_cron'));

        // Fallback: Auto-trigger cron processing if it hasn't run recently (delegate to cron processor)
        // This handles cases where WordPress cron doesn't fire (Docker, DISABLE_WP_CRON, etc)
        add_action('wp_footer', array($this->cron_processor, 'maybe_auto_trigger_cron'));

        // Single comment moderation fallback event (delegate to cron processor)
        add_action('wrb_single_moderate_comment', array($this->cron_processor, 'handle_single_moderate_event'));

        // Safety tick to process due moderation events when WP-Cron loopback fails (Docker etc) (delegate to cron processor)
        add_action('init', array($this->cron_processor, 'process_due_moderation_events'), 50);

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
     * Build moderation prompt for AI
     */
    private function build_moderation_prompt($comment) {
        $prompt = sprintf(
            "You are a WordPress comment moderator. Analyze the following comment and make a decisive classification.\n\n".
            "COMMENT TO ANALYZE:\n".
            "Author: \"%s\"\n".
            "Content: \"%s\"\n".
            "Post: \"%s\"\n\n".
            "DECISION GUIDELINES (be confident and decisive):\n\n".
            "APPROVE (confidence 0.8+) if:\n".
            "- Comment is relevant to the post topic\n".
            "- No spam/promotional links or content\n".
            "- Not abusive, hateful, or harassing\n".
            "- Short comments (like \"Nice post!\", \"Thanks!\", \"Great!\") are OKAY - approve them\n".
            "- Casual/informal language is OKAY - approve it\n".
            "- Brief feedback or reactions are OKAY - approve them\n\n".
            "SPAM (confidence 0.9+) if:\n".
            "- Contains external promotional links\n".
            "- Generic/template spam (\"Nice info, check out...\", \"Great post, visit...\")\n".
            "- Obvious bot patterns (unrelated pharmaceutical/casino/loan mentions)\n".
            "- Classic scam patterns (Nigerian prince, lottery winner, etc.)\n\n".
            "REJECT (confidence 0.9+) if:\n".
            "- Abusive, hateful, or harassing language\n".
            "- Direct personal attacks or insults\n".
            "- Threats or doxxing attempts\n\n".
            "Be DECISIVE. If it's not clearly spam or abusive, APPROVE it with high confidence (0.8-0.95). ".
            "Only use confidence below 0.7 for truly ambiguous edge cases.\n\n".
            "Respond with JSON: {\"decision\": \"approve/spam/reject\", \"confidence\": 0.75-0.95, \"reasoning\": \"Brief explanation\"}",
            $comment['author'],
            $comment['content'],
            $comment['post_title']
        );

        return $prompt;
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
}