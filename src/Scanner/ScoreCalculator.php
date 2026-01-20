<?php
/**
 * Score Calculator.
 *
 * Calculates the overall health score based on collected metrics.
 *
 * @package suspended\WCPerformanceAnalyzer\Scanner
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer\Scanner;

/**
 * Class ScoreCalculator
 *
 * Calculates weighted health scores from metrics.
 */
class ScoreCalculator {

    /**
     * Default scoring weights.
     *
     * @var array<string, float>
     */
    private const DEFAULT_WEIGHTS = array(
        'autoload_size'      => 0.25,
        'orphaned_meta'      => 0.20,
        'expired_transients' => 0.15,
        'wc_sessions'        => 0.15,
        'meta_per_product'   => 0.15,
        'revisions'          => 0.10,
    );

    /**
     * Scoring thresholds for each metric.
     *
     * Format: [ 'excellent' => threshold, 'good' => threshold ]
     * Score: >= excellent = 100, >= good = 70, < good = 30
     *
     * @var array<string, array<string, int|float>>
     */
    private const THRESHOLDS = array(
        'autoload_size'      => array(
            'excellent' => 500000,   // 500KB
            'good'      => 1000000,  // 1MB
        ),
        'orphaned_meta'      => array(
            'excellent' => 0.01,     // 1% of total postmeta
            'good'      => 0.05,     // 5% of total postmeta
        ),
        'expired_transients' => array(
            'excellent' => 50,
            'good'      => 200,
        ),
        'wc_sessions'        => array(
            'excellent' => 100,
            'good'      => 500,
        ),
        'meta_per_product'   => array(
            'excellent' => 50,
            'good'      => 100,
        ),
        'revisions'          => array(
            'excellent' => 5,        // Average revisions per post
            'good'      => 20,
        ),
    );

    /**
     * Score labels and ranges.
     *
     * @var array<string, array<string, mixed>>
     */
    private const SCORE_RANGES = array(
        'excellent'  => array(
            'min'   => 80,
            'max'   => 100,
            'label' => 'Excellent',
            'color' => 'green',
        ),
        'good'       => array(
            'min'   => 60,
            'max'   => 79,
            'label' => 'Good',
            'color' => 'yellow',
        ),
        'attention'  => array(
            'min'   => 40,
            'max'   => 59,
            'label' => 'Needs Attention',
            'color' => 'orange',
        ),
        'critical'   => array(
            'min'   => 0,
            'max'   => 39,
            'label' => 'Critical',
            'color' => 'red',
        ),
    );

    /**
     * Current weights (can be filtered).
     *
     * @var array<string, float>
     */
    private array $weights;

    /**
     * Constructor.
     */
    public function __construct() {
        /**
         * Filter the scoring weights.
         *
         * @param array<string, float> $weights Default weights.
         */
        $this->weights = apply_filters( 'wcpa_health_score_weights', self::DEFAULT_WEIGHTS );
    }

    /**
     * Calculate the overall health score.
     *
     * @param array<string, mixed> $metrics Collected metrics.
     * @return array<string, mixed> Score data including score, label, color, and breakdown.
     */
    public function calculate( array $metrics ): array {
        $breakdown    = array();
        $total_score  = 0;
        $total_weight = 0;

        // Calculate autoload size score.
        $autoload_score           = $this->score_autoload_size( $metrics['autoload_size'] ?? 0 );
        $breakdown['autoload']    = array(
            'score'  => $autoload_score,
            'weight' => $this->weights['autoload_size'],
            'value'  => $this->format_bytes( $metrics['autoload_size'] ?? 0 ),
            'status' => $this->get_metric_status( $autoload_score ),
        );
        $total_score             += $autoload_score * $this->weights['autoload_size'];
        $total_weight            += $this->weights['autoload_size'];

        // Calculate orphaned meta score.
        $orphaned_ratio            = $this->calculate_orphaned_ratio( $metrics );
        $orphaned_score            = $this->score_orphaned_meta( $orphaned_ratio );
        $breakdown['orphaned_meta'] = array(
            'score'  => $orphaned_score,
            'weight' => $this->weights['orphaned_meta'],
            'value'  => $metrics['orphaned_postmeta'] ?? 0,
            'ratio'  => round( $orphaned_ratio * 100, 2 ) . '%',
            'status' => $this->get_metric_status( $orphaned_score ),
        );
        $total_score              += $orphaned_score * $this->weights['orphaned_meta'];
        $total_weight             += $this->weights['orphaned_meta'];

        // Calculate expired transients score.
        $transient_score                = $this->score_expired_transients( $metrics['expired_transients'] ?? 0 );
        $breakdown['expired_transients'] = array(
            'score'  => $transient_score,
            'weight' => $this->weights['expired_transients'],
            'value'  => $metrics['expired_transients'] ?? 0,
            'status' => $this->get_metric_status( $transient_score ),
        );
        $total_score                   += $transient_score * $this->weights['expired_transients'];
        $total_weight                  += $this->weights['expired_transients'];

        // Calculate WC sessions score.
        $sessions_score            = $this->score_wc_sessions( $metrics['wc_sessions'] ?? 0 );
        $breakdown['wc_sessions']  = array(
            'score'   => $sessions_score,
            'weight'  => $this->weights['wc_sessions'],
            'value'   => $metrics['wc_sessions'] ?? 0,
            'expired' => $metrics['expired_wc_sessions'] ?? 0,
            'status'  => $this->get_metric_status( $sessions_score ),
        );
        $total_score              += $sessions_score * $this->weights['wc_sessions'];
        $total_weight             += $this->weights['wc_sessions'];

        // Calculate meta per product score.
        $meta_score                    = $this->score_meta_per_product( $metrics['meta_per_product'] ?? 0 );
        $breakdown['meta_per_product'] = array(
            'score'  => $meta_score,
            'weight' => $this->weights['meta_per_product'],
            'value'  => $metrics['meta_per_product'] ?? 0,
            'status' => $this->get_metric_status( $meta_score ),
        );
        $total_score                  += $meta_score * $this->weights['meta_per_product'];
        $total_weight                 += $this->weights['meta_per_product'];

        // Calculate revisions score.
        $revisions_score         = $this->score_revisions( $metrics['revisions_per_post'] ?? 0 );
        $breakdown['revisions']  = array(
            'score'  => $revisions_score,
            'weight' => $this->weights['revisions'],
            'value'  => $metrics['total_revisions'] ?? 0,
            'ratio'  => $metrics['revisions_per_post'] ?? 0,
            'status' => $this->get_metric_status( $revisions_score ),
        );
        $total_score            += $revisions_score * $this->weights['revisions'];
        $total_weight           += $this->weights['revisions'];

        // Calculate final score.
        $final_score = $total_weight > 0 ? (int) round( $total_score / $total_weight ) : 0;
        $score_info  = $this->get_score_info( $final_score );

        return array(
            'score'       => $final_score,
            'label'       => $score_info['label'],
            'color'       => $score_info['color'],
            'breakdown'   => $breakdown,
            'calculated_at' => current_time( 'mysql' ),
        );
    }

    /**
     * Generate recommendations based on metrics.
     *
     * @param array<string, mixed> $metrics   Collected metrics.
     * @param array<string, mixed> $breakdown Score breakdown.
     * @return array<int, array<string, string>>
     */
    public function generate_recommendations( array $metrics, array $breakdown ): array {
        $recommendations = array();

        // Autoload recommendations.
        if ( ( $breakdown['autoload']['score'] ?? 100 ) < 70 ) {
            $size = $metrics['autoload_size'] ?? 0;
            $recommendations[] = array(
                'type'    => $size > 1000000 ? 'critical' : 'warning',
                'area'    => 'autoload',
                'message' => sprintf(
                    /* translators: %s: autoload size */
                    __( 'Autoload data size is %s. Consider reviewing large autoloaded options.', 'wc-performance-analyzer' ),
                    $this->format_bytes( $size )
                ),
                'action'  => __( 'Review top autoloaded options in the dashboard.', 'wc-performance-analyzer' ),
            );
        }

        // Orphaned meta recommendations.
        if ( ( $breakdown['orphaned_meta']['score'] ?? 100 ) < 70 ) {
            $count = $metrics['orphaned_postmeta'] ?? 0;
            $recommendations[] = array(
                'type'    => $count > 1000 ? 'critical' : 'warning',
                'area'    => 'orphaned_meta',
                'message' => sprintf(
                    /* translators: %d: orphaned meta count */
                    __( 'Found %d orphaned postmeta entries.', 'wc-performance-analyzer' ),
                    $count
                ),
                'action'  => __( 'Run the orphaned postmeta cleanup.', 'wc-performance-analyzer' ),
            );
        }

        // Expired transients recommendations.
        if ( ( $breakdown['expired_transients']['score'] ?? 100 ) < 70 ) {
            $count = $metrics['expired_transients'] ?? 0;
            $recommendations[] = array(
                'type'    => 'warning',
                'area'    => 'transients',
                'message' => sprintf(
                    /* translators: %d: expired transient count */
                    __( 'Found %d expired transients.', 'wc-performance-analyzer' ),
                    $count
                ),
                'action'  => __( 'Run the expired transients cleanup.', 'wc-performance-analyzer' ),
            );
        }

        // WC Sessions recommendations.
        if ( ( $breakdown['wc_sessions']['score'] ?? 100 ) < 70 ) {
            $count   = $metrics['wc_sessions'] ?? 0;
            $expired = $metrics['expired_wc_sessions'] ?? 0;
            $recommendations[] = array(
                'type'    => 'warning',
                'area'    => 'sessions',
                'message' => sprintf(
                    /* translators: 1: total sessions, 2: expired sessions */
                    __( 'Found %1$d WooCommerce sessions (%2$d expired).', 'wc-performance-analyzer' ),
                    $count,
                    $expired
                ),
                'action'  => __( 'Run the WooCommerce sessions cleanup.', 'wc-performance-analyzer' ),
            );
        }

        // Meta per product recommendations.
        if ( ( $breakdown['meta_per_product']['score'] ?? 100 ) < 70 ) {
            $avg = $metrics['meta_per_product'] ?? 0;
            $recommendations[] = array(
                'type'    => 'info',
                'area'    => 'product_meta',
                'message' => sprintf(
                    /* translators: %s: average meta per product */
                    __( 'Average of %s meta entries per product. This may indicate plugin bloat.', 'wc-performance-analyzer' ),
                    number_format( $avg, 1 )
                ),
                'action'  => __( 'Review installed plugins for excessive meta storage.', 'wc-performance-analyzer' ),
            );
        }

        // Revisions recommendations.
        if ( ( $breakdown['revisions']['score'] ?? 100 ) < 70 ) {
            $count = $metrics['total_revisions'] ?? 0;
            $recommendations[] = array(
                'type'    => 'warning',
                'area'    => 'revisions',
                'message' => sprintf(
                    /* translators: %d: revision count */
                    __( 'Found %d post revisions.', 'wc-performance-analyzer' ),
                    $count
                ),
                'action'  => __( 'Run the revisions cleanup or limit revisions in wp-config.php.', 'wc-performance-analyzer' ),
            );
        }

        /**
         * Filter the recommendations.
         *
         * @param array<int, array<string, string>> $recommendations Generated recommendations.
         * @param array<string, mixed>              $metrics         Collected metrics.
         * @param array<string, mixed>              $breakdown       Score breakdown.
         */
        return apply_filters( 'wcpa_health_recommendations', $recommendations, $metrics, $breakdown );
    }

    /**
     * Score autoload size.
     *
     * @param int $size Size in bytes.
     * @return int Score 0-100.
     */
    private function score_autoload_size( int $size ): int {
        $thresholds = self::THRESHOLDS['autoload_size'];

        if ( $size <= $thresholds['excellent'] ) {
            return 100;
        }

        if ( $size <= $thresholds['good'] ) {
            return 70;
        }

        // Scale down for larger sizes.
        if ( $size <= 2000000 ) { // 2MB
            return 50;
        }

        return 30;
    }

    /**
     * Calculate orphaned meta ratio.
     *
     * @param array<string, mixed> $metrics Metrics array.
     * @return float Ratio of orphaned to total.
     */
    private function calculate_orphaned_ratio( array $metrics ): float {
        $total    = $metrics['postmeta_rows'] ?? 0;
        $orphaned = $metrics['orphaned_postmeta'] ?? 0;

        if ( $total === 0 ) {
            return 0.0;
        }

        return (float) $orphaned / $total;
    }

    /**
     * Score orphaned meta ratio.
     *
     * @param float $ratio Ratio of orphaned to total.
     * @return int Score 0-100.
     */
    private function score_orphaned_meta( float $ratio ): int {
        $thresholds = self::THRESHOLDS['orphaned_meta'];

        if ( $ratio <= $thresholds['excellent'] ) {
            return 100;
        }

        if ( $ratio <= $thresholds['good'] ) {
            return 70;
        }

        return 30;
    }

    /**
     * Score expired transients count.
     *
     * @param int $count Expired transient count.
     * @return int Score 0-100.
     */
    private function score_expired_transients( int $count ): int {
        $thresholds = self::THRESHOLDS['expired_transients'];

        if ( $count <= $thresholds['excellent'] ) {
            return 100;
        }

        if ( $count <= $thresholds['good'] ) {
            return 70;
        }

        return 30;
    }

    /**
     * Score WooCommerce sessions count.
     *
     * @param int $count Session count.
     * @return int Score 0-100.
     */
    private function score_wc_sessions( int $count ): int {
        $thresholds = self::THRESHOLDS['wc_sessions'];

        if ( $count <= $thresholds['excellent'] ) {
            return 100;
        }

        if ( $count <= $thresholds['good'] ) {
            return 70;
        }

        return 30;
    }

    /**
     * Score meta per product.
     *
     * @param float $avg Average meta per product.
     * @return int Score 0-100.
     */
    private function score_meta_per_product( float $avg ): int {
        $thresholds = self::THRESHOLDS['meta_per_product'];

        if ( $avg <= $thresholds['excellent'] ) {
            return 100;
        }

        if ( $avg <= $thresholds['good'] ) {
            return 70;
        }

        return 30;
    }

    /**
     * Score revisions per post.
     *
     * @param float $avg Average revisions per post.
     * @return int Score 0-100.
     */
    private function score_revisions( float $avg ): int {
        $thresholds = self::THRESHOLDS['revisions'];

        if ( $avg <= $thresholds['excellent'] ) {
            return 100;
        }

        if ( $avg <= $thresholds['good'] ) {
            return 70;
        }

        return 30;
    }

    /**
     * Get score info (label and color) for a given score.
     *
     * @param int $score Health score.
     * @return array<string, string>
     */
    private function get_score_info( int $score ): array {
        foreach ( self::SCORE_RANGES as $range ) {
            if ( $score >= $range['min'] && $score <= $range['max'] ) {
                return array(
                    'label' => $range['label'],
                    'color' => $range['color'],
                );
            }
        }

        return array(
            'label' => 'Unknown',
            'color' => 'gray',
        );
    }

    /**
     * Get status string for a metric score.
     *
     * @param int $score Metric score.
     * @return string Status: excellent, good, warning, critical.
     */
    private function get_metric_status( int $score ): string {
        if ( $score >= 90 ) {
            return 'excellent';
        }

        if ( $score >= 70 ) {
            return 'good';
        }

        if ( $score >= 40 ) {
            return 'warning';
        }

        return 'critical';
    }

    /**
     * Format bytes to human readable string.
     *
     * @param int $bytes Bytes.
     * @return string Formatted string.
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
