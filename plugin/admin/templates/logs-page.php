<?php
/**
 * Logs Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$logs_table = $wpdb->prefix . 'wrb_logs';

// DEBUG: Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") == $logs_table;
if (!$table_exists) {
    echo '<div class="notice notice-error"><p><strong>DEBUG:</strong> Logs table does not exist! Table name: ' . esc_html($logs_table) . '</p></div>';
    // Try to create it
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
}

// Handle log filtering
$level_filter = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
$comment_filter = isset($_GET['comment_id']) ? intval($_GET['comment_id']) : '';

// Handle clear logs action
if (isset($_POST['action']) && $_POST['action'] === 'clear_logs' && wp_verify_nonce($_POST['_wpnonce'], 'wrb_clear_logs')) {
    $wpdb->query("TRUNCATE TABLE $logs_table");
    echo '<div class="notice notice-success is-dismissible"><p>' . __('All logs cleared successfully.', 'wordpress-review-bot') . '</p></div>';
}

// Build query
$query = "SELECT * FROM $logs_table WHERE 1=1";
$params = array();

if ($level_filter) {
    $query .= " AND level = %s";
    $params[] = $level_filter;
}

if ($comment_filter) {
    $query .= " AND comment_id = %d";
    $params[] = $comment_filter;
}

$query .= " ORDER BY timestamp DESC";

// Get logs with pagination
$page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

$query_with_limit = $query . " LIMIT $per_page OFFSET $offset";
$prepared_query = $params ? $wpdb->prepare($query_with_limit, $params) : $query_with_limit;
$logs = $wpdb->get_results($prepared_query);

// Get total count
$count_query = "SELECT COUNT(*) FROM $logs_table WHERE 1=1";
if ($level_filter) {
    $count_query .= " AND level = %s";
}
if ($comment_filter) {
    $count_query .= " AND comment_id = %d";
}
$prepared_count = $params ? $wpdb->prepare($count_query, $params) : $count_query;
$total_logs = intval($wpdb->get_var($prepared_count));
$total_pages = ceil($total_logs / $per_page);

?>

<div class="wrap wrb-admin">
    <div class="wrb-header">
        <h1 class="wrb-title"><?php _e('Moderation Logs', 'wordpress-review-bot'); ?></h1>
        <p class="wrb-subtitle"><?php _e('View and analyze plugin logs and debugging information', 'wordpress-review-bot'); ?></p>
    </div>

    <div class="wrb-logs-container">
        <!-- Filters -->
        <div class="wrb-logs-filters" style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
            <form method="get" action="">
                <input type="hidden" name="page" value="wrb-logs">
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                    <div>
                        <label for="level-filter" style="margin-right: 8px;"><?php _e('Log Level:', 'wordpress-review-bot'); ?></label>
                        <select name="level" id="level-filter">
                            <option value=""><?php _e('All Levels', 'wordpress-review-bot'); ?></option>
                            <option value="error" <?php selected($level_filter, 'error'); ?>><?php _e('Errors', 'wordpress-review-bot'); ?></option>
                            <option value="warning" <?php selected($level_filter, 'warning'); ?>><?php _e('Warnings', 'wordpress-review-bot'); ?></option>
                            <option value="info" <?php selected($level_filter, 'info'); ?>><?php _e('Info', 'wordpress-review-bot'); ?></option>
                            <option value="debug" <?php selected($level_filter, 'debug'); ?>><?php _e('Debug', 'wordpress-review-bot'); ?></option>
                        </select>
                    </div>

                    <div>
                        <label for="comment-filter" style="margin-right: 8px;"><?php _e('Comment ID:', 'wordpress-review-bot'); ?></label>
                        <input type="number" name="comment_id" id="comment-filter" value="<?php echo esc_attr($comment_filter); ?>" placeholder="<?php _e('Enter comment ID', 'wordpress-review-bot'); ?>" style="width: 150px;">
                    </div>

                    <button type="submit" class="button button-primary"><?php _e('Filter', 'wordpress-review-bot'); ?></button>
                    <a href="?page=wrb-logs" class="button"><?php _e('Clear Filters', 'wordpress-review-bot'); ?></a>
                </div>
            </form>
        </div>

        <!-- Stats -->
        <div style="margin-bottom: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #2271b1; border-radius: 4px;">
            <strong><?php _e('Total Logs:', 'wordpress-review-bot'); ?></strong> <?php echo esc_html($total_logs); ?>
            <?php
            // Show quick recent activity summary
            $recent_errors = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE level='error'");
            $recent_warnings = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE level='warning'");
            $recent_info = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE level='info'");
            $recent_pending = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE level='warning' AND message LIKE '%pending review%'");
            ?>
            | <strong><?php _e('Errors', 'wordpress-review-bot'); ?>:</strong> <?php echo intval($recent_errors); ?>
            | <strong><?php _e('Warnings', 'wordpress-review-bot'); ?>:</strong> <?php echo intval($recent_warnings); ?>
            | <strong><?php _e('Info', 'wordpress-review-bot'); ?>:</strong> <?php echo intval($recent_info); ?>
            | <strong><?php _e('Pending Review', 'wordpress-review-bot'); ?>:</strong> <?php echo intval($recent_pending); ?>
            <?php if ($level_filter): ?>
                | <strong><?php _e('Filtered:', 'wordpress-review-bot'); ?></strong> <?php echo esc_html(count($logs)); ?>
            <?php endif; ?>
        </div>

        <!-- DEBUG INFO -->
        <div style="margin-bottom: 20px; padding: 15px; background: #fff8dc; border-left: 4px solid #ff9800; border-radius: 4px;">
            <details>
                <summary style="cursor: pointer; font-weight: bold;">📊 Debug Information</summary>
                <pre style="margin-top: 10px; background: #f5f5f5; padding: 10px; border-radius: 3px; font-size: 12px;">Table: <?php echo esc_html($logs_table); ?>
Table Exists: <?php echo $table_exists ? 'YES ✓' : 'NO ✗'; ?>
Total Records: <?php echo esc_html($total_logs); ?>
Query: <?php echo esc_html($query); ?>
                </pre>
            </details>
        </div>

        <!-- Logs Table -->
        <table class="widefat striped hover" style="margin-bottom: 20px;">
            <thead>
                <tr>
                    <th style="width: 180px;"><?php _e('Timestamp', 'wordpress-review-bot'); ?></th>
                    <th style="width: 80px;"><?php _e('Level', 'wordpress-review-bot'); ?></th>
                    <th><?php _e('Message', 'wordpress-review-bot'); ?></th>
                    <th style="width: 100px;"><?php _e('Comment ID', 'wordpress-review-bot'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><small><?php echo esc_html($log->timestamp); ?></small></td>
                            <td>
                                <span class="wrb-log-level wrb-log-<?php echo esc_attr($log->level); ?>" style="
                                    padding: 3px 8px;
                                    border-radius: 3px;
                                    font-size: 12px;
                                    font-weight: bold;
                                    display: inline-block;
                                    min-width: 50px;
                                    text-align: center;
                                    <?php
                                    switch ($log->level) {
                                        case 'error':
                                            echo 'background: #fee; color: #c00;';
                                            break;
                                        case 'warning':
                                            echo 'background: #ffd; color: #880;';
                                            break;
                                        case 'info':
                                            echo 'background: #eff; color: #088;';
                                            break;
                                        case 'debug':
                                            echo 'background: #f5f5f5; color: #666;';
                                            break;
                                        default:
                                            echo 'background: #f0f0f0; color: #333;';
                                    }
                                    ?>
                                ">
                                    <?php echo esc_html(strtoupper($log->level)); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 10px;">
                                    <div>
                                        <strong><?php echo esc_html($log->message); ?></strong>
                                        <?php if ($log->context): ?>
                                            <details style="margin-top: 5px;">
                                                <summary style="cursor: pointer; color: #666; font-size: 12px;">
                                                    <?php _e('View Context', 'wordpress-review-bot'); ?>
                                                </summary>
                                                <pre style="
                                                    background: #f5f5f5;
                                                    padding: 10px;
                                                    border-radius: 3px;
                                                    overflow-x: auto;
                                                    font-size: 11px;
                                                    margin-top: 8px;
                                                    max-height: 200px;
                                                    overflow-y: auto;
                                                "><?php echo esc_html($log->context); ?></pre>
                                            </details>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($log->comment_id): ?>
                                    <a href="<?php echo esc_url(admin_url('comment.php?action=editcomment&c=' . $log->comment_id)); ?>" target="_blank">
                                        #<?php echo esc_html($log->comment_id); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px; color: #999;">
                            <?php _e('No logs found.', 'wordpress-review-bot'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="margin-bottom: 20px; text-align: center;">
                <?php
                for ($i = 1; $i <= $total_pages; $i++) {
                    $url = add_query_arg(array(
                        'page' => 'wrb-logs',
                        'paged' => $i,
                        'level' => $level_filter,
                        'comment_id' => $comment_filter
                    ));
                    
                    if ($i === $page) {
                        echo '<strong>[' . $i . ']</strong> ';
                    } else {
                        echo '<a href="' . esc_url($url) . '">[' . $i . ']</a> ';
                    }
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Clear Logs Action -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <form method="post" style="display: inline-block;">
                <?php wp_nonce_field('wrb_clear_logs'); ?>
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php _e('Are you sure you want to clear all logs? This action cannot be undone.', 'wordpress-review-bot'); ?>');">
                    <?php _e('Clear All Logs', 'wordpress-review-bot'); ?>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
    .wrb-logs-container {
        background: white;
        padding: 20px;
        border-radius: 4px;
    }

    .wrb-logs-filters select,
    .wrb-logs-filters input[type="number"] {
        padding: 6px 10px;
        border: 1px solid #ddd;
        border-radius: 3px;
        font-size: 14px;
    }

    .wrb-logs-filters select:focus,
    .wrb-logs-filters input[type="number"]:focus {
        outline: none;
        border-color: #2271b1;
        box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.1);
    }

    .wrb-log-level {
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    @media (max-width: 768px) {
        .wrb-logs-filters {
            display: block;
        }

        .wrb-logs-filters > div {
            flex-direction: column !important;
        }

        table {
            font-size: 12px;
        }

        table th, table td {
            padding: 8px !important;
        }
    }
</style>
