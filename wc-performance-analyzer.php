<?php
/**
 * Plugin Name: WooCommerce Performance Analyzer
 * Plugin URI: https://github.com/developer-developer/wc-performance-analyzer
 * Description: A diagnostic and optimization toolkit for WooCommerce stores. Identifies and fixes performance bottlenecks: database bloat, inefficient queries, session buildup, and admin slowdowns.
 * Version: 1.0.0
 * Author: Dilip - WP Shift Studio
 * Author URI: https://wpshiftstudio.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wc-performance-analyzer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 *
 * @package suspended\WCPerformanceAnalyzer
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'WCPA_VERSION', '1.0.0' );
define( 'WCPA_PLUGIN_FILE', __FILE__ );
define( 'WCPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCPA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCPA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader via Composer.
 */
if ( file_exists( WCPA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once WCPA_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function wcpa_is_woocommerce_active(): bool {
    return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active.
 *
 * @return void
 */
function wcpa_woocommerce_missing_notice(): void {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e( 'WooCommerce Performance Analyzer', 'wc-performance-analyzer' ); ?></strong>
            <?php esc_html_e( 'requires WooCommerce to be installed and active.', 'wc-performance-analyzer' ); ?>
        </p>
    </div>
    <?php
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function wcpa_init(): void {
    // Check for WooCommerce.
    if ( ! wcpa_is_woocommerce_active() ) {
        add_action( 'admin_notices', __NAMESPACE__ . '\\wcpa_woocommerce_missing_notice' );
        return;
    }

    // Boot the plugin.
    Plugin::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\wcpa_init' );

/**
 * Activation hook.
 *
 * @return void
 */
function wcpa_activate(): void {
    Activator::activate();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\wcpa_activate' );

/**
 * Deactivation hook.
 *
 * @return void
 */
function wcpa_deactivate(): void {
    Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\wcpa_deactivate' );

/**
 * Declare HPOS compatibility.
 */
add_action(
    'before_woocommerce_init',
    function () {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);
