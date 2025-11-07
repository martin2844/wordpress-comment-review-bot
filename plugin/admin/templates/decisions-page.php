<?php
/**
 * Decisions History Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}


// Get filter parameters from URL
$decision_filter = isset($_GET['decision']) ? sanitize_text_field($_GET['decision']) : 'all';
$date_filter = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : 'all';

// Build query arguments
$args = array(
    'limit' => 50,
    'offset' => isset($_GET['paged']) ? (intval($_GET['paged']) - 1) * 50 : 0,
    'decision' => $decision_filter,
    'orderby' => 'created_at',
    'order' => 'DESC'
);

// Add date filters
if ($date_filter === 'today') {
    $args['date_from'] = current_time('Y-m-d');
    $args['date_to'] = current_time('Y-m-d');
} elseif ($date_filter === 'week') {
    $args['date_from'] = date('Y-m-d', strtotime('-7 days'));
    $args['date_to'] = current_time('Y-m-d');
} elseif ($date_filter === 'month') {
    $args['date_from'] = date('Y-m-d', strtotime('-30 days'));
    $args['date_to'] = current_time('Y-m-d');
}


// Get decisions from database
$comment_manager = new WRB_Comment_Manager();
$decisions = $comment_manager->get_ai_decisions($args);
$total_count = $comment_manager->get_ai_decisions_count($args);
$stats = $comment_manager->get_ai_decisions_stats();


// Calculate pagination
$total_pages = ceil($total_count / 50);
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
?>

<div class="wrap wrb-admin">
    <div class="wrb-header">
        <h1 class="wrb-title"><?php _e('AI Decisions History', 'wordpress-review-bot'); ?></h1>
        <p class="wrb-subtitle"><?php _e('Review all AI moderation decisions and their reasoning', 'wordpress-review-bot'); ?></p>
    </div>

    <!-- Summary Stats -->
    <div class="wrb-decision-stats">
        <div class="wrb-stat-card">
            <div class="wrb-stat-number"><?php echo number_format_i18n($stats['total']); ?></div>
            <div class="wrb-stat-label"><?php _e('Total Decisions', 'wordpress-review-bot'); ?></div>
        </div>
        <div class="wrb-stat-card wrb-approved">
            <div class="wrb-stat-number"><?php echo number_format_i18n($stats['approve']); ?></div>
            <div class="wrb-stat-label"><?php _e('Approved', 'wordpress-review-bot'); ?></div>
        </div>
        <div class="wrb-stat-card wrb-rejected">
            <div class="wrb-stat-number"><?php echo number_format_i18n($stats['reject']); ?></div>
            <div class="wrb-stat-label"><?php _e('Rejected', 'wordpress-review-bot'); ?></div>
        </div>
        <div class="wrb-stat-card wrb-spam">
            <div class="wrb-stat-number"><?php echo number_format_i18n($stats['spam']); ?></div>
            <div class="wrb-stat-label"><?php _e('Marked as Spam', 'wordpress-review-bot'); ?></div>
        </div>
        <div class="wrb-stat-card wrb-pending-review">
            <div class="wrb-stat-number"><?php echo number_format_i18n(isset($stats['pending_review']) ? $stats['pending_review'] : 0); ?></div>
            <div class="wrb-stat-label"><?php _e('Needs Review', 'wordpress-review-bot'); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="wrb-filters">
        <form method="get" class="wrb-filter-form">
            <input type="hidden" name="page" value="wrb-decisions">

            <div class="wrb-filter-group">
                <label for="decision-filter"><?php _e('Decision:', 'wordpress-review-bot'); ?></label>
                <select name="decision" id="decision-filter">
                    <option value="all" <?php selected($decision_filter, 'all'); ?>><?php _e('All Decisions', 'wordpress-review-bot'); ?></option>
                    <option value="approve" <?php selected($decision_filter, 'approve'); ?>><?php _e('Approved', 'wordpress-review-bot'); ?></option>
                    <option value="reject" <?php selected($decision_filter, 'reject'); ?>><?php _e('Rejected', 'wordpress-review-bot'); ?></option>
                    <option value="spam" <?php selected($decision_filter, 'spam'); ?>><?php _e('Spam', 'wordpress-review-bot'); ?></option>
                    <option value="pending_review" <?php selected($decision_filter, 'pending_review'); ?>><?php _e('Needs Review', 'wordpress-review-bot'); ?></option>
                </select>
            </div>

            <div class="wrb-filter-group">
                <label for="date-filter"><?php _e('Date:', 'wordpress-review-bot'); ?></label>
                <select name="date" id="date-filter">
                    <option value="all" <?php selected($date_filter, 'all'); ?>><?php _e('All Time', 'wordpress-review-bot'); ?></option>
                    <option value="today" <?php selected($date_filter, 'today'); ?>><?php _e('Today', 'wordpress-review-bot'); ?></option>
                    <option value="week" <?php selected($date_filter, 'week'); ?>><?php _e('This Week', 'wordpress-review-bot'); ?></option>
                    <option value="month" <?php selected($date_filter, 'month'); ?>><?php _e('This Month', 'wordpress-review-bot'); ?></option>
                </select>
            </div>

            <div class="wrb-filter-group">
                <button type="submit" class="button"><?php _e('Filter', 'wordpress-review-bot'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wrb-decisions')); ?>" class="button button-secondary"><?php _e('Clear', 'wordpress-review-bot'); ?></a>
            </div>
        </form>
    </div>

    <!-- Decisions List -->
    <div class="wrb-decisions-list">

            <?php
            // Pending review box (always show if there are any pending_review decisions and not currently filtering to only them)
            $pending_args = array(
                'limit' => 10,
                'offset' => 0,
                'decision' => 'pending_review',
                'orderby' => 'created_at',
                'order' => 'DESC'
            );
            $pending_decisions = $comment_manager->get_ai_decisions($pending_args);
            if (!empty($pending_decisions) && $decision_filter !== 'pending_review') : ?>
                <div class="wrb-pending-review-box">
                    <h2><?php _e('Needs Manual Review', 'wordpress-review-bot'); ?></h2>
                    <p class="description"><?php _e('These comments had a low confidence score. Review and override if appropriate. They will not be re-analyzed.', 'wordpress-review-bot'); ?></p>
                    <div class="wrb-pending-review-list">
                        <?php foreach ($pending_decisions as $p): ?>
                            <div class="wrb-pending-item">
                                <strong>#<?php echo esc_html($p->comment_id); ?></strong>
                                <span class="wrb-pending-confidence"><?php printf(__('Confidence: %s%%', 'wordpress-review-bot'), round($p->confidence * 100)); ?></span>
                                <span class="wrb-pending-reasoning" style="display:block;margin-top:4px;">
                                    <?php echo esc_html(wp_trim_words($p->reasoning, 25)); ?>
                                </span>
                                <a class="wrb-view-full" href="<?php echo esc_url(admin_url('comment.php?action=editcomment&c=' . $p->comment_id)); ?>" target="_blank"><?php _e('View Comment', 'wordpress-review-bot'); ?></a>
                            </div>
                        <?php endforeach; ?>
                        <p><a href="<?php echo esc_url(add_query_arg('decision','pending_review', admin_url('admin.php?page=wrb-decisions'))); ?>" class="button button-small"><?php _e('View All Pending Review', 'wordpress-review-bot'); ?></a></p>
                    </div>
                </div>
            <?php endif; ?>

        <?php if (!empty($decisions)): ?>
            <?php foreach ($decisions as $decision): ?>
                <div class="wrb-decision-card" data-decision-id="<?php echo esc_attr($decision->id); ?>">
                    <div class="wrb-decision-header">
                        <div class="wrb-decision-meta">
                            <span class="wrb-decision-id">#<?php echo $decision->comment_id; ?></span>
                            <span class="wrb-decision-date"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($decision->created_at))); ?></span>
                            <span class="wrb-processing-time"><?php printf(__('%.1fs processing', 'wordpress-review-bot'), $decision->processing_time); ?></span>
                        </div>

                        <div class="wrb-decision-badge wrb-<?php echo esc_attr($decision->decision); ?>">
                            <?php
                            switch ($decision->decision) {
                                case 'approve':
                                    _e('Approved', 'wordpress-review-bot');
                                    break;
                                case 'reject':
                                    _e('Rejected', 'wordpress-review-bot');
                                    break;
                                case 'spam':
                                    _e('Spam', 'wordpress-review-bot');
                                    break;
                                case 'pending_review':
                                    _e('Needs Review', 'wordpress-review-bot');
                                    break;
                            }
                            ?>
                        </div>

                        <div class="wrb-confidence-score">
                            <span class="wrb-confidence-label"><?php _e('Confidence:', 'wordpress-review-bot'); ?></span>
                            <div class="wrb-confidence-bar">
                                <div class="wrb-confidence-fill" style="width: <?php echo ($decision->confidence * 100); ?>%;"></div>
                            </div>
                            <span class="wrb-confidence-value"><?php echo round($decision->confidence * 100); ?>%</span>
                        </div>
                    </div>

                    <div class="wrb-decision-content">
                        <div class="wrb-comment-info">
                            <div class="wrb-comment-author">
                                <strong><?php echo esc_html($decision->comment_author); ?></strong>
                                <span class="wrb-comment-post"><?php _e('on', 'wordpress-review-bot'); ?> <a href="<?php echo esc_url(get_permalink($decision->comment_post_ID) . '#comment-' . $decision->comment_id); ?>" target="_blank"><?php echo esc_html($decision->post_title); ?></a></span>
                            </div>
                            <div class="wrb-comment-text">
                                <?php echo esc_html($decision->comment_content); ?>
                            </div>
                        </div>

                        <div class="wrb-ai-analysis">
                            <h4><?php _e('AI Analysis', 'wordpress-review-bot'); ?></h4>
                            <div class="wrb-reasoning">
                                <?php echo esc_html($decision->reasoning); ?>
                            </div>
                            <div class="wrb-model-info">
                                <span class="wrb-model"><?php printf(__('Model: %s', 'wordpress-review-bot'), esc_html($decision->model_used)); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="wrb-decision-actions">
                        <button type="button" class="button button-small wrb-override-decision" data-decision-id="<?php echo esc_attr($decision->id); ?>">
                            <span class="dashicons dashicons-undo"></span>
                            <?php _e('Override Decision', 'wordpress-review-bot'); ?>
                        </button>
                        <button type="button" class="button button-small wrb-view-details">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php _e('Full Details', 'wordpress-review-bot'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="wrb-pagination">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php
                            printf(
                                _n('%d decision', '%d decisions', $total_count, 'wordpress-review-bot'),
                                number_format_i18n($total_count)
                            );
                            ?>
                        </span>

                        <span class="pagination-links">
                            <?php
                            $current_url = remove_query_arg('paged', $_SERVER['REQUEST_URI']);

                            // First page
                            if ($current_page > 1):
                            ?>
                                <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1, $current_url)); ?>">
                                    <span class="screen-reader-text"><?php _e('First page', 'wordpress-review-bot'); ?></span>
                                    <span aria-hidden="true">«</span>
                                </a>
                                <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $current_url)); ?>">
                                    <span class="screen-reader-text"><?php _e('Previous page', 'wordpress-review-bot'); ?></span>
                                    <span aria-hidden="true">‹</span>
                                </a>
                            <?php endif; ?>

                            <span class="paging-input">
                                <label for="current-page-selector" class="screen-reader-text">
                                    <?php _e('Current Page', 'wordpress-review-bot'); ?>
                                </label>
                                <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr($current_page); ?>" size="1" aria-describedby="table-paging">
                                <span class="tablenav-paging-text">
                                    <?php printf(__('of %s', 'wordpress-review-bot'), '<span class="total-pages">' . number_format_i18n($total_pages) . '</span>'); ?>
                                </span>
                            </span>

                            <?php
                            // Next page
                            if ($current_page < $total_pages):
                            ?>
                                <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $current_url)); ?>">
                                    <span class="screen-reader-text"><?php _e('Next page', 'wordpress-review-bot'); ?></span>
                                    <span aria-hidden="true">›</span>
                                </a>
                                <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $total_pages, $current_url)); ?>">
                                    <span class="screen-reader-text"><?php _e('Last page', 'wordpress-review-bot'); ?></span>
                                    <span aria-hidden="true">»</span>
                                </a>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif (!current_user_can('manage_options')): ?>
            <div class="wrb-no-decisions">
                <div class="wrb-empty-state">
                    <div class="wrb-empty-icon">
                        <span class="dashicons dashicons-search"></span>
                    </div>
                    <h3><?php _e('No Decisions Found', 'wordpress-review-bot'); ?></h3>
                    <p><?php _e('No AI moderation decisions match your current filters.', 'wordpress-review-bot'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wrb-decisions')); ?>" class="button button-primary">
                            <?php _e('Clear Filters', 'wordpress-review-bot'); ?>
                        </a>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Export Options -->
    <div class="wrb-export-section">
        <h2><?php _e('Export Data', 'wordpress-review-bot'); ?></h2>
        <p><?php _e('Export AI decision data for analysis or compliance.', 'wordpress-review-bot'); ?></p>

        <div class="wrb-export-buttons">
            <button type="button" class="button button-secondary" id="wrb-export-csv">
                <span class="dashicons dashicons-media-spreadsheet"></span>
                <?php _e('Export as CSV', 'wordpress-review-bot'); ?>
            </button>
            <button type="button" class="button button-secondary" id="wrb-export-json">
                <span class="dashicons dashicons-media-code"></span>
                <?php _e('Export as JSON', 'wordpress-review-bot'); ?>
            </button>
            <button type="button" class="button button-secondary" id="wrb-generate-report">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php _e('Generate Report', 'wordpress-review-bot'); ?>
            </button>
        </div>
    </div>
</div>