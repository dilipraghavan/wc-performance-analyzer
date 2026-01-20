<?php
/**
 * REST API Controller.
 *
 * Handles REST API endpoints for the plugin.
 *
 * @package suspended\WCPerformanceAnalyzer\REST
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer\REST;

use suspended\WCPerformanceAnalyzer\Scanner\HealthScanner;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class RestController
 *
 * REST API endpoints for the performance analyzer.
 */
class RestController extends WP_REST_Controller {

    /**
     * Namespace.
     *
     * @var string
     */
    protected $namespace = 'wcpa/v1';

    /**
     * Health scanner instance.
     *
     * @var HealthScanner
     */
    private HealthScanner $scanner;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->scanner = new HealthScanner();
    }

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Health scan endpoint.
        register_rest_route(
            $this->namespace,
            '/scan',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'run_scan' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );

        // Get metrics endpoint.
        register_rest_route(
            $this->namespace,
            '/metrics',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_metrics' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );

        // Get last scan endpoint.
        register_rest_route(
            $this->namespace,
            '/scan/last',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_last_scan' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );

        // Get top autoloaded options.
        register_rest_route(
            $this->namespace,
            '/autoload/top',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_top_autoload' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                    'args'                => array(
                        'limit' => array(
                            'type'              => 'integer',
                            'default'           => 10,
                            'minimum'           => 1,
                            'maximum'           => 50,
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
            )
        );

        // Cleanup preview endpoint.
        register_rest_route(
            $this->namespace,
            '/cleanup/preview',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'preview_cleanup' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                    'args'                => array(
                        'type' => array(
                            'type'              => 'string',
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );

        // Cleanup run endpoint.
        register_rest_route(
            $this->namespace,
            '/cleanup/run',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'run_cleanup' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                    'args'                => array(
                        'type' => array(
                            'type'              => 'string',
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );

        // Settings endpoints.
        register_rest_route(
            $this->namespace,
            '/settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_settings' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'update_settings' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );
    }

    /**
     * Check if user has admin permission.
     *
     * @return bool|WP_Error
     */
    public function check_admin_permission() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access this endpoint.', 'wc-performance-analyzer' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Run health scan.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function run_scan( WP_REST_Request $request ) {
        try {
            $result = $this->scanner->run_scan();

            return new WP_REST_Response(
                array(
                    'success' => true,
                    'data'    => $result,
                ),
                200
            );
        } catch ( \Exception $e ) {
            return new WP_Error(
                'scan_failed',
                $e->getMessage(),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Get display metrics.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_metrics( WP_REST_Request $request ): WP_REST_Response {
        $metrics = $this->scanner->get_display_metrics();

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $metrics,
            ),
            200
        );
    }

    /**
     * Get last scan results.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_last_scan( WP_REST_Request $request ): WP_REST_Response {
        $scan = $this->scanner->get_last_scan();

        if ( ! $scan ) {
            return new WP_REST_Response(
                array(
                    'success' => true,
                    'data'    => null,
                    'message' => __( 'No scan data available. Run a scan first.', 'wc-performance-analyzer' ),
                ),
                200
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $scan,
            ),
            200
        );
    }

    /**
     * Get top autoloaded options.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_top_autoload( WP_REST_Request $request ): WP_REST_Response {
        $limit   = $request->get_param( 'limit' );
        $options = $this->scanner->get_top_autoloaded_options( $limit );

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $options,
            ),
            200
        );
    }

    /**
     * Preview cleanup operation.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function preview_cleanup( WP_REST_Request $request ): WP_REST_Response {
        $type = $request->get_param( 'type' );

        // Placeholder - will be implemented in Phase 4.
        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => array(
                    'type'    => $type,
                    'count'   => 0,
                    'message' => __( 'Cleanup preview coming in Phase 4.', 'wc-performance-analyzer' ),
                ),
            ),
            200
        );
    }

    /**
     * Run cleanup operation.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function run_cleanup( WP_REST_Request $request ): WP_REST_Response {
        $type = $request->get_param( 'type' );

        // Placeholder - will be implemented in Phase 4.
        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => array(
                    'type'    => $type,
                    'deleted' => 0,
                    'message' => __( 'Cleanup execution coming in Phase 4.', 'wc-performance-analyzer' ),
                ),
            ),
            200
        );
    }

    /**
     * Get plugin settings.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_settings( WP_REST_Request $request ): WP_REST_Response {
        $settings = get_option( 'wcpa_settings', array() );

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $settings,
            ),
            200
        );
    }

    /**
     * Update plugin settings.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function update_settings( WP_REST_Request $request ): WP_REST_Response {
        $current_settings = get_option( 'wcpa_settings', array() );
        $new_settings     = $request->get_json_params();

        // Merge with existing settings.
        $updated_settings = array_merge( $current_settings, $new_settings );

        // Sanitize settings.
        $updated_settings = $this->sanitize_settings( $updated_settings );

        update_option( 'wcpa_settings', $updated_settings );

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $updated_settings,
                'message' => __( 'Settings saved.', 'wc-performance-analyzer' ),
            ),
            200
        );
    }

    /**
     * Sanitize settings array.
     *
     * @param array<string, mixed> $settings Settings to sanitize.
     * @return array<string, mixed>
     */
    private function sanitize_settings( array $settings ): array {
        $sanitized = array();

        if ( isset( $settings['query_log_enabled'] ) ) {
            $sanitized['query_log_enabled'] = (bool) $settings['query_log_enabled'];
        }

        if ( isset( $settings['query_log_threshold'] ) ) {
            $sanitized['query_log_threshold'] = max( 0.01, min( 1.0, (float) $settings['query_log_threshold'] ) );
        }

        if ( isset( $settings['query_log_retention_days'] ) ) {
            $sanitized['query_log_retention_days'] = max( 1, min( 30, (int) $settings['query_log_retention_days'] ) );
        }

        if ( isset( $settings['cart_fragments_mode'] ) ) {
            $valid_modes = array( 'default', 'optimized', 'disabled' );
            $sanitized['cart_fragments_mode'] = in_array( $settings['cart_fragments_mode'], $valid_modes, true )
                ? $settings['cart_fragments_mode']
                : 'default';
        }

        if ( isset( $settings['cleanup_revisions_keep'] ) ) {
            $sanitized['cleanup_revisions_keep'] = max( 0, min( 50, (int) $settings['cleanup_revisions_keep'] ) );
        }

        if ( isset( $settings['scheduled_scan_enabled'] ) ) {
            $sanitized['scheduled_scan_enabled'] = (bool) $settings['scheduled_scan_enabled'];
        }

        return $sanitized;
    }
}
