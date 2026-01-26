<?php
/**
 * Session Cleaner.
 *
 * Cleans expired WooCommerce sessions.
 *
 * @package suspended\WCPerformanceAnalyzer\Cleanup
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer\Cleanup;

/**
 * Class SessionCleaner
 *
 * Removes expired WooCommerce sessions.
 */
class SessionCleaner {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Sessions table name.
     *
     * @var string
     */
    private string $table;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'woocommerce_sessions';
    }

    /**
     * Get cleaner name.
     *
     * @return string
     */
    public function get_name(): string {
        return __( 'WooCommerce Sessions', 'wc-performance-analyzer' );
    }

    /**
     * Get cleaner description.
     *
     * @return string
     */
    public function get_description(): string {
        return __( 'Clear expired customer sessions.', 'wc-performance-analyzer' );
    }

    /**
     * Preview cleanup (count items).
     *
     * @return int
     */
    public function preview(): int {
        return $this->count();
    }

    /**
     * Execute cleanup.
     *
     * @return int Number of items deleted.
     */
    public function execute(): int {
        return $this->clean();
    }

    /**
     * Count expired sessions.
     *
     * @return int
     */
    private function count(): int {
        // Check if table exists.
        if ( ! $this->table_exists() ) {
            return 0;
        }

        $time = time();

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE session_expiry < %d",
                $time
            )
        );

        return (int) $result;
    }

    /**
     * Clean expired sessions.
     *
     * @return int Number of items deleted.
     */
    private function clean(): int {
        // Check if table exists.
        if ( ! $this->table_exists() ) {
            return 0;
        }

        $time = time();

        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table} WHERE session_expiry < %d",
                $time
            )
        );

        return (int) $deleted;
    }

    /**
     * Check if sessions table exists.
     *
     * @return bool
     */
    private function table_exists(): bool {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->table
            )
        );

        return $result === $this->table;
    }
}
