<?php
/**
 * Main Plugin Class.
 *
 * @package suspended\WCPerformanceAnalyzer
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer;

use suspended\WCPerformanceAnalyzer\Admin\AdminMenu;
use suspended\WCPerformanceAnalyzer\REST\RestController;
use suspended\WCPerformanceAnalyzer\Scanner\HealthScanner;

/**
 * Class Plugin
 *
 * Main plugin class using singleton pattern.
 */
final class Plugin {

    /**
     * Plugin instance.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Admin menu instance.
     *
     * @var AdminMenu|null
     */
    private ?AdminMenu $admin_menu = null;

    /**
     * REST controller instance.
     *
     * @var RestController|null
     */
    private ?RestController $rest_controller = null;

    /**
     * Health scanner instance.
     *
     * @var HealthScanner|null
     */
    private ?HealthScanner $scanner = null;

    /**
     * Get plugin instance.
     *
     * @return Plugin
     */
    public static function get_instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     *
     * @throws \Exception Always throws exception.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton.' );
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        // Load text domain.
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Register REST API routes.
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // Admin-only hooks.
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        }
    }

    /**
     * Initialize plugin components.
     *
     * @return void
     */
    private function init_components(): void {
        // Initialize scanner.
        $this->scanner = new HealthScanner();

        // Initialize REST controller.
        $this->rest_controller = new RestController();

        // Admin menu.
        if ( is_admin() ) {
            $this->admin_menu = new AdminMenu( $this->scanner );
        }
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        if ( $this->rest_controller ) {
            $this->rest_controller->register_routes();
        }
    }

    /**
     * Load plugin textdomain.
     *
     * @return void
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'wc-performance-analyzer',
            false,
            dirname( WCPA_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     * @return void
     */
    public function enqueue_admin_assets( string $hook_suffix ): void {
        // Only load on our plugin pages.
        if ( strpos( $hook_suffix, 'wcpa' ) === false && strpos( $hook_suffix, 'wc-performance' ) === false ) {
            return;
        }

        // Dashboard CSS.
        wp_enqueue_style(
            'wcpa-admin-dashboard',
            WCPA_PLUGIN_URL . 'assets/css/admin-dashboard.css',
            array(),
            WCPA_VERSION
        );

        // Dashboard JS.
        wp_enqueue_script(
            'wcpa-admin-dashboard',
            WCPA_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            array( 'jquery' ),
            WCPA_VERSION,
            true
        );

        // Localize script.
        wp_localize_script(
            'wcpa-admin-dashboard',
            'wcpaAdmin',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'restUrl'   => rest_url( 'wcpa/v1/' ),
                'nonce'     => wp_create_nonce( 'wcpa_admin_nonce' ),
                'restNonce' => wp_create_nonce( 'wp_rest' ),
                'strings'   => array(
                    'scanning'    => __( 'Scanning...', 'wc-performance-analyzer' ),
                    'cleaning'    => __( 'Cleaning...', 'wc-performance-analyzer' ),
                    'success'     => __( 'Success!', 'wc-performance-analyzer' ),
                    'error'       => __( 'An error occurred.', 'wc-performance-analyzer' ),
                    'confirmClean' => __( 'Are you sure you want to run this cleanup?', 'wc-performance-analyzer' ),
                ),
            )
        );
    }

    /**
     * Get admin menu instance.
     *
     * @return AdminMenu|null
     */
    public function get_admin_menu(): ?AdminMenu {
        return $this->admin_menu;
    }

    /**
     * Get health scanner instance.
     *
     * @return HealthScanner|null
     */
    public function get_scanner(): ?HealthScanner {
        return $this->scanner;
    }
}
