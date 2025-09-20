<?php
/**
 * Plugin Name: IPTC TagMaker
 * Plugin URI: https://github.com/MattGHarvey/TagMaker
 * Description: Automatically extracts IPTC keywords from the first image in posts and converts them to WordPress tags, with keyword blocking and substitution features.
 * Version: 1.1.0
 * Author: Matt Harvey
 * License: GPL v2 or later
 * Text Domain: iptc-tagmaker
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IPTC_TAGMAKER_VERSION', '1.0.0');
define('IPTC_TAGMAKER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IPTC_TAGMAKER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IPTC_TAGMAKER_PLUGIN_FILE', __FILE__);

/**
 * Main IPTC TagMaker Class
 */
class IPTC_TagMaker {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Plugin version
     */
    public $version = IPTC_TAGMAKER_VERSION;
    
    /**
     * Get single instance of the class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Activation and deactivation hooks
        register_activation_hook(IPTC_TAGMAKER_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(IPTC_TAGMAKER_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename(IPTC_TAGMAKER_PLUGIN_FILE), array($this, 'add_action_links'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load required classes
        $this->load_classes();
        
        // Initialize components
        $this->init_admin();
        $this->init_processor();
    }
    
    /**
     * Load plugin classes
     */
    private function load_classes() {
        require_once IPTC_TAGMAKER_PLUGIN_DIR . 'includes/class-iptc-keyword-processor.php';
        require_once IPTC_TAGMAKER_PLUGIN_DIR . 'includes/class-iptc-admin.php';
        require_once IPTC_TAGMAKER_PLUGIN_DIR . 'includes/class-iptc-post-handler.php';
    }
    
    /**
     * Initialize admin interface
     */
    private function init_admin() {
        if (is_admin()) {
            new IPTC_TagMaker_Admin();
        }
    }
    
    /**
     * Initialize post processor
     */
    private function init_processor() {
        new IPTC_TagMaker_Post_Handler();
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('iptc-tagmaker', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Add plugin action links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=iptc-tagmaker') . '">' . __('Settings', 'iptc-tagmaker') . '</a>';
        $docs_link = '<a href="https://github.com/MattGHarvey/TagMaker#readme" target="_blank">' . __('Documentation', 'iptc-tagmaker') . '</a>';
        
        array_unshift($links, $settings_link, $docs_link);
        return $links;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Blocked keywords table
        $blocked_keywords_table = $wpdb->prefix . 'iptc_blocked_keywords';
        $sql1 = "CREATE TABLE $blocked_keywords_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY keyword (keyword)
        ) $charset_collate;";
        
        // Keyword substitutions table
        $keyword_substitutions_table = $wpdb->prefix . 'iptc_keyword_substitutions';
        $sql2 = "CREATE TABLE $keyword_substitutions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            original_keyword varchar(255) NOT NULL,
            replacement_keyword varchar(255) NOT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY original_keyword (original_keyword)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        
        // Update version
        update_option('iptc_tagmaker_db_version', $this->version);
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        // No default blocked keywords - users can add their own via the admin interface
        
        // Default settings
        $default_settings = array(
            'auto_process_on_save' => 1,
            'remove_existing_tags' => 1
        );
        
        update_option('iptc_tagmaker_settings', $default_settings);
    }
}

// Initialize the plugin
function iptc_tagmaker() {
    return IPTC_TagMaker::get_instance();
}

// Start the plugin
iptc_tagmaker();