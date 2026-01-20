<?php
/**
 * Health Scanner.
 *
 * Orchestrates the health scanning process.
 *
 * @package suspended\WCPerformanceAnalyzer\Scanner
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer\Scanner;

/**
 * Class HealthScanner
 *
 * Main scanner class that coordinates metrics collection and scoring.
 */
class HealthScanner {

    /**
     * Option key for storing last scan results.
     */
    private const SCAN_OPTION_KEY = 'wcpa_last_health_scan';

    /**
     * Metrics collector instance.
     *
     * @var MetricsCollector
     */
    private MetricsCollector $collector;

    /**
     * Score calculator instance.
     *
     * @var ScoreCalculator
     */
    private ScoreCalculator $calculator;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->collector  = new MetricsCollector();
        $this->calculator = new ScoreCalculator();
    }

    /**
     * Run a full health scan.
     *
     * @return array<string, mixed> Scan results.
     */
    public function run_scan(): array {
        /**
         * Fires before the health scan starts.
         */
        do_action( 'wcpa_before_health_scan' );

        // Collect metrics.
        $metrics = $this->collector->collect_all();

        /**
         * Filter collected metrics.
         *
         * @param array<string, mixed> $metrics Collected metrics.
         */
        $metrics = apply_filters( 'wcpa_health_metrics', $metrics );

        // Calculate score.
        $score_data = $this->calculator->calculate( $metrics );

        // Generate recommendations.
        $recommendations = $this->calculator->generate_recommendations( $metrics, $score_data['breakdown'] );

        // Build result array.
        $result = array(
            'scanned_at'      => current_time( 'mysql' ),
            'health_score'    => $score_data['score'],
            'score_label'     => $score_data['label'],
            'score_color'     => $score_data['color'],
            'metrics'         => $metrics,
            'breakdown'       => $score_data['breakdown'],
            'recommendations' => $recommendations,
        );

        // Store results.
        $this->store_scan_results( $result );

        // Store metrics snapshot.
        $this->store_metrics_snapshot( $metrics, 'scan' );

        /**
         * Fires after the health scan completes.
         *
         * @param array<string, mixed> $metrics Collected metrics.
         * @param int                  $score   Health score.
         */
        do_action( 'wcpa_health_scan_complete', $metrics, $score_data['score'] );

        return $result;
    }

    /**
     * Get the last scan results.
     *
     * @return array<string, mixed>|null Scan results or null if no scan has been run.
     */
    public function get_last_scan(): ?array {
        $result = get_option( self::SCAN_OPTION_KEY );

        if ( empty( $result ) || ! is_array( $result ) ) {
            return null;
        }

        return $result;
    }

    /**
     * Check if a scan has been run.
     *
     * @return bool
     */
    public function has_scan_data(): bool {
        return $this->get_last_scan() !== null;
    }

    /**
     * Get time since last scan.
     *
     * @return string|null Human-readable time difference or null.
     */
    public function get_time_since_scan(): ?string {
        $scan = $this->get_last_scan();

        if ( ! $scan || empty( $scan['scanned_at'] ) ) {
            return null;
        }

        $scan_time = strtotime( $scan['scanned_at'] );

        if ( ! $scan_time ) {
            return null;
        }

        return human_time_diff( $scan_time, current_time( 'timestamp' ) );
    }

    /**
     * Get formatted metrics for display.
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_display_metrics(): array {
        $scan = $this->get_last_scan();

        if ( ! $scan || empty( $scan['metrics'] ) ) {
            return $this->get_empty_display_metrics();
        }

        $metrics = $scan['metrics'];

        return array(
            'autoload_size'     => array(
                'label' => __( 'Autoload Size', 'wc-performance-analyzer' ),
                'value' => $this->format_bytes( $metrics['autoload_size'] ?? 0 ),
                'raw'   => $metrics['autoload_size'] ?? 0,
            ),
            'transients'        => array(
                'label' => __( 'Transients', 'wc-performance-analyzer' ),
                'value' => number_format( $metrics['transient_count'] ?? 0 ),
                'sub'   => sprintf(
                    /* translators: %d: expired count */
                    __( '%d expired', 'wc-performance-analyzer' ),
                    $metrics['expired_transients'] ?? 0
                ),
            ),
            'sessions'          => array(
                'label' => __( 'WC Sessions', 'wc-performance-analyzer' ),
                'value' => number_format( $metrics['wc_sessions'] ?? 0 ),
                'sub'   => sprintf(
                    /* translators: %d: expired count */
                    __( '%d expired', 'wc-performance-analyzer' ),
                    $metrics['expired_wc_sessions'] ?? 0
                ),
            ),
            'orphaned_meta'     => array(
                'label' => __( 'Orphaned Meta', 'wc-performance-analyzer' ),
                'value' => number_format( $metrics['orphaned_postmeta'] ?? 0 ),
                'raw'   => $metrics['orphaned_postmeta'] ?? 0,
            ),
            'revisions'         => array(
                'label' => __( 'Revisions', 'wc-performance-analyzer' ),
                'value' => number_format( $metrics['total_revisions'] ?? 0 ),
                'raw'   => $metrics['total_revisions'] ?? 0,
            ),
            'products'          => array(
                'label' => __( 'Products', 'wc-performance-analyzer' ),
                'value' => number_format( $metrics['total_products'] ?? 0 ),
                'sub'   => sprintf(
                    /* translators: %d: variation count */
                    __( '%d variations', 'wc-performance-analyzer' ),
                    $metrics['total_variations'] ?? 0
                ),
            ),
            'orders'            => array(
                'label' => __( 'Orders', 'wc-performance-analyzer' ),
                'value' => number_format( $metrics['total_orders'] ?? 0 ),
            ),
            'postmeta_rows'     => array(
                'label' => __( 'Postmeta Rows', 'wc-performance-analyzer' ),
                'value' => number_format( $metrics['postmeta_rows'] ?? 0 ),
            ),
        );
    }

    /**
     * Get empty display metrics placeholder.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_empty_display_metrics(): array {
        return array(
            'autoload_size' => array(
                'label' => __( 'Autoload Size', 'wc-performance-analyzer' ),
                'value' => '--',
            ),
            'transients'    => array(
                'label' => __( 'Transients', 'wc-performance-analyzer' ),
                'value' => '--',
            ),
            'sessions'      => array(
                'label' => __( 'WC Sessions', 'wc-performance-analyzer' ),
                'value' => '--',
            ),
            'orphaned_meta' => array(
                'label' => __( 'Orphaned Meta', 'wc-performance-analyzer' ),
                'value' => '--',
            ),
        );
    }

    /**
     * Get top autoloaded options.
     *
     * @param int $limit Number of results.
     * @return array<int, array<string, mixed>>
     */
    public function get_top_autoloaded_options( int $limit = 10 ): array {
        $options = $this->collector->get_top_autoloaded_options( $limit );

        // Format the results.
        return array_map(
            function ( $option ) {
                return array(
                    'name' => $option['option_name'],
                    'size' => $this->format_bytes( (int) $option['size'] ),
                    'raw'  => (int) $option['size'],
                );
            },
            $options
        );
    }

    /**
     * Get high variation products.
     *
     * @param int $threshold Minimum variations.
     * @param int $limit     Number of results.
     * @return array<int, array<string, mixed>>
     */
    public function get_high_variation_products( int $threshold = 50, int $limit = 10 ): array {
        return $this->collector->get_high_variation_products( $threshold, $limit );
    }

    /**
     * Store scan results in options.
     *
     * @param array<string, mixed> $result Scan results.
     * @return bool
     */
    private function store_scan_results( array $result ): bool {
        return update_option( self::SCAN_OPTION_KEY, $result, false );
    }

    /**
     * Store metrics snapshot in custom table.
     *
     * @param array<string, mixed> $metrics       Metrics to store.
     * @param string               $snapshot_type Type of snapshot.
     * @return void
     */
    private function store_metrics_snapshot( array $metrics, string $snapshot_type ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'wcpa_metrics_snapshots';

        // Store key metrics.
        $metrics_to_store = array(
            'autoload_size',
            'transient_count',
            'expired_transients',
            'wc_sessions',
            'orphaned_postmeta',
            'total_revisions',
            'postmeta_rows',
        );

        foreach ( $metrics_to_store as $key ) {
            if ( isset( $metrics[ $key ] ) ) {
                $wpdb->insert(
                    $table,
                    array(
                        'metric_key'    => $key,
                        'metric_value'  => (int) $metrics[ $key ],
                        'snapshot_type' => $snapshot_type,
                        'created_at'    => current_time( 'mysql' ),
                    ),
                    array( '%s', '%d', '%s', '%s' )
                );
            }
        }
    }

    /**
     * Clear scan data.
     *
     * @return bool
     */
    public function clear_scan_data(): bool {
        return delete_option( self::SCAN_OPTION_KEY );
    }

    /**
     * Format bytes to human readable string.
     *
     * @param int $bytes Bytes.
     * @return string
     */
    private function format_bytes( int $bytes ): string {
        if ( $bytes >= 1048576 ) {
            return round( $bytes / 1048576, 2 ) . ' MB';
        }

        if ( $bytes >= 1024 ) {
            return round( $bytes / 1024, 2 ) . ' KB';
        }

        return $bytes . ' bytes';
    }
}
