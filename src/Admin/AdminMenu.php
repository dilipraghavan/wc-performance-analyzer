<?php
/**
 * Admin Menu Registration.
 *
 * @package suspended\WCPerformanceAnalyzer\Admin
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer\Admin;

use suspended\WCPerformanceAnalyzer\Cleanup\CleanupManager;
use suspended\WCPerformanceAnalyzer\Scanner\HealthScanner;

/**
 * Class AdminMenu
 *
 * Handles admin menu and page registration.
 */
class AdminMenu {

    /**
     * Menu slug.
     */
    public const MENU_SLUG = 'wcpa-dashboard';

    /**
     * Health scanner instance.
     *
     * @var HealthScanner
     */
    private HealthScanner $scanner;

    /**
     * Cleanup manager instance.
     *
     * @var CleanupManager
     */
    private CleanupManager $cleanup_manager;

    /**
     * Constructor.
     *
     * @param HealthScanner  $scanner         Health scanner instance.
     * @param CleanupManager $cleanup_manager Cleanup manager instance.
     */
    public function __construct( HealthScanner $scanner, CleanupManager $cleanup_manager ) {
        $this->scanner         = $scanner;
        $this->cleanup_manager = $cleanup_manager;
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }

    /**
     * Register admin menu pages.
     *
     * @return void
     */
    public function register_menu(): void {
        // Main menu under WooCommerce.
        add_submenu_page(
            'woocommerce',
            __( 'Performance Analyzer', 'wc-performance-analyzer' ),
            __( 'Performance Analyzer', 'wc-performance-analyzer' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            array( $this, 'render_dashboard_page' )
        );

        // Cleanup Tools submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Cleanup Tools', 'wc-performance-analyzer' ),
            __( 'Cleanup Tools', 'wc-performance-analyzer' ),
            'manage_woocommerce',
            'wcpa-cleanup',
            array( $this, 'render_cleanup_page' )
        );

        // Query Log submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Query Log', 'wc-performance-analyzer' ),
            __( 'Query Log', 'wc-performance-analyzer' ),
            'manage_woocommerce',
            'wcpa-query-log',
            array( $this, 'render_query_log_page' )
        );

        // Optimizations submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Optimizations', 'wc-performance-analyzer' ),
            __( 'Optimizations', 'wc-performance-analyzer' ),
            'manage_woocommerce',
            'wcpa-optimizations',
            array( $this, 'render_optimizations_page' )
        );

        // Settings submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Settings', 'wc-performance-analyzer' ),
            __( 'Settings', 'wc-performance-analyzer' ),
            'manage_woocommerce',
            'wcpa-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Render the main dashboard page.
     *
     * @return void
     */
    public function render_dashboard_page(): void {
        $this->render_page_header( __( 'Performance Dashboard', 'wc-performance-analyzer' ) );

        $last_scan       = $this->scanner->get_last_scan();
        $has_scan_data   = $this->scanner->has_scan_data();
        $time_since_scan = $this->scanner->get_time_since_scan();
        $display_metrics = $this->scanner->get_display_metrics();

        // Get score data.
        $health_score    = $has_scan_data ? ( $last_scan['health_score'] ?? 0 ) : null;
        $score_label     = $has_scan_data ? ( $last_scan['score_label'] ?? '' ) : '';
        $score_color     = $has_scan_data ? ( $last_scan['score_color'] ?? 'gray' ) : 'gray';
        $breakdown       = $has_scan_data ? ( $last_scan['breakdown'] ?? array() ) : array();
        $recommendations = $has_scan_data ? ( $last_scan['recommendations'] ?? array() ) : array();
        $metrics         = $has_scan_data ? ( $last_scan['metrics'] ?? array() ) : array();
        ?>
        <div class="wcpa-dashboard-wrapper">
            <?php if ( ! $has_scan_data ) : ?>
                <!-- First Time / No Scan State -->
                <div class="wcpa-welcome-card">
                    <div class="wcpa-welcome-icon">
                        <span class="dashicons dashicons-performance"></span>
                    </div>
                    <h2><?php esc_html_e( 'Analyze Your Store\'s Performance', 'wc-performance-analyzer' ); ?></h2>
                    <p><?php esc_html_e( 'Run a health scan to identify performance bottlenecks, database bloat, and optimization opportunities.', 'wc-performance-analyzer' ); ?></p>
                    <button type="button" class="button button-primary button-hero wcpa-run-scan">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e( 'Run Health Scan', 'wc-performance-analyzer' ); ?>
                    </button>
                </div>
            <?php else : ?>
                <!-- Dashboard Header Row -->
                <div class="wcpa-dashboard-header">
                    <div class="wcpa-header-left">
                        <h2 class="wcpa-dashboard-title"><?php esc_html_e( 'Store Health Overview', 'wc-performance-analyzer' ); ?></h2>
                        <p class="wcpa-scan-time">
                            <?php
                            printf(
                                /* translators: %s: time since last scan */
                                esc_html__( 'Last scanned %s ago', 'wc-performance-analyzer' ),
                                esc_html( $time_since_scan )
                            );
                            ?>
                        </p>
                    </div>
                    <div class="wcpa-header-right">
                        <button type="button" class="button button-primary wcpa-run-scan">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e( 'Rescan', 'wc-performance-analyzer' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Main Dashboard Grid -->
                <div class="wcpa-dashboard-grid">
                    <!-- Left Column: Health Score -->
                    <div class="wcpa-dashboard-left">
                        <!-- Health Score Card -->
                        <div class="wcpa-card wcpa-health-score-card">
                            <div class="wcpa-score-circle wcpa-score-<?php echo esc_attr( $score_color ); ?>">
                                <span class="wcpa-score-number"><?php echo esc_html( $health_score ); ?></span>
                                <span class="wcpa-score-max">/100</span>
                            </div>
                            <div class="wcpa-score-info">
                                <span class="wcpa-score-badge wcpa-badge-<?php echo esc_attr( $score_color ); ?>">
                                    <?php echo esc_html( $score_label ); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Score Breakdown -->
                        <div class="wcpa-card wcpa-breakdown-card">
                            <h3><?php esc_html_e( 'Score Breakdown', 'wc-performance-analyzer' ); ?></h3>
                            <div class="wcpa-breakdown-list">
                                <?php
                                $breakdown_labels = array(
                                    'autoload'           => __( 'Autoload Size', 'wc-performance-analyzer' ),
                                    'orphaned_meta'      => __( 'Orphaned Meta', 'wc-performance-analyzer' ),
                                    'expired_transients' => __( 'Expired Transients', 'wc-performance-analyzer' ),
                                    'wc_sessions'        => __( 'WC Sessions', 'wc-performance-analyzer' ),
                                    'meta_per_product'   => __( 'Meta per Product', 'wc-performance-analyzer' ),
                                    'revisions'          => __( 'Post Revisions', 'wc-performance-analyzer' ),
                                );
                                foreach ( $breakdown as $key => $data ) :
                                    $label  = $breakdown_labels[ $key ] ?? ucfirst( str_replace( '_', ' ', $key ) );
                                    $score  = $data['score'] ?? 0;
                                    $status = $data['status'] ?? 'good';
                                    $weight = ( $data['weight'] ?? 0 ) * 100;
                                    ?>
                                    <div class="wcpa-breakdown-item">
                                        <div class="wcpa-breakdown-header">
                                            <span class="wcpa-breakdown-label"><?php echo esc_html( $label ); ?></span>
                                            <span class="wcpa-breakdown-score"><?php echo esc_html( $score ); ?>/100</span>
                                        </div>
                                        <div class="wcpa-progress-bar">
                                            <div class="wcpa-progress-fill wcpa-progress-<?php echo esc_attr( $status ); ?>" style="width: <?php echo esc_attr( $score ); ?>%;"></div>
                                        </div>
                                        <span class="wcpa-breakdown-weight"><?php echo esc_html( $weight ); ?>% weight</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Store Stats & Quick Actions -->
                    <div class="wcpa-dashboard-right">
                        <!-- Store Overview -->
                        <div class="wcpa-card wcpa-store-overview-card">
                            <h3><?php esc_html_e( 'Store Overview', 'wc-performance-analyzer' ); ?></h3>
                            <div class="wcpa-store-stats">
                                <div class="wcpa-stat-item">
                                    <span class="wcpa-stat-icon dashicons dashicons-products"></span>
                                    <div class="wcpa-stat-content">
                                        <span class="wcpa-stat-value"><?php echo esc_html( number_format( $metrics['total_products'] ?? 0 ) ); ?></span>
                                        <span class="wcpa-stat-label"><?php esc_html_e( 'Products', 'wc-performance-analyzer' ); ?></span>
                                    </div>
                                </div>
                                <div class="wcpa-stat-item">
                                    <span class="wcpa-stat-icon dashicons dashicons-cart"></span>
                                    <div class="wcpa-stat-content">
                                        <span class="wcpa-stat-value"><?php echo esc_html( number_format( $metrics['total_orders'] ?? 0 ) ); ?></span>
                                        <span class="wcpa-stat-label"><?php esc_html_e( 'Orders', 'wc-performance-analyzer' ); ?></span>
                                    </div>
                                </div>
                                <div class="wcpa-stat-item">
                                    <span class="wcpa-stat-icon dashicons dashicons-database"></span>
                                    <div class="wcpa-stat-content">
                                        <span class="wcpa-stat-value"><?php echo esc_html( number_format( $metrics['postmeta_rows'] ?? 0 ) ); ?></span>
                                        <span class="wcpa-stat-label"><?php esc_html_e( 'Meta Rows', 'wc-performance-analyzer' ); ?></span>
                                    </div>
                                </div>
                                <div class="wcpa-stat-item">
                                    <span class="wcpa-stat-icon dashicons dashicons-backup"></span>
                                    <div class="wcpa-stat-content">
                                        <span class="wcpa-stat-value"><?php echo esc_html( number_format( $metrics['total_revisions'] ?? 0 ) ); ?></span>
                                        <span class="wcpa-stat-label"><?php esc_html_e( 'Revisions', 'wc-performance-analyzer' ); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Key Metrics -->
                        <div class="wcpa-card wcpa-key-metrics-card">
                            <h3><?php esc_html_e( 'Key Metrics', 'wc-performance-analyzer' ); ?></h3>
                            <div class="wcpa-key-metrics">
                                <?php
                                $key_metrics = array(
                                    array(
                                        'label'  => __( 'Autoload Size', 'wc-performance-analyzer' ),
                                        'value'  => $display_metrics['autoload_size']['value'] ?? '--',
                                        'status' => $this->get_metric_status( $breakdown['autoload']['status'] ?? 'good' ),
                                        'icon'   => 'admin-settings',
                                    ),
                                    array(
                                        'label'  => __( 'Transients', 'wc-performance-analyzer' ),
                                        'value'  => $display_metrics['transients']['value'] ?? '--',
                                        'sub'    => $display_metrics['transients']['sub'] ?? '',
                                        'status' => $this->get_metric_status( $breakdown['expired_transients']['status'] ?? 'good' ),
                                        'icon'   => 'clock',
                                    ),
                                    array(
                                        'label'  => __( 'WC Sessions', 'wc-performance-analyzer' ),
                                        'value'  => $display_metrics['sessions']['value'] ?? '--',
                                        'sub'    => $display_metrics['sessions']['sub'] ?? '',
                                        'status' => $this->get_metric_status( $breakdown['wc_sessions']['status'] ?? 'good' ),
                                        'icon'   => 'groups',
                                    ),
                                    array(
                                        'label'  => __( 'Orphaned Meta', 'wc-performance-analyzer' ),
                                        'value'  => $display_metrics['orphaned_meta']['value'] ?? '--',
                                        'status' => $this->get_metric_status( $breakdown['orphaned_meta']['status'] ?? 'good' ),
                                        'icon'   => 'editor-unlink',
                                    ),
                                );
                                foreach ( $key_metrics as $metric ) :
                                    ?>
                                    <div class="wcpa-key-metric wcpa-metric-<?php echo esc_attr( $metric['status'] ); ?>">
                                        <span class="wcpa-key-metric-icon dashicons dashicons-<?php echo esc_attr( $metric['icon'] ); ?>"></span>
                                        <div class="wcpa-key-metric-content">
                                            <span class="wcpa-key-metric-value"><?php echo esc_html( $metric['value'] ); ?></span>
                                            <span class="wcpa-key-metric-label"><?php echo esc_html( $metric['label'] ); ?></span>
                                            <?php if ( ! empty( $metric['sub'] ) ) : ?>
                                                <span class="wcpa-key-metric-sub"><?php echo esc_html( $metric['sub'] ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="wcpa-metric-status-dot"></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="wcpa-card wcpa-quick-actions-card">
                            <h3><?php esc_html_e( 'Quick Actions', 'wc-performance-analyzer' ); ?></h3>
                            <div class="wcpa-quick-actions">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpa-cleanup' ) ); ?>" class="wcpa-quick-action">
                                    <span class="dashicons dashicons-trash"></span>
                                    <span><?php esc_html_e( 'Run Cleanup', 'wc-performance-analyzer' ); ?></span>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpa-query-log' ) ); ?>" class="wcpa-quick-action">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <span><?php esc_html_e( 'Query Log', 'wc-performance-analyzer' ); ?></span>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpa-optimizations' ) ); ?>" class="wcpa-quick-action">
                                    <span class="dashicons dashicons-performance"></span>
                                    <span><?php esc_html_e( 'Optimizations', 'wc-performance-analyzer' ); ?></span>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpa-settings' ) ); ?>" class="wcpa-quick-action">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    <span><?php esc_html_e( 'Settings', 'wc-performance-analyzer' ); ?></span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ( ! empty( $recommendations ) ) : ?>
                    <!-- Recommendations -->
                    <div class="wcpa-card wcpa-recommendations-card">
                        <h3>
                            <span class="dashicons dashicons-lightbulb"></span>
                            <?php esc_html_e( 'Recommendations', 'wc-performance-analyzer' ); ?>
                        </h3>
                        <div class="wcpa-recommendations-list">
                            <?php foreach ( $recommendations as $rec ) : ?>
                                <div class="wcpa-recommendation wcpa-recommendation-<?php echo esc_attr( $rec['type'] ); ?>">
                                    <span class="wcpa-rec-icon dashicons dashicons-<?php echo $rec['type'] === 'critical' ? 'warning' : ( $rec['type'] === 'warning' ? 'flag' : 'info-outline' ); ?>"></span>
                                    <div class="wcpa-rec-content">
                                        <p class="wcpa-rec-message"><?php echo esc_html( $rec['message'] ); ?></p>
                                        <?php if ( ! empty( $rec['action'] ) ) : ?>
                                            <p class="wcpa-rec-action"><?php echo esc_html( $rec['action'] ); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Top Autoloaded Options -->
                <div class="wcpa-card wcpa-autoload-card">
                    <h3>
                        <span class="dashicons dashicons-database-view"></span>
                        <?php esc_html_e( 'Top Autoloaded Options', 'wc-performance-analyzer' ); ?>
                    </h3>
                    <?php
                    $top_autoload = $this->scanner->get_top_autoloaded_options( 10 );
                    if ( ! empty( $top_autoload ) ) :
                        ?>
                        <table class="wcpa-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Option Name', 'wc-performance-analyzer' ); ?></th>
                                    <th class="wcpa-col-size"><?php esc_html_e( 'Size', 'wc-performance-analyzer' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $top_autoload as $option ) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html( $option['name'] ); ?></code></td>
                                        <td class="wcpa-col-size"><?php echo esc_html( $option['size'] ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="wcpa-no-data"><?php esc_html_e( 'No autoloaded options found.', 'wc-performance-analyzer' ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $this->render_page_footer();
    }

    /**
     * Get CSS class suffix for metric status.
     *
     * @param string $status Status string.
     * @return string CSS class suffix.
     */
    private function get_metric_status( string $status ): string {
        $map = array(
            'excellent' => 'good',
            'good'      => 'good',
            'warning'   => 'warning',
            'critical'  => 'critical',
        );

        return $map[ $status ] ?? 'good';
    }

    /**
     * Render the cleanup tools page.
     *
     * @return void
     */
    public function render_cleanup_page(): void {
        $this->render_page_header( __( 'Cleanup Tools', 'wc-performance-analyzer' ) );

        // Get cleanup stats.
        $stats = $this->cleanup_manager->get_all_counts();
        ?>
        <div class="wcpa-cleanup-wrapper">
            <div class="wcpa-notice wcpa-notice-warning">
                <p>
                    <strong><?php esc_html_e( 'Important:', 'wc-performance-analyzer' ); ?></strong>
                    <?php esc_html_e( 'Always backup your database before running cleanup operations.', 'wc-performance-analyzer' ); ?>
                </p>
            </div>

            <div class="wcpa-cleanup-grid">
                <!-- Transients Cleanup -->
                <div class="wcpa-cleanup-card" data-type="transients">
                    <h3><?php esc_html_e( 'Expired Transients', 'wc-performance-analyzer' ); ?></h3>
                    <p><?php esc_html_e( 'Remove expired transient data from the database.', 'wc-performance-analyzer' ); ?></p>
                    <div class="wcpa-cleanup-stats">
                        <span class="wcpa-count"><?php echo esc_html( number_format( $stats['transients'] ?? 0 ) ); ?></span>
                        <span class="wcpa-label"><?php esc_html_e( 'items found', 'wc-performance-analyzer' ); ?></span>
                    </div>
                    <button type="button" class="button wcpa-preview-cleanup" data-type="transients">
                        <?php esc_html_e( 'Preview', 'wc-performance-analyzer' ); ?>
                    </button>
                    <button type="button" class="button button-primary wcpa-execute-cleanup" data-type="transients">
                        <?php esc_html_e( 'Clean', 'wc-performance-analyzer' ); ?>
                    </button>
                </div>

                <!-- Sessions Cleanup -->
                <div class="wcpa-cleanup-card" data-type="sessions">
                    <h3><?php esc_html_e( 'WooCommerce Sessions', 'wc-performance-analyzer' ); ?></h3>
                    <p><?php esc_html_e( 'Clear expired customer sessions.', 'wc-performance-analyzer' ); ?></p>
                    <div class="wcpa-cleanup-stats">
                        <span class="wcpa-count"><?php echo esc_html( number_format( $stats['sessions'] ?? 0 ) ); ?></span>
                        <span class="wcpa-label"><?php esc_html_e( 'items found', 'wc-performance-analyzer' ); ?></span>
                    </div>
                    <button type="button" class="button wcpa-preview-cleanup" data-type="sessions">
                        <?php esc_html_e( 'Preview', 'wc-performance-analyzer' ); ?>
                    </button>
                    <button type="button" class="button button-primary wcpa-execute-cleanup" data-type="sessions">
                        <?php esc_html_e( 'Clean', 'wc-performance-analyzer' ); ?>
                    </button>
                </div>

                <!-- Orphaned Meta Cleanup -->
                <div class="wcpa-cleanup-card" data-type="orphaned_meta">
                    <h3><?php esc_html_e( 'Orphaned Post Meta', 'wc-performance-analyzer' ); ?></h3>
                    <p><?php esc_html_e( 'Remove meta data for deleted posts.', 'wc-performance-analyzer' ); ?></p>
                    <div class="wcpa-cleanup-stats">
                        <span class="wcpa-count"><?php echo esc_html( number_format( $stats['orphaned_meta'] ?? 0 ) ); ?></span>
                        <span class="wcpa-label"><?php esc_html_e( 'items found', 'wc-performance-analyzer' ); ?></span>
                    </div>
                    <button type="button" class="button wcpa-preview-cleanup" data-type="orphaned_meta">
                        <?php esc_html_e( 'Preview', 'wc-performance-analyzer' ); ?>
                    </button>
                    <button type="button" class="button button-primary wcpa-execute-cleanup" data-type="orphaned_meta">
                        <?php esc_html_e( 'Clean', 'wc-performance-analyzer' ); ?>
                    </button>
                </div>

                <!-- Revisions Cleanup -->
                <div class="wcpa-cleanup-card" data-type="revisions">
                    <h3><?php esc_html_e( 'Post Revisions', 'wc-performance-analyzer' ); ?></h3>
                    <p><?php esc_html_e( 'Remove excess post revisions (keeps last 5).', 'wc-performance-analyzer' ); ?></p>
                    <div class="wcpa-cleanup-stats">
                        <span class="wcpa-count"><?php echo esc_html( number_format( $stats['revisions'] ?? 0 ) ); ?></span>
                        <span class="wcpa-label"><?php esc_html_e( 'items found', 'wc-performance-analyzer' ); ?></span>
                    </div>
                    <button type="button" class="button wcpa-preview-cleanup" data-type="revisions">
                        <?php esc_html_e( 'Preview', 'wc-performance-analyzer' ); ?>
                    </button>
                    <button type="button" class="button button-primary wcpa-execute-cleanup" data-type="revisions">
                        <?php esc_html_e( 'Clean', 'wc-performance-analyzer' ); ?>
                    </button>
                </div>

                <!-- Trash Cleanup -->
                <div class="wcpa-cleanup-card" data-type="trash">
                    <h3><?php esc_html_e( 'Trashed Posts', 'wc-performance-analyzer' ); ?></h3>
                    <p><?php esc_html_e( 'Permanently delete trashed posts.', 'wc-performance-analyzer' ); ?></p>
                    <div class="wcpa-cleanup-stats">
                        <span class="wcpa-count"><?php echo esc_html( number_format( $stats['trash'] ?? 0 ) ); ?></span>
                        <span class="wcpa-label"><?php esc_html_e( 'items found', 'wc-performance-analyzer' ); ?></span>
                    </div>
                    <button type="button" class="button wcpa-preview-cleanup" data-type="trash">
                        <?php esc_html_e( 'Preview', 'wc-performance-analyzer' ); ?>
                    </button>
                    <button type="button" class="button button-primary wcpa-execute-cleanup" data-type="trash">
                        <?php esc_html_e( 'Clean', 'wc-performance-analyzer' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        $this->render_page_footer();
    }

    /**
     * Render the query log page.
     *
     * @return void
     */
    public function render_query_log_page(): void {
        $this->render_page_header( __( 'Query Log', 'wc-performance-analyzer' ) );

        $settings = get_option( 'wcpa_settings', array() );
        $is_enabled = ! empty( $settings['query_log_enabled'] );
        ?>
        <div class="wcpa-query-log-wrapper">
            <div class="wcpa-notice wcpa-notice-warning">
                <p>
                    <strong><?php esc_html_e( 'Performance Impact:', 'wc-performance-analyzer' ); ?></strong>
                    <?php esc_html_e( 'Query logging impacts site performance. Enable only for debugging and disable when done.', 'wc-performance-analyzer' ); ?>
                </p>
            </div>

            <div class="wcpa-card">
                <h3><?php esc_html_e( 'Query Logger Controls', 'wc-performance-analyzer' ); ?></h3>
                
                <div class="wcpa-query-log-controls">
                    <label class="wcpa-toggle-label">
                        <input type="checkbox" class="wcpa-toggle-query-log" <?php checked( $is_enabled ); ?>>
                        <?php esc_html_e( 'Enable Query Logging', 'wc-performance-analyzer' ); ?>
                    </label>
                    
                    <div class="wcpa-query-log-stats">
                        <div class="wcpa-stat">
                            <span class="wcpa-stat-label"><?php esc_html_e( 'Status:', 'wc-performance-analyzer' ); ?></span>
                            <span class="wcpa-stat-value wcpa-log-status">
                                <?php echo $is_enabled ? esc_html__( 'Enabled', 'wc-performance-analyzer' ) : esc_html__( 'Disabled', 'wc-performance-analyzer' ); ?>
                            </span>
                        </div>
                        <div class="wcpa-stat">
                            <span class="wcpa-stat-label"><?php esc_html_e( 'Logged Queries:', 'wc-performance-analyzer' ); ?></span>
                            <span class="wcpa-stat-value wcpa-log-count">--</span>
                        </div>
                        <div class="wcpa-stat">
                            <span class="wcpa-stat-label"><?php esc_html_e( 'Threshold:', 'wc-performance-analyzer' ); ?></span>
                            <span class="wcpa-stat-value"><?php echo isset( $settings['query_log_threshold'] ) ? esc_html( $settings['query_log_threshold'] ) : '0.05'; ?>s</span>
                        </div>
                    </div>

                    <button type="button" class="button wcpa-clear-logs">
                        <?php esc_html_e( 'Clear All Logs', 'wc-performance-analyzer' ); ?>
                    </button>
                </div>
            </div>

            <div class="wcpa-notice wcpa-notice-info">
                <p><?php esc_html_e( 'Query Log Viewer coming in Phase 6. Queries are being logged to the database.', 'wc-performance-analyzer' ); ?></p>
            </div>

            <div class="wcpa-card">
                <h3><?php esc_html_e( 'Query Log Preview', 'wc-performance-analyzer' ); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40%;"><?php esc_html_e( 'Query', 'wc-performance-analyzer' ); ?></th>
                            <th style="width: 10%;"><?php esc_html_e( 'Type', 'wc-performance-analyzer' ); ?></th>
                            <th style="width: 10%;"><?php esc_html_e( 'Time (s)', 'wc-performance-analyzer' ); ?></th>
                            <th style="width: 15%;"><?php esc_html_e( 'Request', 'wc-performance-analyzer' ); ?></th>
                            <th style="width: 15%;"><?php esc_html_e( 'Logged', 'wc-performance-analyzer' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px;">
                                <?php esc_html_e( 'Full query viewer coming in Phase 6.', 'wc-performance-analyzer' ); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        $this->render_page_footer();
    }

    /**
     * Render the optimizations page.
     *
     * @return void
     */
    public function render_optimizations_page(): void {
        $this->render_page_header( __( 'WooCommerce Optimizations', 'wc-performance-analyzer' ) );
        ?>
        <div class="wcpa-optimizations-wrapper">
            <div class="wcpa-notice wcpa-notice-info">
                <p><?php esc_html_e( 'Optimizations coming in Phase 7.', 'wc-performance-analyzer' ); ?></p>
            </div>

            <div class="wcpa-optimization-cards">
                <!-- Cart Fragments -->
                <div class="wcpa-optimization-card">
                    <h3><?php esc_html_e( 'Cart Fragments AJAX', 'wc-performance-analyzer' ); ?></h3>
                    <p><?php esc_html_e( 'Control how WooCommerce updates cart totals via AJAX.', 'wc-performance-analyzer' ); ?></p>
                    <select disabled>
                        <option><?php esc_html_e( 'Default (WooCommerce behavior)', 'wc-performance-analyzer' ); ?></option>
                        <option><?php esc_html_e( 'Optimized (reduce frequency)', 'wc-performance-analyzer' ); ?></option>
                        <option><?php esc_html_e( 'Disabled (manual refresh only)', 'wc-performance-analyzer' ); ?></option>
                    </select>
                </div>

                <!-- Checkout Analysis -->
                <div class="wcpa-optimization-card">
                    <h3><?php esc_html_e( 'Checkout Query Analysis', 'wc-performance-analyzer' ); ?></h3>
                    <p><?php esc_html_e( 'Analyze queries running on your checkout page.', 'wc-performance-analyzer' ); ?></p>
                    <button type="button" class="button" disabled><?php esc_html_e( 'Analyze Checkout', 'wc-performance-analyzer' ); ?></button>
                </div>

                <!-- High Variation Products -->
                <div class="wcpa-optimization-card">
                    <h3><?php esc_html_e( 'High-Variation Products', 'wc-performance-analyzer' ); ?></h3>
                    <p><?php esc_html_e( 'Detect products with excessive variations that slow page loads.', 'wc-performance-analyzer' ); ?></p>
                    <button type="button" class="button" disabled><?php esc_html_e( 'Scan Products', 'wc-performance-analyzer' ); ?></button>
                </div>
            </div>
        </div>
        <?php
        $this->render_page_footer();
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings_page(): void {
        $this->render_page_header( __( 'Settings', 'wc-performance-analyzer' ) );
        ?>
        <div class="wcpa-settings-wrapper">
            <div class="wcpa-notice wcpa-notice-info">
                <p><?php esc_html_e( 'Full settings coming in Phase 8.', 'wc-performance-analyzer' ); ?></p>
            </div>

            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wcpa_query_threshold"><?php esc_html_e( 'Slow Query Threshold (seconds)', 'wc-performance-analyzer' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="wcpa_query_threshold" step="0.01" value="0.05" disabled>
                            <p class="description"><?php esc_html_e( 'Queries taking longer than this will be logged.', 'wc-performance-analyzer' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wcpa_log_retention"><?php esc_html_e( 'Query Log Retention (days)', 'wc-performance-analyzer' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="wcpa_log_retention" value="7" disabled>
                            <p class="description"><?php esc_html_e( 'How long to keep query log entries.', 'wc-performance-analyzer' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wcpa_revisions_keep"><?php esc_html_e( 'Revisions to Keep', 'wc-performance-analyzer' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="wcpa_revisions_keep" value="5" disabled>
                            <p class="description"><?php esc_html_e( 'Number of revisions to retain per post during cleanup.', 'wc-performance-analyzer' ); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" disabled><?php esc_html_e( 'Save Settings', 'wc-performance-analyzer' ); ?></button>
                </p>
            </form>
        </div>
        <?php
        $this->render_page_footer();
    }

    /**
     * Render page header.
     *
     * @param string $title Page title.
     * @return void
     */
    private function render_page_header( string $title ): void {
        ?>
        <div class="wrap wcpa-wrap">
            <h1 class="wcpa-page-title">
                <span class="dashicons dashicons-performance"></span>
                <?php echo esc_html( $title ); ?>
            </h1>
            <nav class="wcpa-tabs">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>" 
                   class="<?php echo $this->is_current_page( self::MENU_SLUG ) ? 'active' : ''; ?>">
                    <?php esc_html_e( 'Dashboard', 'wc-performance-analyzer' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpa-cleanup' ) ); ?>"
                   class="<?php echo $this->is_current_page( 'wcpa-cleanup' ) ? 'active' : ''; ?>">
                    <?php esc_html_e( 'Cleanup', 'wc-performance-analyzer' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpa-query-log' ) ); ?>"
                   class="<?php echo $this->is_current_page( 'wcpa-query-log' ) ? 'active' : ''; ?>">
                    <?php esc_html_e( 'Query Log', 'wc-performance-analyzer' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpa-optimizations' ) ); ?>"
                   class="<?php echo $this->is_current_page( 'wcpa-optimizations' ) ? 'active' : ''; ?>">
                    <?php esc_html_e( 'Optimizations', 'wc-performance-analyzer' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpa-settings' ) ); ?>"
                   class="<?php echo $this->is_current_page( 'wcpa-settings' ) ? 'active' : ''; ?>">
                    <?php esc_html_e( 'Settings', 'wc-performance-analyzer' ); ?>
                </a>
            </nav>
        <?php
    }

    /**
     * Render page footer.
     *
     * @return void
     */
    private function render_page_footer(): void {
        ?>
        </div><!-- .wrap -->
        <?php
    }

    /**
     * Check if current page matches given slug.
     *
     * @param string $slug Page slug to check.
     * @return bool
     */
    private function is_current_page( string $slug ): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === $slug;
    }
}
