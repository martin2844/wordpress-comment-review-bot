# WordPress Plugin Development Guide for Engineers

A comprehensive guide for experienced developers who are new to the WordPress ecosystem. This document explains how WordPress plugins work, how our development setup functions, and where to make different types of changes.

## 🏗️ Architecture Overview

### Docker Integration & Plugin Sideload

Our setup uses Docker volumes to **sideload** the plugin into WordPress:

```yaml
# docker-compose.yml
wordpress:
  volumes:
    - ./plugin:/var/www/html/wp-content/plugins/wordpress-review-bot
```

**How it works:**
1. **Host directory**: `./plugin` (on your machine)
2. **Container directory**: `/var/www/html/wp-content/plugins/wordpress-review-bot`
3. **Volume mount**: Docker creates a live symlink between these directories
4. **Hot reloading**: Any changes in `./plugin` instantly appear in WordPress

### WordPress File Structure Inside Container

```
/var/www/html/                 # WordPress root
├── wp-admin/                  # WordPress admin interface
├── wp-includes/               # WordPress core functions
├── wp-content/                # User content (plugins, themes)
│   ├── plugins/               # All plugins
│   │   ├── wordpress-review-bot/  # Our plugin (mounted volume)
│   │   │   ├── wordpress-review-bot.php  # Main plugin file
│   │   │   ├── assets/        # CSS/JS files
│   │   │   ├── admin/         # Admin functionality
│   │   │   ├── public/        # Frontend functionality
│   │   │   └── includes/      # Core functions
│   │   └── other-plugins/
│   ├── themes/                # WordPress themes
│   └── uploads/               # User uploads
└── wp-config.php              # WordPress configuration
```

## 🔧 WordPress Plugin System Explained

### Plugin Lifecycle

1. **WordPress Boot**: Loads core WordPress files
2. **Plugin Discovery**: Scans `wp-content/plugins/` for plugin files
3. **Plugin Headers**: Reads plugin metadata from main PHP file
4. **Hook Registration**: Plugins register actions and filters
5. **Plugin Initialization**: WordPress calls registered hooks

### Core Concepts

#### **Hooks: Actions vs Filters**

WordPress runs on a **hook system** - essentially event listeners:

```php
// Actions: Execute code at specific points
add_action('init', 'my_function');           // WordPress initialization
add_action('admin_menu', 'add_admin_page');  // Admin menu creation
add_action('wp_enqueue_scripts', 'load_assets'); // Frontend asset loading

// Filters: Modify data before it's used
add_filter('the_content', 'modify_content'); // Modify post content
add_filter('wp_title', 'modify_page_title'); // Modify page title
```

#### **WordPress Global Objects**

```php
// WordPress database object
global $wpdb;
$results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts");

// Current WordPress query
global $wp_query;
if ($wp_query->is_single()) { /* Single post view */ }

// WordPress post object (in The Loop)
global $post;
$title = $post->post_title;
```

## 📂 Code Organization: Where to Put What

### **Frontend Development** (`/public/`)

**Location**: `plugin/public/` and `src/css/frontend.css`, `src/js/frontend.js`

**What goes here:**
- Shortcodes for displaying content
- Frontend forms and user interactions
- Public-facing API endpoints
- Widget implementations
- Template overrides

**Example Frontend Feature:**
```php
// plugin/public/class-wrb-shortcodes.php
class WRB_Shortcodes {
    public function __construct() {
        add_shortcode('review_form', [$this, 'render_review_form']);
    }

    public function render_review_form($atts) {
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/review-form.php';
        return ob_get_clean();
    }
}
```

### **Backend/Admin Development** (`/admin/`)

**Location**: `plugin/admin/` and `src/css/admin.css`, `src/js/admin.js`

**What goes here:**
- Admin menu pages and settings
- Dashboard widgets
- Custom post types
- User management interfaces
- Plugin configuration options

**Example Admin Page:**
```php
// plugin/admin/class-wrb-admin.php
class WRB_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu_page() {
        add_menu_page(
            'Review Bot Settings',    // Page title
            'Review Bot',             // Menu title
            'manage_options',         // Required capability
            'wrb-settings',           // Menu slug
            [$this, 'render_page'],   // Callback function
            'dashicons-star-filled',  // Icon
            25                        // Position
        );
    }

    public function render_page() {
        include plugin_dir_path(__FILE__) . 'templates/settings-page.php';
    }
}
```

### **Core Functionality** (`/includes/`)

**Location**: `plugin/includes/`

**What goes here:**
- Database interactions
- API integrations
- Business logic
- Utility functions
- Custom post type definitions

**Example Core Class:**
```php
// plugin/includes/class-wrb-review-manager.php
class WRB_Review_Manager {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wrb_reviews';
    }

    public function create_review($data) {
        global $wpdb;
        return $wpdb->insert($this->table_name, $data);
    }

    public function get_reviews($limit = 10) {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT {$limit}"
        );
    }
}
```

## 🎯 WordPress Menu Integration

### **Adding Admin Menu Pages**

WordPress has a hierarchical menu system. Here are the key functions:

```php
// Add top-level menu item
add_menu_page(
    'Page Title',              // Title in browser tab
    'Menu Title',              // Text in admin menu
    'manage_options',          // Required user capability
    'menu-slug',               // Unique identifier
    'callback_function',       // Function to render page
    'dashicons-icon',          // Dashicon identifier
    25                         // Position in menu (lower number = higher position)
);

// Add submenu item
add_submenu_page(
    'parent-slug',             // Parent menu slug
    'Page Title',              // Page title
    'Menu Title',              // Menu text
    'manage_options',          // Required capability
    'submenu-slug',            // Unique identifier
    'callback_function'        // Callback function
);
```

### **Common Menu Positions**

| Position | Typical Location         | Example Use Case |
|----------|-------------------------|------------------|
| 2-5      | Dashboard area          | Analytics, Overview |
| 10-20    | Below Dashboard         | Posts, Media |
| 25-35    | Middle of menu          | Plugins, Users |
| 60-65    | Settings area           | General settings |
| 70-99    | Bottom of menu          | Custom tools |

### **Adding to Existing WordPress Menus**

```php
// Add to "Settings" menu
add_options_page(
    'My Plugin Settings',
    'My Plugin',
    'manage_options',
    'my-plugin-settings',
    'render_settings_page'
);

// Add to "Tools" menu
add_management_page(
    'My Plugin Tools',
    'My Plugin',
    'manage_options',
    'my-plugin-tools',
    'render_tools_page'
);
```

## 🎨 Asset Loading System

### **WordPress Asset Enqueueing**

WordPress uses a dependency management system for CSS/JS:

```php
// Enqueue admin assets
add_action('admin_enqueue_scripts', 'load_admin_assets');
function load_admin_assets($hook) {
    // Only load on specific admin pages
    if ('toplevel_page_wrb-settings' !== $hook) {
        return;
    }

    wp_enqueue_style(
        'wrb-admin-style',           // Handle (unique identifier)
        WRB_PLUGIN_URL . 'assets/css/admin.css',  // File URL
        array('wp-admin'),           // Dependencies
        '1.0.0',                     // Version
        'all'                        // Media
    );

    wp_enqueue_script(
        'wrb-admin-script',
        WRB_PLUGIN_URL . 'assets/js/admin.js',
        array('jquery', 'wp-api'),   // Dependencies
        '1.0.0',
        true                         // Load in footer
    );

    // Pass PHP data to JavaScript
    wp_localize_script('wrb-admin-script', 'wrb_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wrb_nonce'),
        'api_endpoint' => rest_url('wrb/v1/')
    ));
}
```

## 🔀 WordPress Data Flow

### **The WordPress Request Lifecycle**

1. **URL Routing**: WordPress parses the URL and determines what to load
2. **Template Loading**: WordPress selects the appropriate template file
3. **Query Execution**: WordPress queries the database for content
4. **The Loop**: WordPress iterates through posts and renders content
5. **Filter Application**: Content is passed through registered filters
6. **Output**: Final HTML is sent to browser

### **Custom Post Types & Taxonomies**

```php
// Register custom post type
register_post_type('review', array(
    'labels' => array(
        'name' => 'Reviews',
        'singular_name' => 'Review'
    ),
    'public' => true,
    'has_archive' => true,
    'supports' => array('title', 'editor', 'thumbnail'),
    'menu_icon' => 'dashicons-star-filled',
    'show_in_rest' => true,  // Enable Gutenberg editor
));

// Register custom taxonomy
register_taxonomy('review_category', 'review', array(
    'labels' => array(
        'name' => 'Review Categories',
        'singular_name' => 'Review Category'
    ),
    'hierarchical' => true,
    'show_in_rest' => true,
));
```

## 🗄️ Database Integration

### **WordPress Database API (wpdb)**

```php
global $wpdb;

// Custom table name with prefix
$table_name = $wpdb->prefix . 'my_plugin_data';

// Insert data
$wpdb->insert(
    $table_name,
    array('name' => 'John', 'email' => 'john@example.com'),
    array('%s', '%s')  // Format strings
);

// Get single row
$result = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $user_id)
);

// Get multiple results
$results = $wpdb->get_results(
    "SELECT * FROM $table_name WHERE status = 'active'"
);

// Update data
$wpdb->update(
    $table_name,
    array('status' => 'inactive'),
    array('id' => $user_id),
    array('%s'),
    array('%d')
);
```

### **Options API for Plugin Settings**

```php
// Save plugin settings
update_option('wrb_settings', array(
    'api_key' => 'your-api-key',
    'auto_publish' => true,
    'max_reviews' => 50
));

// Get plugin settings
$settings = get_option('wrb_settings', array());  // Default empty array

// Delete plugin settings
delete_option('wrb_settings');
```

## 🔄 AJAX & REST API Integration

### **WordPress AJAX (Traditional)**

```php
// Register AJAX action for logged-in users
add_action('wp_ajax_wrb_save_review', 'wrb_save_review');
add_action('wp_ajax_nopriv_wrb_save_review', 'wrb_save_review'); // For non-logged users

function wrb_save_review() {
    // Security check
    if (!wp_verify_nonce($_POST['nonce'], 'wrb_nonce')) {
        wp_die('Security check failed');
    }

    // Process data
    $review_data = array(
        'title' => sanitize_text_field($_POST['title']),
        'content' => sanitize_textarea_field($_POST['content']),
        'rating' => intval($_POST['rating'])
    );

    // Save to database
    // ... database logic here ...

    // Return response
    wp_send_json_success(array('message' => 'Review saved successfully'));
}
```

### **WordPress REST API (Modern)**

```php
// Register REST API route
add_action('rest_api_init', 'wrb_register_api_routes');
function wrb_register_api_routes() {
    register_rest_route('wrb/v1', '/reviews', array(
        'methods' => 'GET',
        'callback' => 'wrb_get_reviews',
        'permission_callback' => function() {
            return current_user_can('read');
        }
    ));

    register_rest_route('wrb/v1', '/reviews', array(
        'methods' => 'POST',
        'callback' => 'wrb_create_review',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
}

function wrb_get_reviews(WP_REST_Request $request) {
    $params = $request->get_params();
    // ... logic to get reviews ...
    return new WP_REST_Response($reviews, 200);
}
```

## 🚀 Development Workflow

### **Making Changes**

1. **PHP Changes**: Edit files in `plugin/` - changes are immediate
2. **CSS Changes**: Edit `src/css/input.css` - auto-compiles with Tailwind
3. **JavaScript Changes**: Edit `src/js/` - auto-bundles with Vite
4. **Database Changes**: Use phpMyAdmin at http://localhost:8081

### **Debugging WordPress**

```php
// Enable WordPress debug mode (already enabled in our setup)
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);  // Logs to wp-content/debug.log
define('WP_DEBUG_DISPLAY', false);

// WordPress query debugging
add_action('wp_footer', 'debug_queries');
function debug_queries() {
    global $wpdb;
    echo "<!-- Queries: " . $wpdb->num_queries . " -->";
    echo "<!-- Query Time: " . $wpdb->num_queries . "s -->";
}

// Print variables safely
function wrb_debug($var, $label = 'DEBUG') {
    if (WP_DEBUG) {
        error_log(print_r($var, true));
        if (current_user_can('administrator')) {
            echo '<pre>' . esc_html(print_r($var, true)) . '</pre>';
        }
    }
}
```

### **WordPress CLI Commands**

```bash
# WordPress CLI inside Docker
docker-compose exec wordpress wp <command>

# Common commands
npm run wp:cli plugin list                    # List all plugins
npm run wp:cli plugin activate my-plugin      # Activate plugin
npm run wp:cli post list                      # List posts
npm run wp:cli user list                      # List users
npm run wp:cli option get wrb_settings       # Get plugin options
```

## 📝 Best Practices for Engineers

### **Security**
```php
// Always sanitize input
$text = sanitize_text_field($_POST['text']);
$html = wp_kses_post($_POST['html']);
$int = intval($_POST['number']);

// Always escape output
echo esc_html($text);
echo esc_url($url);
echo wp_kses_post($html);

// Use nonces for security
wp_nonce_field('wrb_action', 'wrb_nonce');
wp_verify_nonce($_POST['wrb_nonce'], 'wrb_action');

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die('Permission denied');
}
```

### **Performance**
```php
// Use WordPress caching
$transient_key = 'wrb_expensive_query';
$results = get_transient($transient_key);
if (false === $results) {
    $results = perform_expensive_query();
    set_transient($transient_key, $results, HOUR_IN_SECONDS);
}

// Only load scripts when needed
add_action('admin_enqueue_scripts', 'conditional_scripts');
function conditional_scripts($hook) {
    if ('my-plugin_page' !== $hook) return;
    // Load scripts here
}
```

### **Internationalization**
```php
// Make strings translatable
__('Text to translate', 'textdomain');
_e('Text to echo', 'textdomain');

// Handle variables
printf(
    __('Hello %s, welcome to %s', 'textdomain'),
    $user_name,
    $site_name
);
```

This guide should provide everything you need to understand how WordPress plugins work and how to effectively develop within this ecosystem. The key is understanding the hook system and WordPress's file organization structure.

## Async Comment Moderation: Robust Background Processing (2025-10-30 Revision)

### What Was Fixed
- The plugin now supports truly robust, non-blocking auto moderation of new comments using WordPress's full async event stack 1 fallback order.
- Critical error and reliability fixes were made so that moderation **never** blocks user comment submission and works in dev (Docker), prod, and host environments.

### How It Now Works
1. **Primary Path: WordPress Cron**
    - Moderation job is scheduled using `wp_schedule_single_event`.
    - If WP-Cron (HTTP loopback to `/wp-cron.php`) is working, the event fires in background (default for most hosts).
2. **Loopback and AJAX Fallbacks**
    - If main cron approach fails, plugin will attempt triggering via common local fallback HTTP endpoints: `home_url`, `localhost`, `127.0.0.1`.
    - If those fail, it attempts to use core `spawn_cron()` internally on supported hosts.
    - All results, codes, and errors are logged to `wp-content/debug.log` for transparency.
3. **Shutdown Handler (Last Resort)**
    - If event cannot be processed by any of the above, a PHP shutdown handler attempts to start moderation a few seconds after the HTTP response is sent back to the user (doesn't block user experience).
    - The job is run using loaded context, and result is also logged.
4. **Admin Dashboard Notice**
    - If all async attempts fail, a transient is set. An error notice appears in the WP admin, showing a clear message and offering a button for the admin to manually trigger moderation of any unprocessed comments (never leaves you "in the dark").
    - The notice disappears after successful background processing.

### Logging and Debugging
- Every scheduling, fallback attempt, and error is clearly logged (prefixed with `WRB:`) with the exact comment ID, timing, and status.
- Log details allow rapid diagnosis of which step (event, HTTP, AJAX, or shutdown) worked or failed.
- All parameters, environment details (user, URLs, REMOTE_ADDR), and even POST/GET data are included for full traceability in debugging.

### No-Block Guarantee
- **User comment posting is NEVER blocked** for AI moderation, regardless of environment, network issues, or PHP bugs.
- All fallback mechanisms are fully async with the only possible user-block being temporary server resource exhaustion (never design, just host behavior).

### Manual Moderation Trigger (If Needed)
- If async fails, a button appears in admin for manual trigger 5synchronizes pending jobs with a single click without users being impacted.

### Developer Tips
- For Docker/local dev: recommended to add a real cron job or system event for production polish, but not required for plugin operation.
- Use `wp-content/debug.log` to monitor the exact moderation job fate per comment.
- If developing further, see `class-wrb-comment-manager.php` and search for `WRB:` logs for full traceability.

---
**This approach now exceeds typical plugin reliability for async jobs in the WordPress ecosystem and should be safe for use in all modern hosting, local, and CI environments.**