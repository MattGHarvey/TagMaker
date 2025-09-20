<?php
/**
 * IPTC TagMaker Uninstall Script
 * 
 * This file is executed when the plugin is deleted from WordPress admin.
 * It cleans up all plugin data including database tables and options.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete database tables
global $wpdb;

$blocked_keywords_table = $wpdb->prefix . 'iptc_blocked_keywords';
$substitutions_table = $wpdb->prefix . 'iptc_keyword_substitutions';

$wpdb->query("DROP TABLE IF EXISTS $blocked_keywords_table");
$wpdb->query("DROP TABLE IF EXISTS $substitutions_table");

// Delete plugin options
delete_option('iptc_tagmaker_settings');
delete_option('iptc_tagmaker_db_version');

// Clean up any transients (cached data)
delete_transient('iptc_tagmaker_cache');

// Remove any user meta related to the plugin
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'iptc_tagmaker_%'");

// Remove post meta created by the plugin
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_iptc_last_processed_image', '_iptc_generated_tags', 'fullKW')");

// Clear any object cache
wp_cache_flush();