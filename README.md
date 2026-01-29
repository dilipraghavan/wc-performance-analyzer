# WooCommerce Performance Analyzer

A comprehensive WordPress plugin for monitoring, analyzing, and optimizing WooCommerce store performance. This plugin provides deep insights into database queries, system health, and WooCommerce-specific bottlenecks, helping store owners maintain optimal performance.

## Features

### Health Scanner & Performance Metrics
- **Performance Score**: Real-time health score (0-100) with color-coded indicators (Excellent, Good, Fair, Poor)
- **Key Metrics Tracking**: Products, orders, postmeta rows, revisions, transients, sessions, and orphaned metadata
- **Automated Scans**: Schedule daily performance scans or run manual diagnostics
- **Historical Data**: Track performance trends over time

### Query Logger & Analyzer
- **Real-time Query Logging**: Capture all database queries exceeding configurable thresholds (default: 0.05s)
- **Query Viewer**: Paginated interface with filters by type (SELECT, INSERT, UPDATE, DELETE)
- **Performance Analysis**: Color-coded execution times (green <0.1s, yellow 0.1-0.5s, red >0.5s)
- **Detailed Diagnostics**: View query text, caller, stack trace, execution time, and request context
- **Query Statistics**: Track slow queries, frequency, and performance patterns
- **Configurable Retention**: Automatic cleanup of old logs (default: 7 days)

### Cleanup Suite
Five powerful cleanup operations to optimize your WordPress database:
- **Transients**: Remove expired or all WordPress transients to reduce database bloat
- **Sessions**: Clean expired WooCommerce customer sessions
- **Orphaned Metadata**: Remove postmeta and usermeta entries with no parent records
- **Post Revisions**: Limit or remove old post revisions (configurable retention)
- **Trash**: Permanently delete posts from trash older than specified days

### WooCommerce Optimizations
Five WooCommerce-specific performance modules:
- **Cart Fragments Control**: Optimize or disable AJAX cart updates (4 modes: Default, Disabled, Selective, Optimized)
- **Transient Manager**: Monitor and clean WooCommerce-specific transients with detailed statistics
- **Session Manager**: Track active/expired sessions with size monitoring and bulk cleanup
- **Image Optimizer**: Scan product images for optimization issues (oversized, large files, missing alt text)
- **Product Query Optimizer**: Analyze slow product queries and detect missing database indexes

### Dashboard & Interface
- **Admin Dashboard**: Centralized performance overview with metric cards and trend indicators
- **Cleanup Tools Page**: One-click cleanup operations with confirmation dialogs
- **Query Log Viewer**: Advanced filtering and pagination for query analysis
- **Optimizations Tab**: Real-time WooCommerce performance statistics and recommendations
- **Settings Panel**: Configure thresholds, retention periods, and optimization modes

### REST API
Full REST API for programmatic access:
- `GET /wcpa/v1/health/scan` - Run health scan
- `GET /wcpa/v1/health/metrics` - Get current metrics
- `POST /wcpa/v1/cleanup/{operation}` - Execute cleanup operations
- `GET /wcpa/v1/query-log` - Retrieve query logs
- `POST /wcpa/v1/query-log/toggle` - Enable/disable query logging
- `POST /wcpa/v1/query-log/clear` - Clear all query logs

## Installation

There are two methods for installation depending on whether you are an end-user or a developer.

### For End-Users (Packaged Plugin)

To install a ready-to-use version of the plugin, download the latest release from the [Releases page](https://github.com/dilipbhavan/wc-performance-analyzer/releases). This version is pre-packaged with all dependencies included.

1. Download the `.zip` file from the latest release.
2. In the WordPress dashboard, go to **Plugins** > **Add New**.
3. Click **Upload Plugin**, select the downloaded `.zip` file, and click **Install Now**.
4. After installation, click **Activate Plugin**.

### For Developers (with Composer)

This is the recommended method for developers who want to work with the source code or contribute to the plugin.

1. **Clone the Repository**: Clone the plugin from GitHub to your local machine using Git.
   ```bash
   git clone https://github.com/dilipbhavan/wc-performance-analyzer.git
   ```

2. **Install Dependencies**: Navigate into the cloned folder from your command line and run Composer to install the required libraries.
   ```bash
   cd wc-performance-analyzer
   composer install
   ```

3. **Create ZIP Archive**: Create a `.zip` archive of the entire `wc-performance-analyzer` folder. This zip file now contains all the necessary plugin files, including the `vendor` directory.

4. **Upload to WordPress**: In the WordPress dashboard, go to **Plugins** > **Add New**, click **Upload Plugin**, and select the `.zip` file you just created.

5. **Activate Plugin**: After installation, click **Activate Plugin**.

## Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 8.0 or higher
- **WooCommerce**: 6.0 or higher (for WooCommerce-specific features)
- **MySQL**: 5.7 or higher / MariaDB 10.2 or higher

## Usage

### Running a Health Scan

1. Navigate to **WooCommerce** > **Performance Analyzer** > **Dashboard**
2. Click the **Run Health Scan** button
3. View your performance score and metrics
4. Review recommendations for optimization

### Monitoring Query Performance

1. Enable query logging in **Settings** tab
2. Set your slow query threshold (default: 0.05 seconds)
3. Navigate to **Query Log** tab to view captured queries
4. Filter by query type, execution time, or search for specific patterns
5. Click on any query to view detailed stack trace and caller information

### Running Cleanup Operations

1. Navigate to **WooCommerce** > **Performance Analyzer** > **Cleanup**
2. Review statistics for each cleanup operation
3. Click the corresponding cleanup button
4. Confirm the operation
5. View cleanup results (items removed, database size freed)

### Optimizing WooCommerce Performance

1. Navigate to **WooCommerce** > **Performance Analyzer** > **Optimizations**
2. Review statistics for each WooCommerce module:
   - Cart Fragments: Check current mode and status
   - Transients: View total count, expired count, and database size
   - Sessions: Monitor active vs expired sessions
   - Images: Review product image optimization issues
   - Queries: Analyze product query performance
3. Adjust settings in the **Settings** tab as needed
4. Use cleanup tools to remove expired transients and sessions

### Configuring Settings

1. Navigate to **WooCommerce** > **Performance Analyzer** > **Settings**
2. Configure query logging:
   - Enable/disable query logging
   - Set slow query threshold (seconds)
   - Set query log retention period (days)
3. Configure cleanup settings:
   - Set number of revisions to keep
   - Set trash retention period
4. Configure WooCommerce optimizations:
   - Set cart fragments mode (Default, Disabled, Selective, Optimized)

### Using the REST API

All endpoints require authentication with WordPress REST API credentials.

Example: Run a health scan
```bash
curl -X GET https://yoursite.com/wp-json/wcpa/v1/health/scan \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Example: Clear transients
```bash
curl -X POST https://yoursite.com/wp-json/wcpa/v1/cleanup/transients \
  -H "Authorization: Bearer YOUR_TOKEN"
```
 
## Technical Details

### Architecture

- **Namespace**: `suspended\WCPerformanceAnalyzer`
- **Minimum PHP**: 8.0 (strict types enabled)
- **WordPress Coding Standards**: WPCS compliant
- **Database Tables**: 
  - `wp_wcpa_query_log` - Query logging and analysis
  - `wp_wcpa_metrics_snapshots` - Historical performance data
- **Cron Jobs**: Daily maintenance and cleanup tasks

### Performance Impact

- Query logger has minimal overhead (~0.001s per request)
- Database tables use optimized indexes for fast querying
- Cleanup operations run in batches to prevent timeouts
- Health scans are cacheable and run asynchronously

### Security

- All REST API endpoints require proper authentication
- Capability checks: `manage_woocommerce` for all admin features
- Input sanitization and output escaping throughout
- Prepared SQL statements for all database queries
- CSRF protection on all admin forms

## Contributing

We welcome contributions! If you have a bug fix or a new feature, please follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or bug fix.
3. Commit your changes following a clear and concise commit message format.
4. Push your branch to your forked repository.
5. Submit a pull request with a detailed description of your changes.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/dilipbhavan/wc-performance-analyzer.git
cd wc-performance-analyzer

# Install dependencies
composer install

# Run PHPCS (code standards)
composer phpcs

# Run PHPStan (static analysis)
composer phpstan
```
 
