<?php
/**
 * Revision Cleaner.
 *
 * Cleans excess post revisions.
 *
 * @package suspended\WCPerformanceAnalyzer\Cleanup
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer\Cleanup;

/**
 * Class RevisionCleaner
 *
 * Removes excess post revisions while keeping recent ones.
 */
class RevisionCleaner {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Number of revisions to keep per post.
     *
     * @var int
     */
    private int $keep;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        // Get retention setting.
        $settings   = get_option( 'wcpa_settings', array() );
        $this->keep = isset( $settings['cleanup_revisions_keep'] ) ? (int) $settings['cleanup_revisions_keep'] : 5;
    }

    /**
     * Get cleaner name.
     *
     * @return string
     */
    public function get_name(): string {
        return __( 'Post Revisions', 'wc-performance-analyzer' );
    }

    /**
     * Get cleaner description.
     *
     * @return string
     */
    public function get_description(): string {
        return __( 'Remove excess post revisions.', 'wc-performance-analyzer' );
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
     * Count revisions that would be deleted.
     *
     * @return int
     */
    private function count(): int {
        if ( $this->keep < 0 ) {
            // If keep is negative, count all revisions.
            return $this->get_total_revision_count();
        }

        // Count revisions beyond the keep limit.
        $total = 0;

        // Get all parent posts that have revisions.
        $parent_ids = $this->wpdb->get_col(
            "SELECT DISTINCT post_parent 
             FROM {$this->wpdb->posts} 
             WHERE post_type = 'revision' 
             AND post_parent > 0"
        );

        foreach ( $parent_ids as $parent_id ) {
            $revision_count = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) 
                     FROM {$this->wpdb->posts} 
                     WHERE post_type = 'revision' 
                     AND post_parent = %d",
                    $parent_id
                )
            );

            if ( $revision_count > $this->keep ) {
                $total += ( $revision_count - $this->keep );
            }
        }

        return $total;
    }

    /**
     * Clean excess revisions.
     *
     * @return int Number of items deleted.
     */
    private function clean(): int {
        $total_deleted = 0;

        // Get all parent posts that have revisions.
        $parent_ids = $this->wpdb->get_col(
            "SELECT DISTINCT post_parent 
             FROM {$this->wpdb->posts} 
             WHERE post_type = 'revision' 
             AND post_parent > 0"
        );

        foreach ( $parent_ids as $parent_id ) {
            // Get revision IDs ordered by date (newest first).
            $revision_ids = $this->wpdb->get_col(
                $this->wpdb->prepare(
                    "SELECT ID 
                     FROM {$this->wpdb->posts} 
                     WHERE post_type = 'revision' 
                     AND post_parent = %d 
                     ORDER BY post_date DESC",
                    $parent_id
                )
            );

            // Skip if we have fewer revisions than the keep limit.
            if ( count( $revision_ids ) <= $this->keep ) {
                continue;
            }

            // Get IDs to delete (all except the ones to keep).
            $ids_to_delete = array_slice( $revision_ids, $this->keep );

            // Delete revisions and their meta.
            foreach ( $ids_to_delete as $revision_id ) {
                // Delete postmeta.
                $this->wpdb->delete(
                    $this->wpdb->postmeta,
                    array( 'post_id' => $revision_id ),
                    array( '%d' )
                );

                // Delete post.
                $deleted = $this->wpdb->delete(
                    $this->wpdb->posts,
                    array( 'ID' => $revision_id ),
                    array( '%d' )
                );

                $total_deleted += (int) $deleted;
            }
        }

        return $total_deleted;
    }

    /**
     * Get total revision count.
     *
     * @return int
     */
    private function get_total_revision_count(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->posts} 
             WHERE post_type = 'revision'"
        );

        return (int) $result;
    }
}
