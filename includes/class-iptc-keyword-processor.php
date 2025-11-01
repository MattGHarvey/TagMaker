<?php
/**
 * IPTC Keyword Processor Class
 * 
 * Handles extraction and processing of IPTC/XMP keywords from images (JPG, WebP)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class IPTC_TagMaker_Keyword_Processor {
    
    /**
     * Process IPTC/XMP keywords for a post
     * 
     * @param int $post_id The post ID
     * @param bool $force_clear Force clearing of existing tags
     * @return bool Whether processing was successful
     */
    public function process_keywords_for_post($post_id, $force_clear = false) {
        $this->debug_log('Starting IPTC/XMP processing for post ID: ' . $post_id, array(
            'force_clear' => $force_clear
        ));
        
        // Get the first image from post content
        $attachment_id = $this->get_first_image_attachment($post_id);
        
        if (!$attachment_id) {
            $this->debug_log('No image found for post ID: ' . $post_id);
            // No image found - clear existing IPTC tags if any
            $this->clear_iptc_generated_tags($post_id);
            return false;
        }
        
        $this->debug_log('Found image attachment ID: ' . $attachment_id . ' for post ID: ' . $post_id);
        
        // Check if this is a different image than last processed
        $last_processed_image = get_post_meta($post_id, '_iptc_last_processed_image', true);
        $image_changed = ($last_processed_image != $attachment_id);
        
        $fullsize_path = get_attached_file($attachment_id);
        
        if (!$fullsize_path || !file_exists($fullsize_path)) {
            return false;
        }

        // Extract keywords based on file type
        $keywords = $this->extract_keywords_from_image($fullsize_path, $attachment_id);
        
        if ($keywords === false) {
            $this->debug_log('No metadata found in image', array(
                'attachment_id' => $attachment_id
            ));
            // No metadata - clear existing tags if image changed
            if ($image_changed || $force_clear) {
                $this->clear_iptc_generated_tags($post_id);
                update_post_meta($post_id, '_iptc_last_processed_image', $attachment_id);
            }
            return false;
        }

        if (empty($keywords)) {
            $this->debug_log('No keywords found in image metadata', array(
                'attachment_id' => $attachment_id
            ));
            // No keywords - clear existing tags if image changed
            if ($image_changed || $force_clear) {
                $this->clear_iptc_generated_tags($post_id);
                update_post_meta($post_id, '_iptc_last_processed_image', $attachment_id);
            }
            return false;
        }

        $this->debug_log('Found keywords', array(
            'attachment_id' => $attachment_id,
            'raw_keywords' => $keywords
        ));
        
        $filtered_keywords = $this->filter_keywords($keywords);
        
        // Apply keywords to post (this will handle clearing based on settings)
        $this->apply_keywords_to_post($filtered_keywords, $post_id, $image_changed || $force_clear);
        
        // Track which image we processed
        update_post_meta($post_id, '_iptc_last_processed_image', $attachment_id);
        
        return true;
    }
    
    /**
     * Extract keywords from image file (supports IPTC for JPG and XMP for WebP)
     * 
     * @param string $file_path Full path to image file
     * @param int $attachment_id Attachment ID for logging
     * @return array|false Array of keywords or false if no metadata found
     */
    private function extract_keywords_from_image($file_path, $attachment_id = 0) {
        $mime_type = mime_content_type($file_path);
        
        $this->debug_log('Extracting keywords from image', array(
            'file_path' => $file_path,
            'mime_type' => $mime_type,
            'attachment_id' => $attachment_id
        ));
        
        // Handle WebP files (extract XMP data)
        if ($mime_type === 'image/webp') {
            return $this->extract_keywords_from_webp($file_path);
        }
        
        // Handle JPG files (extract IPTC data)
        if (in_array($mime_type, array('image/jpeg', 'image/jpg'))) {
            return $this->extract_keywords_from_jpeg($file_path);
        }
        
        $this->debug_log('Unsupported image type for metadata extraction', array(
            'mime_type' => $mime_type
        ));
        
        return false;
    }
    
    /**
     * Extract keywords from JPEG using IPTC or XMP data
     * 
     * @param string $file_path Full path to JPEG file
     * @return array|false Array of keywords or false if no metadata found
     */
    private function extract_keywords_from_jpeg($file_path) {
        // First try traditional IPTC extraction
        $info = array();
        $image = getimagesize($file_path, $info);
        
        if (isset($info['APP13'])) {
            $iptc = iptcparse($info['APP13']);
            
            if ($iptc && isset($iptc["2#025"])) {
                $this->debug_log('Found IPTC keywords in JPEG', array(
                    'keywords' => $iptc["2#025"]
                ));
                return $iptc["2#025"];
            }
        }
        
        // If no IPTC data found, try XMP (modern tools like Lightroom use XMP in JPEGs)
        $xmp_data = $this->extract_xmp_from_jpeg($file_path);
        
        if ($xmp_data) {
            $keywords = $this->parse_keywords_from_xmp($xmp_data);
            if ($keywords !== false) {
                $this->debug_log('Found XMP keywords in JPEG', array(
                    'keywords' => $keywords
                ));
                return $keywords;
            }
        }
        
        return false;
    }
    
    /**
     * Extract XMP data from JPEG file
     * 
     * @param string $file_path Full path to JPEG file
     * @return string|false XMP data or false if not found
     */
    private function extract_xmp_from_jpeg($file_path) {
        $contents = file_get_contents($file_path);
        
        if ($contents === false) {
            return false;
        }
        
        // XMP data in JPEG is embedded between <?xpacket begin and <?xpacket end markers
        // or between <x:xmpmeta and </x:xmpmeta> tags
        $xmp_start = strpos($contents, '<x:xmpmeta');
        $xmp_end = strpos($contents, '</x:xmpmeta>');
        
        if ($xmp_start !== false && $xmp_end !== false) {
            $xmp_data = substr($contents, $xmp_start, $xmp_end - $xmp_start + 12); // +12 for </x:xmpmeta>
            return $xmp_data;
        }
        
        // Try alternative XMP markers
        $xmp_start = strpos($contents, '<?xpacket begin');
        $xmp_end = strpos($contents, '<?xpacket end');
        
        if ($xmp_start !== false && $xmp_end !== false) {
            $xmp_data = substr($contents, $xmp_start, $xmp_end - $xmp_start);
            return $xmp_data;
        }
        
        return false;
    }
    
    /**
     * Extract keywords from WebP using XMP data
     * 
     * @param string $file_path Full path to WebP file
     * @return array|false Array of keywords or false if no XMP data found
     */
    private function extract_keywords_from_webp($file_path) {
        $this->debug_log('Attempting to extract keywords from WebP', array(
            'file_path' => $file_path,
            'file_exists' => file_exists($file_path)
        ));
        
        // Read the WebP file
        $contents = file_get_contents($file_path);
        
        if ($contents === false) {
            $this->debug_log('Failed to read WebP file');
            return false;
        }
        
        // Look for XMP metadata in the file
        // WebP stores XMP in a chunk labeled "XMP "
        $xmp_data = $this->extract_xmp_from_webp($contents);
        
        if (!$xmp_data) {
            $this->debug_log('No XMP data found in WebP file');
            return false;
        }
        
        // Parse keywords from XMP
        $keywords = $this->parse_keywords_from_xmp($xmp_data);
        
        $this->debug_log('WebP XMP parsing result', array(
            'keywords_found' => $keywords !== false,
            'keyword_count' => is_array($keywords) ? count($keywords) : 0,
            'keywords' => $keywords
        ));
        
        return $keywords;
    }
    
    /**
     * Extract XMP chunk from WebP file contents
     * 
     * @param string $contents WebP file contents
     * @return string|false XMP data or false if not found
     */
    private function extract_xmp_from_webp($contents) {
        // WebP file structure: RIFF....WEBP[chunks]
        // XMP chunk format: "XMP " + size (4 bytes) + XMP data
        
        $pos = 0;
        $length = strlen($contents);
        
        $this->debug_log('Extracting XMP from WebP', array(
            'file_size' => $length
        ));
        
        // Verify RIFF header
        if (substr($contents, 0, 4) !== 'RIFF') {
            $this->debug_log('Not a valid RIFF file', array(
                'header' => substr($contents, 0, 4)
            ));
            return false;
        }
        
        // Verify WEBP signature
        $webp_sig = substr($contents, 8, 4);
        if ($webp_sig !== 'WEBP') {
            $this->debug_log('Not a valid WebP file', array(
                'signature' => $webp_sig
            ));
            return false;
        }
        
        // Skip RIFF header (4 bytes) + file size (4 bytes) + WEBP signature (4 bytes)
        $pos = 12;
        
        // Search for XMP chunk
        $chunk_count = 0;
        while ($pos < $length - 8) {
            $chunk_header = substr($contents, $pos, 4);
            $chunk_size = unpack('V', substr($contents, $pos + 4, 4))[1];
            
            $chunk_count++;
            $this->debug_log('Processing WebP chunk', array(
                'chunk_number' => $chunk_count,
                'position' => $pos,
                'header' => $chunk_header,
                'size' => $chunk_size
            ));
            
            if ($chunk_header === 'XMP ') {
                // Found XMP chunk, extract the data
                $xmp_data = substr($contents, $pos + 8, $chunk_size);
                $this->debug_log('Found XMP chunk in WebP', array(
                    'xmp_size' => strlen($xmp_data),
                    'xmp_preview' => substr($xmp_data, 0, 200)
                ));
                return $xmp_data;
            }
            
            // Move to next chunk (header + size + data, padded to even byte)
            $pos += 8 + $chunk_size;
            if ($chunk_size % 2 == 1) {
                $pos++; // Padding byte
            }
            
            // Safety check to prevent infinite loop
            if ($chunk_count > 100) {
                $this->debug_log('Too many chunks, stopping search');
                break;
            }
        }
        
        $this->debug_log('No XMP chunk found in WebP', array(
            'chunks_processed' => $chunk_count
        ));
        
        return false;
    }
    
    /**
     * Parse keywords from XMP data
     * 
     * @param string $xmp_data XMP data as string
     * @return array|false Array of keywords or false if no keywords found
     */
    private function parse_keywords_from_xmp($xmp_data) {
        $keywords = array();
        
        $this->debug_log('Parsing XMP data', array(
            'xmp_length' => strlen($xmp_data),
            'xmp_preview' => substr($xmp_data, 0, 500)
        ));
        
        // XMP keywords can be in several formats:
        // 1. dc:subject in a Bag structure with rdf:li elements
        // 2. dc:subject as a simple comma-separated string (Lightroom format)
        // 3. IPTC Core keywords
        // 4. Lightroom hierarchical keywords
        
        // Try to parse as XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmp_data);
        
        if ($xml === false) {
            $this->debug_log('XML parsing failed, trying regex approach');
            // If XML parsing fails, try regex approach
            return $this->parse_keywords_from_xmp_regex($xmp_data);
        }
        
        // Register namespaces
        $namespaces = $xml->getNamespaces(true);
        
        $this->debug_log('XML parsed successfully', array(
            'namespaces' => array_keys($namespaces)
        ));
        
        // Register RDF namespace explicitly (needed for xpath)
        $xml->registerXPathNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        $xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        
        // Try dc:subject (Dublin Core) with rdf:Bag structure
        $dc_subjects = $xml->xpath('//dc:subject/rdf:Bag/rdf:li');
        
        $this->debug_log('Searching for dc:subject', array(
            'xpath_result_count' => $dc_subjects ? count($dc_subjects) : 0
        ));
        
        if ($dc_subjects && count($dc_subjects) > 0) {
            foreach ($dc_subjects as $subject) {
                $keyword = trim((string)$subject);
                if (!empty($keyword)) {
                    $keywords[] = $keyword;
                }
            }
        }
            
        // If no keywords found in Bag structure, try simple dc:subject element
        // This handles comma-separated keywords from Lightroom
        if (empty($keywords)) {
            $dc_subject_simple = $xml->xpath('//dc:subject');
            if ($dc_subject_simple) {
                foreach ($dc_subject_simple as $subject) {
                    $subject_text = trim((string)$subject);
                    if (!empty($subject_text)) {
                        // Split by comma if it's a comma-separated list
                        $subject_keywords = array_map('trim', explode(',', $subject_text));
                        foreach ($subject_keywords as $keyword) {
                            if (!empty($keyword)) {
                                $keywords[] = $keyword;
                            }
                        }
                    }
                }
            }
        }
        
        // Try Iptc4xmpCore:SubjectCode
        if (empty($keywords)) {
            $xml->registerXPathNamespace('Iptc4xmpCore', 'http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/');
            $iptc_subjects = $xml->xpath('//Iptc4xmpCore:SubjectCode/rdf:Bag/rdf:li');
            if ($iptc_subjects && count($iptc_subjects) > 0) {
                foreach ($iptc_subjects as $subject) {
                    $keyword = trim((string)$subject);
                    if (!empty($keyword)) {
                        $keywords[] = $keyword;
                    }
                }
            }
        }
        
        // Try lr:hierarchicalSubject (Lightroom)
        if (empty($keywords)) {
            $xml->registerXPathNamespace('lr', 'http://ns.adobe.com/lightroom/1.0/');
            $lr_subjects = $xml->xpath('//lr:hierarchicalSubject/rdf:Bag/rdf:li');
            if ($lr_subjects && count($lr_subjects) > 0) {
                foreach ($lr_subjects as $subject) {
                    $keyword = trim((string)$subject);
                    if (!empty($keyword)) {
                        $keywords[] = $keyword;
                    }
                }
            }
        }
        
        // Remove duplicates
        $keywords = array_unique($keywords);
        
        return !empty($keywords) ? array_values($keywords) : false;
    }
    
    /**
     * Parse keywords from XMP using regex (fallback method)
     * 
     * @param string $xmp_data XMP data as string
     * @return array|false Array of keywords or false if no keywords found
     */
    private function parse_keywords_from_xmp_regex($xmp_data) {
        $keywords = array();
        
        // Look for dc:subject, Iptc4xmpCore:SubjectCode, or lr:hierarchicalSubject
        // Pattern matches <rdf:li>keyword</rdf:li>
        if (preg_match_all('/<rdf:li>([^<]+)<\/rdf:li>/i', $xmp_data, $matches)) {
            foreach ($matches[1] as $keyword) {
                $keyword = trim($keyword);
                if (!empty($keyword)) {
                    $keywords[] = $keyword;
                }
            }
        }
        
        // If no keywords found in rdf:li format, look for dc:subject with comma-separated values
        if (empty($keywords)) {
            // Match dc:subject>value</dc:subject> pattern
            if (preg_match('/<dc:subject>([^<]+)<\/dc:subject>/i', $xmp_data, $subject_match)) {
                $subject_text = trim($subject_match[1]);
                if (!empty($subject_text)) {
                    // Split by comma
                    $subject_keywords = array_map('trim', explode(',', $subject_text));
                    foreach ($subject_keywords as $keyword) {
                        if (!empty($keyword)) {
                            $keywords[] = $keyword;
                        }
                    }
                }
            }
        }
        
        // Remove duplicates
        $keywords = array_unique($keywords);
        
        return !empty($keywords) ? array_values($keywords) : false;
    }
    
    /**
     * Clear IPTC-generated tags from a post
     * 
     * @param int $post_id Post ID
     */
    private function clear_iptc_generated_tags($post_id) {
        // Get tags that were generated by IPTC processing
        $iptc_tags = get_post_meta($post_id, '_iptc_generated_tags', true);
        
        if (is_array($iptc_tags) && !empty($iptc_tags)) {
            // Remove these specific tags
            $current_tags = wp_get_post_tags($post_id, array('fields' => 'ids'));
            $remaining_tags = array_diff($current_tags, $iptc_tags);
            wp_set_object_terms($post_id, $remaining_tags, 'post_tag');
        }
        
        // Clear the meta
        delete_post_meta($post_id, '_iptc_generated_tags');
    }
    
    /**
     * Get the first image attachment from a post
     * 
     * @param int $post_id The post ID
     * @return int|false The attachment ID or false if none found
     */
    public function get_first_image_attachment($post_id) {
        // Get the first image from post content (not just attachments)
        return $this->catch_that_image($post_id);
    }
    
    /**
     * Get the first image from post content and return its attachment ID
     * This ensures we're processing the actual first image that appears in the post,
     * not just featured images or attachments
     * 
     * @param int $post_id Post ID
     * @return int|false Attachment ID or false if no image found
     */
    private function catch_that_image($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        $content = $post->post_content;
        
        // Look for images in post content
        $pattern = '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i';
        preg_match_all($pattern, $content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $image_url) {
                // Convert URL to attachment ID and get original full-size image
                $attachment_id = $this->get_attachment_id_from_url($image_url);
                
                if ($attachment_id) {
                    // Verify this is the original full-size image, not a thumbnail
                    $original_url = wp_get_attachment_url($attachment_id);
                    $attachment_id = $this->ensure_original_image($attachment_id, $image_url);
                    
                    if ($attachment_id) {
                        return $attachment_id;
                    }
                }
            }
        }
        
        // Fallback: Try featured image if no content images found
        $attachment_id = get_post_thumbnail_id($post_id);
        if ($attachment_id) {
            return $attachment_id;
        }
        
        // Final fallback: Try attached images
        $attachments = get_attached_media('image', $post_id);
        if (!empty($attachments)) {
            $first_attachment = reset($attachments);
            return $first_attachment->ID;
        }
        
        return false;
    }
    
    /**
     * Get attachment ID from image URL
     * 
     * @param string $image_url Image URL
     * @return int|false Attachment ID or false
     */
    private function get_attachment_id_from_url($image_url) {
        // Try WordPress built-in function first
        $attachment_id = attachment_url_to_postid($image_url);
        
        if ($attachment_id) {
            return $attachment_id;
        }
        
        // If that fails, try to find by checking if it's a resized version
        // Remove size suffix (e.g., -150x150, -300x200, etc.)
        $image_url_clean = preg_replace('/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', $image_url);
        
        if ($image_url_clean !== $image_url) {
            $attachment_id = attachment_url_to_postid($image_url_clean);
            if ($attachment_id) {
                return $attachment_id;
            }
        }
        
        return false;
    }
    
    /**
     * Ensure we're working with the original full-size image
     * 
     * @param int $attachment_id Attachment ID
     * @param string $found_url The URL that was found in content
     * @return int|false Original attachment ID or false
     */
    private function ensure_original_image($attachment_id, $found_url) {
        // Get the original full-size URL
        $original_url = wp_get_attachment_url($attachment_id);
        
        if (!$original_url) {
            return false;
        }
        
        // If the found URL is different from original, it might be a thumbnail
        // We always want to process the original full-size image for IPTC data
        $original_path = get_attached_file($attachment_id);
        
        if ($original_path && file_exists($original_path)) {
            return $attachment_id;
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
        
        $this->debug_log('Starting keyword filtering', array(
            'raw_keywords' => $keywords,
            'blocked_keywords' => $blocked_keywords,
            'keyword_substitutions' => $keyword_substitutions,
            'exclude_substrings' => $exclude_substrings
        ));
        
        foreach ($keywords as $keyword) {
            $keyword_trim = trim($keyword);
            $keyword_lower = strtolower($keyword_trim);
            $original_keyword = $keyword_trim; // Keep track of original for logging
            
            $this->debug_log('Processing keyword: ' . $keyword_trim);
            
            // Skip if blocked
            if (in_array($keyword_trim, $blocked_keywords, true) || 
                in_array($keyword_lower, array_map('strtolower', $blocked_keywords), true)) {
                $this->debug_log('Keyword blocked: ' . $keyword_trim);
                continue;
            }
            
            // Skip if contains excluded substring
            $skip_keyword = false;
            foreach ($exclude_substrings as $needle) {
                if (str_contains($keyword_lower, $needle)) {
                    $this->debug_log('Keyword excluded (contains "' . $needle . '"): ' . $keyword_trim);
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
                // Clean and normalize both for comparison
                $original_clean = trim(strtolower($original));
                $keyword_clean = trim(strtolower($keyword_trim));
                
                $this->debug_log('Checking substitution', array(
                    'keyword' => $keyword_trim,
                    'original' => $original,
                    'replacement' => $replacement,
                    'match' => ($keyword_clean === $original_clean) ? 'YES' : 'NO'
                ));
                
                if ($keyword_clean === $original_clean) {
                    $this->debug_log('Substitution APPLIED: "' . $keyword_trim . '" -> "' . $replacement . '"');
                    $keyword_trim = $replacement;
                    $substitution_applied = true;
                    break;
                }
            }
            
            if (!$substitution_applied) {
                $this->debug_log('No substitution applied for: ' . $original_keyword);
            }
            
            $filtered_keywords[] = $keyword_trim;
        }
        
        $this->debug_log('Keyword filtering complete', array(
            'input_count' => count($keywords),
            'output_count' => count($filtered_keywords),
            'filtered_keywords' => $filtered_keywords
        ));
        
        return $filtered_keywords;
    }
    
    /**
     * Apply filtered keywords as tags to post
     * 
     * @param array $keywords Filtered keywords
     * @param int $post_id The post ID
     * @param bool $image_changed Whether the source image has changed
     */
    private function apply_keywords_to_post($keywords, $post_id, $image_changed = false) {
        $settings = get_option('iptc_tagmaker_settings', array());
        
        $this->debug_log('Applying keywords to post', array(
            'post_id' => $post_id,
            'keywords' => $keywords,
            'keyword_count' => count($keywords)
        ));
        
        // Determine tag processing mode (with backward compatibility)
        $tag_mode = 'append'; // Default to append
        if (isset($settings['tag_mode'])) {
            $tag_mode = $settings['tag_mode'];
        } elseif (!empty($settings['remove_existing_tags'])) {
            // Backward compatibility: convert old setting
            $tag_mode = 'replace';
        }
        
        $this->debug_log('Tag processing mode', array(
            'tag_mode' => $tag_mode,
            'image_changed' => $image_changed
        ));
        
        // If image changed, always clear IPTC-generated tags first
        if ($image_changed) {
            $this->clear_iptc_generated_tags($post_id);
        }
        
        // Handle tag replacement mode
        if ($tag_mode === 'replace') {
            $this->debug_log('Replacing all existing tags with IPTC keywords');
            wp_set_object_terms($post_id, array(), 'post_tag');
        } else {
            $this->debug_log('Appending IPTC keywords to existing tags');
        }
        
        $tag_ids = array();
        
        foreach ($keywords as $keyword) {
            $this->debug_log('Creating/finding tag for keyword: ' . $keyword);
            $tag_id = $this->get_or_create_tag($keyword);
            
            if ($tag_id) {
                $this->debug_log('Tag created/found successfully', array(
                    'keyword' => $keyword,
                    'tag_id' => $tag_id
                ));
                $tag_ids[] = (int) $tag_id;
            } else {
                $this->debug_log('Failed to create/find tag for keyword: ' . $keyword);
            }
        }
        
        // Apply all tags at once using term IDs instead of names/slugs to avoid comma parsing
        if (!empty($tag_ids)) {
            $append_tags = ($tag_mode === 'append');
            
            $this->debug_log('Applying tags to post', array(
                'tag_ids' => $tag_ids,
                'append_mode' => $append_tags
            ));
            
            wp_set_object_terms(
                $post_id, 
                $tag_ids, 
                'post_tag', 
                $append_tags
            );
            
            // Track which tags were generated by IPTC processing
            update_post_meta($post_id, '_iptc_generated_tags', $tag_ids);
        } else {
            // No keywords to apply, clear the tracking meta
            delete_post_meta($post_id, '_iptc_generated_tags');
        }
    }
    
    /**
     * Get or create a WordPress tag
     * 
     * @param string $tag_name The tag name
     * @return int|null The tag ID or null on error
     */
    private function get_or_create_tag($tag_name) {
        // Clean the tag name
        $tag_name = trim($tag_name);
        
        if (empty($tag_name)) {
            return null;
        }
        
        $term = term_exists($tag_name, 'post_tag');
        
        if ($term !== 0 && $term !== null) {
            $this->debug_log('Tag already exists', array(
                'tag_name' => $tag_name,
                'term_id' => $term['term_id']
            ));
            return $term['term_id'];
        } else {
            // Use wp_insert_term with proper args array to handle commas correctly
            $proposed_slug = sanitize_title($tag_name);
            
            $this->debug_log('Creating new tag', array(
                'tag_name' => $tag_name,
                'proposed_slug' => $proposed_slug
            ));
            
            $term = wp_insert_term(
                $tag_name,  // The term name
                'post_tag', // The taxonomy
                array(
                    'slug' => $proposed_slug
                )
            );
            
            if (is_wp_error($term)) {
                $this->debug_log('Tag creation failed', array(
                    'tag_name' => $tag_name,
                    'error_code' => $term->get_error_code(),
                    'error_message' => $term->get_error_message()
                ));
                return null;
            }
            
            $this->debug_log('Tag created successfully', array(
                'tag_name' => $tag_name,
                'term_id' => $term['term_id'],
                'actual_slug' => get_term($term['term_id'], 'post_tag')->slug ?? 'unknown'
            ));
            
            return $term['term_id'];
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
     * Get exclude substrings - removed hardcoded filters, users should control all filtering
     * 
     * @return array Substrings to exclude (empty array - no hardcoded exclusions)
     */
    private function get_exclude_substrings() {
        // No hardcoded exclusions - users control all filtering via blocked keywords
        return array();
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
        
        // Clean the keyword the same way we do during import
        $keyword = stripslashes(trim($keyword, '"\''));
        $keyword = trim($keyword);
        
        $result = $wpdb->insert(
            $table_name,
            array('keyword' => $keyword),
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
        
        // Clean the keyword the same way we do during import
        $keyword = stripslashes(trim($keyword, '"\''));
        $keyword = trim($keyword);
        
        $result = $wpdb->delete(
            $table_name,
            array('keyword' => $keyword),
            array('%s')
        );
        
        return $result !== false && $result > 0;
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
    
    /**
     * Debug logging helper
     * 
     * @param string $message Message to log
     * @param array $data Additional data to log
     */
    private function debug_log($message, $data = array()) {
        $settings = get_option('iptc_tagmaker_settings', array());
        
        if (empty($settings['debug_logging'])) {
            return;
        }
        
        $log_message = '[IPTC TagMaker] ' . $message;
        
        if (!empty($data)) {
            $log_message .= ' | Data: ' . print_r($data, true);
        }
        
        error_log($log_message);
    }
    

}