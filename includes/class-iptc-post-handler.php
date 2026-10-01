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

        // Add an administrator-only processing control to individual post views.
        add_filter('the_content', array($this, 'add_post_processing_section'));
        add_action('admin_post_iptc_process_post_keywords', array($this, 'handle_post_processing_request'));
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
        return $this->processor->process_keywords_for_post($post_id);
    }

    /**
     * Add a manual processing section to a single post for administrators.
     *
     * @param string $content Post content.
     * @return string Post content with the processing section appended when applicable.
     */
    public function add_post_processing_section($content) {
        global $post;

        if (!is_singular('post') || !in_the_loop() || !is_main_query() || !$post ||
            !current_user_can('manage_options') || !current_user_can('edit_post', $post->ID)) {
            return $content;
        }

        $attachment_id = $this->processor->get_first_image_attachment($post->ID);
        $result = isset($_GET['iptc_tagmaker_result']) ? sanitize_key(wp_unslash($_GET['iptc_tagmaker_result'])) : '';

        $section = '<section id="iptc-tagmaker-process" class="iptc-tagmaker-process">';
        $section .= '<h2>' . esc_html__('IPTC TagMaker', 'iptc-tagmaker') . '</h2>';

        if ($result === 'success') {
            $section .= '<p>' . esc_html__('IPTC keywords were extracted and the post tags have been updated.', 'iptc-tagmaker') . '</p>';
        } elseif ($result === 'failed') {
            $section .= '<p>' . esc_html__('No IPTC keywords could be extracted from this post image.', 'iptc-tagmaker') . '</p>';
        }

        if ($attachment_id) {
            $section .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            $section .= '<input type="hidden" name="action" value="iptc_process_post_keywords" />';
            $section .= '<input type="hidden" name="post_id" value="' . esc_attr($post->ID) . '" />';
            $section .= wp_nonce_field('iptc_process_post_keywords_' . $post->ID, 'iptc_tagmaker_process_nonce', true, false);
            $section .= '<button type="submit">' . esc_html__('Extract IPTC Tags', 'iptc-tagmaker') . '</button>';
            $section .= '</form>';
        } else {
            $section .= '<p>' . esc_html__('No image was found in this post.', 'iptc-tagmaker') . '</p>';
        }

        $section .= '</section>';

        return $content . $section;
    }

    /**
     * Process a post from the administrator-only front-end section.
     */
    public function handle_post_processing_request() {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $nonce = isset($_POST['iptc_tagmaker_process_nonce']) ? sanitize_text_field(wp_unslash($_POST['iptc_tagmaker_process_nonce'])) : '';

        if (!$post_id || !wp_verify_nonce($nonce, 'iptc_process_post_keywords_' . $post_id)) {
            wp_die(esc_html__('Security check failed.', 'iptc-tagmaker'));
        }

        if (!current_user_can('manage_options') || !current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('You do not have permission to process this post.', 'iptc-tagmaker'));
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            wp_die(esc_html__('Invalid post.', 'iptc-tagmaker'));
        }

        $result = $this->process_post_keywords($post_id) ? 'success' : 'failed';
        $redirect_url = add_query_arg('iptc_tagmaker_result', $result, get_permalink($post_id));

        wp_safe_redirect($redirect_url);
        exit;
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
            
            echo '<p style="margin-top: 10px;"><em>' . __('This will process both filtered Tags and unfiltered Keywords.', 'iptc-tagmaker') . '</em></p>';
            
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
        
        // Filter keywords with detailed reasons
        $filter_result = $this->get_filtered_keywords_preview($keywords);
        $filtered_keywords = $filter_result['filtered'];
        $filter_reasons = $filter_result['reasons'];
        
        $html = '<h4>' . __('Raw IPTC Keywords:', 'iptc-tagmaker') . '</h4>';
        $html .= '<p><strong>' . __('(will be added to Keyword taxonomy without filtering)', 'iptc-tagmaker') . '</strong></p>';
        $html .= '<p>' . implode(', ', $keywords) . '</p>';
        
        $html .= '<hr style="margin: 15px 0;">';
        
        $html .= '<h4>' . __('Filtered Keywords (will be used as Tags):', 'iptc-tagmaker') . '</h4>';
        if (!empty($filtered_keywords)) {
            $html .= '<p>' . implode(', ', $filtered_keywords) . '</p>';
        } else {
            $html .= '<p><em>' . __('No keywords will be used after filtering.', 'iptc-tagmaker') . '</em></p>';
        }
        
        if (!empty($filter_reasons)) {
            $html .= '<h4>' . __('Keywords Filtered Out (with reasons):', 'iptc-tagmaker') . '</h4>';
            $html .= '<ul style="margin-left: 20px;">';
            foreach ($filter_reasons as $reason) {
                $html .= '<li>' . esc_html($reason) . '</li>';
            }
            $html .= '</ul>';
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
                'message' => __('Keywords processed successfully! Tags and Keywords have been updated.', 'iptc-tagmaker')
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
        
        // Use reflection to access the private extract_keywords_from_image method
        $processor = new IPTC_TagMaker_Keyword_Processor();
        $processor_reflection = new ReflectionClass('IPTC_TagMaker_Keyword_Processor');
        
        $extract_method = $processor_reflection->getMethod('extract_keywords_from_image');
        $extract_method->setAccessible(true);
        
        return $extract_method->invoke($processor, $fullsize_path);
    }
    
    /**
     * Get filtered keywords for preview with detailed reasons
     * 
     * @param array $keywords Raw keywords
     * @return array Array with 'filtered' keywords and 'reasons' for filtering
     */
    private function get_filtered_keywords_preview($keywords) {
        $processor = new IPTC_TagMaker_Keyword_Processor();
        
        // Get filtering data
        $processor_reflection = new ReflectionClass('IPTC_TagMaker_Keyword_Processor');
        
        $blocked_method = $processor_reflection->getMethod('get_blocked_keywords');
        $blocked_method->setAccessible(true);
        $blocked_keywords = $blocked_method->invoke($processor);
        
        $substitutions_method = $processor_reflection->getMethod('get_keyword_substitutions');
        $substitutions_method->setAccessible(true);
        $keyword_substitutions = $substitutions_method->invoke($processor);
        
        $exclude_method = $processor_reflection->getMethod('get_exclude_substrings');
        $exclude_method->setAccessible(true);
        $exclude_substrings = $exclude_method->invoke($processor);
        
        $filtered_keywords = array();
        $filter_reasons = array();
        
        foreach ($keywords as $keyword) {
            $keyword_trim = trim($keyword);
            $keyword_lower = strtolower($keyword_trim);
            $original_keyword = $keyword_trim;
            
            // Check if blocked
            if (in_array($keyword_trim, $blocked_keywords, true) || 
                in_array($keyword_lower, array_map('strtolower', $blocked_keywords), true)) {
                $filter_reasons[] = $keyword_trim . ' → BLOCKED (in blocked keywords list)';
                continue;
            }
            
            // Check if contains excluded substring
            $skip_keyword = false;
            foreach ($exclude_substrings as $needle) {
                if (str_contains($keyword_lower, $needle)) {
                    $filter_reasons[] = $keyword_trim . ' → EXCLUDED (contains "' . $needle . '")';
                    $skip_keyword = true;
                    break;
                }
            }
            
            if ($skip_keyword) {
                continue;
            }
            
            // Apply substitutions
            $substitution_applied = false;
            foreach ($keyword_substitutions as $original => $replacement) {
                $original_clean = trim(strtolower($original));
                $keyword_clean = trim(strtolower($keyword_trim));
                
                if ($keyword_clean === $original_clean) {
                    $filter_reasons[] = $keyword_trim . ' → SUBSTITUTED to "' . $replacement . '"';
                    $keyword_trim = $replacement;
                    $substitution_applied = true;
                    break;
                }
            }
            
            $filtered_keywords[] = $keyword_trim;
        }
        
        return array(
            'filtered' => $filtered_keywords,
            'reasons' => $filter_reasons
        );
    }
}