<?php
/**
 * WP Product Feed Manager Feed Performance Monitor
 *
 * Tracks performance metrics during feed generation for optimization testing.
 * Monitors: feed generation time, memory usage, database queries, and file I/O operations.
 *
 * @package WP Product Feed Manager/Application
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPPFM_Feed_Performance_Monitor
 *
 * Monitors feed generation performance metrics.
 */
class WPPFM_Feed_Performance_Monitor {

	/**
	 * Singleton instance
	 *
	 * @var WPPFM_Feed_Performance_Monitor
	 */
	private static $instance = null;

	/**
	 * Performance data for current feed
	 *
	 * @var array
	 */
	private $metrics = array();

	/**
	 * Feed ID being monitored
	 *
	 * @var string
	 */
	private $current_feed_id = null;

	/**
	 * Query count at start
	 *
	 * @var int
	 */
	private $start_query_count = 0;

	/**
	 * File operation counter
	 *
	 * @var int
	 */
	private $file_operations = 0;

	/**
	 * Products processed counter
	 *
	 * @var int
	 */
	private $products_processed = 0;

	/**
	 * Memory samples collected during processing
	 *
	 * @var array
	 */
	private $memory_samples = array();

	/**
	 * Get singleton instance
	 *
	 * @return WPPFM_Feed_Performance_Monitor
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - private for singleton
	 */
	private function __construct() {
		// Initialize hooks
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Only enable if monitoring is active
		if ( ! $this->is_monitoring_enabled() ) {
			return;
		}

		// Feed lifecycle hooks
		add_action( 'wppfm_feed_generation_preparing', array( $this, 'on_feed_start' ), 1, 1 );
		add_action( 'wppfm_feed_generation_ready_to_start', array( $this, 'on_feed_processing_start' ), 1, 1 );
		add_action( 'wppfm_feed_generation_complete', array( $this, 'on_feed_complete' ), 999, 1 );

		// Product processing hooks
		add_action( 'wppfm_add_product_to_feed', array( $this, 'on_product_added' ), 10, 2 );

		// Batch processing hooks
		add_action( 'wppfm_activated_next_batch', array( $this, 'on_batch_complete' ), 10, 1 );

		// File operation monitoring
		add_action( 'wppfm_before_file_write', array( $this, 'track_file_operation' ), 10, 1 );

		// Add admin menu for viewing results
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 100 );
	}

	/**
	 * Check if monitoring is enabled
	 *
	 * @return bool
	 */
	private function is_monitoring_enabled() {
		// Enable via option or constant
		return defined( 'WPPFM_ENABLE_PERFORMANCE_MONITORING' ) && WPPFM_ENABLE_PERFORMANCE_MONITORING
			|| get_option( 'wppfm_enable_performance_monitoring', false );
	}

	/**
	 * Handle feed generation start
	 *
	 * @param string $feed_id Feed ID.
	 */
	public function on_feed_start( $feed_id ) {
		$this->current_feed_id = $feed_id;

		// Initialize metrics array
		$this->metrics[ $feed_id ] = array(
			'feed_id'            => $feed_id,
			'start_time'         => microtime( true ),
			'start_memory'       => memory_get_usage( true ),
			'start_memory_real'  => memory_get_usage( false ),
			'peak_memory'        => 0,
			'products_processed' => 0,
			'batches_processed'  => 0,
			'database_queries'   => 0,
			'file_operations'    => 0,
			'memory_samples'     => array(),
			'php_version'        => PHP_VERSION,
			'wp_version'         => get_bloginfo( 'version' ),
			'memory_limit'       => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
		);

		// Enable query tracking
		if ( ! defined( 'SAVEQUERIES' ) ) {
			define( 'SAVEQUERIES', true );
		}

		global $wpdb;
		$this->start_query_count = isset( $wpdb->num_queries ) ? $wpdb->num_queries : 0;

		// Reset counters
		$this->file_operations    = 0;
		$this->products_processed = 0;
		$this->memory_samples     = array();

		// Log start
		$this->log( $feed_id, 'Performance monitoring started' );
		
		// Store the initial query count baseline in metrics for the first batch
		$this->metrics[ $feed_id ]['_last_batch_query_count'] = $this->start_query_count;
		
		// Save initial metrics to database so they persist across background requests
		update_option( 'wppfm_performance_metrics_active_' . $feed_id, $this->metrics[ $feed_id ], false );
	}

	/**
	 * Handle feed processing start (after preparation)
	 *
	 * @param string $feed_id Feed ID.
	 */
	public function on_feed_processing_start( $feed_id ) {
		if ( isset( $this->metrics[ $feed_id ] ) ) {
			$this->metrics[ $feed_id ]['processing_start_time'] = microtime( true );
			$this->log( $feed_id, 'Feed processing started' );
		}
	}

	/**
	 * Handle product added to feed
	 *
	 * @param string $feed_id    Feed ID.
	 * @param string $product_id Product ID.
	 */
	public function on_product_added( $feed_id, $product_id ) {
		// Load metrics from database if not in memory
		if ( ! isset( $this->metrics[ $feed_id ] ) ) {
			$loaded_metrics = get_option( 'wppfm_performance_metrics_active_' . $feed_id, false );
			
			if ( ! $loaded_metrics ) {
				return; // No metrics found, skip
			}
			
			$this->metrics[ $feed_id ] = $loaded_metrics;
			
			// Restore counters from loaded metrics
			$this->products_processed = isset( $this->metrics[ $feed_id ]['products_processed'] ) ? $this->metrics[ $feed_id ]['products_processed'] : 0;
			$this->memory_samples = isset( $this->metrics[ $feed_id ]['memory_samples'] ) ? $this->metrics[ $feed_id ]['memory_samples'] : array();
		}

		// Always get the current count from metrics first (in case this is a new batch)
		$this->products_processed = isset( $this->metrics[ $feed_id ]['products_processed'] ) ? $this->metrics[ $feed_id ]['products_processed'] : 0;
		$this->products_processed++;
		$this->metrics[ $feed_id ]['products_processed'] = $this->products_processed;

		// Sample memory every 10 products
		if ( $this->products_processed % 10 === 0 ) {
			$current_memory = memory_get_usage( true );
			$this->memory_samples[] = array(
				'product_count' => $this->products_processed,
				'memory'        => $current_memory,
				'timestamp'     => microtime( true ),
			);

			// Update peak memory
			if ( $current_memory > $this->metrics[ $feed_id ]['peak_memory'] ) {
				$this->metrics[ $feed_id ]['peak_memory'] = $current_memory;
			}
			
			// Update memory samples in the metrics
			$this->metrics[ $feed_id ]['memory_samples'] = $this->memory_samples;
		}
		
		// Save updated metrics to database every 5 products to ensure accurate count across batches
		// This is important because the last batch doesn't trigger on_batch_complete
		if ( $this->products_processed % 5 === 0 ) {
			update_option( 'wppfm_performance_metrics_active_' . $feed_id, $this->metrics[ $feed_id ], false );
		}
	}

	/**
	 * Handle batch completion
	 *
	 * @param string $feed_id Feed ID.
	 */
	public function on_batch_complete( $feed_id ) {
		// Load metrics from database if not in memory
		if ( ! isset( $this->metrics[ $feed_id ] ) ) {
			$this->metrics[ $feed_id ] = get_option( 'wppfm_performance_metrics_active_' . $feed_id, false );
			if ( ! $this->metrics[ $feed_id ] ) {
				return; // No metrics found, skip
			}
			// Restore counters and samples from loaded metrics
			$this->products_processed = isset( $this->metrics[ $feed_id ]['products_processed'] ) ? $this->metrics[ $feed_id ]['products_processed'] : 0;
			$this->memory_samples = isset( $this->metrics[ $feed_id ]['memory_samples'] ) ? $this->metrics[ $feed_id ]['memory_samples'] : array();
			$this->start_query_count = isset( $this->metrics[ $feed_id ]['_last_batch_query_count'] ) ? $this->metrics[ $feed_id ]['_last_batch_query_count'] : 0;
		}
		
		$this->metrics[ $feed_id ]['batches_processed'] = isset( $this->metrics[ $feed_id ]['batches_processed'] ) ? $this->metrics[ $feed_id ]['batches_processed'] + 1 : 1;

		// Sample memory at batch boundaries
		$current_memory = memory_get_usage( true );
		$this->memory_samples[] = array(
			'product_count' => $this->products_processed,
			'memory'        => $current_memory,
			'timestamp'     => microtime( true ),
			'event'         => 'batch_complete',
		);
		
		// Update memory samples in metrics
		$this->metrics[ $feed_id ]['memory_samples'] = $this->memory_samples;
		
		// Track database queries for this batch and add to cumulative total
		global $wpdb;
		$current_query_count = isset( $wpdb->num_queries ) ? $wpdb->num_queries : 0;
		$queries_in_this_batch = $current_query_count - $this->start_query_count;
		
		// Add this batch's queries to the cumulative total
		$cumulative_queries = isset( $this->metrics[ $feed_id ]['database_queries'] ) ? $this->metrics[ $feed_id ]['database_queries'] : 0;
		$this->metrics[ $feed_id ]['database_queries'] = $cumulative_queries + $queries_in_this_batch;
		
		// Store the current query count so next batch knows where to start
		$this->metrics[ $feed_id ]['_last_batch_query_count'] = $current_query_count;
		
		// Save updated metrics to database
		update_option( 'wppfm_performance_metrics_active_' . $feed_id, $this->metrics[ $feed_id ], false );
	}

	/**
	 * Track file operations
	 *
	 * @param mixed $data Data being written (unused, just for hook compatibility).
	 */
	public function track_file_operation( $data = null ) {
		// Try to get current feed ID from the global feed queue if not set
		if ( null === $this->current_feed_id ) {
			$feed_id = WPPFM_Feed_Controller::get_next_id_from_feed_queue();
			if ( $feed_id ) {
				$this->current_feed_id = $feed_id;
			}
		}
		
		// Load metrics from database if not in memory and we have a current feed ID
		if ( null !== $this->current_feed_id && ! isset( $this->metrics[ $this->current_feed_id ] ) ) {
			$this->metrics[ $this->current_feed_id ] = get_option( 'wppfm_performance_metrics_active_' . $this->current_feed_id, false );
			if ( $this->metrics[ $this->current_feed_id ] ) {
				// Restore file operations counter
				$this->file_operations = isset( $this->metrics[ $this->current_feed_id ]['file_operations'] ) ? $this->metrics[ $this->current_feed_id ]['file_operations'] : 0;
			}
		}
		
		$this->file_operations++;

		if ( null !== $this->current_feed_id && isset( $this->metrics[ $this->current_feed_id ] ) ) {
			$this->metrics[ $this->current_feed_id ]['file_operations'] = $this->file_operations;
			
			// Save updated file operations count every 50 operations
			if ( $this->file_operations % 50 === 0 ) {
				update_option( 'wppfm_performance_metrics_active_' . $this->current_feed_id, $this->metrics[ $this->current_feed_id ], false );
			}
		}
	}

	/**
	 * Handle feed generation complete
	 *
	 * @param string $feed_id Feed ID.
	 */
	public function on_feed_complete( $feed_id ) {
		// Load metrics from database if not in memory (background processing runs in separate requests)
		if ( ! isset( $this->metrics[ $feed_id ] ) ) {
			$this->metrics[ $feed_id ] = get_option( 'wppfm_performance_metrics_active_' . $feed_id, false );
		}
		
		if ( ! $this->metrics[ $feed_id ] ) {
			return;
		}
		
		// Restore counters and samples from loaded metrics
		$this->products_processed = isset( $this->metrics[ $feed_id ]['products_processed'] ) ? $this->metrics[ $feed_id ]['products_processed'] : 0;
		$this->memory_samples = isset( $this->metrics[ $feed_id ]['memory_samples'] ) ? $this->metrics[ $feed_id ]['memory_samples'] : array();
		$this->file_operations = isset( $this->metrics[ $feed_id ]['file_operations'] ) ? $this->metrics[ $feed_id ]['file_operations'] : 0;
		$this->current_feed_id = $feed_id;
		
		// Restore the query count baseline for the final batch
		global $wpdb;
		$this->start_query_count = isset( $this->metrics[ $feed_id ]['_last_batch_query_count'] ) ? $this->metrics[ $feed_id ]['_last_batch_query_count'] : 0;

		// Calculate final metrics
		$end_time   = microtime( true );
		$end_memory = memory_get_usage( true );

		$this->metrics[ $feed_id ]['end_time']              = $end_time;
		$this->metrics[ $feed_id ]['end_memory']            = $end_memory;
		$this->metrics[ $feed_id ]['total_time']            = $end_time - $this->metrics[ $feed_id ]['start_time'];
		$this->metrics[ $feed_id ]['processing_time']       = isset( $this->metrics[ $feed_id ]['processing_start_time'] )
			? $end_time - $this->metrics[ $feed_id ]['processing_start_time']
			: null;
		$this->metrics[ $feed_id ]['memory_used']           = $end_memory - $this->metrics[ $feed_id ]['start_memory'];
		$this->metrics[ $feed_id ]['peak_memory']           = max( $this->metrics[ $feed_id ]['peak_memory'], memory_get_peak_usage( true ) );
		$this->metrics[ $feed_id ]['products_processed']    = $this->products_processed;
		$this->metrics[ $feed_id ]['file_operations']       = $this->file_operations;
		$this->metrics[ $feed_id ]['memory_samples']        = $this->memory_samples;

		// Calculate database queries for the final batch and add to cumulative total
		global $wpdb;
		$current_query_count = isset( $wpdb->num_queries ) ? $wpdb->num_queries : 0;
		$queries_in_final_batch = $current_query_count - $this->start_query_count;
		
		// Add final batch queries to cumulative total
		$cumulative_queries = isset( $this->metrics[ $feed_id ]['database_queries'] ) ? $this->metrics[ $feed_id ]['database_queries'] : 0;
		$this->metrics[ $feed_id ]['database_queries'] = $cumulative_queries + $queries_in_final_batch;

		// Analyze slow queries if available
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && isset( $wpdb->queries ) ) {
			$this->metrics[ $feed_id ]['slow_queries'] = $this->analyze_slow_queries( $wpdb->queries );
		}

		// Calculate derived metrics
		if ( $this->products_processed > 0 ) {
			$this->metrics[ $feed_id ]['time_per_product']    = $this->metrics[ $feed_id ]['total_time'] / $this->products_processed;
			$this->metrics[ $feed_id ]['products_per_second'] = $this->products_processed / $this->metrics[ $feed_id ]['total_time'];
			$this->metrics[ $feed_id ]['queries_per_product'] = $this->metrics[ $feed_id ]['database_queries'] / $this->products_processed;
			$this->metrics[ $feed_id ]['memory_per_product']  = $this->metrics[ $feed_id ]['memory_used'] / $this->products_processed;
		}

		// Remove internal tracking properties before saving final metrics
		unset( $this->metrics[ $feed_id ]['_last_batch_query_count'] );
		
		// Save metrics to database
		$this->save_metrics( $feed_id );

		// Log completion
		$this->log( $feed_id, sprintf(
			'Performance monitoring complete: %d products in %.2f seconds (%.2f products/sec)',
			$this->products_processed,
			$this->metrics[ $feed_id ]['total_time'],
			isset( $this->metrics[ $feed_id ]['products_per_second'] ) ? $this->metrics[ $feed_id ]['products_per_second'] : 0
		) );
	}

	/**
	 * Analyze slow queries
	 *
	 * @param array $queries Query log from $wpdb->queries.
	 * @return array
	 */
	private function analyze_slow_queries( $queries ) {
		$slow_queries = array();
		$threshold    = 0.1; // 100ms threshold

		foreach ( $queries as $query ) {
			if ( isset( $query[1] ) && $query[1] > $threshold ) {
				$slow_queries[] = array(
					'query' => isset( $query[0] ) ? $query[0] : '',
					'time'  => isset( $query[1] ) ? $query[1] : 0,
					'stack' => isset( $query[2] ) ? $query[2] : '',
				);
			}
		}

		// Sort by execution time descending
		usort( $slow_queries, function( $a, $b ) {
			return $b['time'] <=> $a['time'];
		} );

		// Return top 20 slowest queries
		return array_slice( $slow_queries, 0, 20 );
	}

	/**
	 * Save metrics to database
	 *
	 * @param string $feed_id Feed ID.
	 */
	private function save_metrics( $feed_id ) {
		if ( ! isset( $this->metrics[ $feed_id ] ) ) {
			return;
		}

		// Get existing metrics history
		$metrics_history = get_option( 'wppfm_performance_metrics_history', array() );

		// Add current metrics with timestamp
		$metrics_history[] = array_merge(
			$this->metrics[ $feed_id ],
			array(
				'completed_at' => current_time( 'mysql' ),
				'timestamp'    => time(),
			)
		);

		// Keep only last 50 runs
		if ( count( $metrics_history ) > 50 ) {
			$metrics_history = array_slice( $metrics_history, -50, 50, false );
		}

		// Save to database
		update_option( 'wppfm_performance_metrics_history', $metrics_history, false );
		update_option( 'wppfm_performance_metrics_latest', $this->metrics[ $feed_id ], false );
		
		// Clean up the active metrics option now that we've saved the final version
		delete_option( 'wppfm_performance_metrics_active_' . $feed_id );
	}

	/**
	 * Get latest metrics
	 *
	 * @return array|false
	 */
	public function get_latest_metrics() {
		return get_option( 'wppfm_performance_metrics_latest', false );
	}

	/**
	 * Get metrics history
	 *
	 * @param int $limit Number of records to return.
	 * @return array
	 */
	public function get_metrics_history( $limit = 10 ) {
		$history = get_option( 'wppfm_performance_metrics_history', array() );
		return array_slice( $history, -$limit, $limit, false );
	}

	/**
	 * Clear all metrics
	 */
	public function clear_metrics() {
		delete_option( 'wppfm_performance_metrics_latest' );
		delete_option( 'wppfm_performance_metrics_history' );
		$this->metrics = array();
	}

	/**
	 * Log message
	 *
	 * @param string $feed_id Feed ID.
	 * @param string $message Message to log.
	 */
	private function log( $feed_id, $message ) {
		do_action( 'wppfm_feed_generation_message', $feed_id, $message );
	}

	/**
	 * Add admin menu for viewing metrics
	 */
	public function add_admin_menu() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		add_submenu_page(
			'wp-product-feed-manager',
			'Performance Metrics',
			'Performance',
			'manage_woocommerce',
			'wppfm-performance-metrics',
			array( $this, 'render_metrics_page' )
		);
	}

	/**
	 * Render performance metrics page
	 */
	public function render_metrics_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Handle actions
		if ( isset( $_POST['wppfm_clear_metrics'] ) && check_admin_referer( 'wppfm_clear_metrics' ) ) {
			$this->clear_metrics();
			echo '<div class="notice notice-success"><p>Performance metrics cleared.</p></div>';
		}

		$history = $this->get_metrics_history( 20 );

		?>
		<div class="wrap">
			<h1>Feed Performance Metrics</h1>

			<?php if ( ! empty( $history ) ) : ?>
				<p>Performance metrics for the last <?php echo count( $history ); ?> feed generations.</p>
				<?php $this->render_history_table( $history ); ?>
				
				<p style="margin-top: 20px;">
					<form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear all performance metrics?');">
						<?php wp_nonce_field( 'wppfm_clear_metrics' ); ?>
						<button type="submit" name="wppfm_clear_metrics" class="button">Clear All Metrics</button>
					</form>
				</p>
			<?php else : ?>
				<div class="notice notice-info">
					<p>No performance metrics available yet. Generate a feed to see performance data.</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render metrics table
	 *
	 * @param array $metrics Metrics data.
	 */
	private function render_metrics_table( $metrics ) {
		?>
		<table class="widefat" style="max-width: 1200px;">
			<thead>
				<tr>
					<th colspan="2"><strong>Performance Summary</strong></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><strong>Feed ID</strong></td>
					<td><?php echo esc_html( $metrics['feed_id'] ); ?></td>
				</tr>
				<tr>
					<td><strong>Products Processed</strong></td>
					<td><?php echo esc_html( $metrics['products_processed'] ); ?></td>
				</tr>
				<tr>
					<td><strong>Batches Processed</strong></td>
					<td><?php echo esc_html( $metrics['batches_processed'] ); ?></td>
				</tr>
				<tr>
					<td><strong>Total Time</strong></td>
					<td><?php echo esc_html( number_format( $metrics['total_time'], 2 ) ); ?> seconds</td>
				</tr>
				<?php if ( isset( $metrics['processing_time'] ) && $metrics['processing_time'] ) : ?>
				<tr>
					<td><strong>Processing Time (excluding prep)</strong></td>
					<td><?php echo esc_html( number_format( $metrics['processing_time'], 2 ) ); ?> seconds</td>
				</tr>
				<?php endif; ?>
				<tr>
					<td><strong>Products per Second</strong></td>
					<td><?php echo isset( $metrics['products_per_second'] ) ? esc_html( number_format( $metrics['products_per_second'], 2 ) ) : 'N/A'; ?></td>
				</tr>
				<tr>
					<td><strong>Time per Product</strong></td>
					<td><?php echo isset( $metrics['time_per_product'] ) ? esc_html( number_format( $metrics['time_per_product'], 4 ) ) . ' seconds' : 'N/A'; ?></td>
				</tr>
				<tr>
					<td colspan="2"><strong>Memory Usage</strong></td>
				</tr>
				<tr>
					<td><strong>Start Memory</strong></td>
					<td><?php echo esc_html( $this->format_bytes( $metrics['start_memory'] ) ); ?></td>
				</tr>
				<tr>
					<td><strong>End Memory</strong></td>
					<td><?php echo esc_html( $this->format_bytes( $metrics['end_memory'] ) ); ?></td>
				</tr>
				<tr>
					<td><strong>Memory Used</strong></td>
					<td><?php echo esc_html( $this->format_bytes( $metrics['memory_used'] ) ); ?></td>
				</tr>
				<tr>
					<td><strong>Peak Memory</strong></td>
					<td><?php echo esc_html( $this->format_bytes( $metrics['peak_memory'] ) ); ?></td>
				</tr>
				<tr>
					<td><strong>Memory per Product</strong></td>
					<td><?php echo isset( $metrics['memory_per_product'] ) ? esc_html( $this->format_bytes( $metrics['memory_per_product'] ) ) : 'N/A'; ?></td>
				</tr>
				<tr>
					<td colspan="2"><strong>Database & I/O</strong></td>
				</tr>
				<tr>
					<td><strong>Database Queries</strong></td>
					<td><?php echo esc_html( number_format( $metrics['database_queries'] ) ); ?></td>
				</tr>
				<tr>
					<td><strong>Queries per Product</strong></td>
					<td><?php echo isset( $metrics['queries_per_product'] ) ? esc_html( number_format( $metrics['queries_per_product'], 2 ) ) : 'N/A'; ?></td>
				</tr>
				<tr>
					<td><strong>File Operations</strong></td>
					<td><?php echo esc_html( number_format( $metrics['file_operations'] ) ); ?></td>
				</tr>
				<tr>
					<td colspan="2"><strong>Environment</strong></td>
				</tr>
				<tr>
					<td><strong>PHP Version</strong></td>
					<td><?php echo esc_html( $metrics['php_version'] ); ?></td>
				</tr>
				<tr>
					<td><strong>WordPress Version</strong></td>
					<td><?php echo esc_html( $metrics['wp_version'] ); ?></td>
				</tr>
				<tr>
					<td><strong>PHP Memory Limit</strong></td>
					<td><?php echo esc_html( $metrics['memory_limit'] ); ?></td>
				</tr>
			</tbody>
		</table>

		<?php if ( ! empty( $metrics['slow_queries'] ) ) : ?>
			<h3>Slow Queries (> 100ms)</h3>
			<table class="widefat" style="max-width: 1200px;">
				<thead>
					<tr>
						<th style="width: 80px;">Time (s)</th>
						<th>Query</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $metrics['slow_queries'], 0, 10 ) as $query ) : ?>
						<tr>
							<td><?php echo esc_html( number_format( $query['time'], 4 ) ); ?></td>
							<td><code style="font-size: 11px;"><?php echo esc_html( substr( $query['query'], 0, 200 ) ); ?>...</code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( ! empty( $metrics['memory_samples'] ) ) : ?>
			<h3>Memory Usage Over Time</h3>
			<table class="widefat" style="max-width: 800px;">
				<thead>
					<tr>
						<th>Products Processed</th>
						<th>Memory Usage</th>
						<th>Event</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $metrics['memory_samples'] as $sample ) : ?>
						<tr>
							<td><?php echo esc_html( $sample['product_count'] ); ?></td>
							<td><?php echo esc_html( $this->format_bytes( $sample['memory'] ) ); ?></td>
							<td><?php echo isset( $sample['event'] ) ? esc_html( $sample['event'] ) : 'sample'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render history table
	 *
	 * @param array $history Metrics history.
	 */
	private function render_history_table( $history ) {
		?>
		<style>
			.wppfm-performance-table th { background-color: #f0f0f1; font-weight: 600; }
			.wppfm-performance-table td { vertical-align: middle; }
			.wppfm-performance-table .number { text-align: right; font-family: monospace; }
			.wppfm-performance-table .date-col { white-space: nowrap; }
		</style>
		<table class="widefat wppfm-performance-table" style="max-width: 100%;">
			<thead>
				<tr>
					<th class="date-col">Date</th>
					<th>Feed ID</th>
					<th class="number">Products</th>
					<th class="number">Time (s)</th>
					<th class="number">Products/sec</th>
					<th class="number">Queries</th>
					<th class="number">Queries/Product</th>
					<th class="number">Peak Memory</th>
					<th class="number">File Ops</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_reverse( $history ) as $run ) : ?>
					<tr>
						<td class="date-col"><?php echo isset( $run['completed_at'] ) ? esc_html( gmdate( 'M j, Y H:i', strtotime( $run['completed_at'] ) ) ) : 'N/A'; ?></td>
						<td><?php echo esc_html( $run['feed_id'] ); ?></td>
						<td class="number"><?php echo esc_html( number_format( $run['products_processed'] ) ); ?></td>
						<td class="number"><?php echo esc_html( number_format( $run['total_time'], 1 ) ); ?></td>
						<td class="number"><?php echo isset( $run['products_per_second'] ) ? esc_html( number_format( $run['products_per_second'], 2 ) ) : 'N/A'; ?></td>
						<td class="number"><?php echo esc_html( number_format( $run['database_queries'] ) ); ?></td>
						<td class="number"><?php echo isset( $run['queries_per_product'] ) ? esc_html( number_format( $run['queries_per_product'], 1 ) ) : 'N/A'; ?></td>
						<td class="number"><?php echo esc_html( $this->format_bytes( $run['peak_memory'] ) ); ?></td>
						<td class="number"><?php echo esc_html( number_format( $run['file_operations'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Format bytes to human-readable format
	 *
	 * @param int $bytes Number of bytes.
	 * @return string
	 */
	private function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );
		$bytes /= ( 1 << ( 10 * $pow ) );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
}

// Initialize the monitor on plugins_loaded to ensure WordPress is ready
add_action( 'plugins_loaded', function() {
	WPPFM_Feed_Performance_Monitor::get_instance();
}, 5 );

