<?php
/**
 * Cleanup Manager.
 *
 * Orchestrates cleanup operations.
 *
 * @package suspended\WCPerformanceAnalyzer\Cleanup
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer\Cleanup;

/**
 * Class CleanupManager
 *
 * Manages all cleanup operations.
 */
class CleanupManager {

    /**
     * Available cleaners.
     *
     * @var array<string, object>
     */
    private array $cleaners = array();

    /**
     * Constructor.
     */
    public function __construct() {
        $this->register_cleaners();
    }

    /**
     * Register all available cleaners.
     *
     * @return void
     */
    private function register_cleaners(): void {
        $this->cleaners = array(
            'transients'     => new TransientCleaner(),
            'sessions'       => new SessionCleaner(),
            'orphaned_meta'  => new OrphanedMetaCleaner(),
            'revisions'      => new RevisionCleaner(),
            'trash'          => new TrashCleaner(),
        );

        /**
         * Filter available cleanup types.
         *
         * @param array<string, object> $cleaners Registered cleaners.
         */
        $this->cleaners = apply_filters( 'wcpa_cleanup_types', $this->cleaners );
    }

    /**
     * Preview cleanup operation (dry run).
     *
     * @param string $type Cleanup type.
     * @return array<string, mixed> Preview results.
     */
    public function preview( string $type ): array {
        if ( ! isset( $this->cleaners[ $type ] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid cleanup type.', 'wc-performance-analyzer' ),
            );
        }

        try {
            $cleaner = $this->cleaners[ $type ];
            $count   = $cleaner->preview();

            return array(
                'success' => true,
                'type'    => $type,
                'count'   => $count,
                'message' => sprintf(
                    /* translators: %d: item count */
                    _n( '%d item found', '%d items found', $count, 'wc-performance-analyzer' ),
                    $count
                ),
            );
        } catch ( \Exception $e ) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }

    /**
     * Execute cleanup operation.
     *
     * @param string $type Cleanup type.
     * @return array<string, mixed> Cleanup results.
     */
    public function execute( string $type ): array {
        if ( ! isset( $this->cleaners[ $type ] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid cleanup type.', 'wc-performance-analyzer' ),
            );
        }

        try {
            $cleaner = $this->cleaners[ $type ];

            // Get count before cleanup.
            $before = $cleaner->preview();

            // Execute cleanup.
            $deleted = $cleaner->execute();

            // Get count after cleanup.
            $after = $cleaner->preview();

            /**
             * Fires after cleanup completes.
             *
             * @param string $type    Cleanup type.
             * @param int    $deleted Number of items deleted.
             */
            do_action( 'wcpa_cleanup_complete', $type, $deleted );

            return array(
                'success' => true,
                'type'    => $type,
                'before'  => $before,
                'deleted' => $deleted,
                'after'   => $after,
                'message' => sprintf(
                    /* translators: %d: deleted count */
                    _n( '%d item cleaned', '%d items cleaned', $deleted, 'wc-performance-analyzer' ),
                    $deleted
                ),
            );
        } catch ( \Exception $e ) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }

    /**
     * Get counts for all cleanup types.
     *
     * @return array<string, int>
     */
    public function get_all_counts(): array {
        $counts = array();

        foreach ( $this->cleaners as $type => $cleaner ) {
            try {
                $counts[ $type ] = $cleaner->preview();
            } catch ( \Exception $e ) {
                $counts[ $type ] = 0;
            }
        }

        return $counts;
    }

    /**
     * Get available cleanup types.
     *
     * @return array<string, string> Type => Label mapping.
     */
    public function get_available_types(): array {
        return array(
            'transients'     => __( 'Expired Transients', 'wc-performance-analyzer' ),
            'sessions'       => __( 'WooCommerce Sessions', 'wc-performance-analyzer' ),
            'orphaned_meta'  => __( 'Orphaned Post Meta', 'wc-performance-analyzer' ),
            'revisions'      => __( 'Post Revisions', 'wc-performance-analyzer' ),
            'trash'          => __( 'Trashed Posts', 'wc-performance-analyzer' ),
        );
    }
}
