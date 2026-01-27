<?php
/**
 * Plugin Activator.
 *
 * @package suspended\WCPerformanceAnalyzer
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer;

/**
 * Class Activator
 *
 * Handles plugin activation tasks.
 */
class Activator {

    /**
     * Database version option key.
     */
    private const DB_VERSION_OPTION = 'wcpa_db_version';

    /**
     * Current database version.
     */
    private const DB_VERSION = '1.0.0';

    /**
     * Activate the plugin.
     *
     * @return void
     */
    public static function activate(): void {
        self::create_tables();
        self::set_default_options();
        self::schedule_events();

        // Update database version.
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables.
     *
     * @return void
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Query log table.
        $query_log_table = $wpdb->prefix . 'wcpa_query_log';
        $query_log_sql   = "CREATE TABLE {$query_log_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            query TEXT NOT NULL,
            query_type VARCHAR(20) DEFAULT 'SELECT',
            execution_time FLOAT NOT NULL DEFAULT 0,
            caller TEXT,
            stack_trace TEXT,
            request_uri VARCHAR(255) DEFAULT NULL,
            request_type VARCHAR(20) DEFAULT 'web',
            is_admin TINYINT(1) DEFAULT 0,
            user_id BIGINT UNSIGNED DEFAULT 0,
            logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_execution_time (execution_time),
            KEY idx_query_type (query_type),
            KEY idx_logged_at (logged_at),
            KEY idx_request_type (request_type)
        ) {$charset_collate};";

        // Metrics snapshots table.
        $metrics_table = $wpdb->prefix . 'wcpa_metrics_snapshots';
        $metrics_sql   = "CREATE TABLE {$metrics_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            metric_key VARCHAR(100) NOT NULL,
            metric_value BIGINT NOT NULL DEFAULT 0,
            snapshot_type VARCHAR(20) NOT NULL DEFAULT 'manual',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_metric_key (metric_key),
            KEY idx_snapshot_type (snapshot_type),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( $query_log_sql );
        dbDelta( $metrics_sql );
    }

    /**
     * Set default plugin options.
     *
     * @return void
     */
    private static function set_default_options(): void {
        $default_settings = array(
            'query_log_enabled'       => false,
            'query_log_threshold'     => 0.05,
            'query_log_retention_days' => 7,
            'cart_fragments_mode'     => 'default',
            'cleanup_revisions_keep'  => 5,
            'scheduled_scan_enabled'  => false,
        );

        // Only set if not already exists.
        if ( false === get_option( 'wcpa_settings' ) ) {
            add_option( 'wcpa_settings', $default_settings );
        }
    }

    /**
     * Schedule cron events.
     *
     * @return void
     */
    private static function schedule_events(): void {
        // Schedule daily cleanup of old query logs.
        if ( ! wp_next_scheduled( 'wcpa_daily_maintenance' ) ) {
            wp_schedule_event( time(), 'daily', 'wcpa_daily_maintenance' );
        }
    }

    /**
     * Get table names used by the plugin.
     *
     * @return array<string, string>
     */
    public static function get_table_names(): array {
        global $wpdb;

        return array(
            'query_log' => $wpdb->prefix . 'wcpa_query_log',
            'metrics'   => $wpdb->prefix . 'wcpa_metrics_snapshots',
        );
    }
}
