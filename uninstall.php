<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled.
 * Removes all plugin data including database tables and options.
 *
 * @package suspended\WCPerformanceAnalyzer
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete custom tables.
$tables = array(
    $wpdb->prefix . 'wcpa_query_log',
    $wpdb->prefix . 'wcpa_metrics_snapshots',
);

foreach ( $tables as $table ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete options.
$options = array(
    'wcpa_settings',
    'wcpa_db_version',
    'wcpa_last_health_scan',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Clear any scheduled hooks.
$scheduled_hooks = array(
    'wcpa_daily_maintenance',
    'wcpa_scheduled_scan',
);

foreach ( $scheduled_hooks as $hook ) {
    $timestamp = wp_next_scheduled( $hook );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, $hook );
    }
}

// Clear all transients with our prefix.
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcpa_%' OR option_name LIKE '_transient_timeout_wcpa_%'"
);

// Flush rewrite rules.
flush_rewrite_rules();
