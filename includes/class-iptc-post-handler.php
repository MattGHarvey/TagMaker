<?php
/**
 * IPTC Post Handler Class
 * 
 * Handles WordPress post save events and processes IPTC keywords
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class IPTC_TagMaker_Post_Handler {
    
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
        // Hook into post save
        add_action('save_post', array($this, 'on_post_save'), 10, 3);
        
        // Hook into post status transitions (for published posts)
        add_action('transition_post_status', array($this, 'on_post_status_change'), 10, 3);
        
        // Add meta box for manual processing
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Handle AJAX requests for manual processing
        add_action('wp_ajax_iptc_process_keywords', array($this, 'ajax_process_keywords'));
        
        // Handle AJAX requests for keyword preview
        add_action('wp_ajax_iptc_preview_keywords', array($this, 'ajax_preview_keywords'));
    }
    
    /**
     * Handle post save
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function on_post_save($post_id, $post, $update) {
        // Skip if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Skip if user doesn't have permission to edit
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Skip if this is a revision
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Only process posts (not pages or other post types unless specified)
        if ($post->post_type !== 'post') {
            return;
        }
        
        // Check if auto-processing is enabled
        $settings = get_option('iptc_tagmaker_settings', array());
        if (empty($settings['auto_process_on_save'])) {
            return;
        }
        
        // Process keywords
        $this->process_post_keywords($post_id);
    }
    
    /**
     * Handle post status changes (e.g., draft to published)
     * 
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     */
    public function on_post_status_change($new_status, $old_status, $post) {
        // Only process when post is being published
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        
        // Only process posts
        if ($post->post_type !== 'post') {
            return;
        }
        
        $settings = get_option('iptc_tagmaker_settings', array());
        if (empty($settings['auto_process_on_save'])) {
            return;
        }
        
        // Process keywords
        $this->process_post_keywords($post->ID);
    }
    
    /**
     * Process keywords for a post
     * 
     * @param int $post_id Post ID
     * @return bool Success
     */
    private function process_post_keywords($post_id) {
        $attachment_id = $this->processor->get_first_image_attachment($post_id);
        
        if (!$attachment_id) {
            return false;
        }
        
        return $this->processor->process_keywords_for_post($attachment_id, $post_id);
    }
    
    /**
     * Add meta boxes to post edit screen
     */
    public function add_meta_boxes() {
        add_meta_box(
            'iptc-tagmaker-meta',
            __('IPTC TagMaker', 'iptc-tagmaker'),
            array($this, 'render_meta_box'),
            'post',
            'side',
            'default'
        );
    }
    
    /**
     * Render the meta box
     * 
     * @param WP_Post $post Post object
     */
    public function render_meta_box($post) {
        wp_nonce_field('iptc_tagmaker_meta', 'iptc_tagmaker_nonce');
        
        $attachment_id = $this->processor->get_first_image_attachment($post->ID);
        
        echo '<div id="iptc-tagmaker-meta-content">';
        
        if ($attachment_id) {
            echo '<p><strong>' . __('First Image Found:', 'iptc-tagmaker') . '</strong></p>';
            echo '<p>' . get_the_title($attachment_id) . '</p>';
            
            echo '<p>';
            echo '<button type="button" id="iptc-preview-keywords" class="button" data-post-id="' . $post->ID . '">';
            echo __('Preview Keywords', 'iptc-tagmaker');
            echo '</button>';
            echo '</p>';
            
            echo '<p>';
            echo '<button type="button" id="iptc-process-keywords" class="button button-primary" data-post-id="' . $post->ID . '">';
            echo __('Process Keywords Now', 'iptc-tagmaker');
            echo '</button>';
            echo '</p>';
            
            echo '<div id="iptc-keywords-preview" style="display: none;"></div>';
            echo '<div id="iptc-process-result" style="display: none;"></div>';
            
        } else {
            echo '<p>' . __('No images found in this post.', 'iptc-tagmaker') . '</p>';
        }
        
        echo '</div>';
        
        // Add inline JavaScript
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#iptc-preview-keywords').on('click', function() {
                var postId = $(this).data('post-id');
                var button = $(this);
                
                button.prop('disabled', true).text('<?php echo esc_js(__('Loading...', 'iptc-tagmaker')); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'iptc_preview_keywords',
                        post_id: postId,
                        nonce: $('#iptc_tagmaker_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#iptc-keywords-preview').html(response.data.html).show();
                        } else {
                            alert(response.data || '<?php echo esc_js(__('Error previewing keywords', 'iptc-tagmaker')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('AJAX error occurred', 'iptc-tagmaker')); ?>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php echo esc_js(__('Preview Keywords', 'iptc-tagmaker')); ?>');
                    }
                });
            });
            
            $('#iptc-process-keywords').on('click', function() {
                var postId = $(this).data('post-id');
                var button = $(this);
                
                button.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'iptc-tagmaker')); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'iptc_process_keywords',
                        post_id: postId,
                        nonce: $('#iptc_tagmaker_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#iptc-process-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
                            // Refresh the tags metabox
                            location.reload();
                        } else {
                            $('#iptc-process-result').html('<div class="notice notice-error"><p>' + (response.data || '<?php echo esc_js(__('Error processing keywords', 'iptc-tagmaker')); ?>') + '</p></div>').show();
                        }
                    },
                    error: function() {
                        $('#iptc-process-result').html('<div class="notice notice-error"><p><?php echo esc_js(__('AJAX error occurred', 'iptc-tagmaker')); ?></p></div>').show();
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php echo esc_js(__('Process Keywords Now', 'iptc-tagmaker')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle AJAX request to preview keywords
     */
    public function ajax_preview_keywords() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'iptc_tagmaker_meta')) {
            wp_die(__('Security check failed', 'iptc-tagmaker'));
        }
        
        $post_id = intval($_POST['post_id']);
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('You do not have permission to edit this post.', 'iptc-tagmaker'));
        }
        
        $attachment_id = $this->processor->get_first_image_attachment($post_id);
        
        if (!$attachment_id) {
            wp_send_json_error(__('No image found in this post.', 'iptc-tagmaker'));
        }
        
        // Get raw keywords
        $keywords = $this->get_raw_keywords($attachment_id);
        
        if (empty($keywords)) {
            wp_send_json_error(__('No IPTC keywords found in the image.', 'iptc-tagmaker'));
        }
        
        // Filter keywords
        $processor = new IPTC_TagMaker_Keyword_Processor();
        $filtered_keywords = $this->get_filtered_keywords_preview($keywords);
        
        $html = '<h4>' . __('Raw Keywords:', 'iptc-tagmaker') . '</h4>';
        $html .= '<p>' . implode(', ', $keywords) . '</p>';
        
        $html .= '<h4>' . __('Filtered Keywords (will be used as tags):', 'iptc-tagmaker') . '</h4>';
        if (!empty($filtered_keywords)) {
            $html .= '<p>' . implode(', ', $filtered_keywords) . '</p>';
        } else {
            $html .= '<p><em>' . __('No keywords will be used after filtering.', 'iptc-tagmaker') . '</em></p>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Handle AJAX request to process keywords
     */
    public function ajax_process_keywords() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'iptc_tagmaker_meta')) {
            wp_die(__('Security check failed', 'iptc-tagmaker'));
        }
        
        $post_id = intval($_POST['post_id']);
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('You do not have permission to edit this post.', 'iptc-tagmaker'));
        }
        
        $success = $this->process_post_keywords($post_id);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Keywords processed successfully! Tags have been updated.', 'iptc-tagmaker')
            ));
        } else {
            wp_send_json_error(__('Failed to process keywords. Make sure the post has an image with IPTC data.', 'iptc-tagmaker'));
        }
    }
    
    /**
     * Get raw keywords from image
     * 
     * @param int $attachment_id Attachment ID
     * @return array Raw keywords
     */
    private function get_raw_keywords($attachment_id) {
        $fullsize_path = get_attached_file($attachment_id);
        
        if (!$fullsize_path || !file_exists($fullsize_path)) {
            return array();
        }
        
        $info = array();
        $image = getimagesize($fullsize_path, $info);
        
        if (!isset($info['APP13'])) {
            return array();
        }
        
        $iptc = iptcparse($info['APP13']);
        
        if (!$iptc || !isset($iptc["2#025"])) {
            return array();
        }
        
        return $iptc["2#025"];
    }
    
    /**
     * Get filtered keywords for preview
     * 
     * @param array $keywords Raw keywords
     * @return array Filtered keywords
     */
    private function get_filtered_keywords_preview($keywords) {
        $processor_reflection = new ReflectionClass('IPTC_TagMaker_Keyword_Processor');
        $filter_method = $processor_reflection->getMethod('filter_keywords');
        $filter_method->setAccessible(true);
        
        $processor = new IPTC_TagMaker_Keyword_Processor();
        return $filter_method->invoke($processor, $keywords);
    }
}