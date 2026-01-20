<?php
/**
 * Admin Menu Registration.
 *
 * @package suspended\WCPerformanceAnalyzer\Admin
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer\Admin;

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
     * Constructor.
     *
     * @param HealthScanner $scanner Health scanner instance.
     */
    public function __construct( HealthScanner $scanner ) {
        $this->scanner = $scanner;
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
        $health_score = $has_scan_data ? ( $last_scan['health_score'] ?? 0 ) : null;
        $score_label  = $has_scan_data ? ( $last_scan['score_label'] ?? '' ) : '';
        $score_color  = $has_scan_data ? ( $last_scan['score_color'] ?? 'gray' ) : 'gray';
        $recommendations = $has_scan_data ? ( $last_scan['recommendations'] ?? array() ) : array();
        ?>
        <div class="wcpa-dashboard-wrapper">
            <!-- Health Score Card -->
            <div class="wcpa-card wcpa-health-score-card">
                <h2><?php esc_html_e( 'Store Health Score', 'wc-performance-analyzer' ); ?></h2>
                <div class="wcpa-health-gauge">
                    <span class="wcpa-score-placeholder <?php echo $has_scan_data ? 'wcpa-score-' . esc_attr( $score_color ) : ''; ?>">
                        <?php echo $has_scan_data ? esc_html( $health_score ) : '--'; ?>
                    </span>
                </div>
                <p class="wcpa-score-label">
                    <?php
                    if ( $has_scan_data ) {
                        echo esc_html( $score_label );
                        if ( $time_since_scan ) {
                            printf(
                                /* translators: %s: time since last scan */
                                ' &mdash; ' . esc_html__( 'Scanned %s ago', 'wc-performance-analyzer' ),
                                esc_html( $time_since_scan )
                            );
                        }
                    } else {
                        esc_html_e( 'Run a scan to calculate your score', 'wc-performance-analyzer' );
                    }
                    ?>
                </p>
                <button type="button" class="button button-primary wcpa-run-scan">
                    <?php echo $has_scan_data ? esc_html__( 'Rescan', 'wc-performance-analyzer' ) : esc_html__( 'Run Health Scan', 'wc-performance-analyzer' ); ?>
                </button>
            </div>

            <!-- Metrics Grid -->
            <div class="wcpa-metrics-grid">
                <?php foreach ( $display_metrics as $key => $metric ) : ?>
                    <div class="wcpa-metric-card" data-metric="<?php echo esc_attr( $key ); ?>">
                        <span class="wcpa-metric-value"><?php echo esc_html( $metric['value'] ); ?></span>
                        <span class="wcpa-metric-label"><?php echo esc_html( $metric['label'] ); ?></span>
                        <?php if ( ! empty( $metric['sub'] ) ) : ?>
                            <span class="wcpa-metric-sub"><?php echo esc_html( $metric['sub'] ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ( ! empty( $recommendations ) ) : ?>
                <!-- Recommendations -->
                <div class="wcpa-card wcpa-recommendations-card">
                    <h2><?php esc_html_e( 'Recommendations', 'wc-performance-analyzer' ); ?></h2>
                    <ul class="wcpa-recommendations-list">
                        <?php foreach ( $recommendations as $rec ) : ?>
                            <li class="wcpa-recommendation wcpa-recommendation-<?php echo esc_attr( $rec['type'] ); ?>">
                                <span class="wcpa-rec-icon dashicons dashicons-<?php echo $rec['type'] === 'critical' ? 'warning' : ( $rec['type'] === 'warning' ? 'flag' : 'info' ); ?>"></span>
                                <div class="wcpa-rec-content">
                                    <p class="wcpa-rec-message"><?php echo esc_html( $rec['message'] ); ?></p>
                                    <?php if ( ! empty( $rec['action'] ) ) : ?>
                                        <p class="wcpa-rec-action"><?php echo esc_html( $rec['action'] ); ?></p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ( $has_scan_data ) : ?>
                <!-- Top Autoloaded Options -->
                <div class="wcpa-card wcpa-autoload-card">
                    <h2><?php esc_html_e( 'Top Autoloaded Options', 'wc-performance-analyzer' ); ?></h2>
                    <?php
                    $top_autoload = $this->scanner->get_top_autoloaded_options( 10 );
                    if ( ! empty( $top_autoload ) ) :
                        ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Option Name', 'wc-performance-analyzer' ); ?></th>
                                    <th><?php esc_html_e( 'Size', 'wc-performance-analyzer' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $top_autoload as $option ) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html( $option['name'] ); ?></code></td>
                                        <td><?php echo esc_html( $option['size'] ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No autoloaded options found.', 'wc-performance-analyzer' ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $this->render_page_footer();
    }

    /**
     * Render the cleanup tools page.
     *
     * @return void
     */
    public function render_cleanup_page(): void {
        $this->render_page_header( __( 'Cleanup Tools', 'wc-performance-analyzer' ) );
        ?>
        <div class="wcpa-cleanup-wrapper">
            <div class="wcpa-notice wcpa-notice-info">
                <p><?php esc_html_e( 'Cleanup tools coming in Phase 4.', 'wc-performance-analyzer' ); ?></p>
            </div>

            <div class="wcpa-cleanup-grid">
                <!-- Transients Cleanup -->
                <div class="wcpa-cleanup-card">
                    <h3><?php esc_html_e( 'Expired Transients', 'wc-performance-analyzer' ); ?></h3>
                    <p><?php esc_html_e( 'Remove expired transient data from the database.', 'wc-performance-analyzer' ); ?></p>
                    <div class="wcpa-cleanup-stats">
                        <span class="wcpa-count">--</span>
                        <span class="wcpa-label"><?php esc_html_e( 'items found', 'wc-performance-analyzer' ); ?></span>
                    </div>
                    <button type="button" class="button" disabled><?php esc_html_e( 'Preview', 'wc-performance-analyzer' ); ?></button>
                    <button type="button" class="button button-primary" disabled><?php esc_html_e( 'Clean', 'wc-performance-analyzer' ); ?></button>
                </div>

                <!-- Sessions Cleanup -->
                <div class="wcpa-cleanup-card">
                    <h3><?php esc_html_e( 'WooCommerce Sessions', 'wc-performance-analyzer' ); ?></h3>
                    <p><?php esc_html_e( 'Clear expired customer sessions.', 'wc-performance-analyzer' ); ?></p>
                    <div class="wcpa-cleanup-stats">
                        <span class="wcpa-count">--</span>
                        <span class="wcpa-label"><?php esc_html_e( 'items found', 'wc-performance-analyzer' ); ?></span>
                    </div>
                    <button type="button" class="button" disabled><?php esc_html_e( 'Preview', 'wc-performance-analyzer' ); ?></button>
                    <button type="button" class="button button-primary" disabled><?php esc_html_e( 'Clean', 'wc-performance-analyzer' ); ?></button>
                </div>

                <!-- Orphaned Meta Cleanup -->
                <div class="wcpa-cleanup-card">
                    <h3><?php esc_html_e( 'Orphaned Post Meta', 'wc-performance-analyzer' ); ?></h3>
                    <p><?php esc_html_e( 'Remove meta data for deleted posts.', 'wc-performance-analyzer' ); ?></p>
                    <div class="wcpa-cleanup-stats">
                        <span class="wcpa-count">--</span>
                        <span class="wcpa-label"><?php esc_html_e( 'items found', 'wc-performance-analyzer' ); ?></span>
                    </div>
                    <button type="button" class="button" disabled><?php esc_html_e( 'Preview', 'wc-performance-analyzer' ); ?></button>
                    <button type="button" class="button button-primary" disabled><?php esc_html_e( 'Clean', 'wc-performance-analyzer' ); ?></button>
                </div>

                <!-- Revisions Cleanup -->
                <div class="wcpa-cleanup-card">
                    <h3><?php esc_html_e( 'Post Revisions', 'wc-performance-analyzer' ); ?></h3>
                    <p><?php esc_html_e( 'Remove excess post revisions.', 'wc-performance-analyzer' ); ?></p>
                    <div class="wcpa-cleanup-stats">
                        <span class="wcpa-count">--</span>
                        <span class="wcpa-label"><?php esc_html_e( 'items found', 'wc-performance-analyzer' ); ?></span>
                    </div>
                    <button type="button" class="button" disabled><?php esc_html_e( 'Preview', 'wc-performance-analyzer' ); ?></button>
                    <button type="button" class="button button-primary" disabled><?php esc_html_e( 'Clean', 'wc-performance-analyzer' ); ?></button>
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
        ?>
        <div class="wcpa-query-log-wrapper">
            <div class="wcpa-notice wcpa-notice-info">
                <p><?php esc_html_e( 'Query logging coming in Phase 5 & 6.', 'wc-performance-analyzer' ); ?></p>
            </div>

            <div class="wcpa-query-log-controls">
                <label>
                    <input type="checkbox" disabled>
                    <?php esc_html_e( 'Enable Query Logging', 'wc-performance-analyzer' ); ?>
                </label>
                <span class="wcpa-warning"><?php esc_html_e( '(Impacts performance - use for debugging only)', 'wc-performance-analyzer' ); ?></span>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Query', 'wc-performance-analyzer' ); ?></th>
                        <th><?php esc_html_e( 'Time (s)', 'wc-performance-analyzer' ); ?></th>
                        <th><?php esc_html_e( 'Pattern', 'wc-performance-analyzer' ); ?></th>
                        <th><?php esc_html_e( 'Page', 'wc-performance-analyzer' ); ?></th>
                        <th><?php esc_html_e( 'Logged', 'wc-performance-analyzer' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">
                            <?php esc_html_e( 'No queries logged yet.', 'wc-performance-analyzer' ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
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
