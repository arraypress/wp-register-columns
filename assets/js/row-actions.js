/**
 * Row Actions - AJAX JavaScript
 *
 * Handles AJAX row actions in WordPress admin tables.
 *
 * @package ArrayPress\WP\RegisterRowActions
 * @version 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * Row Action Handler
     */
    const RowActionHandler = {

        /**
         * Initialize the handler
         */
        init() {
            this.bindEvents();
        },

        /**
         * Bind click events to AJAX action links
         */
        bindEvents() {
            $(document).on('click', '.row-action-ajax', (e) => {
                e.preventDefault();
                this.handleAction($(e.currentTarget));
            });
        },

        /**
         * Handle an action click
         *
         * @param {jQuery} $link The clicked link element
         */
        async handleAction($link) {
            const config = window.rowActionsConfig || {};
            const strings = config.strings || {};

            // Get confirmation if needed
            const confirmMessage = $link.data('confirm');
            if (confirmMessage && !window.confirm(confirmMessage)) {
                return;
            }

            // Get action data
            const data = {
                // From the link, not rebuilt here: the action carries this
                // build's prefix, which the browser has no way to derive, and
                // a shared config object would be owned by whichever copy
                // localised last.
                action: $link.data('ajax-action'),
                action_key: $link.data('action-key'),
                object_id: $link.data('object-id'),
                _wpnonce: $link.data('nonce'),
                options: {}
            };

            // Visual feedback - store original text and show spinner
            const originalHtml = $link.html();
            const hasIcon = $link.find('.dashicons').length > 0;

            if (hasIcon) {
                const $icon = $link.find('.dashicons');
                $icon.removeClass().addClass('dashicons dashicons-update-alt row-action-spin');
            } else {
                $link.html('<span class="dashicons dashicons-update-alt row-action-spin"></span> ' + (strings.processing || 'Processing...'));
            }

            // Disable the link
            $link.css('pointer-events', 'none').css('opacity', '0.6');

            try {
                const response = await $.ajax({
                    url: config.ajaxUrl || ajaxurl,
                    type: 'POST',
                    data: data
                });

                if (response.success) {
                    // Handle success
                    this.handleSuccess($link, response.data, originalHtml);
                } else {
                    // Handle error
                    this.handleError($link, response.data, originalHtml, strings);
                }

            } catch (error) {
                // Handle AJAX error
                this.handleError($link, {message: error.statusText || 'AJAX request failed'}, originalHtml, strings);
            }
        },

        /**
         * Handle successful response
         *
         * @param {jQuery} $link The link element
         * @param {Object} data Response data
         * @param {string} originalHtml Original link HTML
         */
        handleSuccess($link, data, originalHtml) {
            const strings = (window.rowActionsConfig || {}).strings || {};

            // Show success message if provided
            if (data.message) {
                this.showNotice(data.message, 'success');
            }

            // Default to reload unless explicitly set to false
            const shouldReload = data.reload !== false;

            if (shouldReload) {
                // Brief visual feedback before reload
                $link.html('<span class="dashicons dashicons-yes"></span> ' + (strings.success || 'Success'));
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                // No reload - show success icon briefly then restore
                $link.html('<span class="dashicons dashicons-yes"></span>');
                setTimeout(() => {
                    $link.html(originalHtml);
                    $link.css('pointer-events', '').css('opacity', '');
                }, 1000);
            }
        },

        /**
         * Handle error response
         *
         * @param {jQuery} $link The link element
         * @param {Object} data Response data
         * @param {string} originalHtml Original link HTML
         * @param {Object} strings Localized strings
         */
        handleError($link, data, originalHtml, strings) {
            // Show error message
            const message = data?.message || (strings.error || 'Error') + ': Unknown error';
            this.showNotice(message, 'error');

            // Show error icon briefly
            $link.html('<span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>');

            // Restore original state after showing error
            setTimeout(() => {
                $link.html(originalHtml);
                $link.css('pointer-events', '').css('opacity', '');
            }, 2000);
        },

        /**
         * Show admin notice
         *
         * @param {string} message The message to display
         * @param {string} type The notice type (success, error, warning, info)
         */
        showNotice(message, type = 'success') {
            const noticeClass = type === 'error' ? 'notice-error' :
                type === 'warning' ? 'notice-warning' :
                    type === 'info' ? 'notice-info' :
                        'notice-success';

            const $notice = $(`
                <div class="notice ${noticeClass} is-dismissible">
                    <p>${message}</p>
                </div>
            `);

            // Find the best place to insert the notice
            const $target = $('.wrap > h1, .wrap > h2').first();
            if ($target.length) {
                $target.after($notice);
            } else {
                $('.wrap').prepend($notice);
            }

            // Make dismissible
            $notice.on('click', '.notice-dismiss', function () {
                $notice.fadeOut(() => $notice.remove());
            });

            // Auto-remove success notices
            if (type === 'success') {
                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, 5000);
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(() => {
        RowActionHandler.init();
    });

    // Expose to global scope if needed
    window.RowActionHandler = RowActionHandler;

})(jQuery);