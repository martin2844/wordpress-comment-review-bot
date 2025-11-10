<?php
/**
 * WRB Cron & Async Processor Module
 *
 * Handles all cron scheduling, async processing, and background comment moderation.
 * This module manages:
 * - WordPress cron job scheduling and custom intervals
 * - Async moderation triggers after comments are posted
 * - Batch processing of held comments
 * - Fallback mechanisms for Docker/disabled WP-Cron environments
 * - Pre-comment approval filtering to hold comments for review
 *
 * @package WordPress_Review_Bot
 */

if (!defined('ABSPATH')) {
    exit;
}

class WRB_Cron_Processor {
    
    /**
     * Reference to main Comment Manager
     *
     * @var WRB_Comment_Manager
     */
    private $comment_manager;

    /**
     * Reference to AI Decisions module
     *
     * @var WRB_AI_Decisions
     */
    private $ai_decisions;

    /**
     * Reference to Database module
     *
     * @var WRB_Database
     */
    private $database;

    /**
     * Reference to OpenAI Client module
     *
     * @var WRB_OpenAI_Client
     */
    private $openai_client;

    /**
     * Constructor
     *
     * @param WRB_Comment_Manager $comment_manager Main comment manager instance
     * @param WRB_AI_Decisions $ai_decisions AI decisions module
     * @param WRB_Database $database Database module
     * @param WRB_OpenAI_Client $openai_client OpenAI client module
     */
    public function __construct($comment_manager, $ai_decisions, $database, $openai_client) {
        $this->comment_manager = $comment_manager;
        $this->ai_decisions = $ai_decisions;
        $this->database = $database;
        $this->openai_client = $openai_client;
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
        $this->database->log_event('debug', 'Single comment moderation event invoked', array('comment_id' => $comment_id), $comment_id);
        
        // Safety: ensure no decision yet
        if ($this->get_comment_decision($comment_id)) {
            error_log("WRB: handle_single_moderate_event abort - decision already exists for comment #{$comment_id}");
            $this->database->log_event('debug', 'Abort single-event (decision exists)', null, $comment_id);
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
                        $this->database->log_event('debug', 'Manual tick processing single comment event', array('comment_id' => $comment_id), $comment_id);
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
                    $this->database->log_event('debug', 'Manual tick processing held comments batch');
                    $this->process_held_comments_cron();
                    wp_unschedule_event($timestamp, 'wrb_process_held_comments', $event['args']);
                    $processed_any = true;
                }
            }
        }

        if ($processed_any) {
            error_log('WRB: Manual async events tick processed one or more events');
            $this->database->log_event('info', 'Manual async tick processed events', array('comment_ids' => $processed_comments));
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
            $this->database->log_event('debug', 'Skip async trigger (status not pending)', array('status' => $comment_approved), $comment_id);
            return;
        }

        $options = get_option('wrb_options', array());
        $auto_moderation_enabled = !empty($options['auto_moderation_enabled']);
        if (!$auto_moderation_enabled) {
            error_log("WRB: maybe_trigger_async_after_comment auto-moderation disabled for #{$comment_id}");
            $this->database->log_event('debug', 'Skip async trigger (auto moderation disabled)', null, $comment_id);
            return;
        }

        // Skip if already has a decision
        if ($this->get_comment_decision($comment_id)) {
            error_log("WRB: maybe_trigger_async_after_comment decision already exists for #{$comment_id}");
            $this->database->log_event('debug', 'Skip async trigger (decision exists)', null, $comment_id);
            return;
        }

        // Build signed payload
        // Always schedule a single-event moderation in short delay (non-blocking)
        if (!wp_next_scheduled('wrb_single_moderate_comment', array($comment_id))) {
            wp_schedule_single_event(time() + 5, 'wrb_single_moderate_comment', array($comment_id));
            error_log("WRB: Scheduled single-event async moderation for comment #{$comment_id}");
            $this->database->log_event('info', 'Scheduled async moderation', array('delay_seconds' => 5), $comment_id);
        } else {
            error_log("WRB: Single-event already scheduled for comment #{$comment_id}");
            $this->database->log_event('debug', 'Single-event already scheduled', null, $comment_id);
        }
    }

    /**
     * Process async moderation (runs in background)
     *
     * @param int $comment_id The comment ID to moderate
     */
    public function process_async_moderation($comment_id) {
        error_log("WRB: process_async_moderation (start) for comment #{$comment_id}");
        
        // Log test entry to verify logging is working
        $this->database->log_event('debug', "Starting async moderation for comment #{$comment_id}", array('test' => true), $comment_id);
        
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

            // Build the moderation prompt
            $prompt = $this->build_moderation_prompt($comment_data);

            // Get AI decision
            $result = $this->openai_client->moderate_comment($api_key, $model, $comment_data, $prompt, $max_tokens, $temperature);
            $end_time = microtime(true);
            $processing_time = round(($end_time - $start_time) * 1000) / 1000;

            if (!$result['success']) {
                // Log the error to the database
                $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
                $error_code = isset($result['code']) ? $result['code'] : 'unknown_error';
                error_log("WRB: Comment #{$comment_id} failed: {$error_code} - {$error_message}");
                
                $this->database->log_event(
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
                $this->ai_decisions->save_ai_decision(array(
                    'comment_id' => $comment_id,
                    'decision' => $result['decision'],
                    'reasoning' => $result['reasoning'],
                    'confidence' => $result['confidence'],
                    'model_used' => $model,
                    'processing_time' => $processing_time
                ));

                // Log successful decision (info level, minimal context)
                $this->database->log_event(
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
                $this->ai_decisions->save_ai_decision(array(
                    'comment_id' => $comment_id,
                    'decision' => 'pending_review',
                    'reasoning' => $result['reasoning'],
                    'confidence' => $result['confidence'],
                    'model_used' => $model,
                    'processing_time' => $processing_time
                ));

                // Keep the comment pending (do not change status)

                // Log low confidence to DB
                $this->database->log_event(
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
            
            $this->database->log_event(
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
     * DEPRECATED: Comments are now processed only via cron job (wrb_process_held_comments)
     * This function is kept for backward compatibility but does nothing
     *
     * @deprecated Use process_held_comments_cron instead
     */
    public function schedule_async_moderation($comment_id, $comment) {
        // No-op: Cron job handles all comment processing
    }

    /**
     * Hold comment for AI review (pre_comment_approved filter)
     *
     * @param mixed $approved The current approval status
     * @param array $commentdata Comment data
     * @return mixed The approval status
     */
    public function hold_comment_for_ai_review($approved, $commentdata) {
        error_log("WRB: hold_comment_for_ai_review called with approved=" . var_export($approved, true));
        $this->database->log_event('debug', 'Filter pre_comment_approved invoked', array('original_status' => $approved));
        
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
        $this->database->log_event('info', 'Comment held for AI moderation', array('comment_post_ID' => $post_id));
        return 0; // Use numeric 0 to avoid edge cases with 'hold' string
    }

    /**
     * Get existing decision for a comment
     *
     * @param int $comment_id The comment ID
     * @return object|null The decision object or null if not found
     */
    private function get_comment_decision($comment_id) {
        global $wpdb;

        $table_name = $this->database->get_decisions_table();
        $sql = $wpdb->prepare("SELECT * FROM {$table_name} WHERE comment_id = %d LIMIT 1", $comment_id);

        return $wpdb->get_row($sql);
    }

    /**
     * Build moderation prompt for AI
     *
     * @param array $comment Comment data
     * @return string The prompt
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
}
