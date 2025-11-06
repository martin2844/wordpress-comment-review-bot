<?php
/**
 * Pending Comments Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wrb-admin">
    <div class="wrb-header">
        <h1 class="wrb-title">
            <?php _e('Pending Comments', 'wordpress-review-bot'); ?>
            <?php if ($total_count > 0): ?>
                <span class="count">(<?php echo number_format_i18n($total_count); ?>)</span>
            <?php endif; ?>
        </h1>
    </div>

    <?php if ($total_count > 0): ?>
        <!-- Bulk Actions Bar -->
        <div class="wrb-bulk-actions-bar">
            <div class="alignleft actions bulkactions">
                <select name="wrb_bulk_action" id="wrb-bulk-action-selector">
                    <option value="-1"><?php _e('Bulk actions', 'wordpress-review-bot'); ?></option>
                    <option value="approve"><?php _e('Approve', 'wordpress-review-bot'); ?></option>
                    <option value="spam"><?php _e('Mark as Spam', 'wordpress-review-bot'); ?></option>
                    <option value="trash"><?php _e('Move to Trash', 'wordpress-review-bot'); ?></option>
                </select>
                <button type="button" class="button action" id="wrb-bulk-action-apply">
                    <?php _e('Apply', 'wordpress-review-bot'); ?>
                </button>
            </div>

            <div class="alignright">
                <button type="button" class="button button-secondary wrb-refresh-btn">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Refresh', 'wordpress-review-bot'); ?>
                </button>
            </div>

            <div class="clear"></div>
        </div>

        <!-- Comments List -->
        <div class="wrb-comments-list">
            <?php foreach ($pending_comments as $comment): ?>
                <div class="wrb-comment-card" data-comment-id="<?php echo esc_attr($comment->comment_ID); ?>">
                    <div class="wrb-comment-header">
                        <div class="wrb-comment-author">
                            <div class="wrb-author-avatar">
                                <?php echo $comment->author_avatar; ?>
                            </div>
                            <div class="wrb-author-info">
                                <strong class="wrb-author-name">
                                    <?php echo esc_html($comment->comment_author); ?>
                                </strong>
                                <div class="wrb-author-email">
                                    <?php echo esc_html($comment->comment_author_email); ?>
                                </div>
                                <?php if ($comment->comment_author_url): ?>
                                    <div class="wrb-author-url">
                                        <a href="<?php echo esc_url($comment->comment_author_url); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html(parse_url($comment->comment_author_url, PHP_URL_HOST)); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="wrb-comment-meta">
                            <div class="wrb-comment-date">
                                <?php echo esc_html($comment->comment_date_formatted); ?>
                            </div>
                            <?php if ($comment->is_spam_candidate): ?>
                                <span class="wrb-spam-indicator" title="<?php esc_attr_e('This comment shows signs of being spam', 'wordpress-review-bot'); ?>">
                                    <span class="dashicons dashicons-flag"></span>
                                    <?php _e('Possible Spam', 'wordpress-review-bot'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="wrb-comment-content">
                        <div class="wrb-comment-text">
                            <?php echo esc_html($comment->comment_content); ?>
                        </div>
                        <div class="wrb-comment-post">
                            <?php _e('On:', 'wordpress-review-bot'); ?>
                            <a href="<?php echo esc_url($comment->post_permalink . '#comment-' . $comment->comment_ID); ?>" target="_blank">
                                <?php echo esc_html($comment->post_title); ?>
                            </a>
                        </div>
                    </div>

                    <div class="wrb-comment-footer">
                        <div class="wrb-comment-select">
                            <input type="checkbox" class="wrb-comment-checkbox" value="<?php echo esc_attr($comment->comment_ID); ?>">
                            <label for="wrb-comment-<?php echo esc_attr($comment->comment_ID); ?>">
                                <?php _e('Select', 'wordpress-review-bot'); ?>
                            </label>
                        </div>

                        <div class="wrb-comment-actions">
                            <button type="button" class="button button-small wrb-action-approve" data-comment-id="<?php echo esc_attr($comment->comment_ID); ?>">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('Approve', 'wordpress-review-bot'); ?>
                            </button>
                            <button type="button" class="button button-small wrb-action-spam" data-comment-id="<?php echo esc_attr($comment->comment_ID); ?>">
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php _e('Spam', 'wordpress-review-bot'); ?>
                            </button>
                            <button type="button" class="button button-small wrb-action-trash" data-comment-id="<?php echo esc_attr($comment->comment_ID); ?>">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Trash', 'wordpress-review-bot'); ?>
                            </button>
                            <button type="button" class="button button-small wrb-action-edit" data-comment-id="<?php echo esc_attr($comment->comment_ID); ?>">
                                <span class="dashicons dashicons-edit"></span>
                                <?php _e('Edit', 'wordpress-review-bot'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="wrb-pagination">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php
                        printf(
                            _n(
                                '%d comment',
                                '%d comments',
                                $total_count,
                                'wordpress-review-bot'
                            ),
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

    <?php else: ?>
        <!-- No pending comments -->
        <div class="wrb-no-comments">
            <div class="wrb-empty-state">
                <div class="wrb-empty-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <h3><?php _e('No Pending Comments', 'wordpress-review-bot'); ?></h3>
                <p><?php _e('All comments have been reviewed! Great job keeping on top of comment moderation.', 'wordpress-review-bot'); ?></p>
                <p>
                    <a href="<?php echo esc_url(admin_url('edit-comments.php?comment_status=all')); ?>" class="button button-primary">
                        <?php _e('View All Comments', 'wordpress-review-bot'); ?>
                    </a>
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>