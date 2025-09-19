<?php
/**
 * IPTC Keyword Processor Class
 * 
 * Handles extraction and processing of IPTC keywords from images
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class IPTC_TagMaker_Keyword_Processor {
    
    /**
     * Process IPTC keywords for a post
     * 
     * @param int $attachment_id The attachment ID
     * @param int $post_id The post ID
     * @return bool Whether processing was successful
     */
    public function process_keywords_for_post($attachment_id, $post_id) {
        $fullsize_path = get_attached_file($attachment_id);
        
        if (!$fullsize_path || !file_exists($fullsize_path)) {
            return false;
        }

        $info = array();
        $image = getimagesize($fullsize_path, $info);
        
        if (!isset($info['APP13'])) {
            return false;
        }

        $iptc = iptcparse($info['APP13']);
        
        if (!$iptc || !isset($iptc["2#025"])) {
            return false;
        }

        $keywords = $iptc["2#025"];
        $filtered_keywords = $this->filter_keywords($keywords);
        
        // Apply keywords to post
        $this->apply_keywords_to_post($filtered_keywords, $post_id);
        
        // Save full keywords metadata if needed
        $this->save_full_keywords_meta($keywords, $filtered_keywords, $post_id);
        
        return true;
    }
    
    /**
     * Get the first image attachment from a post
     * 
     * @param int $post_id The post ID
     * @return int|false The attachment ID or false if none found
     */
    public function get_first_image_attachment($post_id) {
        // Try featured image first
        $attachment_id = get_post_thumbnail_id($post_id);
        
        if ($attachment_id) {
            return $attachment_id;
        }
        
        // Try attached images
        $attachments = get_attached_media('image', $post_id);
        
        if (!empty($attachments)) {
            $first_attachment = reset($attachments);
            return $first_attachment->ID;
        }
        
        return false;
    }
    
    /**
     * Filter keywords based on blocked list and substitutions
     * 
     * @param array $keywords Raw keywords from IPTC
     * @return array Filtered keywords
     */
    private function filter_keywords($keywords) {
        $filtered_keywords = array();
        $blocked_keywords = $this->get_blocked_keywords();
        $keyword_substitutions = $this->get_keyword_substitutions();
        $exclude_substrings = $this->get_exclude_substrings();
        
        foreach ($keywords as $keyword) {
            $keyword_trim = trim($keyword);
            $keyword_lower = strtolower($keyword_trim);
            
            // Skip if blocked
            if (in_array($keyword_trim, $blocked_keywords, true) || 
                in_array($keyword_lower, array_map('strtolower', $blocked_keywords), true)) {
                continue;
            }
            
            // Skip if contains excluded substring
            $skip_keyword = false;
            foreach ($exclude_substrings as $needle) {
                if (str_contains($keyword_lower, $needle)) {
                    $skip_keyword = true;
                    break;
                }
            }
            
            if ($skip_keyword) {
                continue;
            }
            
            // Apply substitutions
            foreach ($keyword_substitutions as $original => $replacement) {
                if ($keyword_lower === strtolower($original)) {
                    $keyword_trim = $replacement;
                    break;
                }
            }
            
            $filtered_keywords[] = $keyword_trim;
        }
        
        return $filtered_keywords;
    }
    
    /**
     * Apply filtered keywords as tags to post
     * 
     * @param array $keywords Filtered keywords
     * @param int $post_id The post ID
     */
    private function apply_keywords_to_post($keywords, $post_id) {
        $settings = get_option('iptc_tagmaker_settings', array());
        
        // Remove existing tags if setting is enabled
        if (!empty($settings['remove_existing_tags'])) {
            wp_set_post_tags($post_id, array());
        }
        
        $tag_slugs = array();
        
        foreach ($keywords as $keyword) {
            $tag_id = $this->get_or_create_tag($keyword);
            
            if ($tag_id) {
                $term = get_term($tag_id, 'post_tag');
                
                if (!is_wp_error($term) && $term && !empty($term->slug)) {
                    $tag_slugs[] = $term->slug;
                }
            }
        }
        
        // Apply all tags at once
        if (!empty($tag_slugs)) {
            wp_set_post_tags($post_id, $tag_slugs, !empty($settings['remove_existing_tags']) ? false : true);
        }
    }
    
    /**
     * Get or create a WordPress tag
     * 
     * @param string $tag_name The tag name
     * @return int|null The tag ID or null on error
     */
    private function get_or_create_tag($tag_name) {
        $term = term_exists($tag_name, 'post_tag');
        
        if ($term !== 0 && $term !== null) {
            return $term['term_id'];
        } else {
            $tag_name = wp_slash($tag_name);
            $term = wp_insert_term($tag_name, 'post_tag');
            
            if (is_wp_error($term)) {
                return null;
            }
            
            return $term['term_id'];
        }
    }
    
    /**
     * Save full keywords metadata
     * 
     * @param array $original_keywords Original keywords
     * @param array $filtered_keywords Filtered keywords
     * @param int $post_id The post ID
     */
    private function save_full_keywords_meta($original_keywords, $filtered_keywords, $post_id) {
        $settings = get_option('iptc_tagmaker_settings', array());
        $min_keywords = !empty($settings['minimum_keywords_for_fullkw']) ? $settings['minimum_keywords_for_fullkw'] : 5;
        
        if (count($original_keywords) > $min_keywords && 
            empty(trim(get_post_meta($post_id, 'fullKW', true)))) {
            update_post_meta($post_id, 'fullKW', implode(',', $filtered_keywords));
        }
    }
    
    /**
     * Get blocked keywords from database
     * 
     * @return array Blocked keywords
     */
    private function get_blocked_keywords() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iptc_blocked_keywords';
        
        $results = $wpdb->get_col("SELECT keyword FROM $table_name");
        
        return $results ? $results : array();
    }
    
    /**
     * Get keyword substitutions from database
     * 
     * @return array Keyword substitutions (original => replacement)
     */
    private function get_keyword_substitutions() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iptc_keyword_substitutions';
        
        $results = $wpdb->get_results("SELECT original_keyword, replacement_keyword FROM $table_name", ARRAY_A);
        
        $substitutions = array();
        if ($results) {
            foreach ($results as $row) {
                $substitutions[$row['original_keyword']] = $row['replacement_keyword'];
            }
        }
        
        return $substitutions;
    }
    
    /**
     * Get exclude substrings (hardcoded for now, could be made configurable)
     * 
     * @return array Substrings to exclude
     */
    private function get_exclude_substrings() {
        return array(
            'camera',
            'lens',
            'iso',
            'f/',
            'mm',
            'sec',
            'exposure',
            'aperture',
            'focal',
            'flash'
        );
    }
    
    /**
     * Add a blocked keyword
     * 
     * @param string $keyword The keyword to block
     * @return bool Success
     */
    public function add_blocked_keyword($keyword) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iptc_blocked_keywords';
        
        $result = $wpdb->insert(
            $table_name,
            array('keyword' => trim($keyword)),
            array('%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Remove a blocked keyword
     * 
     * @param string $keyword The keyword to unblock
     * @return bool Success
     */
    public function remove_blocked_keyword($keyword) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iptc_blocked_keywords';
        
        $result = $wpdb->delete(
            $table_name,
            array('keyword' => trim($keyword)),
            array('%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Add a keyword substitution
     * 
     * @param string $original Original keyword
     * @param string $replacement Replacement keyword
     * @return bool Success
     */
    public function add_keyword_substitution($original, $replacement) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iptc_keyword_substitutions';
        
        $result = $wpdb->replace(
            $table_name,
            array(
                'original_keyword' => trim($original),
                'replacement_keyword' => trim($replacement)
            ),
            array('%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Remove a keyword substitution
     * 
     * @param string $original Original keyword
     * @return bool Success
     */
    public function remove_keyword_substitution($original) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iptc_keyword_substitutions';
        
        $result = $wpdb->delete(
            $table_name,
            array('original_keyword' => trim($original)),
            array('%s')
        );
        
        return $result !== false;
    }
}