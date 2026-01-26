<?php
/**
 * Transient Cleaner.
 *
 * Cleans expired transients from the database.
 *
 * @package suspended\WCPerformanceAnalyzer\Cleanup
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer\Cleanup;

/**
 * Class TransientCleaner
 *
 * Removes expired transients.
 */
class TransientCleaner {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get cleaner name.
     *
     * @return string
     */
    public function get_name(): string {
        return __( 'Expired Transients', 'wc-performance-analyzer' );
    }

    /**
     * Get cleaner description.
     *
     * @return string
     */
    public function get_description(): string {
        return __( 'Remove expired transient data from the database.', 'wc-performance-analyzer' );
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
     * Count expired transients.
     *
     * @return int
     */
    private function count(): int {
        $time = time();

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$this->wpdb->options} 
                 WHERE option_name LIKE '_transient_timeout_%' 
                 AND option_value < %d",
                $time
            )
        );

        return (int) $result;
    }

    /**
     * Clean expired transients.
     *
     * @return int Number of items deleted.
     */
    private function clean(): int {
        $time = time();

        // Get expired transient timeout keys.
        $timeout_keys = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT option_name 
                 FROM {$this->wpdb->options} 
                 WHERE option_name LIKE '_transient_timeout_%' 
                 AND option_value < %d",
                $time
            )
        );

        if ( empty( $timeout_keys ) ) {
            return 0;
        }

        $deleted = 0;

        foreach ( $timeout_keys as $timeout_key ) {
            // Extract transient name from timeout key.
            $transient_key = str_replace( '_transient_timeout_', '_transient_', $timeout_key );

            // Delete both the timeout and the transient.
            $deleted += $this->wpdb->delete(
                $this->wpdb->options,
                array( 'option_name' => $timeout_key ),
                array( '%s' )
            );

            $deleted += $this->wpdb->delete(
                $this->wpdb->options,
                array( 'option_name' => $transient_key ),
                array( '%s' )
            );
        }

        return $deleted;
    }
}
