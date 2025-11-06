<?php
/**
 * Plugin Name:       WordPress Comment Review Bot
 * Plugin URI:        https://github.com/martin2844
 * Description:       AI-powered comment moderation for WordPress using OpenAI. Automatically approve, reject, or mark comments as spam with intelligent AI analysis.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Martin Chammah
 * Author URI:        https://github.com/martin2844
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       wordpress-review-bot
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Define plugin constants
 */
define( 'WRB_VERSION', '1.0.0' );
define( 'WRB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WRB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load required files
 */
require_once WRB_PLUGIN_DIR . 'includes/class-wrb-comment-manager.php';
require_once WRB_PLUGIN_DIR . 'admin/class-wrb-admin.php';

/**
 * Main plugin class
 */
class WordPress_Review_Bot {

	/**
	 * Comment manager instance
	 */
	private $comment_manager;

	/**
	 * Admin instance
	 */
	private $admin;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->load_dependencies();
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
	}

	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		$this->comment_manager = new WRB_Comment_Manager();
		$this->admin = new WRB_Admin();
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Load text domain
		load_plugin_textdomain( 'wordpress-review-bot', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Register activation hook
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Add AJAX handlers for settings
		add_action( 'wp_ajax_wrb_clear_cache', array( $this, 'ajax_clear_cache' ) );
		add_action( 'wp_ajax_wrb_reset_settings', array( $this, 'ajax_reset_settings' ) );
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Set default settings for AI moderation
		$default_options = array(
			'openai_api_key' => '',
			'openai_model' => 'gpt-5-mini',
			'auto_moderation_enabled' => false,
			'confidence_threshold' => 0.7,
			'max_tokens' => 800,
			'temperature' => 0.1,
			'webhook_url' => '',
			'moderate_post_comments' => true,
			'moderate_page_comments' => true,
			'moderate_product_comments' => false,
			'log_decisions' => true
		);

		add_option( 'wrb_options', $default_options );
		add_option( 'wrb_first_activation', current_time( 'mysql' ) );

		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on our plugin pages
		if ( strpos( $hook, 'wrb-' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'wrb-admin-style',
			WRB_PLUGIN_URL . 'assets/css/admin.css',
			array( 'wp-admin' ),
			WRB_VERSION
		);

		wp_enqueue_script(
			'wrb-admin-script',
			WRB_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-ajax-response' ),
			WRB_VERSION,
			true
		);

		// Localize script for AJAX
		wp_localize_script( 'wrb-admin-script', 'wrb_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wrb_comment_action' ),
			'strings' => array(
				'confirm_bulk' => __( 'Are you sure you want to perform this action on selected comments?', 'wordpress-review-bot' ),
				'no_comments_selected' => __( 'Please select at least one comment to perform this action.', 'wordpress-review-bot' ),
				'loading' => __( 'Loading...', 'wordpress-review-bot' )
			)
		));
	}

	/**
	 * Enqueue frontend scripts and styles
	 */
	public function enqueue_frontend_scripts() {
		wp_enqueue_style(
			'wrb-frontend-style',
			WRB_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			WRB_VERSION
		);

		wp_enqueue_script(
			'wrb-frontend-script',
			WRB_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			WRB_VERSION,
			true
		);
	}

	/**
	 * AJAX handler for clearing cache
	 */
	public function ajax_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'wordpress-review-bot' )
			));
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wrb_comment_action' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed.', 'wordpress-review-bot' )
			));
		}

		// Clear any transients or cached data
		wp_cache_flush();

		wp_send_json_success( array(
			'message' => __( 'Cache cleared successfully!', 'wordpress-review-bot' )
		));
	}

	/**
	 * AJAX handler for resetting settings
	 */
	public function ajax_reset_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'wordpress-review-bot' )
			));
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wrb_comment_action' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed.', 'wordpress-review-bot' )
			));
		}

		// Reset to default settings
		$default_options = array(
			'comments_per_page' => 25,
			'auto_spam_detection' => true,
			'show_author_gravatars' => true,
			'show_content_preview' => true,
			'preview_length' => 100,
			'email_notifications' => false,
			'notification_threshold' => 5,
			'auto_refresh' => false,
			'enable_keyboard_shortcuts' => true
		);

		update_option( 'wrb_options', $default_options );

		wp_send_json_success( array(
			'message' => __( 'Settings reset successfully!', 'wordpress-review-bot' )
		));
	}

	/**
	 * Get plugin instance
	 */
	public static function get_instance() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}

// Initialize the plugin
WordPress_Review_Bot::get_instance();