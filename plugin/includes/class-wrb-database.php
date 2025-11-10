<?php
/**
 * Database and Logging Module
 * Handles database table creation and event logging
 */

if (!defined('ABSPATH')) {
    exit;
}

class WRB_Database {

    /**
     * Database table name for AI decisions
     */
    private $decisions_table;

    /**
     * Database table name for plugin logs
     */
    private $logs_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->decisions_table = $wpdb->prefix . 'wrb_ai_decisions';
        $this->logs_table = $wpdb->prefix . 'wrb_logs';

        // Initialize database tables
        $this->create_decisions_table();
        $this->create_logs_table();
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
     * Get the logs table name
     *
     * @return string
     */
    public function get_logs_table() {
        return $this->logs_table;
    }

    /**
     * Create the AI decisions database table
     */
    private function create_decisions_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->decisions_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            comment_id int(11) NOT NULL,
            decision varchar(20) NOT NULL,
            confidence decimal(3,2) NOT NULL,
            reasoning text NOT NULL,
            model_used varchar(50) NOT NULL,
            processing_time decimal(5,2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            overridden tinyint(1) DEFAULT 0,
            overridden_by varchar(255) DEFAULT NULL,
            overridden_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY comment_id (comment_id),
            KEY decision (decision),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create the plugin logs database table
     */
    private function create_logs_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->logs_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL,
            message longtext NOT NULL,
            context longtext,
            comment_id int(11),
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY level (level),
            KEY comment_id (comment_id),
            KEY level_timestamp (level, timestamp)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log an event to the database logs table
     *
     * @param string $level Log level (error, warning, info, debug)
     * @param string $message Log message
     * @param mixed $context Additional context (array or string)
     * @param int $comment_id Optional comment ID
     */
    public function log_event($level, $message, $context = null, $comment_id = null) {
        global $wpdb;

        // Normalize level to lowercase to match filters/UI
        $level = strtolower($level);

        // Also log to error_log for debugging
        error_log("WRB [{$level}] {$message}" . ($context ? ' - ' . (is_array($context) ? json_encode($context) : $context) : ''));

        // Store in database
        $wpdb->insert(
            $this->logs_table,
            array(
                'level' => sanitize_text_field($level),
                'message' => wp_kses_post($message),
                'context' => is_array($context) ? json_encode($context) : ($context ? wp_kses_post($context) : null),
                'comment_id' => $comment_id ? intval($comment_id) : null,
            ),
            array('%s', '%s', '%s', '%d')
        );
    }
}
