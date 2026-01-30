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

            // Clear query logs
            $(document).on('click', '.wcpa-clear-logs:not(:disabled)', this.handleClearQueryLogs.bind(this));

            // Query log filters
            $(document).on('click', '.wcpa-apply-filters', this.handleApplyFilters.bind(this));
            $(document).on('click', '.wcpa-reset-filters', this.handleResetFilters.bind(this));

            // View query log details
            $(document).on('click', '.wcpa-view-log-detail', this.handleViewLogDetail.bind(this));

            // Query log pagination
            $(document).on('click', '.wcpa-log-page', this.handleLogPagination.bind(this));

            // Settings form
            $(document).on('submit', '#wcpa-settings-form', this.handleSaveSettings.bind(this));
        },

        /**
         * Initialize UI components.
         */
        initComponents: function() {
            // Load query log stats if on query log page
            if ($('.wcpa-query-log-wrapper').length) {
                this.loadQueryLogStats();
                this.loadQueryLogs();
            }
        },

        /**
         * Handle health scan button click.
         *
         * @param {Event} e Click event.
         */
        handleRunScan: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const originalText = $button.text();

            $button.text(wcpaAdmin.strings.scanning);
            this.setButtonLoading($button, true);

            this.apiRequest('scan', 'POST')
                .done(function(response) {
                    if (response.success) {
                        WCPADashboard.showNotice('success', wcpaAdmin.strings.success + ' Reloading...');
                        // Reload page to show updated data
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        WCPADashboard.showNotice('error', response.data.message || wcpaAdmin.strings.error);
                        $button.text(originalText);
                        WCPADashboard.setButtonLoading($button, false);
                    }
                })
                .fail(function(xhr) {
                    let errorMsg = wcpaAdmin.strings.error;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    WCPADashboard.showNotice('error', errorMsg);
                    $button.text(originalText);
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
                        WCPADashboard.showNotice('info', response.message || wcpaAdmin.strings.success);
                        // Update count display
                        const $card = $('.wcpa-cleanup-card[data-type="' + cleanupType + '"]');
                        if ($card.length && response.count !== undefined) {
                            $card.find('.wcpa-count').text(response.count);
                        }
                    } else {
                        WCPADashboard.showNotice('error', response.message || wcpaAdmin.strings.error);
                    }
                })
                .fail(function(xhr) {
                    let errorMsg = wcpaAdmin.strings.error;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    WCPADashboard.showNotice('error', errorMsg);
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
                        // Update count to show remaining items
                        const $card = $('.wcpa-cleanup-card[data-type="' + cleanupType + '"]');
                        if ($card.length) {
                            const remaining = response.after !== undefined ? response.after : 0;
                            $card.find('.wcpa-count').text(remaining);
                        }
                        WCPADashboard.showNotice('success', response.message || wcpaAdmin.strings.success);
                    } else {
                        WCPADashboard.showNotice('error', response.message || wcpaAdmin.strings.error);
                    }
                })
                .fail(function(xhr) {
                    let errorMsg = wcpaAdmin.strings.error;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    WCPADashboard.showNotice('error', errorMsg);
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

            this.apiRequest('query-log/toggle', 'POST', { enabled: enabled })
                .done(function(response) {
                    if (response.success) {
                        WCPADashboard.showNotice('success', response.message);
                        $('.wcpa-log-status').text(enabled ? wcpaAdmin.strings.enabled || 'Enabled' : wcpaAdmin.strings.disabled || 'Disabled');
                    } else {
                        // Revert toggle
                        $(e.currentTarget).prop('checked', !enabled);
                        WCPADashboard.showNotice('error', response.message || wcpaAdmin.strings.error);
                    }
                })
                .fail(function(xhr) {
                    // Revert toggle
                    $(e.currentTarget).prop('checked', !enabled);
                    let errorMsg = wcpaAdmin.strings.error;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    WCPADashboard.showNotice('error', errorMsg);
                });
        },

        /**
         * Handle clear query logs button click.
         *
         * @param {Event} e Click event.
         */
        handleClearQueryLogs: function(e) {
            e.preventDefault();

            if (!confirm(wcpaAdmin.strings.confirmClearLogs || 'Clear all query logs?')) {
                return;
            }

            const $button = $(e.currentTarget);
            this.setButtonLoading($button, true);

            this.apiRequest('query-log/clear', 'POST')
                .done(function(response) {
                    if (response.success) {
                        WCPADashboard.showNotice('success', response.message);
                        $('.wcpa-log-count').text('0');
                    } else {
                        WCPADashboard.showNotice('error', response.message || wcpaAdmin.strings.error);
                    }
                })
                .fail(function(xhr) {
                    let errorMsg = wcpaAdmin.strings.error;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    WCPADashboard.showNotice('error', errorMsg);
                })
                .always(function() {
                    WCPADashboard.setButtonLoading($button, false);
                });
        },

        /**
         * Load query log statistics.
         */
        loadQueryLogStats: function() {
            this.apiRequest('query-log/stats', 'GET')
                .done(function(response) {
                    if (response.success) {
                        $('.wcpa-log-count').text(response.count || '0');
                        $('.wcpa-log-status').text(response.enabled ? (wcpaAdmin.strings.enabled || 'Enabled') : (wcpaAdmin.strings.disabled || 'Disabled'));
                    }
                })
                .fail(function() {
                    $('.wcpa-log-count').text('--');
                });
        },

        /**
         * Load query logs.
         *
         * @param {number} page Page number.
         */
        loadQueryLogs: function(page) {
            page = page || 1;

            const params = {
                page: page,
                per_page: 20,
                query_type: $('.wcpa-filter-query-type').val() || '',
                request_type: $('.wcpa-filter-request-type').val() || '',
                search: $('.wcpa-filter-search').val() || ''
            };

            $('#wcpa-query-log-table').html('<div class="wcpa-loading" style="text-align: center; padding: 40px;">Loading query logs...</div>');

            this.apiRequest('query-log/logs', 'GET', params)
                .done(function(response) {
                    if (response.success) {
                        WCPADashboard.renderQueryLogs(response);
                    } else {
                        $('#wcpa-query-log-table').html('<div style="text-align: center; padding: 40px;">Failed to load logs.</div>');
                    }
                })
                .fail(function() {
                    $('#wcpa-query-log-table').html('<div style="text-align: center; padding: 40px;">Error loading logs.</div>');
                });
        },

        /**
         * Render query logs table.
         *
         * @param {Object} data Response data.
         */
        renderQueryLogs: function(data) {
            if (!data.logs || data.logs.length === 0) {
                $('#wcpa-query-log-table').html('<div style="text-align: center; padding: 40px;">No query logs found.</div>');
                $('#wcpa-log-pagination').hide();
                return;
            }

            // Cache logs for View button access
            this.queryLogsCache = data.logs;

            let html = '<table class="wp-list-table widefat fixed striped wcpa-logs-table">';
            html += '<thead><tr>';
            html += '<th style="width: 40%;">Query</th>';
            html += '<th style="width: 10%;">Type</th>';
            html += '<th style="width: 10%;">Time</th>';
            html += '<th style="width: 15%;">Request</th>';
            html += '<th style="width: 15%;">Logged</th>';
            html += '<th style="width: 10%;">Actions</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            $.each(data.logs, function(i, log) {
                const timeClass = WCPADashboard.getTimeClass(parseFloat(log.execution_time));
                const query = WCPADashboard.truncateQuery(log.query, 80);
                const time = parseFloat(log.execution_time).toFixed(3) + 's';
                const logged = WCPADashboard.formatDate(log.logged_at);

                html += '<tr>';
                html += '<td><code>' + WCPADashboard.escapeHtml(query) + '</code></td>';
                html += '<td><span class="wcpa-query-type">' + WCPADashboard.escapeHtml(log.query_type) + '</span></td>';
                html += '<td><span class="wcpa-time-' + timeClass + '">' + time + '</span></td>';
                html += '<td>' + WCPADashboard.escapeHtml(log.request_type) + '</td>';
                html += '<td>' + logged + '</td>';
                html += '<td><button class="button-link wcpa-view-log-detail" data-id="' + log.id + '">View</button></td>';
                html += '</tr>';
            });

            html += '</tbody></table>';

            $('#wcpa-query-log-table').html(html);

            // Render pagination
            WCPADashboard.renderPagination(data);
        },

        /**
         * Render pagination.
         *
         * @param {Object} data Pagination data.
         */
        renderPagination: function(data) {
            if (data.total_pages <= 1) {
                $('#wcpa-log-pagination').hide();
                return;
            }

            let html = '<div class="tablenav"><div class="tablenav-pages">';
            html += '<span class="displaying-num">' + data.total + ' items</span>';
            html += '<span class="pagination-links">';

            // First page
            if (data.page > 1) {
                html += '<a class="button wcpa-log-page" data-page="1">&laquo;</a> ';
                html += '<a class="button wcpa-log-page" data-page="' + (data.page - 1) + '">&lsaquo;</a> ';
            }

            // Page numbers
            html += '<span class="paging-input">';
            html += '<span class="tablenav-paging-text">';
            html += data.page + ' of ' + data.total_pages;
            html += '</span>';
            html += '</span>';

            // Next/Last page
            if (data.page < data.total_pages) {
                html += ' <a class="button wcpa-log-page" data-page="' + (data.page + 1) + '">&rsaquo;</a>';
                html += ' <a class="button wcpa-log-page" data-page="' + data.total_pages + '">&raquo;</a>';
            }

            html += '</span></div></div>';

            $('#wcpa-log-pagination').html(html).show();
        },

        /**
         * Handle apply filters button.
         *
         * @param {Event} e Click event.
         */
        handleApplyFilters: function(e) {
            e.preventDefault();
            this.loadQueryLogs(1);
        },

        /**
         * Handle reset filters button.
         *
         * @param {Event} e Click event.
         */
        handleResetFilters: function(e) {
            e.preventDefault();
            $('.wcpa-filter-query-type').val('');
            $('.wcpa-filter-request-type').val('');
            $('.wcpa-filter-search').val('');
            this.loadQueryLogs(1);
        },

        /**
         * Handle view log detail button click.
         *
         * @param {Event} e Click event.
         */
        handleViewLogDetail: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const $row = $button.closest('tr');
            const logId = $button.data('id');
            
            // Check if details row already exists
            const $existingDetail = $row.next('.wcpa-log-detail-row');
            
            if ($existingDetail.length) {
                // Toggle visibility
                $existingDetail.toggle();
                $button.text($existingDetail.is(':visible') ? 'Hide' : 'View');
            } else {
                // Create and insert details row
                this.showLogDetails($row, logId, $button);
            }
        },

        /**
         * Show log details in an expanded row.
         *
         * @param {jQuery} $row Current table row.
         * @param {number} logId Log ID.
         * @param {jQuery} $button View button element.
         */
        showLogDetails: function($row, logId, $button) {
            // Find the log data from the already loaded data
            const logs = this.queryLogsCache || [];
            const log = logs.find(l => l.id == logId);
            
            if (!log) {
                this.showNotice('error', 'Could not find log details.');
                return;
            }
            
            // Build detail HTML
            const detailHtml = `
                <tr class="wcpa-log-detail-row">
                    <td colspan="6" class="wcpa-log-detail-cell">
                        <div class="wcpa-log-detail">
                            <div class="wcpa-detail-section">
                                <h4>Query:</h4>
                                <pre class="wcpa-query-text">${this.escapeHtml(log.query)}</pre>
                            </div>
                            ${log.stack_trace ? `
                            <div class="wcpa-detail-section">
                                <h4>Stack Trace:</h4>
                                <pre class="wcpa-stack-trace">${this.escapeHtml(log.stack_trace)}</pre>
                            </div>
                            ` : ''}
                            ${log.caller ? `
                            <div class="wcpa-detail-section">
                                <h4>Caller:</h4>
                                <code>${this.escapeHtml(log.caller)}</code>
                            </div>
                            ` : ''}
                            <div class="wcpa-detail-section">
                                <h4>Additional Info:</h4>
                                <ul>
                                    <li><strong>Request URI:</strong> ${log.request_uri || 'N/A'}</li>
                                    <li><strong>Request Type:</strong> ${log.request_type || 'N/A'}</li>
                                    <li><strong>User ID:</strong> ${log.user_id || '0 (Guest)'}</li>
                                    <li><strong>Admin Request:</strong> ${log.is_admin ? 'Yes' : 'No'}</li>
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            
            $row.after(detailHtml);
            $button.text('Hide');
        },

        /**
         * Handle pagination click.
         *
         * @param {Event} e Click event.
         */
        handleLogPagination: function(e) {
            e.preventDefault();
            const page = $(e.currentTarget).data('page');
            this.loadQueryLogs(page);
        },

        /**
         * Handle settings form submission.
         *
         * @param {Event} e Submit event.
         */
        handleSaveSettings: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const $button = $form.find('.wcpa-save-settings');
            const $status = $form.find('.wcpa-settings-status');
            
            // Get form data
            const formData = {};
            $form.find('input, select').each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                
                if (!name) return;
                
                if ($input.attr('type') === 'checkbox') {
                    formData[name] = $input.is(':checked');
                } else {
                    formData[name] = $input.val();
                }
            });
            
            // Disable button
            $button.prop('disabled', true).text('Saving...');
            $status.html('');
            
            // Save settings
            this.apiRequest('settings', 'POST', formData)
                .done(function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #46b450;">✓ Settings saved successfully!</span>');
                        
                        // Refresh Query Logger status if query logging was toggled
                        if ($('#wcpa-query-log-table').length) {
                            WCPADashboard.loadQueryLogs(1);
                        }
                        
                        // Clear success message after 3 seconds
                        setTimeout(function() {
                            $status.html('');
                        }, 3000);
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ Failed to save settings</span>');
                    }
                })
                .fail(function() {
                    $status.html('<span style="color: #dc3232;">✗ Error saving settings</span>');
                })
                .always(function() {
                    $button.prop('disabled', false).text('Save Settings');
                });
        },

        /**
         * Get time class based on execution time.
         *
         * @param {number} time Execution time in seconds.
         * @return {string} CSS class.
         */
        getTimeClass: function(time) {
            if (time >= 1.0) return 'critical';
            if (time >= 0.5) return 'warning';
            if (time >= 0.1) return 'attention';
            return 'good';
        },

        /**
         * Truncate query for display.
         *
         * @param {string} query SQL query.
         * @param {number} length Maximum length.
         * @return {string} Truncated query.
         */
        truncateQuery: function(query, length) {
            if (query.length > length) {
                return query.substring(0, length) + '...';
            }
            return query;
        },

        /**
         * Format date for display.
         *
         * @param {string} date Date string.
         * @return {string} Formatted date.
         */
        formatDate: function(date) {
            const d = new Date(date);
            return d.toLocaleString();
        },

        /**
         * Escape HTML for safe display.
         *
         * @param {string} text Text to escape.
         * @return {string} Escaped text.
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * Load query log statistics.
         */
        loadQueryLogStats: function() {
            this.apiRequest('query-log/stats', 'GET')
                .done(function(response) {
                    if (response.success) {
                        $('.wcpa-log-count').text(response.count || '0');
                        $('.wcpa-log-status').text(response.enabled ? (wcpaAdmin.strings.enabled || 'Enabled') : (wcpaAdmin.strings.disabled || 'Disabled'));
                    }
                })
                .fail(function() {
                    $('.wcpa-log-count').text('--');
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
