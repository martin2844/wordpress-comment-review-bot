<?php
/**
 * Admin Class
 * Handles admin menu pages and functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class WRB_Admin {

    /**
     * Comment manager instance
     */
    private $comment_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->comment_manager = new WRB_Comment_Manager();

        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }

    /**
     * Add admin menu pages
     */
    public function add_menu_pages() {
        // Main menu page
        add_menu_page(
            __('Review Bot', 'wordpress-review-bot'),           // Page title
            __('Review Bot', 'wordpress-review-bot'),           // Menu title
            'moderate_comments',                                 // Capability required
            'wrb-dashboard',                                     // Menu slug
            array($this, 'render_dashboard_page'),              // Callback function
            'dashicons-star-filled',                             // Icon
            25                                                   // Position
        );

        // Submenu: AI Decisions
        add_submenu_page(
            'wrb-dashboard',                                     // Parent slug
            __('AI Decisions', 'wordpress-review-bot'),         // Page title
            __('AI Decisions', 'wordpress-review-bot'),         // Menu title
            'moderate_comments',                                 // Capability
            'wrb-decisions',                                     // Menu slug
            array($this, 'render_decisions_page')               // Callback
        );

        // Submenu: Settings
        add_submenu_page(
            'wrb-dashboard',
            __('Settings', 'wordpress-review-bot'),
            __('Settings', 'wordpress-review-bot'),
            'manage_options',
            'wrb-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('wrb_settings', 'wrb_options', array($this, 'sanitize_openai_settings'));

        // We're using custom templates, so no need for WordPress settings sections
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'wrb-') === false) {
            return;
        }

        wp_enqueue_style(
            'wrb-admin-style',
            WRB_PLUGIN_URL . 'assets/css/admin.css',
            array('wp-admin'),
            WRB_VERSION
        );

        wp_enqueue_script(
            'wrb-admin-script',
            WRB_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-ajax-response'),
            WRB_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('wrb-admin-script', 'wrb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wrb_comment_action'),
            'strings' => array(
                'confirm_bulk' => __('Are you sure you want to perform this action on selected comments?', 'wordpress-review-bot'),
                'no_comments_selected' => __('Please select at least one comment to perform this action.', 'wordpress-review-bot'),
                'loading' => __('Loading...', 'wordpress-review-bot')
            )
        ));
    }

    /**
     * Render main dashboard page
     */
    public function render_dashboard_page() {
        $stats = $this->comment_manager->get_comment_stats();
        $recent_pending = $this->comment_manager->get_pending_comments(5);

        include plugin_dir_path(__FILE__) . 'templates/dashboard-page.php';
    }

    /**
     * Render AI decisions page
     */
    public function render_decisions_page() {
        include plugin_dir_path(__FILE__) . 'templates/decisions-page.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include plugin_dir_path(__FILE__) . 'templates/settings-page.php';
    }

    /**
     * Sanitize OpenAI settings before saving
     */
    public function sanitize_openai_settings($input) {
        $sanitized = array();

        // OpenAI API Key
        if (isset($input['openai_api_key'])) {
            $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        }

        // AI Model
        if (isset($input['openai_model'])) {
            $allowed_models = array(
                'gpt-5', 'gpt-5-mini', 'gpt-5-nano',
                'gpt-4o', 'gpt-4.1-nano',
                'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo'
            );
            $sanitized['openai_model'] = in_array($input['openai_model'], $allowed_models) ? $input['openai_model'] : 'gpt-5-mini';
        }

        // Auto-moderation enabled
        if (isset($input['auto_moderation_enabled'])) {
            $sanitized['auto_moderation_enabled'] = boolval($input['auto_moderation_enabled']);
        }

        // Confidence threshold
        if (isset($input['confidence_threshold'])) {
            $sanitized['confidence_threshold'] = max(0.5, min(1.0, floatval($input['confidence_threshold'])));
        }

        // Max tokens
        if (isset($input['max_tokens'])) {
            $sanitized['max_tokens'] = max(50, min(1000, intval($input['max_tokens'])));
        }

        // Temperature
        if (isset($input['temperature'])) {
            $sanitized['temperature'] = max(0, min(1.0, floatval($input['temperature'])));
        }

        // Webhook URL
        if (isset($input['webhook_url'])) {
            $sanitized['webhook_url'] = esc_url_raw($input['webhook_url']);
        }

        // Boolean settings
        $boolean_settings = array(
            'moderate_post_comments',
            'moderate_page_comments',
            'moderate_product_comments',
            'log_decisions'
        );

        foreach ($boolean_settings as $setting) {
            if (isset($input[$setting])) {
                $sanitized[$setting] = boolval($input[$setting]);
            }
        }

        return $sanitized;
    }

    /**
     * Add admin notices
     */
    public function add_admin_notices() {
        if (isset($_GET['wrb_message'])) {
            $message = sanitize_text_field($_GET['wrb_message']);
            $type = isset($_GET['wrb_type']) ? sanitize_text_field($_GET['wrb_type']) : 'success';

            $class = $type === 'success' ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    public function admin_notices() {
        if ( get_transient( 'wrb_async_fallback_needed' ) ) {
            $pending_url = admin_url( 'admin.php?page=wrb-decisions&wrb_process_pending=1' );
            echo '<div class="notice notice-error"><p>';
            echo __( 'Automatic background moderation failed to run via wp-cron/AJAX. ', 'wordpress-review-bot' );
            echo '<a href="' . esc_url( $pending_url ) . '" class="button button-primary">' . __( 'Process pending AI moderation now', 'wordpress-review-bot' ) . '</a>';
            echo '</p></div>';
        }
    }
}