<?php
/**
 * Test Logging Function
 * Run this from command line or visit from browser with ?test=1
 * 
 * Usage: 
 * - From browser: http://localhost:8080/test-logging.php?test=1
 * - From CLI: php test-logging.php
 */

// Check if we're in WordPress environment
if (file_exists(__DIR__ . '/wordpress/wp-load.php')) {
    require_once(__DIR__ . '/wordpress/wp-load.php');
    echo "✓ WordPress loaded\n";
} else {
    die("Could not find WordPress. Make sure this file is in the root of the workspace.\n");
}

global $wpdb;

echo "\n=== LOGGING TEST ===\n";
echo "Current WordPress URL: " . get_site_url() . "\n";
echo "Database: " . DB_NAME . "\n";
echo "Database User: " . DB_USER . "\n";
echo "Table Prefix: " . $wpdb->prefix . "\n\n";

// Check if WRB plugin is active
if (!is_plugin_active('wordpress-review-bot/wordpress-review-bot.php')) {
    echo "⚠ WARNING: WordPress Review Bot plugin is NOT active!\n";
    echo "Activating plugin...\n";
    activate_plugin('wordpress-review-bot/wordpress-review-bot.php');
} else {
    echo "✓ WordPress Review Bot plugin is active\n";
}

// Check if logs table exists
$logs_table = $wpdb->prefix . 'wrb_logs';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") == $logs_table;
echo "\nLogs Table: {$logs_table}\n";
echo "Table Exists: " . ($table_exists ? "YES ✓" : "NO ✗") . "\n";

if (!$table_exists) {
    echo "\nAttempting to create logs table...\n";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$logs_table} (
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
    dbDelta($sql);
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") == $logs_table;
    echo "Table Created: " . ($table_exists ? "YES ✓" : "NO ✗") . "\n";
}

// Count current logs
$current_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $logs_table"));
echo "\nCurrent Log Entries: {$current_count}\n";

// Get comment manager class
require_once(ABSPATH . 'wp-load.php');
$comment_manager = WRB_Comment_Manager::get_instance();

if (!$comment_manager) {
    die("Could not initialize WRB_Comment_Manager\n");
}

// Create test log entries
echo "\nCreating test log entries...\n";

$test_logs = array(
    array('Error', 'Test error message', array('test' => true), null),
    array('Warning', 'Test warning message', array('test' => true), null),
    array('Info', 'Test info message', array('test' => true), null),
    array('Debug', 'Test debug message', array('test' => true), null),
);

foreach ($test_logs as $log) {
    $result = $comment_manager->log_event($log[0], $log[1], $log[2], $log[3]);
    echo "  - {$log[0]}: " . ($result ? "✓" : "✗") . "\n";
}

// Verify logs were written
$new_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $logs_table"));
echo "\nNew Log Entries: {$new_count}\n";
echo "Logs Added: " . ($new_count - $current_count) . "\n";

// Display recent logs
echo "\nRecent Log Entries:\n";
$recent_logs = $wpdb->get_results("SELECT * FROM $logs_table ORDER BY timestamp DESC LIMIT 10");

if ($recent_logs) {
    foreach ($recent_logs as $log) {
        echo "  [{$log->timestamp}] {$log->level}: {$log->message}\n";
    }
} else {
    echo "  (No logs found)\n";
}

echo "\n=== TEST COMPLETE ===\n";
echo "View logs at: " . admin_url('admin.php?page=wrb-logs') . "\n";
echo "\n";
?>
