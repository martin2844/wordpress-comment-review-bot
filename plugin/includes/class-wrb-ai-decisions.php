<?php
/**
 * AI Decisions Module
 * Handles CRUD operations for AI moderation decisions
 */

if (!defined('ABSPATH')) {
    exit;
}

class WRB_AI_Decisions {

    /**
     * Database table name for AI decisions
     */
    private $decisions_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->decisions_table = $wpdb->prefix . 'wrb_ai_decisions';
    }

    /**
     * Get the decisions table name
     *
     * @return string
     */
    public function get_decisions_table() {
        return $this->decisions_table;
    }

    /**
     * Save an AI decision to the database
     *
     * @param array $decision_data Decision data
     * @return int|false Decision ID or false on failure
     */
    public function save_ai_decision($decision_data) {
        global $wpdb;

        $data = array(
            'comment_id' => intval($decision_data['comment_id']),
            'decision' => sanitize_text_field($decision_data['decision']),
            'confidence' => floatval($decision_data['confidence']),
            'reasoning' => wp_kses_post($decision_data['reasoning']),
            'model_used' => sanitize_text_field($decision_data['model_used']),
            'processing_time' => floatval($decision_data['processing_time']),
            'created_at' => current_time('mysql')
        );

        $format = array('%d', '%s', '%f', '%s', '%s', '%f', '%s');

        return $wpdb->insert($this->decisions_table, $data, $format) ? $wpdb->insert_id : false;
    }

    /**
     * Get AI decisions from the database
     *
     * @param array $args Query arguments
     * @return array Array of decision objects
     */
    public function get_ai_decisions($args = array()) {
        global $wpdb;

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'decision' => 'all',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        
        $where_conditions = array();
        $where_values = array();

        // Add decision filter
        if ($args['decision'] !== 'all') {
            $where_conditions[] = "decision = %s";
            $where_values[] = $args['decision'];
        }

        // Add date filters
        if (!empty($args['date_from'])) {
            $where_conditions[] = "DATE(created_at) >= %s";
            $where_values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where_conditions[] = "DATE(created_at) <= %s";
            $where_values[] = $args['date_to'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get comment info for each decision
        $sql = "SELECT d.*, c.comment_author, c.comment_content, c.comment_post_ID, p.post_title
                FROM {$this->decisions_table} d
                LEFT JOIN {$wpdb->comments} c ON d.comment_id = c.comment_ID
                LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
                {$where_clause}
                ORDER BY {$args['orderby']} {$args['order']}
                LIMIT %d OFFSET %d";

        $values = array_merge($where_values, array($args['limit'], $args['offset']));

        $prepared_sql = $wpdb->prepare($sql, $values);
        $results = $wpdb->get_results($prepared_sql);

        return $results;
    }

    /**
     * Get AI decisions count
     *
     * @param array $args Query arguments
     * @return int Total count
     */
    public function get_ai_decisions_count($args = array()) {
        global $wpdb;

        $defaults = array(
            'decision' => 'all',
            'date_from' => '',
            'date_to' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array();
        $where_values = array();

        if ($args['decision'] !== 'all') {
            $where_conditions[] = "decision = %s";
            $where_values[] = $args['decision'];
        }

        if (!empty($args['date_from'])) {
            $where_conditions[] = "DATE(created_at) >= %s";
            $where_values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where_conditions[] = "DATE(created_at) <= %s";
            $where_values[] = $args['date_to'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $sql = "SELECT COUNT(*) FROM {$this->decisions_table} d {$where_clause}";

        $prepared_count_sql = $wpdb->prepare($sql, $where_values);
        $count_result = $wpdb->get_var($prepared_count_sql);

        return intval($count_result);
    }

    /**
     * Get AI decisions statistics
     *
     * @return array Statistics data
     */
    public function get_ai_decisions_stats() {
        global $wpdb;

        $sql = "SELECT
                    decision,
                    COUNT(*) as count,
                    AVG(confidence) as avg_confidence,
                    AVG(processing_time) as avg_processing_time
                FROM {$this->decisions_table}
                GROUP BY decision";

        $results = $wpdb->get_results($sql);

        $stats = array(
            'total' => 0,
            'approve' => 0,
            'reject' => 0,
            'spam' => 0,
            'pending_review' => 0
        );

        foreach ($results as $row) {
            if (!isset($stats[$row->decision])) {
                $stats[$row->decision] = 0;
            }
            $stats[$row->decision] = intval($row->count);
            $stats['total'] += intval($row->count);
        }

        // For pending_review, only count non-overridden decisions with pending comments
        $pending_sql = "SELECT COUNT(*) as count
                       FROM {$this->decisions_table} d
                       LEFT JOIN {$wpdb->comments} c ON d.comment_id = c.comment_ID
                       WHERE d.decision = 'pending_review'
                       AND d.overridden = 0
                       AND c.comment_approved = '0'";
        $pending_result = $wpdb->get_row($pending_sql);
        $stats['pending_review'] = $pending_result ? intval($pending_result->count) : 0;

        return $stats;
    }

    /**
     * Override an AI decision
     *
     * @param int $decision_id Decision ID
     * @param string $reason Override reason
     * @return bool Success status
     */
    public function override_ai_decision($decision_id, $reason) {
        global $wpdb;

        return $wpdb->update(
            $this->decisions_table,
            array(
                'overridden' => 1,
                'overridden_by' => get_current_user_id(),
                'overridden_at' => current_time('mysql')
            ),
            array('id' => intval($decision_id)),
            array('%d', '%d', '%s'),
            array('%d')
        ) !== false;
    }

    /**
     * Clear all AI decisions
     *
     * @return int Number of rows deleted
     */
    public function clear_all_decisions() {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->decisions_table}");
    }

    /**
     * Generate sample AI decisions for testing
     *
     * @param int $count Number of decisions to generate
     * @return int Number of decisions generated
     */
    public function generate_sample_decisions($count = 10) {
        global $wpdb;

        $sample_comments = $wpdb->get_results("SELECT comment_ID, comment_author, comment_content, comment_post_ID FROM {$wpdb->comments} LIMIT {$count}");

        if (empty($sample_comments)) {
            return 0;
        }

        $decisions = array('approve', 'reject', 'spam');
        $models = array('gpt-5', 'gpt-5-mini', 'gpt-5-nano', 'gpt-4o', 'gpt-4.1-nano');
        $generated = 0;

        foreach ($sample_comments as $comment) {
            $decision = $decisions[array_rand($decisions)];
            $model = $models[array_rand($models)];
            $confidence = (rand(80, 99) / 100);
            $processing_time = (rand(20, 120) / 100);

            $reasoning = $this->generate_sample_reasoning($decision, $comment->comment_content);

            $decision_data = array(
                'comment_id' => $comment->comment_ID,
                'decision' => $decision,
                'confidence' => $confidence,
                'reasoning' => $reasoning,
                'model_used' => $model,
                'processing_time' => $processing_time
            );

            if ($this->save_ai_decision($decision_data)) {
                $generated++;
            }
        }

        return $generated;
    }

    /**
     * Generate sample reasoning based on decision type
     *
     * @param string $decision Decision type
     * @param string $content Comment content
     * @return string Generated reasoning
     */
    private function generate_sample_reasoning($decision, $content) {
        switch ($decision) {
            case 'approve':
                return 'Comment is positive, relevant to the content, and adds value to the discussion. The tone is constructive and respectful.';
            case 'reject':
                return 'Comment contains inappropriate language, personal attacks, or does not contribute constructively to the conversation.';
            case 'spam':
                return 'Comment contains spam indicators such as suspicious links, promotional content, or patterns commonly associated with automated spam.';
            default:
                return 'Comment analysis completed based on content and context.';
        }
    }
}
