<?php
/**
 * Settings Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$options = get_option('wrb_options', array());
$openai_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
$auto_moderation = isset($options['auto_moderation_enabled']) && $options['auto_moderation_enabled'];
$webhook_url = isset($options['webhook_url']) ? $options['webhook_url'] : '';
$model = isset($options['openai_model']) ? $options['openai_model'] : 'gpt-3.5-turbo';
$reasoning_effort = isset($options['reasoning_effort']) ? $options['reasoning_effort'] : 'low';
?>

<div class="wrap wrb-admin">
    <div class="wrb-header">
        <h1 class="wrb-title"><?php _e('AI Moderation Settings', 'wordpress-review-bot'); ?></h1>
        <p class="wrb-subtitle"><?php _e('Configure OpenAI integration for automatic comment moderation', 'wordpress-review-bot'); ?></p>
    </div>

    <form method="post" action="options.php">
        <?php
        settings_fields('wrb_settings');
        do_settings_sections('wrb_settings');
        ?>

        <!-- OpenAI Configuration -->
        <div class="wrb-settings-section">
            <h2><?php _e('OpenAI Configuration', 'wordpress-review-bot'); ?></h2>
            <p class="description"><?php _e('Enter your OpenAI API credentials to enable AI-powered comment moderation.', 'wordpress-review-bot'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wrb_openai_api_key"><?php _e('OpenAI API Key', 'wordpress-review-bot'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="wrb_options[openai_api_key]" id="wrb_openai_api_key" value="<?php echo esc_attr($openai_key); ?>" class="regular-text" placeholder="sk-...">
                        <p class="description">
                            <?php _e('Your OpenAI API key. Get one from', 'wordpress-review-bot'); ?>
                            <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer"><?php _e('OpenAI Dashboard', 'wordpress-review-bot'); ?></a>.
                        </p>
                        <?php if (!empty($openai_key)): ?>
                            <p class="description">
                                <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                <?php _e('API key configured', 'wordpress-review-bot'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wrb_openai_model"><?php _e('AI Model', 'wordpress-review-bot'); ?></label>
                    </th>
                    <td>
                        <select name="wrb_options[openai_model]" id="wrb_openai_model">
                            <optgroup label="Latest Models (Recommended)">
                                <option value="gpt-5" <?php selected($model, 'gpt-5'); ?>>GPT-5 (Most Advanced)</option>
                                <option value="gpt-5-mini" <?php selected($model, 'gpt-5-mini'); ?>>GPT-5 Mini (Balanced)</option>
                                <option value="gpt-5-nano" <?php selected($model, 'gpt-5-nano'); ?>>GPT-5 Nano (Ultra Fast)</option>
                            </optgroup>
                            <optgroup label="Current Models">
                                <option value="gpt-4o" <?php selected($model, 'gpt-4o'); ?>>GPT-4o (Multimodal)</option>
                                <option value="gpt-4.1-nano" <?php selected($model, 'gpt-4.1-nano'); ?>>GPT-4.1 Nano (Efficient)</option>
                            </optgroup>
                            <optgroup label="Legacy Models">
                                <option value="gpt-4-turbo" <?php selected($model, 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                <option value="gpt-4" <?php selected($model, 'gpt-4'); ?>>GPT-4</option>
                                <option value="gpt-3.5-turbo" <?php selected($model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Legacy)</option>
                            </optgroup>
                        </select>
                        <p class="description"><?php _e('Choose the AI model for comment analysis. GPT-5 Mini is recommended for most use cases.', 'wordpress-review-bot'); ?></p>
                    </td>
                </tr>

                <tr id="wrb_reasoning_effort_row">
                    <th scope="row">
                        <label for="wrb_reasoning_effort"><?php _e('Reasoning Effort (GPT-5)', 'wordpress-review-bot'); ?></label>
                    </th>
                    <td>
                        <select name="wrb_options[reasoning_effort]" id="wrb_reasoning_effort">
                            <option value="low" <?php selected($reasoning_effort, 'low'); ?>><?php _e('Low (fastest)', 'wordpress-review-bot'); ?></option>
                            <option value="medium" <?php selected($reasoning_effort, 'medium'); ?>><?php _e('Medium (balanced)', 'wordpress-review-bot'); ?></option>
                            <option value="high" <?php selected($reasoning_effort, 'high'); ?>><?php _e('High (most accurate)', 'wordpress-review-bot'); ?></option>
                        </select>
                        <p class="description"><?php _e('Controls the depth of reasoning for GPT-5 models. Higher effort can increase accuracy and confidence at higher cost/time.', 'wordpress-review-bot'); ?></p>
                        <p class="description" id="wrb_reasoning_effort_hint" style="display:none; color:#666;">
                            <?php _e('Reasoning effort only applies to GPT-5 family models and is hidden for other selections.', 'wordpress-review-bot'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wrb_webhook_url"><?php _e('Webhook URL (Optional)', 'wordpress-review-bot'); ?></label>
                    </th>
                    <td>
                        <input type="url" name="wrb_options[webhook_url]" id="wrb_webhook_url" value="<?php echo esc_attr($webhook_url); ?>" class="regular-text" placeholder="https://your-app.com/webhook">
                        <p class="description"><?php _e('Receive webhook notifications when AI makes moderation decisions.', 'wordpress-review-bot'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Auto-Moderation Settings -->
        <div class="wrb-settings-section">
            <h2><?php _e('Auto-Moderation Rules', 'wordpress-review-bot'); ?></h2>
            <p class="description"><?php _e('Configure how AI should automatically handle comment moderation.', 'wordpress-review-bot'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wrb_auto_moderation_enabled"><?php _e('Enable Auto-Moderation', 'wordpress-review-bot'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="wrb_options[auto_moderation_enabled]" id="wrb_auto_moderation_enabled" value="1" <?php checked($auto_moderation); ?>>
                            <?php _e('Automatically moderate new comments using AI', 'wordpress-review-bot'); ?>
                        </label>
                        <p class="description"><?php _e('When enabled, new comments will be automatically analyzed by AI and approved or flagged based on the analysis.', 'wordpress-review-bot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wrb_confidence_threshold"><?php _e('Confidence Threshold', 'wordpress-review-bot'); ?></label>
                    </th>
                    <td>
                        <input type="range" name="wrb_options[confidence_threshold]" id="wrb_confidence_threshold" min="0.5" max="1.0" step="0.1" value="<?php echo esc_attr(isset($options['confidence_threshold']) ? $options['confidence_threshold'] : 0.7); ?>">
                        <span id="confidence-value"><?php echo esc_attr(isset($options['confidence_threshold']) ? $options['confidence_threshold'] : 0.7); ?></span>
                        <p class="description"><?php _e('Minimum confidence level for automatic decisions. Higher values are more conservative.', 'wordpress-review-bot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php _e('Comment Types to Auto-Moderate', 'wordpress-review-bot'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="wrb_options[moderate_post_comments]" id="wrb_moderate_post_comments" value="1" <?php checked(isset($options['moderate_post_comments']) && $options['moderate_post_comments']); ?>>
                            <?php _e('Post comments', 'wordpress-review-bot'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="wrb_options[moderate_page_comments]" id="wrb_moderate_page_comments" value="1" <?php checked(isset($options['moderate_page_comments']) && $options['moderate_page_comments']); ?>>
                            <?php _e('Page comments', 'wordpress-review-bot'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="wrb_options[moderate_product_comments]" id="wrb_moderate_product_comments" value="1" <?php checked(isset($options['moderate_product_comments']) && $options['moderate_product_comments']); ?>>
                            <?php _e('Product reviews (WooCommerce)', 'wordpress-review-bot'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Advanced Options -->
        <div class="wrb-settings-section">
            <h2><?php _e('Advanced Options', 'wordpress-review-bot'); ?></h2>
            <p class="description"><?php _e('Fine-tune AI behavior and logging.', 'wordpress-review-bot'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wrb_max_tokens"><?php _e('Max Tokens', 'wordpress-review-bot'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="wrb_options[max_tokens]" id="wrb_max_tokens" value="<?php echo esc_attr(isset($options['max_tokens']) ? $options['max_tokens'] : 150); ?>" min="50" step="50">
                        <p class="description"><?php _e('Maximum tokens for AI response. Higher values allow more detailed analysis but cost more. No upper limit imposed by the plugin.', 'wordpress-review-bot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wrb_temperature"><?php _e('Temperature', 'wordpress-review-bot'); ?></label>
                    </th>
                    <td>
                        <input type="range" name="wrb_options[temperature]" id="wrb_temperature" min="0" max="1" step="0.1" value="<?php echo esc_attr(isset($options['temperature']) ? $options['temperature'] : 0.1); ?>">
                        <span id="temperature-value"><?php echo esc_attr(isset($options['temperature']) ? $options['temperature'] : 0.1); ?></span>
                        <p class="description"><?php _e('Lower values make AI responses more consistent and deterministic.', 'wordpress-review-bot'); ?></p>
                        <p class="description"><em><?php _e('Note: Some newer models (like GPT-5 series) use fixed temperature settings. The plugin will automatically adjust this parameter when needed.', 'wordpress-review-bot'); ?></em></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wrb_log_decisions"><?php _e('Log AI Decisions', 'wordpress-review-bot'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="wrb_options[log_decisions]" id="wrb_log_decisions" value="1" <?php checked(isset($options['log_decisions']) && $options['log_decisions']); ?>>
                            <?php _e('Save all AI moderation decisions for review', 'wordpress-review-bot'); ?>
                        </label>
                        <p class="description"><?php _e('Store AI reasoning and decisions for transparency and debugging.', 'wordpress-review-bot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php _e('Testing', 'wordpress-review-bot'); ?></label>
                    </th>
                    <td>
                        <button type="button" class="button button-secondary" id="wrb-test-connection">
                            <?php _e('Test OpenAI Connection', 'wordpress-review-bot'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="wrb-test-moderation">
                            <?php _e('Test AI Moderation', 'wordpress-review-bot'); ?>
                        </button>
                        <p class="description"><?php _e('Test your OpenAI connection and AI moderation functionality. The AI moderation test will analyze a sample spam comment to verify spam detection is working properly.', 'wordpress-review-bot'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="wrb-settings-actions">
            <?php submit_button(__('Save Settings', 'wordpress-review-bot'), 'primary', 'submit', false); ?>
            <button type="button" class="button button-secondary" id="wrb-clear-decisions">
                <?php _e('Clear Decision History', 'wordpress-review-bot'); ?>
            </button>
        </div>
    </form>

    <!-- Info Section -->
    <div class="wrb-info-box">
        <h3><?php _e('How AI Moderation Works', 'wordpress-review-bot'); ?></h3>
        <p><?php _e('When a new comment is posted, the AI analyzes several factors:', 'wordpress-review-bot'); ?></p>

        <div class="wrb-steps">
            <div class="wrb-step">
                <span class="wrb-step-number">1</span>
                <div class="wrb-step-content">
                    <h4><?php _e('Content Analysis', 'wordpress-review-bot'); ?></h4>
                    <p><?php _e('Analyzes comment text for sentiment, relevance, and appropriateness', 'wordpress-review-bot'); ?></p>
                </div>
            </div>

            <div class="wrb-step">
                <span class="wrb-step-number">2</span>
                <div class="wrb-step-content">
                    <h4><?php _e('Context Check', 'wordpress-review-bot'); ?></h4>
                    <p><?php _e('Considers the post/page context and conversation thread', 'wordpress-review-bot'); ?></p>
                </div>
            </div>

            <div class="wrb-step">
                <span class="wrb-step-number">3</span>
                <div class="wrb-step-content">
                    <h4><?php _e('Decision', 'wordpress-review-bot'); ?></h4>
                    <p><?php _e('Approves, flags for review, or marks as spam based on analysis', 'wordpress-review-bot'); ?></p>
                </div>
            </div>

            <div class="wrb-step">
                <span class="wrb-step-number">4</span>
                <div class="wrb-step-content">
                    <h4><?php _e('Logging', 'wordpress-review-bot'); ?></h4>
                    <p><?php _e('Records decision and reasoning for transparency', 'wordpress-review-bot'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<script>
// Hide reasoning effort unless model starts with 'gpt-5'
document.addEventListener('DOMContentLoaded', function() {
    const modelSelect = document.getElementById('wrb_openai_model');
    const reasoningRow = document.getElementById('wrb_reasoning_effort_row');
    const hint = document.getElementById('wrb_reasoning_effort_hint');

    function updateReasoningVisibility() {
        if (!modelSelect || !reasoningRow) return;
        const val = modelSelect.value || '';
        const isGpt5 = val.startsWith('gpt-5');
        reasoningRow.style.display = isGpt5 ? '' : 'none';
        if (hint) hint.style.display = isGpt5 ? 'none' : 'block';
    }

    modelSelect && modelSelect.addEventListener('change', updateReasoningVisibility);
    updateReasoningVisibility();
});
</script>