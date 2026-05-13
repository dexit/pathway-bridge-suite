<?php
/**
 * @var WP_Route_Manager\DB\LogRepository (via use) - dashboard template.
 */
use WP_Route_Manager\DB\LogRepository;
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wprm-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-rest-api"></span>
		<?php esc_html_e( 'WP Route Manager', 'wp-route-manager' ); ?>
	</h1>

	<?php
	$demo_key = get_transient( 'wprm_demo_api_key' );
	if ( $demo_key ) :
		delete_transient( 'wprm_demo_api_key' );
	?>
	<div class="notice notice-warning wprm-notice-key">
		<h3>⚠️ <?php esc_html_e( 'Your Demo API Key — Copy it now', 'wp-route-manager' ); ?></h3>
		<p><?php esc_html_e( 'This key will not be shown again. It has access to all demo endpoints.', 'wp-route-manager' ); ?></p>
		<div class="wprm-key-reveal">
			<code id="wprm-demo-key"><?php echo esc_html( $demo_key ); ?></code>
			<button class="button wprm-copy-btn" data-clipboard-target="#wprm-demo-key">
				<?php esc_html_e( 'Copy', 'wp-route-manager' ); ?>
			</button>
		</div>
	</div>
	<?php endif; ?>

	<?php
	// Stats.
	global $wpdb;
	$ns            = get_option( 'wprm_namespace', 'wprm' );
	$ver           = get_option( 'wprm_api_version', 'v1' );
	$endpoint_counts = wp_count_posts( 'wprm_endpoint' );
	$snippet_counts  = wp_count_posts( 'wprm_snippet' );
	$key_count     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wprm_api_keys WHERE status=1" ); // phpcs:ignore
	$log_count     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wprm_logs" ); // phpcs:ignore
	$error_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wprm_logs WHERE response_code >= 400" ); // phpcs:ignore
	$last_logs     = ( new LogRepository() )->tail( 5 );
	?>

	<div class="wprm-stat-grid">
		<div class="wprm-stat-card">
			<span class="wprm-stat-icon dashicons dashicons-rest-api"></span>
			<div class="wprm-stat-number"><?php echo (int) $endpoint_counts->publish; ?></div>
			<div class="wprm-stat-label"><?php esc_html_e( 'Active Endpoints', 'wp-route-manager' ); ?></div>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wprm_endpoint' ) ); ?>"><?php esc_html_e( 'Manage →', 'wp-route-manager' ); ?></a>
		</div>
		<div class="wprm-stat-card">
			<span class="wprm-stat-icon dashicons dashicons-editor-code"></span>
			<div class="wprm-stat-number"><?php echo (int) $snippet_counts->publish; ?></div>
			<div class="wprm-stat-label"><?php esc_html_e( 'PHP Snippets', 'wp-route-manager' ); ?></div>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wprm_snippet' ) ); ?>"><?php esc_html_e( 'Manage →', 'wp-route-manager' ); ?></a>
		</div>
		<div class="wprm-stat-card">
			<span class="wprm-stat-icon dashicons dashicons-key"></span>
			<div class="wprm-stat-number"><?php echo (int) $key_count; ?></div>
			<div class="wprm-stat-label"><?php esc_html_e( 'Active API Keys', 'wp-route-manager' ); ?></div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wprm-api-keys' ) ); ?>"><?php esc_html_e( 'Manage →', 'wp-route-manager' ); ?></a>
		</div>
		<div class="wprm-stat-card <?php echo $error_count > 0 ? 'wprm-stat-card--warn' : ''; ?>">
			<span class="wprm-stat-icon dashicons dashicons-media-text"></span>
			<div class="wprm-stat-number"><?php echo (int) $log_count; ?></div>
			<div class="wprm-stat-label"><?php esc_html_e( 'Log Entries', 'wp-route-manager' ); ?></div>
			<?php if ( $error_count > 0 ) : ?>
				<span class="wprm-error-badge"><?php echo (int) $error_count; ?> <?php esc_html_e( 'errors', 'wp-route-manager' ); ?></span>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wprm-logs' ) ); ?>"><?php esc_html_e( 'View Logs →', 'wp-route-manager' ); ?></a>
		</div>
	</div>

	<div class="wprm-dashboard-cols">

		<div class="wprm-dashboard-main">
			<h2><?php esc_html_e( 'Recent Activity', 'wp-route-manager' ); ?></h2>
			<?php if ( $last_logs ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( '#', 'wp-route-manager' ); ?></th>
						<th><?php esc_html_e( 'Endpoint', 'wp-route-manager' ); ?></th>
						<th><?php esc_html_e( 'Method', 'wp-route-manager' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-route-manager' ); ?></th>
						<th><?php esc_html_e( 'Time', 'wp-route-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $last_logs as $log ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=wprm-logs' ) ); ?>">#<?php echo (int) $log->id; ?></a></td>
						<td><?php echo esc_html( $log->endpoint_slug ); ?></td>
						<td><?php echo esc_html( $log->method ); ?></td>
						<td>
							<span class="wprm-status wprm-status--<?php echo (int) $log->response_code >= 400 ? 'error' : 'active'; ?>">
								<?php echo (int) $log->response_code; ?>
							</span>
						</td>
						<td><?php echo esc_html( human_time_diff( strtotime( $log->created_at ) ) . ' ago' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No requests logged yet. Try one of the demo endpoints below.', 'wp-route-manager' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="wprm-dashboard-side">
			<h2><?php esc_html_e( 'Quick Reference', 'wp-route-manager' ); ?></h2>
			<p><?php esc_html_e( 'API base URL:', 'wp-route-manager' ); ?></p>
			<code class="wprm-url-block"><?php echo esc_html( rest_url( $ns . '/' . $ver . '/' ) ); ?></code>

			<h3><?php esc_html_e( 'Authentication', 'wp-route-manager' ); ?></h3>
			<code class="wprm-url-block">X-WRM-Key: your_api_key</code>
			<p><?php esc_html_e( 'or', 'wp-route-manager' ); ?></p>
			<code class="wprm-url-block">?api_key=your_api_key</code>

			<h3><?php esc_html_e( 'Demo Endpoints', 'wp-route-manager' ); ?></h3>
			<ul class="wprm-demo-list">
				<li><code>GET <?php echo esc_html( rest_url( $ns . '/' . $ver . '/demo/ping' ) ); ?></code><br><small><?php esc_html_e( 'Public — no key needed', 'wp-route-manager' ); ?></small></li>
				<li><code>POST …/demo/action</code><br><small><?php esc_html_e( 'Fires do_action("wprm_demo_ingest")', 'wp-route-manager' ); ?></small></li>
				<li><code>POST …/demo/filter</code><br><small><?php esc_html_e( 'apply_filters → returns enriched JSON', 'wp-route-manager' ); ?></small></li>
				<li><code>POST …/demo/snippet</code><br><small><?php esc_html_e( 'PHP snippet (requires WPRM_ALLOW_PHP_SNIPPETS)', 'wp-route-manager' ); ?></small></li>
				<li><code>POST …/demo/forward</code><br><small><?php esc_html_e( 'Forwards to httpbin.org/post', 'wp-route-manager' ); ?></small></li>
			</ul>

			<h3><?php esc_html_e( 'WP-CLI', 'wp-route-manager' ); ?></h3>
			<code class="wprm-url-block">wp wprm endpoint list
wp wprm key create --label="My Key"
wp wprm log tail --watch
wp wprm test demo/ping --method=GET</code>

			<h3><?php esc_html_e( 'Extension Hooks', 'wp-route-manager' ); ?></h3>
			<code class="wprm-url-block">// After plugin loaded
do_action( 'wprm_loaded', $plugin );

// Override handler per action_type
add_filter( 'wprm_action_handler', $handler, $type );

// Modify parsed body
add_filter( 'wprm_parsed_data', $data, $request, $id );

// Pre/post request
add_action( 'wprm_before_request', $request, $id );
add_action( 'wprm_after_request',  $response, $request, $id );

// Snippet executed
add_action( 'wprm_snippet_executed', $id, $ep_id, $result, $output );
</code>
		</div>
	</div>
</div>
