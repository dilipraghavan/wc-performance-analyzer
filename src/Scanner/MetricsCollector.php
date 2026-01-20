<?php
/**
 * Metrics Collector.
 *
 * Collects various database and WooCommerce metrics.
 *
 * @package suspended\WCPerformanceAnalyzer\Scanner
 */

declare(strict_types=1);

namespace suspended\WCPerformanceAnalyzer\Scanner;

/**
 * Class MetricsCollector
 *
 * Queries the database for performance-related metrics.
 */
class MetricsCollector {

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
     * Collect all metrics.
     *
     * @return array<string, mixed>
     */
    public function collect_all(): array {
        return array(
            // Database size metrics
            'autoload_size'       => $this->get_autoload_size(),
            'autoload_count'      => $this->get_autoload_count(),
            'total_options'       => $this->get_total_options(),

            // Transient metrics
            'transient_count'     => $this->get_transient_count(),
            'expired_transients'  => $this->get_expired_transient_count(),

            // Post metrics
            'total_posts'         => $this->get_total_posts(),
            'total_revisions'     => $this->get_revision_count(),
            'trashed_posts'       => $this->get_trashed_posts_count(),

            // Postmeta metrics
            'postmeta_rows'       => $this->get_postmeta_count(),
            'orphaned_postmeta'   => $this->get_orphaned_postmeta_count(),

            // WooCommerce metrics
            'total_products'      => $this->get_product_count(),
            'total_variations'    => $this->get_variation_count(),
            'total_orders'        => $this->get_order_count(),
            'wc_sessions'         => $this->get_wc_session_count(),
            'expired_wc_sessions' => $this->get_expired_wc_session_count(),

            // Calculated ratios
            'meta_per_product'    => $this->get_meta_per_product(),
            'revisions_per_post'  => $this->get_revisions_per_post(),
        );
    }

    /**
     * Get autoload data size in bytes.
     *
     * @return int
     */
    public function get_autoload_size(): int {
        $result = $this->wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) 
             FROM {$this->wpdb->options} 
             WHERE autoload = 'yes'"
        );

        return (int) $result;
    }

    /**
     * Get count of autoloaded options.
     *
     * @return int
     */
    public function get_autoload_count(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->options} 
             WHERE autoload = 'yes'"
        );

        return (int) $result;
    }

    /**
     * Get total options count.
     *
     * @return int
     */
    public function get_total_options(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->options}"
        );

        return (int) $result;
    }

    /**
     * Get total transient count.
     *
     * @return int
     */
    public function get_transient_count(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->options} 
             WHERE option_name LIKE '_transient_%' 
             AND option_name NOT LIKE '_transient_timeout_%'"
        );

        return (int) $result;
    }

    /**
     * Get expired transient count.
     *
     * @return int
     */
    public function get_expired_transient_count(): int {
        $time = time();

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$this->wpdb->options} 
                 WHERE option_name LIKE '_transient_timeout_%' 
                 AND option_value < %d",
                $time
            )
        );

        return (int) $result;
    }

    /**
     * Get total posts count (excluding revisions and auto-drafts).
     *
     * @return int
     */
    public function get_total_posts(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->posts} 
             WHERE post_type NOT IN ('revision', 'auto-draft', 'nav_menu_item')
             AND post_status != 'trash'"
        );

        return (int) $result;
    }

    /**
     * Get revision count.
     *
     * @return int
     */
    public function get_revision_count(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->posts} 
             WHERE post_type = 'revision'"
        );

        return (int) $result;
    }

    /**
     * Get trashed posts count.
     *
     * @return int
     */
    public function get_trashed_posts_count(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->posts} 
             WHERE post_status = 'trash'"
        );

        return (int) $result;
    }

    /**
     * Get postmeta row count.
     *
     * @return int
     */
    public function get_postmeta_count(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->postmeta}"
        );

        return (int) $result;
    }

    /**
     * Get orphaned postmeta count (meta for deleted posts).
     *
     * @return int
     */
    public function get_orphaned_postmeta_count(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->postmeta} pm 
             LEFT JOIN {$this->wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.ID IS NULL"
        );

        return (int) $result;
    }

    /**
     * Get WooCommerce product count.
     *
     * @return int
     */
    public function get_product_count(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->posts} 
             WHERE post_type = 'product' 
             AND post_status IN ('publish', 'draft', 'private')"
        );

        return (int) $result;
    }

    /**
     * Get product variation count.
     *
     * @return int
     */
    public function get_variation_count(): int {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->posts} 
             WHERE post_type = 'product_variation'"
        );

        return (int) $result;
    }

    /**
     * Get WooCommerce order count.
     *
     * @return int
     */
    public function get_order_count(): int {
        // Check if HPOS is enabled.
        if ( $this->is_hpos_enabled() ) {
            $result = $this->wpdb->get_var(
                "SELECT COUNT(*) 
                 FROM {$this->wpdb->prefix}wc_orders"
            );
        } else {
            $result = $this->wpdb->get_var(
                "SELECT COUNT(*) 
                 FROM {$this->wpdb->posts} 
                 WHERE post_type = 'shop_order'"
            );
        }

        return (int) $result;
    }

    /**
     * Get WooCommerce session count.
     *
     * @return int
     */
    public function get_wc_session_count(): int {
        $table = $this->wpdb->prefix . 'woocommerce_sessions';

        // Check if table exists.
        if ( ! $this->table_exists( $table ) ) {
            return 0;
        }

        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$table}"
        );

        return (int) $result;
    }

    /**
     * Get expired WooCommerce session count.
     *
     * @return int
     */
    public function get_expired_wc_session_count(): int {
        $table = $this->wpdb->prefix . 'woocommerce_sessions';

        // Check if table exists.
        if ( ! $this->table_exists( $table ) ) {
            return 0;
        }

        $time = time();

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE session_expiry < %d",
                $time
            )
        );

        return (int) $result;
    }

    /**
     * Calculate average meta entries per product.
     *
     * @return float
     */
    public function get_meta_per_product(): float {
        $product_count = $this->get_product_count();

        if ( $product_count === 0 ) {
            return 0.0;
        }

        $product_meta = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->postmeta} pm
             INNER JOIN {$this->wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = 'product'"
        );

        return round( (float) $product_meta / $product_count, 2 );
    }

    /**
     * Calculate average revisions per post.
     *
     * @return float
     */
    public function get_revisions_per_post(): float {
        $post_count = $this->get_total_posts();

        if ( $post_count === 0 ) {
            return 0.0;
        }

        $revision_count = $this->get_revision_count();

        return round( (float) $revision_count / $post_count, 2 );
    }

    /**
     * Get top autoloaded options by size.
     *
     * @param int $limit Number of results to return.
     * @return array<int, array<string, mixed>>
     */
    public function get_top_autoloaded_options( int $limit = 10 ): array {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT option_name, LENGTH(option_value) as size 
                 FROM {$this->wpdb->options} 
                 WHERE autoload = 'yes' 
                 ORDER BY size DESC 
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $results ?: array();
    }

    /**
     * Get products with high variation counts.
     *
     * @param int $threshold Minimum variation count to include.
     * @param int $limit     Number of results to return.
     * @return array<int, array<string, mixed>>
     */
    public function get_high_variation_products( int $threshold = 50, int $limit = 10 ): array {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT p.ID, p.post_title, COUNT(v.ID) as variation_count
                 FROM {$this->wpdb->posts} p
                 INNER JOIN {$this->wpdb->posts} v ON v.post_parent = p.ID
                 WHERE p.post_type = 'product'
                 AND v.post_type = 'product_variation'
                 GROUP BY p.ID
                 HAVING variation_count >= %d
                 ORDER BY variation_count DESC
                 LIMIT %d",
                $threshold,
                $limit
            ),
            ARRAY_A
        );

        return $results ?: array();
    }

    /**
     * Check if HPOS (High-Performance Order Storage) is enabled.
     *
     * @return bool
     */
    private function is_hpos_enabled(): bool {
        if ( ! class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
            return false;
        }

        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Check if a database table exists.
     *
     * @param string $table Table name.
     * @return bool
     */
    private function table_exists( string $table ): bool {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table
            )
        );

        return $result === $table;
    }
}
