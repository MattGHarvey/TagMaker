# IPTC TagMaker WordPress Plugin

A WordPress plugin that automatically extracts IPTC keywords from the first image in posts and converts them to WordPress tags, with advanced keyword blocking and substitution features.

## Features

- **Automatic Keyword Processing**: Extracts IPTC keywords from images and converts them to WordPress tags
- **Smart Image Detection**: Processes the first image found in post content using original full-size images
- **Keyword Blocking**: User-friendly interface to block unwanted keywords with bulk import/export
- **Keyword Substitution**: Replace specific keywords with preferred alternatives
- **Manual Processing**: Meta box in post editor for manual keyword processing and preview
- **Bulk Operations**: Import/export blocked keywords and substitutions via comma-delimited text
- **Configurable Settings**: Control auto-processing and tag replacement behavior
- **Database Optimization**: Efficient storage using custom database tables
- **WordPress Standards**: Follows WordPress coding standards and best practices

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Images with IPTC metadata keywords

## Installation

### From WordPress Admin (Recommended)
1. Navigate to **Plugins > Add New** in your WordPress admin
2. Search for "IPTC TagMaker"  
3. Click **Install Now** and then **Activate**
4. Go to **Settings > IPTC TagMaker** to configure

### Manual Installation
1. Download the plugin ZIP file
2. Upload the plugin files to `/wp-content/plugins/iptc-tagmaker/`
3. Activate the plugin through the **Plugins** menu in WordPress
4. Go to **Settings > IPTC TagMaker** to configure

## Configuration

### General Settings
- **Auto-process keywords**: Automatically process IPTC keywords when posts are saved
- **Remove existing tags**: Remove current tags before adding new ones from IPTC

The plugin automatically processes the first image found in the post content, ensuring the most relevant image is used for keyword extraction.

### Blocked Keywords
- Add individual keywords to block from becoming tags
- Bulk import comma-delimited lists of blocked keywords
- Clear all blocked keywords with one click

### Keyword Substitutions
- Replace unwanted keywords with preferred alternatives
- Format: `original keyword => replacement keyword`
- Bulk import substitution rules
- Clear all substitutions with one click

## Usage

### Automatic Processing

When enabled in settings, the plugin will automatically process IPTC keywords whenever:
- A post is saved or updated
- A post status changes to "published"

### Manual Processing

In the post editor, you'll find an "IPTC TagMaker" meta box that allows you to:
- Preview keywords that would be extracted
- Manually process keywords for the current post
- See which image will be processed

### Managing Blocked Keywords

Go to Settings > IPTC TagMaker to:
- Add keywords to the blocked list
- Remove keywords from the blocked list
- View all currently blocked keywords

### Managing Keyword Substitutions

In the same settings page, you can:
- Add keyword substitution rules (original → replacement)
- Remove existing substitution rules
- View all current substitutions

## Settings

- **Auto-process on Post Save**: Automatically process keywords when posts are saved
- **Remove Existing Tags**: Clear existing tags before adding new ones from IPTC
- **Process Featured Image Only**: Only process featured images (ignore other attachments)
- **Minimum Keywords for Full Keywords Meta**: Minimum number of keywords required to save full keywords metadata

## Technical Details

### Database Tables

The plugin creates two custom tables:
- `wp_iptc_blocked_keywords`: Stores blocked keywords
- `wp_iptc_keyword_substitutions`: Stores keyword substitution rules

### IPTC Processing

The plugin reads IPTC metadata from image files using PHP's `iptcparse()` function. It specifically looks for:
- IPTC field `2#025` (Keywords)

### Filtering Logic

Keywords are filtered based on:
1. Blocked keywords list
2. Hardcoded exclude substrings (camera technical terms)
3. Keyword substitution rules

### WordPress Integration

The plugin hooks into:
- `save_post` action for automatic processing
- `transition_post_status` for publish events
- Admin interface for settings and management
- AJAX endpoints for dynamic keyword management

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Images with IPTC metadata

## File Structure

```
iptc-tagmaker/
├── iptc-tagmaker.php          # Main plugin file
├── includes/
│   ├── class-iptc-keyword-processor.php  # Keyword processing logic
│   ├── class-iptc-admin.php              # Admin interface
│   └── class-iptc-post-handler.php       # Post save handling
├── assets/
│   ├── admin.css              # Admin interface styles
│   └── admin.js               # Admin interface JavaScript
└── README.md                  # This file
```

Use the **IPTC Keywords** meta box in the post editor to:
- Preview keywords that would be extracted from images
- Manually process keywords for individual posts
- See which keywords are blocked or substituted

### Bulk Import Formats

**Blocked Keywords**: Comma-separated list
```
camera, lens, technical, metadata, settings
```

**Keyword Substitutions**: Arrow-separated pairs
```
NYC => New York City, LA => Los Angeles, SF => San Francisco
```

## Troubleshooting

### Common Issues

**No keywords extracted**
- Ensure images contain IPTC keyword metadata
- Check that images are properly attached to posts
- Verify IPTC keywords field (2#025) exists in image metadata

**Keywords not appearing as tags**
- Check if keywords are in the blocked list
- Verify auto-processing is enabled in settings
- Ensure user has permission to manage post tags

**Bulk import not working**
- Use proper format: comma-separated for blocked keywords
- For substitutions, use format: `original => replacement`
- Remove quotes around individual keywords

### Debug Information

Enable WordPress debug logging by adding to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## FAQ

**Q: Can I process existing posts?**
A: Yes, use the meta box in each post editor to manually process keywords, or re-save posts to trigger auto-processing.

**Q: Does this work with all image formats?**
A: The plugin works with any image format that supports IPTC metadata (JPEG, TIFF, etc.). PNG files typically don't contain IPTC data.

**Q: Can I export my blocked keywords and substitutions?**
A: Currently, export functionality is not built-in, but you can access the data via the database tables `wp_iptc_blocked_keywords` and `wp_iptc_keyword_substitutions`.

**Q: Will this slow down my site?**
A: The plugin only processes images when posts are saved, not on every page load. Processing is efficient and uses WordPress's built-in image handling functions.

## Changelog

### 1.0.0
- Initial release
- Automatic IPTC keyword extraction from first image in post content
- Smart image detection using catch_that_image() method
- Keyword blocking and substitution with bulk operations
- Admin interface with bulk import/export
- Proper tag clearing when images change
- Manual processing via post meta box
- Support for comma-containing tags
- WordPress standards compliance

## Development

### Extending the Plugin

You can extend the plugin by:
- Adding custom filters for keyword processing
- Modifying the exclude substrings list
- Adding new IPTC fields to process

### Hooks and Filters

The plugin provides several WordPress hooks for customization:
- Actions for post processing events
- Filters for keyword manipulation
- Database interaction hooks

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This plugin is licensed under the GPL v2 or later.

## Support

- GitHub Issues: [https://github.com/MattGHarvey/TagMaker/issues](https://github.com/MattGHarvey/TagMaker/issues)
- Documentation: [https://github.com/MattGHarvey/TagMaker#readme](https://github.com/MattGHarvey/TagMaker#readme)

## Credits

Developed by [Matt Harvey](https://github.com/MattGHarvey)

Built with WordPress best practices and modern PHP standards.
- Filters for keyword modification
- AJAX endpoints for custom functionality

## Changelog

### Version 1.0.0
- Initial release
- Basic IPTC keyword extraction
- Blocked keywords management
- Keyword substitution system
- Auto-processing on post save
- Manual processing interface

## Support

For support and bug reports, please visit the plugin's GitHub repository.

## License

This plugin is licensed under the GPL v2 or later.

---

Built with ❤️ for WordPress content creators who use IPTC metadata.