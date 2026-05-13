<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wprm-wrap">
	<h1><?php esc_html_e( 'Route Manager Settings', 'wp-route-manager' ); ?></h1>

	<?php settings_errors( 'wprm_settings' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'wprm_settings' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable API', 'wp-route-manager' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wprm_global_enabled" value="1" <?php checked( get_option( 'wprm_global_enabled', 1 ) ); ?>>
						<?php esc_html_e( 'Globally enable the REST API endpoints', 'wp-route-manager' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wprm_namespace"><?php esc_html_e( 'API Namespace', 'wp-route-manager' ); ?></label></th>
				<td>
					<input type="text" id="wprm_namespace" name="wprm_namespace" value="<?php echo esc_attr( get_option( 'wprm_namespace', 'wprm' ) ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Routes will register under /wp-json/{namespace}/{version}/{slug}', 'wp-route-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wprm_api_version"><?php esc_html_e( 'API Version', 'wp-route-manager' ); ?></label></th>
				<td><input type="text" id="wprm_api_version" name="wprm_api_version" value="<?php echo esc_attr( get_option( 'wprm_api_version', 'v1' ) ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Request Logging', 'wp-route-manager' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wprm_global_logging" value="1" <?php checked( get_option( 'wprm_global_logging', 1 ) ); ?>>
						<?php esc_html_e( 'Log all request/response data', 'wp-route-manager' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wprm_log_retention_days"><?php esc_html_e( 'Log Retention (days)', 'wp-route-manager' ); ?></label></th>
				<td>
					<select name="wprm_log_retention_days" id="wprm_log_retention_days">
						<?php
						$retention = (string) get_option( 'wprm_log_retention_days', '7' );
						foreach ( [ '3' => '3 days', '7' => '7 days', '14' => '14 days', '30' => '30 days', '0' => 'Forever' ] as $val => $label ) {
							printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $retention, $val, false ), esc_html( $label ) );
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'CRON Log Purge', 'wp-route-manager' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wprm_enable_cron_purge" value="1" <?php checked( get_option( 'wprm_enable_cron_purge', 1 ) ); ?>>
						<?php esc_html_e( 'Automatically delete old logs via WP-CRON (runs daily)', 'wp-route-manager' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'PHP Snippets', 'wp-route-manager' ); ?></h2>
		<p>
			<?php
			$enabled = WPRM_ALLOW_PHP_SNIPPETS;
			if ( $enabled ) {
				echo '<span class="wprm-status wprm-status--active">' . esc_html__( 'Enabled', 'wp-route-manager' ) . '</span> ';
				esc_html_e( 'PHP snippet execution is enabled.', 'wp-route-manager' );
			} else {
				echo '<span class="wprm-status wprm-status--inactive">' . esc_html__( 'Disabled', 'wp-route-manager' ) . '</span> ';
				esc_html_e( 'PHP snippet execution is disabled. To enable, add the following to wp-config.php:', 'wp-route-manager' );
				echo '<br><code>define( \'WPRM_ALLOW_PHP_SNIPPETS\', true );</code>';
			}
			?>
		</p>

		<?php submit_button(); ?>
	</form>

	<hr>
	<h2><?php esc_html_e( 'Danger Zone', 'wp-route-manager' ); ?></h2>
	<p>
		<button id="wprm-clear-all-logs" class="button button-secondary"
		        data-confirm="<?php esc_attr_e( 'Delete ALL log entries? This cannot be undone.', 'wp-route-manager' ); ?>">
			<?php esc_html_e( 'Clear All Logs', 'wp-route-manager' ); ?>
		</button>
	</p>
</div>
