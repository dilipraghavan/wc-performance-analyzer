<?php
/**
 * Plugin Deactivator.
 *
 * @package suspended\WCPerformanceAnalyzer
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer;

/**
 * Class Deactivator
 *
 * Handles plugin deactivation tasks.
 */
class Deactivator {

    /**
     * Deactivate the plugin.
     *
     * @return void
     */
    public static function deactivate(): void {
        self::clear_scheduled_events();

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Clear all scheduled cron events.
     *
     * @return void
     */
    private static function clear_scheduled_events(): void {
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
    }
}
