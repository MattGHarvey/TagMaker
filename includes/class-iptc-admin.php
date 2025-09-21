<?php
/**
 * IPTC Admin Class
 * 
 * Handles admin interface for managing blocked keywords and substitutions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class IPTC_TagMaker_Admin {
    
    /**
     * Keyword processor instance
     */
    private $processor;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->processor = new IPTC_TagMaker_Keyword_Processor();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_iptc_add_blocked_keyword', array($this, 'ajax_add_blocked_keyword'));
        add_action('wp_ajax_iptc_remove_blocked_keyword', array($this, 'ajax_remove_blocked_keyword'));
        add_action('wp_ajax_iptc_clear_all_blocked_keywords', array($this, 'ajax_clear_all_blocked_keywords'));
        add_action('wp_ajax_iptc_add_keyword_substitution', array($this, 'ajax_add_keyword_substitution'));
        add_action('wp_ajax_iptc_edit_keyword_substitution', array($this, 'ajax_edit_keyword_substitution'));
        add_action('wp_ajax_iptc_remove_keyword_substitution', array($this, 'ajax_remove_keyword_substitution'));
        add_action('wp_ajax_iptc_clear_all_keyword_substitutions', array($this, 'ajax_clear_all_keyword_substitutions'));
        add_action('wp_ajax_iptc_bulk_import_blocked_keywords', array($this, 'ajax_bulk_import_blocked_keywords'));
        add_action('wp_ajax_iptc_bulk_import_substitutions', array($this, 'ajax_bulk_import_substitutions'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('IPTC TagMaker Settings', 'iptc-tagmaker'),
            __('IPTC TagMaker', 'iptc-tagmaker'),
            'manage_options',
            'iptc-tagmaker',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('iptc_tagmaker_settings', 'iptc_tagmaker_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
    }
    
    /**
     * Sanitize settings
     * 
     * @param array $input Input data
     * @return array Sanitized data
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['auto_process_on_save'] = !empty($input['auto_process_on_save']) ? 1 : 0;
        $sanitized['remove_existing_tags'] = !empty($input['remove_existing_tags']) ? 1 : 0;
        
        return $sanitized;
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Page hook
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_iptc-tagmaker') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_style('iptc-tagmaker-admin', IPTC_TAGMAKER_PLUGIN_URL . 'assets/admin.css', array(), IPTC_TAGMAKER_VERSION);
        wp_enqueue_script('iptc-tagmaker-admin', IPTC_TAGMAKER_PLUGIN_URL . 'assets/admin.js', array('jquery'), IPTC_TAGMAKER_VERSION, true);
        
        wp_localize_script('iptc-tagmaker-admin', 'iptcTagMaker', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iptc_tagmaker_admin'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this item?', 'iptc-tagmaker'),
                'addingKeyword' => __('Adding...', 'iptc-tagmaker'),
                'removingKeyword' => __('Removing...', 'iptc-tagmaker'),
                'savingChanges' => __('Saving...', 'iptc-tagmaker'),
                'errorOccurred' => __('An error occurred. Please try again.', 'iptc-tagmaker'),
                'keywordRequired' => __('Keyword is required.', 'iptc-tagmaker'),
                'substitutionRequired' => __('Both original and replacement keywords are required.', 'iptc-tagmaker')
            )
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $settings = get_option('iptc_tagmaker_settings', array());
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div id="iptc-admin-notifications"></div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('iptc_tagmaker_settings');
                do_settings_sections('iptc_tagmaker_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Auto-process on Post Save', 'iptc-tagmaker'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="iptc_tagmaker_settings[auto_process_on_save]" value="1" <?php checked(!empty($settings['auto_process_on_save'])); ?> />
                                <?php _e('Automatically process IPTC keywords when posts are saved', 'iptc-tagmaker'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Remove Existing Tags', 'iptc-tagmaker'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="iptc_tagmaker_settings[remove_existing_tags]" value="1" <?php checked(!empty($settings['remove_existing_tags'])); ?> />
                                <?php _e('Remove existing tags before adding new ones from IPTC keywords', 'iptc-tagmaker'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr />
            
            <!-- Blocked Keywords Section -->
            <div class="iptc-admin-section">
                <h2><?php _e('Blocked Keywords', 'iptc-tagmaker'); ?></h2>
                <p><?php _e('Keywords in this list will be ignored and not converted to tags.', 'iptc-tagmaker'); ?></p>
                
                <div class="iptc-add-form">
                    <input type="text" id="new-blocked-keyword" placeholder="<?php esc_attr_e('Enter keyword to block...', 'iptc-tagmaker'); ?>" />
                    <button type="button" id="add-blocked-keyword" class="button"><?php _e('Add Blocked Keyword', 'iptc-tagmaker'); ?></button>
                    <button type="button" id="clear-all-blocked-keywords" class="button button-secondary" style="margin-left: 10px;"><?php _e('Clear All', 'iptc-tagmaker'); ?></button>
                    <button type="button" id="show-bulk-blocked" class="button button-secondary" style="margin-left: 10px;"><?php _e('Show Bulk Import', 'iptc-tagmaker'); ?></button>
                </div>
                
                <div class="iptc-bulk-import" style="margin-bottom: 20px;">
                    <h4><?php _e('Bulk Import Blocked Keywords', 'iptc-tagmaker'); ?></h4>
                    <p><?php _e('Paste comma-separated keywords below to import multiple blocked keywords at once:', 'iptc-tagmaker'); ?></p>
                    <textarea id="bulk-blocked-keywords" rows="4" cols="50" placeholder="<?php esc_attr_e('keyword1, keyword2, keyword3, ...', 'iptc-tagmaker'); ?>" style="width: 100%; max-width: 600px;"></textarea>
                    <br>
                    <button type="button" id="import-blocked-keywords" class="button button-primary" style="margin-top: 10px;"><?php _e('Import Keywords', 'iptc-tagmaker'); ?></button>
                    <button type="button" id="toggle-bulk-blocked" class="button button-secondary" style="margin-top: 10px; margin-left: 10px;"><?php _e('Hide Bulk Import', 'iptc-tagmaker'); ?></button>
                </div>
                
                <div id="blocked-keywords-list" class="iptc-keywords-list">
                    <?php $this->render_blocked_keywords_list(); ?>
                </div>
            </div>
            
            <!-- Keyword Substitutions Section -->
            <div class="iptc-admin-section">
                <h2><?php _e('Keyword Substitutions', 'iptc-tagmaker'); ?></h2>
                <p><?php _e('Replace specific keywords with different ones when processing.', 'iptc-tagmaker'); ?></p>
                
                <div class="iptc-add-form">
                    <input type="text" id="original-keyword" placeholder="<?php esc_attr_e('Original keyword...', 'iptc-tagmaker'); ?>" />
                    <input type="text" id="replacement-keyword" placeholder="<?php esc_attr_e('Replacement keyword...', 'iptc-tagmaker'); ?>" />
                    <button type="button" id="add-keyword-substitution" class="button"><?php _e('Add Substitution', 'iptc-tagmaker'); ?></button>
                    <button type="button" id="clear-all-keyword-substitutions" class="button button-secondary" style="margin-left: 10px;"><?php _e('Clear All', 'iptc-tagmaker'); ?></button>
                    <button type="button" id="show-bulk-substitutions" class="button button-secondary" style="margin-left: 10px;"><?php _e('Show Bulk Import', 'iptc-tagmaker'); ?></button>
                </div>
                
                <!-- Edit Substitution Form (hidden by default) -->
                <div id="edit-substitution-form" class="iptc-edit-form" style="display: none; background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin: 20px 0;">
                    <h4><?php _e('Edit Keyword Substitution', 'iptc-tagmaker'); ?></h4>
                    <input type="hidden" id="edit-substitution-original-value" />
                    <input type="text" id="edit-original-keyword" placeholder="<?php esc_attr_e('Original keyword...', 'iptc-tagmaker'); ?>" />
                    <input type="text" id="edit-replacement-keyword" placeholder="<?php esc_attr_e('Replacement keyword...', 'iptc-tagmaker'); ?>" />
                    <button type="button" id="save-keyword-substitution-edit" class="button button-primary"><?php _e('Save Changes', 'iptc-tagmaker'); ?></button>
                    <button type="button" id="cancel-keyword-substitution-edit" class="button button-secondary" style="margin-left: 10px;"><?php _e('Cancel', 'iptc-tagmaker'); ?></button>
                </div>
                
                <div class="iptc-bulk-import" style="margin-bottom: 20px;">
                    <h4><?php _e('Bulk Import Keyword Substitutions', 'iptc-tagmaker'); ?></h4>
                    <p><?php _e('Paste substitution rules below. Format: "original1 => replacement1, original2 => replacement2" or one per line:', 'iptc-tagmaker'); ?></p>
                    <textarea id="bulk-substitutions" rows="4" cols="50" placeholder="<?php esc_attr_e('old keyword => new keyword, another old => another new', 'iptc-tagmaker'); ?>" style="width: 100%; max-width: 600px;"></textarea>
                    <br>
                    <button type="button" id="import-substitutions" class="button button-primary" style="margin-top: 10px;"><?php _e('Import Substitutions', 'iptc-tagmaker'); ?></button>
                    <button type="button" id="toggle-bulk-substitutions" class="button button-secondary" style="margin-top: 10px; margin-left: 10px;"><?php _e('Hide Bulk Import', 'iptc-tagmaker'); ?></button>
                </div>
                
                <div id="keyword-substitutions-list" class="iptc-substitutions-list">
                    <?php $this->render_keyword_substitutions_list(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render blocked keywords list
     */
    private function render_blocked_keywords_list() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iptc_blocked_keywords';
        
        $keywords = $wpdb->get_results("SELECT * FROM $table_name ORDER BY keyword ASC");
        
        if (empty($keywords)) {
            echo '<p><em>' . __('No blocked keywords yet.', 'iptc-tagmaker') . '</em></p>';
            return;
        }
        
        echo '<ul>';
        foreach ($keywords as $keyword) {
            echo '<li>';
            echo '<span class="keyword">' . esc_html($keyword->keyword) . '</span>';
            echo '<button type="button" class="button button-small remove-blocked-keyword" data-keyword="' . esc_attr($keyword->keyword) . '">';
            echo __('Remove', 'iptc-tagmaker');
            echo '</button>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Render keyword substitutions list
     */
    private function render_keyword_substitutions_list() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iptc_keyword_substitutions';
        
        $substitutions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY original_keyword ASC");
        
        if (empty($substitutions)) {
            echo '<p><em>' . __('No keyword substitutions yet.', 'iptc-tagmaker') . '</em></p>';
            return;
        }
        
        echo '<table class="widefat">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Original Keyword', 'iptc-tagmaker') . '</th>';
        echo '<th>' . __('Replacement Keyword', 'iptc-tagmaker') . '</th>';
        echo '<th>' . __('Actions', 'iptc-tagmaker') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($substitutions as $substitution) {
            echo '<tr>';
            echo '<td>' . esc_html($substitution->original_keyword) . '</td>';
            echo '<td>' . esc_html($substitution->replacement_keyword) . '</td>';
            echo '<td>';
            echo '<button type="button" class="button button-small edit-keyword-substitution" data-original="' . esc_attr($substitution->original_keyword) . '" data-replacement="' . esc_attr($substitution->replacement_keyword) . '">';
            echo __('Edit', 'iptc-tagmaker');
            echo '</button> ';
            echo '<button type="button" class="button button-small remove-keyword-substitution" data-original="' . esc_attr($substitution->original_keyword) . '">';
            echo __('Remove', 'iptc-tagmaker');
            echo '</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    /**
     * AJAX handler to add blocked keyword
     */
    public function ajax_add_blocked_keyword() {
        if (!wp_verify_nonce($_POST['nonce'], 'iptc_tagmaker_admin')) {
            wp_send_json_error(__('Security check failed', 'iptc-tagmaker'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'iptc-tagmaker'));
        }
        
        $keyword = sanitize_text_field($_POST['keyword']);
        
        if (empty($keyword)) {
            wp_send_json_error(__('Keyword is required.', 'iptc-tagmaker'));
        }
        
        $success = $this->processor->add_blocked_keyword($keyword);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Blocked keyword added successfully.', 'iptc-tagmaker'),
                'html' => $this->get_blocked_keywords_list_html()
            ));
        } else {
            wp_send_json_error(__('Failed to add blocked keyword. It may already exist.', 'iptc-tagmaker'));
        }
    }
    
    /**
     * AJAX handler to remove blocked keyword
     */
    public function ajax_remove_blocked_keyword() {
        if (!wp_verify_nonce($_POST['nonce'], 'iptc_tagmaker_admin')) {
            wp_send_json_error(__('Security check failed', 'iptc-tagmaker'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'iptc-tagmaker'));
        }
        
        $keyword = wp_unslash($_POST['keyword']); // Preserve exact characters
        
        // Additional cleaning to match what we do during import
        $keyword = html_entity_decode($keyword, ENT_QUOTES, 'UTF-8');
        
        $success = $this->processor->remove_blocked_keyword($keyword);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Blocked keyword removed successfully.', 'iptc-tagmaker'),
                'html' => $this->get_blocked_keywords_list_html()
            ));
        } else {
            wp_send_json_error(__('Failed to remove blocked keyword. Check that the keyword exists exactly as shown.', 'iptc-tagmaker'));
        }
    }
    
    /**
     * AJAX handler to add keyword substitution
     */
    public function ajax_add_keyword_substitution() {
        if (!wp_verify_nonce($_POST['nonce'], 'iptc_tagmaker_admin')) {
            wp_send_json_error(__('Security check failed', 'iptc-tagmaker'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'iptc-tagmaker'));
        }
        
        $original = $this->clean_keyword($_POST['original']);
        $replacement = $this->clean_keyword($_POST['replacement']);
        
        if (empty($original) || empty($replacement)) {
            wp_send_json_error(__('Both original and replacement keywords are required.', 'iptc-tagmaker'));
        }
        
        $success = $this->processor->add_keyword_substitution($original, $replacement);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Keyword substitution added successfully.', 'iptc-tagmaker'),
                'html' => $this->get_keyword_substitutions_list_html()
            ));
        } else {
            wp_send_json_error(__('Failed to add keyword substitution.', 'iptc-tagmaker'));
        }
    }
    
    /**
     * AJAX handler to edit keyword substitution
     */
    public function ajax_edit_keyword_substitution() {
        if (!wp_verify_nonce($_POST['nonce'], 'iptc_tagmaker_admin')) {
            wp_send_json_error(__('Security check failed', 'iptc-tagmaker'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'iptc-tagmaker'));
        }
        
        $old_original = $this->clean_keyword($_POST['old_original']);
        $new_original = $this->clean_keyword($_POST['new_original']);
        $new_replacement = $this->clean_keyword($_POST['new_replacement']);
        
        if (empty($new_original) || empty($new_replacement)) {
            wp_send_json_error(__('Both original and replacement keywords are required.', 'iptc-tagmaker'));
        }
        
        // Remove the old substitution
        $remove_success = $this->processor->remove_keyword_substitution($old_original);
        
        if (!$remove_success) {
            wp_send_json_error(__('Failed to remove old keyword substitution.', 'iptc-tagmaker'));
        }
        
        // Add the new substitution
        $add_success = $this->processor->add_keyword_substitution($new_original, $new_replacement);
        
        if ($add_success) {
            wp_send_json_success(array(
                'message' => __('Keyword substitution updated successfully.', 'iptc-tagmaker'),
                'html' => $this->get_keyword_substitutions_list_html()
            ));
        } else {
            wp_send_json_error(__('Failed to update keyword substitution.', 'iptc-tagmaker'));
        }
    }
    
    /**
     * AJAX handler to remove keyword substitution
     */
    public function ajax_remove_keyword_substitution() {
        if (!wp_verify_nonce($_POST['nonce'], 'iptc_tagmaker_admin')) {
            wp_send_json_error(__('Security check failed', 'iptc-tagmaker'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'iptc-tagmaker'));
        }
        
        $original = sanitize_text_field($_POST['original']);
        
        $success = $this->processor->remove_keyword_substitution($original);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Keyword substitution removed successfully.', 'iptc-tagmaker'),
                'html' => $this->get_keyword_substitutions_list_html()
            ));
        } else {
            wp_send_json_error(__('Failed to remove keyword substitution.', 'iptc-tagmaker'));
        }
    }
    
    /**
     * AJAX handler to clear all blocked keywords
     */
    public function ajax_clear_all_blocked_keywords() {
        if (!wp_verify_nonce($_POST['nonce'], 'iptc_tagmaker_admin')) {
            wp_send_json_error(__('Security check failed', 'iptc-tagmaker'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'iptc-tagmaker'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'iptc_blocked_keywords';
        
        $result = $wpdb->query("TRUNCATE TABLE $table_name");
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('All blocked keywords cleared successfully.', 'iptc-tagmaker'),
                'html' => $this->get_blocked_keywords_list_html()
            ));
        } else {
            wp_send_json_error(__('Failed to clear blocked keywords.', 'iptc-tagmaker'));
        }
    }
    
    /**
     * AJAX handler to clear all keyword substitutions
     */
    public function ajax_clear_all_keyword_substitutions() {
        if (!wp_verify_nonce($_POST['nonce'], 'iptc_tagmaker_admin')) {
            wp_send_json_error(__('Security check failed', 'iptc-tagmaker'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'iptc-tagmaker'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'iptc_keyword_substitutions';
        
        $result = $wpdb->query("TRUNCATE TABLE $table_name");
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('All keyword substitutions cleared successfully.', 'iptc-tagmaker'),
                'html' => $this->get_keyword_substitutions_list_html()
            ));
        } else {
            wp_send_json_error(__('Failed to clear keyword substitutions.', 'iptc-tagmaker'));
        }
    }
    
    /**
     * AJAX handler to bulk import blocked keywords
     */
    public function ajax_bulk_import_blocked_keywords() {
        if (!wp_verify_nonce($_POST['nonce'], 'iptc_tagmaker_admin')) {
            wp_send_json_error(__('Security check failed', 'iptc-tagmaker'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'iptc-tagmaker'));
        }
        
        $keywords_text = sanitize_textarea_field($_POST['keywords']);
        
        if (empty($keywords_text)) {
            wp_send_json_error(__('No keywords provided.', 'iptc-tagmaker'));
        }
        
        // Parse keywords - split by comma and clean up
        $keywords = array_map('trim', explode(',', $keywords_text));
        $keywords = array_filter($keywords); // Remove empty values
        
        if (empty($keywords)) {
            wp_send_json_error(__('No valid keywords found.', 'iptc-tagmaker'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'iptc_blocked_keywords';
        $imported_count = 0;
        $duplicate_count = 0;
        
        foreach ($keywords as $keyword) {
            $keyword = $this->clean_keyword($keyword);
            if (empty($keyword)) continue;
            
            $result = $wpdb->insert(
                $table_name,
                array('keyword' => $keyword),
                array('%s')
            );
            
            if ($result !== false) {
                $imported_count++;
            } else {
                $duplicate_count++;
            }
        }
        
        $message = sprintf(
            __('Import completed: %d keywords imported, %d duplicates skipped.', 'iptc-tagmaker'),
            $imported_count,
            $duplicate_count
        );
        
        wp_send_json_success(array(
            'message' => $message,
            'html' => $this->get_blocked_keywords_list_html()
        ));
    }
    
    /**
     * AJAX handler to bulk import keyword substitutions
     */
    public function ajax_bulk_import_substitutions() {
        if (!wp_verify_nonce($_POST['nonce'], 'iptc_tagmaker_admin')) {
            wp_send_json_error(__('Security check failed', 'iptc-tagmaker'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'iptc-tagmaker'));
        }
        
        $substitutions_text = sanitize_textarea_field($_POST['substitutions']);
        
        if (empty($substitutions_text)) {
            wp_send_json_error(__('No substitutions provided.', 'iptc-tagmaker'));
        }
        
        // Parse substitutions - support multiple formats
        $lines = preg_split('/[\r\n,]+/', $substitutions_text);
        $substitutions = array();
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Look for => separator first
            if (strpos($line, '=>') !== false) {
                list($original, $replacement) = array_map('trim', explode('=>', $line, 2));
            } 
            // Also support tab-separated format (from Excel/spreadsheets)
            elseif (strpos($line, "\t") !== false) {
                list($original, $replacement) = array_map('trim', explode("\t", $line, 2));
            } 
            else {
                continue; // Skip lines that don't have a clear separator
            }
            
            // More comprehensive cleaning
            $original = $this->clean_keyword($original);
            $replacement = $this->clean_keyword($replacement);
            
            if (!empty($original) && !empty($replacement)) {
                $substitutions[$original] = $replacement;
            }
        }
        
        if (empty($substitutions)) {
            wp_send_json_error(__('No valid substitutions found. Use format: "original => replacement"', 'iptc-tagmaker'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'iptc_keyword_substitutions';
        $imported_count = 0;
        $updated_count = 0;
        
        foreach ($substitutions as $original => $replacement) {
            $result = $wpdb->replace(
                $table_name,
                array(
                    'original_keyword' => $original,
                    'replacement_keyword' => $replacement
                ),
                array('%s', '%s')
            );
            
            if ($result !== false) {
                if ($result == 1) {
                    $imported_count++;
                } else {
                    $updated_count++;
                }
            }
        }
        
        $message = sprintf(
            __('Import completed: %d substitutions imported, %d updated.', 'iptc-tagmaker'),
            $imported_count,
            $updated_count
        );
        
        wp_send_json_success(array(
            'message' => $message,
            'html' => $this->get_keyword_substitutions_list_html()
        ));
    }
    
    /**
     * Clean a keyword by removing quotes, slashes, and extra whitespace
     * 
     * @param string $keyword The keyword to clean
     * @return string Cleaned keyword
     */
    private function clean_keyword($keyword) {
        // Remove escape slashes
        $keyword = stripslashes($keyword);
        
        // Remove any remaining surrounding whitespace
        $keyword = trim($keyword);
        
        // More aggressive quote removal - keep doing it until no more quotes
        do {
            $before = $keyword;
            $keyword = trim($keyword, '"\'');
            $keyword = trim($keyword);
        } while ($before !== $keyword && !empty($keyword));
        
        return $keyword;
    }
    
    /**
     * Get blocked keywords list HTML
     * 
     * @return string HTML
     */
    private function get_blocked_keywords_list_html() {
        ob_start();
        $this->render_blocked_keywords_list();
        return ob_get_clean();
    }
    
    /**
     * Get keyword substitutions list HTML
     * 
     * @return string HTML
     */
    private function get_keyword_substitutions_list_html() {
        ob_start();
        $this->render_keyword_substitutions_list();
        return ob_get_clean();
    }
}