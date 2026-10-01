# Changelog

## Version 1.3.0 - November 1, 2025

### Added
- **WebP Support**: Plugin now extracts keywords from WebP images using XMP metadata
- Support for multiple XMP keyword formats:
  - Dublin Core (dc:subject)
  - IPTC Core (Iptc4xmpCore:SubjectCode)
  - Lightroom hierarchical keywords (lr:hierarchicalSubject)
- New internal methods for WebP processing:
  - `extract_keywords_from_webp()` - Extracts XMP data from WebP files
  - `extract_xmp_from_webp()` - Parses WebP chunk structure to find XMP data
  - `parse_keywords_from_xmp()` - Parses keywords from XMP using XML
  - `parse_keywords_from_xmp_regex()` - Fallback regex-based XMP parser

### Changed
- `extract_keywords_from_image()` - Now routes to appropriate extraction method based on file type
- `process_keywords_for_post()` - Updated to use new unified extraction method
- Plugin description updated to reflect multi-format support
- Version number updated from 1.2.0 to 1.3.0

### Technical Details
- JPG files continue to use IPTC metadata extraction via `getimagesize()` and `iptcparse()`
- WebP files use binary chunk parsing to locate XMP metadata
- XMP parsing supports both XML parsing and regex fallback for robustness
- All existing functionality for JPG files remains unchanged

### Compatibility
- Maintains backward compatibility with existing JPG/IPTC workflow
- No database changes required
- No changes to settings or user interface
- Works with WordPress 5.0+ and PHP 7.4+
