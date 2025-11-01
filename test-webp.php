<?php
/**
 * Test WebP XMP extraction
 * 
 * This is a standalone test script to debug WebP XMP extraction
 * Upload your WebP file to WordPress and replace the path below
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

// Make sure you're an admin
if (!current_user_can('manage_options')) {
    die('You must be an administrator to run this test.');
}

// Set the attachment ID or file path of your WebP file
$attachment_id = null; // Set this to your attachment ID, or leave null to use file path
$file_path = '/Applications/MAMP/htdocs/wpdev/wp-content/uploads/2025/11/Sea-Wall-2048x1365.webp'; // Set this to your file path if not using attachment ID

if ($attachment_id) {
    $file_path = get_attached_file($attachment_id);
}

// Try to find the original full-size image by removing size suffix
$original_file_path = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $file_path);

echo '<h1>WebP XMP Extraction Test</h1>';

if ($original_file_path !== $file_path) {
    echo '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 10px 0;">';
    echo '<strong>Note:</strong> The provided file appears to be a resized version. Trying original full-size image instead.<br>';
    echo '<strong>Resized file:</strong> ' . htmlspecialchars($file_path) . '<br>';
    echo '<strong>Original file:</strong> ' . htmlspecialchars($original_file_path);
    echo '</div>';
    
    if (file_exists($original_file_path)) {
        $file_path = $original_file_path;
        echo '<p style="color: green;">✓ Original full-size file found! Using: ' . htmlspecialchars($file_path) . '</p>';
    } else {
        echo '<p style="color: red;">✗ Original file not found. Proceeding with resized version (may not have metadata).</p>';
    }
}

if (!file_exists($file_path)) {
    die('File not found: ' . $file_path);
}

echo '<p><strong>File:</strong> ' . htmlspecialchars($file_path) . '</p>';
echo '<p><strong>File Size:</strong> ' . filesize($file_path) . ' bytes</p>';
echo '<p><strong>MIME Type:</strong> ' . mime_content_type($file_path) . '</p>';

// Read the file
$contents = file_get_contents($file_path);

echo '<hr>';
echo '<h2>File Header Analysis</h2>';
echo '<p><strong>First 12 bytes:</strong> ' . bin2hex(substr($contents, 0, 12)) . '</p>';
echo '<p><strong>RIFF header:</strong> ' . substr($contents, 0, 4) . '</p>';
echo '<p><strong>WEBP signature:</strong> ' . substr($contents, 8, 4) . '</p>';

// Search for XMP chunk
$pos = 12;
$length = strlen($contents);
$chunks_found = array();

echo '<hr>';
echo '<h2>WebP Chunks</h2>';
echo '<table border="1" cellpadding="5">';
echo '<tr><th>#</th><th>Position</th><th>Header</th><th>Size</th></tr>';

$chunk_count = 0;
$xmp_found = false;
$xmp_data = null;

while ($pos < $length - 8 && $chunk_count < 20) {
    $chunk_header = substr($contents, $pos, 4);
    $chunk_size = unpack('V', substr($contents, $pos + 4, 4))[1];
    
    $chunk_count++;
    echo '<tr>';
    echo '<td>' . $chunk_count . '</td>';
    echo '<td>' . $pos . '</td>';
    echo '<td><strong>' . htmlspecialchars($chunk_header) . '</strong></td>';
    echo '<td>' . $chunk_size . '</td>';
    echo '</tr>';
    
    if ($chunk_header === 'XMP ') {
        $xmp_found = true;
        $xmp_data = substr($contents, $pos + 8, $chunk_size);
        echo '<tr><td colspan="4" style="background: #efe;"><strong>XMP CHUNK FOUND!</strong></td></tr>';
    }
    
    $pos += 8 + $chunk_size;
    if ($chunk_size % 2 == 1) {
        $pos++;
    }
}

echo '</table>';

if ($xmp_found && $xmp_data) {
    echo '<hr>';
    echo '<h2>XMP Data Found!</h2>';
    echo '<p><strong>XMP Size:</strong> ' . strlen($xmp_data) . ' bytes</p>';
    
    echo '<h3>XMP Content (first 2000 characters):</h3>';
    echo '<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">';
    echo htmlspecialchars(substr($xmp_data, 0, 2000));
    echo '</pre>';
    
    // Try to parse keywords
    echo '<h3>Looking for dc:subject...</h3>';
    
    // Simple regex search for Subject
    if (preg_match('/<dc:subject>([^<]+)<\/dc:subject>/i', $xmp_data, $match)) {
        echo '<p style="background: #efe; padding: 10px;"><strong>Found dc:subject:</strong><br>';
        echo htmlspecialchars($match[1]);
        echo '</p>';
        
        // Split by comma
        $keywords = array_map('trim', explode(',', $match[1]));
        echo '<h4>Extracted Keywords (' . count($keywords) . '):</h4>';
        echo '<ul>';
        foreach ($keywords as $keyword) {
            echo '<li>' . htmlspecialchars($keyword) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p style="background: #fee; padding: 10px;">No dc:subject found with simple regex</p>';
        
        // Try to find it another way
        if (strpos($xmp_data, 'dc:subject') !== false) {
            echo '<p>Found "dc:subject" string in XMP data at position: ' . strpos($xmp_data, 'dc:subject') . '</p>';
            
            // Show context around dc:subject
            $pos = strpos($xmp_data, 'dc:subject');
            echo '<pre style="background: #f5f5f5; padding: 10px;">';
            echo htmlspecialchars(substr($xmp_data, max(0, $pos - 100), 500));
            echo '</pre>';
        } else {
            echo '<p>String "dc:subject" not found in XMP data at all</p>';
        }
    }
    
} else {
    echo '<hr>';
    echo '<h2 style="color: red;">No XMP Chunk Found</h2>';
    echo '<p>Processed ' . $chunk_count . ' chunks but no XMP data was found.</p>';
}
