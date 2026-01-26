<?php
/**
 * Orphaned Meta Cleaner.
 *
 * Cleans orphaned postmeta entries.
 *
 * @package suspended\WCPerformanceAnalyzer\Cleanup
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer\Cleanup;

/**
 * Class OrphanedMetaCleaner
 *
 * Removes postmeta for deleted posts.
 */
class OrphanedMetaCleaner {

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
        return __( 'Orphaned Post Meta', 'wc-performance-analyzer' );
    }

    /**
     * Get cleaner description.
     *
     * @return string
     */
    public function get_description(): string {
        return __( 'Remove meta data for deleted posts.', 'wc-performance-analyzer' );
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
     * Count orphaned postmeta entries.
     *
     * @return int
     */
    private function count(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->postmeta} pm 
             LEFT JOIN {$this->wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.ID IS NULL"
        );

        return (int) $result;
    }

    /**
     * Clean orphaned postmeta.
     *
     * @return int Number of items deleted.
     */
    private function clean(): int {
        // Delete in batches to avoid memory issues.
        $batch_size = 500;
        $total_deleted = 0;

        do {
            // Get orphaned meta IDs.
            $orphaned_ids = $this->wpdb->get_col(
                $this->wpdb->prepare(
                    "SELECT pm.meta_id 
                     FROM {$this->wpdb->postmeta} pm 
                     LEFT JOIN {$this->wpdb->posts} p ON pm.post_id = p.ID 
                     WHERE p.ID IS NULL 
                     LIMIT %d",
                    $batch_size
                )
            );

            if ( empty( $orphaned_ids ) ) {
                break;
            }

            // Delete batch.
            $ids_placeholder = implode( ',', array_fill( 0, count( $orphaned_ids ), '%d' ) );

            $deleted = $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$this->wpdb->postmeta} WHERE meta_id IN ({$ids_placeholder})",
                    ...$orphaned_ids
                )
            );

            $total_deleted += (int) $deleted;

        } while ( count( $orphaned_ids ) === $batch_size );

        return $total_deleted;
    }
}
