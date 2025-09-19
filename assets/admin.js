/**
 * IPTC TagMaker Admin JavaScript
 */

(function($) {
    'use strict';
    
    var iptcAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Add blocked keyword
            $('#add-blocked-keyword').on('click', this.addBlockedKeyword);
            
            // Remove blocked keyword
            $(document).on('click', '.remove-blocked-keyword', this.removeBlockedKeyword);
            
            // Clear all blocked keywords
            $('#clear-all-blocked-keywords').on('click', this.clearAllBlockedKeywords);
            
            // Add keyword substitution
            $('#add-keyword-substitution').on('click', this.addKeywordSubstitution);
            
            // Remove keyword substitution
            $(document).on('click', '.remove-keyword-substitution', this.removeKeywordSubstitution);
            
            // Clear all keyword substitutions
            $('#clear-all-keyword-substitutions').on('click', this.clearAllKeywordSubstitutions);
            
            // Bulk import handlers
            $('#import-blocked-keywords').on('click', this.bulkImportBlockedKeywords);
            $('#import-substitutions').on('click', this.bulkImportSubstitutions);
            
            // Toggle bulk import sections
            $('#show-bulk-blocked, #toggle-bulk-blocked').on('click', this.toggleBulkBlocked);
            $('#show-bulk-substitutions, #toggle-bulk-substitutions').on('click', this.toggleBulkSubstitutions);
            
            // Enter key handlers
            $('#new-blocked-keyword').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#add-blocked-keyword').trigger('click');
                }
            });
            
            $('#original-keyword, #replacement-keyword').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#add-keyword-substitution').trigger('click');
                }
            });
        },
        
        /**
         * Add blocked keyword
         */
        addBlockedKeyword: function() {
            var $input = $('#new-blocked-keyword');
            var keyword = $input.val().trim();
            var $button = $(this);
            
            if (!keyword) {
                iptcAdmin.showNotification(iptcTagMaker.strings.keywordRequired, 'error');
                return;
            }
            
            $button.prop('disabled', true).text(iptcTagMaker.strings.addingKeyword);
            
            $.ajax({
                url: iptcTagMaker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'iptc_add_blocked_keyword',
                    keyword: keyword,
                    nonce: iptcTagMaker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $input.val('');
                        $('#blocked-keywords-list').html(response.data.html);
                        iptcAdmin.showNotification(response.data.message, 'success');
                    } else {
                        iptcAdmin.showNotification(response.data || iptcTagMaker.strings.errorOccurred, 'error');
                    }
                },
                error: function() {
                    iptcAdmin.showNotification(iptcTagMaker.strings.errorOccurred, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Add Blocked Keyword');
                }
            });
        },
        
        /**
         * Remove blocked keyword
         */
        removeBlockedKeyword: function() {
            var keyword = $(this).data('keyword');
            var $button = $(this);
            
            if (!confirm(iptcTagMaker.strings.confirmDelete)) {
                return;
            }
            
            $button.prop('disabled', true).text(iptcTagMaker.strings.removingKeyword);
            
            $.ajax({
                url: iptcTagMaker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'iptc_remove_blocked_keyword',
                    keyword: keyword,
                    nonce: iptcTagMaker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#blocked-keywords-list').html(response.data.html);
                        iptcAdmin.showNotification(response.data.message, 'success');
                    } else {
                        iptcAdmin.showNotification(response.data || iptcTagMaker.strings.errorOccurred, 'error');
                    }
                },
                error: function() {
                    iptcAdmin.showNotification(iptcTagMaker.strings.errorOccurred, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Remove');
                }
            });
        },
        
        /**
         * Add keyword substitution
         */
        addKeywordSubstitution: function() {
            var $originalInput = $('#original-keyword');
            var $replacementInput = $('#replacement-keyword');
            var original = $originalInput.val().trim();
            var replacement = $replacementInput.val().trim();
            var $button = $(this);
            
            if (!original || !replacement) {
                iptcAdmin.showNotification(iptcTagMaker.strings.substitutionRequired, 'error');
                return;
            }
            
            $button.prop('disabled', true).text(iptcTagMaker.strings.addingKeyword);
            
            $.ajax({
                url: iptcTagMaker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'iptc_add_keyword_substitution',
                    original: original,
                    replacement: replacement,
                    nonce: iptcTagMaker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $originalInput.val('');
                        $replacementInput.val('');
                        $('#keyword-substitutions-list').html(response.data.html);
                        iptcAdmin.showNotification(response.data.message, 'success');
                    } else {
                        iptcAdmin.showNotification(response.data || iptcTagMaker.strings.errorOccurred, 'error');
                    }
                },
                error: function() {
                    iptcAdmin.showNotification(iptcTagMaker.strings.errorOccurred, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Add Substitution');
                }
            });
        },
        
        /**
         * Remove keyword substitution
         */
        removeKeywordSubstitution: function() {
            var original = $(this).data('original');
            var $button = $(this);
            
            if (!confirm(iptcTagMaker.strings.confirmDelete)) {
                return;
            }
            
            $button.prop('disabled', true).text(iptcTagMaker.strings.removingKeyword);
            
            $.ajax({
                url: iptcTagMaker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'iptc_remove_keyword_substitution',
                    original: original,
                    nonce: iptcTagMaker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#keyword-substitutions-list').html(response.data.html);
                        iptcAdmin.showNotification(response.data.message, 'success');
                    } else {
                        iptcAdmin.showNotification(response.data || iptcTagMaker.strings.errorOccurred, 'error');
                    }
                },
                error: function() {
                    iptcAdmin.showNotification(iptcTagMaker.strings.errorOccurred, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Remove');
                }
            });
        },
        
        /**
         * Clear all blocked keywords
         */
        clearAllBlockedKeywords: function() {
            if (!confirm('Are you sure you want to clear ALL blocked keywords? This action cannot be undone.')) {
                return;
            }
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: iptcTagMaker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'iptc_clear_all_blocked_keywords',
                    nonce: iptcTagMaker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#blocked-keywords-list').html(response.data.html);
                        iptcAdmin.showNotification(response.data.message, 'success');
                    } else {
                        iptcAdmin.showNotification(response.data || iptcTagMaker.strings.errorOccurred, 'error');
                    }
                },
                error: function() {
                    iptcAdmin.showNotification(iptcTagMaker.strings.errorOccurred, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Clear all keyword substitutions
         */
        clearAllKeywordSubstitutions: function() {
            if (!confirm('Are you sure you want to clear ALL keyword substitutions? This action cannot be undone.')) {
                return;
            }
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: iptcTagMaker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'iptc_clear_all_keyword_substitutions',
                    nonce: iptcTagMaker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#keyword-substitutions-list').html(response.data.html);
                        iptcAdmin.showNotification(response.data.message, 'success');
                    } else {
                        iptcAdmin.showNotification(response.data || iptcTagMaker.strings.errorOccurred, 'error');
                    }
                },
                error: function() {
                    iptcAdmin.showNotification(iptcTagMaker.strings.errorOccurred, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Bulk import blocked keywords
         */
        bulkImportBlockedKeywords: function() {
            var $textarea = $('#bulk-blocked-keywords');
            var keywords = $textarea.val().trim();
            var $button = $(this);
            var originalText = $button.text();
            
            if (!keywords) {
                iptcAdmin.showNotification('Please enter keywords to import.', 'error');
                return;
            }
            
            $button.prop('disabled', true).text('Importing...');
            
            $.ajax({
                url: iptcTagMaker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'iptc_bulk_import_blocked_keywords',
                    keywords: keywords,
                    nonce: iptcTagMaker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $textarea.val('');
                        $('#blocked-keywords-list').html(response.data.html);
                        iptcAdmin.showNotification(response.data.message, 'success');
                    } else {
                        iptcAdmin.showNotification(response.data || iptcTagMaker.strings.errorOccurred, 'error');
                    }
                },
                error: function() {
                    iptcAdmin.showNotification(iptcTagMaker.strings.errorOccurred, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Bulk import keyword substitutions
         */
        bulkImportSubstitutions: function() {
            var $textarea = $('#bulk-substitutions');
            var substitutions = $textarea.val().trim();
            var $button = $(this);
            var originalText = $button.text();
            
            if (!substitutions) {
                iptcAdmin.showNotification('Please enter substitutions to import.', 'error');
                return;
            }
            
            $button.prop('disabled', true).text('Importing...');
            
            $.ajax({
                url: iptcTagMaker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'iptc_bulk_import_substitutions',
                    substitutions: substitutions,
                    nonce: iptcTagMaker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $textarea.val('');
                        $('#keyword-substitutions-list').html(response.data.html);
                        iptcAdmin.showNotification(response.data.message, 'success');
                    } else {
                        iptcAdmin.showNotification(response.data || iptcTagMaker.strings.errorOccurred, 'error');
                    }
                },
                error: function() {
                    iptcAdmin.showNotification(iptcTagMaker.strings.errorOccurred, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Toggle bulk blocked keywords section
         */
        toggleBulkBlocked: function() {
            var $section = $('.iptc-admin-section').first().find('.iptc-bulk-import');
            var $showButton = $('#show-bulk-blocked');
            
            if ($section.is(':visible')) {
                $section.hide();
                $showButton.show();
            } else {
                $section.show();
                $showButton.hide();
            }
        },
        
        /**
         * Toggle bulk substitutions section
         */
        toggleBulkSubstitutions: function() {
            var $section = $('.iptc-admin-section').last().find('.iptc-bulk-import');
            var $showButton = $('#show-bulk-substitutions');
            
            if ($section.is(':visible')) {
                $section.hide();
                $showButton.show();
            } else {
                $section.show();
                $showButton.hide();
            }
        },
        
        /**
         * Show notification message
         */
        showNotification: function(message, type) {
            var $notifications = $('#iptc-admin-notifications');
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            
            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $notifications.empty().append($notice);
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
            
            // Scroll to top to show notification
            $('html, body').animate({
                scrollTop: $notifications.offset().top - 50
            }, 300);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        iptcAdmin.init();
    });
    
})(jQuery);