# IPTC TagMaker WordPress Plugin

A WordPress plugin that automatically extracts IPTC keywords from the first image in posts and converts them to WordPress tags, with advanced keyword blocking and substitution features.

## Features

- **Automatic Keyword Processing**: Extracts IPTC keywords from images and converts them to WordPress tags
- **Smart Image Detection**: Processes the first image found in posts (featured image or first attached image)
- **Keyword Blocking**: User-friendly interface to block unwanted keywords
- **Keyword Substitution**: Replace specific keywords with preferred alternatives
- **Manual Processing**: Meta box in post editor for manual keyword processing and preview
- **Configurable Settings**: Control auto-processing, tag replacement behavior, and more
- **Database Optimization**: Efficient storage using custom database tables

## Installation

1. Upload the plugin files to `/wp-content/plugins/iptc-tagmaker/`
2. Activate the plugin through the WordPress admin interface
3. Go to Settings > IPTC TagMaker to configure the plugin

## Usage

### Automatic Processing

When enabled in settings, the plugin will automatically process IPTC keywords whenever:
- A post is saved
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

## Development

### Extending the Plugin

You can extend the plugin by:
- Adding custom filters for keyword processing
- Modifying the exclude substrings list
- Adding new IPTC fields to process

### Hooks and Filters

The plugin provides several WordPress hooks for customization:
- Actions for post processing events
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