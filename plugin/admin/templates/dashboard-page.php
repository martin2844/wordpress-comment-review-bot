<?php
/**
 * Dashboard Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wrb-admin">
    <div class="wrb-header">
        <h1 class="wrb-title"><?php _e('Review Bot Dashboard', 'wordpress-review-bot'); ?></h1>
        <p class="wrb-subtitle"><?php _e('AI-powered comment moderation for WordPress', 'wordpress-review-bot'); ?></p>
    </div>

    <!-- Essential Stats -->
    <div class="wrb-simple-stats">
        <div class="wrb-stat-box">
            <div class="wrb-stat-number"><?php echo number_format_i18n($stats['total']); ?></div>
            <div class="wrb-stat-label"><?php _e('Total Comments', 'wordpress-review-bot'); ?></div>
        </div>

        <div class="wrb-stat-box wrb-pending">
            <div class="wrb-stat-number"><?php echo number_format_i18n($stats['hold']); ?></div>
            <div class="wrb-stat-label"><?php _e('Pending Review', 'wordpress-review-bot'); ?></div>
        </div>

        <div class="wrb-stat-box">
            <div class="wrb-stat-number"><?php echo number_format_i18n($stats['approve']); ?></div>
            <div class="wrb-stat-label"><?php _e('Approved', 'wordpress-review-bot'); ?></div>
        </div>
    </div>

    <!-- Auto Moderation Status -->
    <div class="wrb-moderation-status">
        <h2><?php _e('AI Moderation Status', 'wordpress-review-bot'); ?></h2>
        <div class="wrb-status-card">
            <?php
            $options = get_option('wrb_options', array());
            $auto_moderation = isset($options['auto_moderation_enabled']) && $options['auto_moderation_enabled'];
            $openai_key = isset($options['openai_api_key']) && !empty($options['openai_api_key']);
            ?>

            <div class="wrb-status-indicator <?php echo ($auto_moderation && $openai_key) ? 'active' : 'inactive'; ?>">
                <span class="dashicons <?php echo ($auto_moderation && $openai_key) ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                <span class="wrb-status-text">
                    <?php
                    if (!$openai_key) {
                        _e('OpenAI API key required', 'wordpress-review-bot');
                    } elseif (!$auto_moderation) {
                        _e('Auto-moderation disabled', 'wordpress-review-bot');
                    } else {
                        _e('AI moderation active', 'wordpress-review-bot');
                    }
                    ?>
                </span>
            </div>

            <div class="wrb-status-actions">
                <?php if (!$openai_key || !$auto_moderation): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wrb-settings')); ?>" class="button button-primary">
                        <?php _e('Configure AI Moderation', 'wordpress-review-bot'); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wrb-decisions')); ?>" class="button">
                        <?php _e('View AI Decisions', 'wordpress-review-bot'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="wrb-quick-links">
        <h2><?php _e('Quick Actions', 'wordpress-review-bot'); ?></h2>
        <div class="wrb-links-grid">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wrb-settings')); ?>" class="wrb-link-card">
                <span class="dashicons dashicons-admin-settings"></span>
                <div>
                    <h3><?php _e('AI Settings', 'wordpress-review-bot'); ?></h3>
                    <p><?php _e('Configure OpenAI integration', 'wordpress-review-bot'); ?></p>
                </div>
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=wrb-decisions')); ?>" class="wrb-link-card">
                <span class="dashicons dashicons-visibility"></span>
                <div>
                    <h3><?php _e('AI Decisions', 'wordpress-review-bot'); ?></h3>
                    <p><?php _e('Review moderation history', 'wordpress-review-bot'); ?></p>
                </div>
            </a>

            <?php if ($stats['hold'] > 0): ?>
                <a href="<?php echo esc_url(admin_url('edit-comments.php?comment_status=moderated')); ?>" class="wrb-link-card">
                    <span class="dashicons dashicons-clock"></span>
                    <div>
                        <h3><?php _e('Pending Comments', 'wordpress-review-bot'); ?></h3>
                        <p><?php printf(_n('%d comment needs review', '%d comments need review', $stats['hold'], 'wordpress-review-bot'), $stats['hold']); ?></p>
                    </div>
                    <span class="wrb-badge"><?php echo $stats['hold']; ?></span>
                </a>
            <?php endif; ?>

            <?php if ($stats['hold'] > 0): ?>
                <div class="wrb-link-card" style="cursor: pointer;" id="wrb-process-pending-btn">
                    <span class="dashicons dashicons-robot"></span>
                    <div>
                        <h3><?php _e('Review with AI', 'wordpress-review-bot'); ?></h3>
                        <p><?php _e('Process pending comments with AI moderation', 'wordpress-review-bot'); ?></p>
                    </div>
                    <span class="wrb-badge"><?php echo $stats['hold']; ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Section -->
    <div class="wrb-info-box">
        <h3><?php _e('How It Works', 'wordpress-review-bot'); ?></h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <h4 style="color: #2271b1; margin-top: 0;">📝 Step 1: Comment Submitted</h4>
                <p><?php _e('When a visitor submits a comment, it is saved immediately as "pending moderation". The response is instant - no waiting!', 'wordpress-review-bot'); ?></p>
            </div>
            <div>
                <h4 style="color: #2271b1; margin-top: 0;">🤖 Step 2: AI Review</h4>
                <p><?php _e('Comments are processed by AI in the background every 2 minutes, or click "Review with AI" to process immediately.', 'wordpress-review-bot'); ?></p>
            </div>
            <div>
                <h4 style="color: #2271b1; margin-top: 0;">✅ Step 3: Auto-Approval</h4>
                <p><?php _e('AI determines if each comment should be approved, rejected, or marked as spam based on your settings.', 'wordpress-review-bot'); ?></p>
            </div>
            <div>
                <h4 style="color: #2271b1; margin-top: 0;">👁️ Step 4: Review Board</h4>
                <p><?php _e('All AI decisions are logged and can be reviewed. You can override any decision if needed.', 'wordpress-review-bot'); ?></p>
            </div>
        </div>
        <p style="background: #f0f0f0; padding: 10px; border-radius: 4px;"><strong><?php _e('💡 Tip:', 'wordpress-review-bot'); ?></strong> <?php _e('Comments are processed automatically when the site receives visitor traffic. Use the "Review with AI" button to manually trigger processing.', 'wordpress-review-bot'); ?></p>
    </div>

    <!-- Info Section -->
    <div class="wrb-info-box">
        <h3><?php _e('About AI Moderation', 'wordpress-review-bot'); ?></h3>
        <p><?php _e('Review Bot uses OpenAI\'s powerful language models to analyze comment content and automatically approve or flag comments based on context, sentiment, and spam indicators. This helps reduce manual moderation while maintaining quality control.', 'wordpress-review-bot'); ?></p>

        <div class="wrb-features">
            <div class="wrb-feature">
                <span class="dashicons dashicons-shield-alt"></span>
                <span><?php _e('Context-aware analysis', 'wordpress-review-bot'); ?></span>
            </div>
            <div class="wrb-feature">
                <span class="dashicons dashicons-clock"></span>
                <span><?php _e('Real-time processing', 'wordpress-review-bot'); ?></span>
            </div>
            <div class="wrb-feature">
                <span class="dashicons dashicons-chart-line"></span>
                <span><?php _e('Decision tracking', 'wordpress-review-bot'); ?></span>
            </div>
        </div>
    </div>
</div>