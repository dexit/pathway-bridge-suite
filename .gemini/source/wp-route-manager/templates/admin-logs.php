<?php
/**
 * @var WP_Route_Manager\Admin\LogListTable $table
 * @var WP_Post[]                           $endpoints
 * @var int                                 $endpoint_id
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wprm-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-media-text"></span>
		<?php esc_html_e( 'Request Logs', 'wp-route-manager' ); ?>
	</h1>

	<!-- Filters bar -->
	<form method="get" class="wprm-filter-bar">
		<input type="hidden" name="page" value="wprm-logs">
		<select name="endpoint_id">
			<option value=""><?php esc_html_e( 'All Endpoints', 'wp-route-manager' ); ?></option>
			<?php foreach ( $endpoints as $ep ) : ?>
				<option value="<?php echo (int) $ep->ID; ?>" <?php selected( $endpoint_id, $ep->ID ); ?>>
					<?php echo esc_html( $ep->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<select name="response_code">
			<option value=""><?php esc_html_e( 'All Status Codes', 'wp-route-manager' ); ?></option>
			<option value="200" <?php selected( $_GET['response_code'] ?? '', '200' ); ?>>200</option>
			<option value="400" <?php selected( $_GET['response_code'] ?? '', '400' ); ?>>400</option>
			<option value="401" <?php selected( $_GET['response_code'] ?? '', '401' ); ?>>401</option>
			<option value="403" <?php selected( $_GET['response_code'] ?? '', '403' ); ?>>403</option>
			<option value="500" <?php selected( $_GET['response_code'] ?? '', '500' ); ?>>500</option>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-route-manager' ); ?></button>
		<?php if ( $endpoint_id || ! empty( $_GET['response_code'] ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wprm-logs' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'wp-route-manager' ); ?></a>
		<?php endif; ?>
	</form>

	<form method="post">
		<?php $table->display(); ?>
	</form>
</div>

<!-- Log Detail Modal -->
<div id="wprm-log-modal" class="wprm-modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="wprm-modal-title">
	<div class="wprm-modal-backdrop"></div>
	<div class="wprm-modal-box">
		<div class="wprm-modal-header">
			<h2 id="wprm-modal-title"><?php esc_html_e( 'Log Entry Detail', 'wp-route-manager' ); ?></h2>
			<button class="wprm-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wp-route-manager' ); ?>">✕</button>
		</div>
		<div class="wprm-modal-body">
			<div class="wprm-log-meta">
				<span id="wprm-log-method" class="wprm-badge"></span>
				<span id="wprm-log-code" class="wprm-status"></span>
				<span id="wprm-log-time"></span>
				<span id="wprm-log-ip"></span>
				<span id="wprm-log-duration"></span>
			</div>

			<div class="wprm-log-tabs">
				<button class="wprm-tab-btn active" data-tab="request"><?php esc_html_e( 'Request', 'wp-route-manager' ); ?></button>
				<button class="wprm-tab-btn" data-tab="response"><?php esc_html_e( 'Response', 'wp-route-manager' ); ?></button>
				<button class="wprm-tab-btn" data-tab="snippet"><?php esc_html_e( 'Snippet Output', 'wp-route-manager' ); ?></button>
				<button class="wprm-tab-btn" data-tab="error"><?php esc_html_e( 'Error', 'wp-route-manager' ); ?></button>
			</div>

			<div class="wprm-tab-panel active" id="wprm-tab-request">
				<h4><?php esc_html_e( 'Headers', 'wp-route-manager' ); ?></h4>
				<pre id="wprm-req-headers" class="wprm-pre"></pre>
				<h4><?php esc_html_e( 'Query Params', 'wp-route-manager' ); ?></h4>
				<pre id="wprm-req-params" class="wprm-pre"></pre>
				<h4><?php esc_html_e( 'Body (Raw)', 'wp-route-manager' ); ?></h4>
				<pre id="wprm-req-body-raw" class="wprm-pre"></pre>
				<h4><?php esc_html_e( 'Body (Parsed)', 'wp-route-manager' ); ?></h4>
				<pre id="wprm-req-body-parsed" class="wprm-pre"></pre>
			</div>

			<div class="wprm-tab-panel" id="wprm-tab-response">
				<pre id="wprm-response-body" class="wprm-pre"></pre>
			</div>

			<div class="wprm-tab-panel" id="wprm-tab-snippet">
				<pre id="wprm-snippet-output" class="wprm-pre"></pre>
			</div>

			<div class="wprm-tab-panel" id="wprm-tab-error">
				<pre id="wprm-error-output" class="wprm-pre wprm-pre--error"></pre>
			</div>
		</div>
	</div>
</div>
