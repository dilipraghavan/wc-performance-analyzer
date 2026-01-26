<?php
/**
 * Trash Cleaner.
 *
 * Cleans trashed posts permanently.
 *
 * @package suspended\WCPerformanceAnalyzer\Cleanup
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer\Cleanup;

/**
 * Class TrashCleaner
 *
 * Permanently deletes trashed posts.
 */
class TrashCleaner {

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
        return __( 'Trashed Posts', 'wc-performance-analyzer' );
    }

    /**
     * Get cleaner description.
     *
     * @return string
     */
    public function get_description(): string {
        return __( 'Permanently delete trashed posts.', 'wc-performance-analyzer' );
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
     * Count trashed posts.
     *
     * @return int
     */
    private function count(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->posts} 
             WHERE post_status = 'trash'"
        );

        return (int) $result;
    }

    /**
     * Clean trashed posts.
     *
     * @return int Number of items deleted.
     */
    private function clean(): int {
        // Get trashed post IDs.
        $trashed_ids = $this->wpdb->get_col(
            "SELECT ID 
             FROM {$this->wpdb->posts} 
             WHERE post_status = 'trash'"
        );

        if ( empty( $trashed_ids ) ) {
            return 0;
        }

        $total_deleted = 0;

        foreach ( $trashed_ids as $post_id ) {
            // Use wp_delete_post to properly clean up relationships.
            $deleted = wp_delete_post( (int) $post_id, true );

            if ( $deleted ) {
                $total_deleted++;
            }
        }

        return $total_deleted;
    }
}
