/**
 * WooCommerce Performance Analyzer - Admin Dashboard Scripts
 *
 * @package suspended\WCPerformanceAnalyzer
 */

/* global jQuery, wcpaAdmin */

(function($) {
    'use strict';

    /**
     * WCPA Admin Dashboard Module
     */
    const WCPADashboard = {

        /**
         * Initialize the module.
         */
        init: function() {
            this.bindEvents();
            this.initComponents();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            // Health scan button
            $(document).on('click', '.wcpa-run-scan:not(:disabled)', this.handleRunScan.bind(this));

            // Cleanup preview buttons
            $(document).on('click', '.wcpa-preview-cleanup:not(:disabled)', this.handlePreviewCleanup.bind(this));

            // Cleanup execute buttons
            $(document).on('click', '.wcpa-execute-cleanup:not(:disabled)', this.handleExecuteCleanup.bind(this));

            // Query log toggle
            $(document).on('change', '.wcpa-toggle-query-log', this.handleToggleQueryLog.bind(this));
        },

        /**
         * Initialize UI components.
         */
        initComponents: function() {
            // Future: Initialize charts, gauges, etc.
        },

        /**
         * Handle health scan button click.
         *
         * @param {Event} e Click event.
         */
        handleRunScan: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);

            this.setButtonLoading($button, true);

            this.apiRequest('scan', 'POST')
                .done(function(response) {
                    if (response.success) {
                        WCPADashboard.updateHealthScore(response.data);
                        WCPADashboard.updateMetrics(response.data.metrics);
                        WCPADashboard.showNotice('success', wcpaAdmin.strings.success);
                    } else {
                        WCPADashboard.showNotice('error', response.data.message || wcpaAdmin.strings.error);
                    }
                })
                .fail(function() {
                    WCPADashboard.showNotice('error', wcpaAdmin.strings.error);
                })
                .always(function() {
                    WCPADashboard.setButtonLoading($button, false);
                });
        },

        /**
         * Handle cleanup preview button click.
         *
         * @param {Event} e Click event.
         */
        handlePreviewCleanup: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const cleanupType = $button.data('type');

            this.setButtonLoading($button, true);

            this.apiRequest('cleanup/preview', 'POST', { type: cleanupType })
                .done(function(response) {
                    if (response.success) {
                        WCPADashboard.showCleanupPreview(cleanupType, response.data);
                    } else {
                        WCPADashboard.showNotice('error', response.data.message || wcpaAdmin.strings.error);
                    }
                })
                .fail(function() {
                    WCPADashboard.showNotice('error', wcpaAdmin.strings.error);
                })
                .always(function() {
                    WCPADashboard.setButtonLoading($button, false);
                });
        },

        /**
         * Handle cleanup execute button click.
         *
         * @param {Event} e Click event.
         */
        handleExecuteCleanup: function(e) {
            e.preventDefault();

            if (!confirm(wcpaAdmin.strings.confirmClean)) {
                return;
            }

            const $button = $(e.currentTarget);
            const cleanupType = $button.data('type');

            this.setButtonLoading($button, true);

            this.apiRequest('cleanup/run', 'POST', { type: cleanupType })
                .done(function(response) {
                    if (response.success) {
                        WCPADashboard.updateCleanupStats(cleanupType, response.data);
                        WCPADashboard.showNotice('success', response.data.message || wcpaAdmin.strings.success);
                    } else {
                        WCPADashboard.showNotice('error', response.data.message || wcpaAdmin.strings.error);
                    }
                })
                .fail(function() {
                    WCPADashboard.showNotice('error', wcpaAdmin.strings.error);
                })
                .always(function() {
                    WCPADashboard.setButtonLoading($button, false);
                });
        },

        /**
         * Handle query log toggle.
         *
         * @param {Event} e Change event.
         */
        handleToggleQueryLog: function(e) {
            const enabled = $(e.currentTarget).is(':checked');

            this.apiRequest('settings', 'POST', { query_log_enabled: enabled })
                .done(function(response) {
                    if (response.success) {
                        WCPADashboard.showNotice('success', wcpaAdmin.strings.success);
                    } else {
                        // Revert toggle
                        $(e.currentTarget).prop('checked', !enabled);
                        WCPADashboard.showNotice('error', response.data.message || wcpaAdmin.strings.error);
                    }
                })
                .fail(function() {
                    // Revert toggle
                    $(e.currentTarget).prop('checked', !enabled);
                    WCPADashboard.showNotice('error', wcpaAdmin.strings.error);
                });
        },

        /**
         * Make API request.
         *
         * @param {string} endpoint API endpoint.
         * @param {string} method   HTTP method.
         * @param {Object} data     Request data.
         * @return {jqXHR} jQuery AJAX object.
         */
        apiRequest: function(endpoint, method, data) {
            return $.ajax({
                url: wcpaAdmin.restUrl + endpoint,
                method: method || 'GET',
                data: data || {},
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wcpaAdmin.restNonce);
                }
            });
        },

        /**
         * Set button loading state.
         *
         * @param {jQuery} $button Button element.
         * @param {boolean} loading Loading state.
         */
        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.addClass('wcpa-loading').prop('disabled', true);
            } else {
                $button.removeClass('wcpa-loading').prop('disabled', false);
            }
        },

        /**
         * Update health score display.
         *
         * @param {Object} data Scan response data.
         */
        updateHealthScore: function(data) {
            const $gauge = $('.wcpa-score-placeholder');
            const score = data.health_score || 0;

            // Remove existing score classes
            $gauge.removeClass('wcpa-score-excellent wcpa-score-good wcpa-score-attention wcpa-score-critical');

            // Add appropriate class
            if (score >= 80) {
                $gauge.addClass('wcpa-score-excellent');
            } else if (score >= 60) {
                $gauge.addClass('wcpa-score-good');
            } else if (score >= 40) {
                $gauge.addClass('wcpa-score-attention');
            } else {
                $gauge.addClass('wcpa-score-critical');
            }

            // Update score text
            $gauge.text(score);

            // Update label
            $('.wcpa-score-label').text(data.score_label || '');
        },

        /**
         * Update metrics display.
         *
         * @param {Object} metrics Metrics data.
         */
        updateMetrics: function(metrics) {
            if (!metrics) {
                return;
            }

            // Update each metric card
            Object.keys(metrics).forEach(function(key) {
                const $card = $('.wcpa-metric-card[data-metric="' + key + '"]');
                if ($card.length) {
                    $card.find('.wcpa-metric-value').text(metrics[key]);
                }
            });
        },

        /**
         * Show cleanup preview.
         *
         * @param {string} type Cleanup type.
         * @param {Object} data Preview data.
         */
        showCleanupPreview: function(type, data) {
            const $card = $('.wcpa-cleanup-card[data-type="' + type + '"]');
            if ($card.length) {
                $card.find('.wcpa-count').text(data.count || 0);
            }
        },

        /**
         * Update cleanup stats after execution.
         *
         * @param {string} type Cleanup type.
         * @param {Object} data Response data.
         */
        updateCleanupStats: function(type, data) {
            const $card = $('.wcpa-cleanup-card[data-type="' + type + '"]');
            if ($card.length) {
                $card.find('.wcpa-count').text(0);
            }
        },

        /**
         * Show admin notice.
         *
         * @param {string} type    Notice type (success, error, warning, info).
         * @param {string} message Notice message.
         */
        showNotice: function(type, message) {
            // Remove existing notices
            $('.wcpa-admin-notice').remove();

            const $notice = $('<div class="notice wcpa-admin-notice is-dismissible"></div>')
                .addClass('notice-' + type)
                .append($('<p></p>').text(message));

            $('.wcpa-wrap > h1').after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WCPADashboard.init();
    });

})(jQuery);
