=== WooCommerce Performance Analyzer ===
Contributors: wpshiftstudio
Tags: woocommerce, performance, optimization, database, cleanup
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

A diagnostic and optimization toolkit for WooCommerce stores. Identifies and fixes performance bottlenecks: database bloat, inefficient queries, session buildup, and admin slowdowns.

== Description ==

**WooCommerce Performance Analyzer** is a comprehensive diagnostic toolkit designed specifically for WooCommerce stores. Unlike generic caching plugins that apply blanket optimizations, this plugin helps you identify and fix the actual performance bottlenecks in your store.

= Key Features =

**Store Health Scanner**
* Analyzes your store's database health
* Calculates autoload size, transient count, session count
* Identifies bloat ratios and orphaned records
* Generates a health score with actionable recommendations

**Database Cleanup Suite**
* Expired transients removal
* Orphaned postmeta cleanup
* Expired WooCommerce sessions purge
* Post revisions cleanup with retention options
* Trashed posts/products removal
* Safe dry-run preview before cleanup

**Slow Query Logger**
* Captures queries exceeding configurable threshold
* Logs execution time and backtrace source
* Flags common patterns: N+1, full table scans, expensive JOINs
* Toggle on/off for production safety

**WooCommerce-Specific Optimizations**
* Cart fragments AJAX control
* Checkout page query analysis
* High-variation product detection
* Action scheduler queue health monitoring

**Performance Dashboard**
* Visual display of all collected metrics
* Before/after comparison for cleanup operations
* Health score gauge
* Quick-action buttons for common tasks

= Who Is This For? =

* Store owners experiencing slow admin or frontend
* Developers diagnosing performance issues
* Agencies managing multiple WooCommerce sites
* Anyone who wants to understand their store's database health

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wc-performance-analyzer`, or install directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to WooCommerce → Performance Analyzer to access the dashboard.

== Frequently Asked Questions ==

= Will this plugin slow down my site? =

No. The plugin only runs scans and cleanups when you manually trigger them. The query logger is disabled by default and should only be enabled temporarily for debugging.

= Is it safe to run the cleanup tools? =

Yes. Each cleanup tool includes a "Preview" option that shows exactly what will be deleted before you commit. We recommend always previewing first.

= Does this replace caching plugins? =

No. This plugin focuses on diagnosing and fixing database-level issues. It complements caching plugins like WP Rocket or W3 Total Cache.

= Will the query logger impact performance? =

The query logger does add overhead, which is why it's disabled by default. Enable it only when actively debugging, then disable it again.

== Screenshots ==

1. Performance Dashboard with health score
2. Database cleanup tools with preview
3. Slow query log viewer
4. WooCommerce optimizations panel

== Changelog ==

= 1.0.0 =
* Initial release
* Store Health Scanner
* Database Cleanup Suite
* Slow Query Logger
* WooCommerce Optimizations
* Performance Dashboard

== Upgrade Notice ==

= 1.0.0 =
Initial release of WooCommerce Performance Analyzer.
