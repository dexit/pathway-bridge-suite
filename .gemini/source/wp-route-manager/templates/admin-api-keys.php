<?php
/**
 * @var WP_Post[] $endpoints
 * @var object[]  $keys
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wprm-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-key"></span>
		<?php esc_html_e( 'API Keys', 'wp-route-manager' ); ?>
	</h1>

	<!-- Create new key form -->
	<div class="wprm-card" id="wprm-create-key-form">
		<h2><?php esc_html_e( 'Create New API Key', 'wp-route-manager' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="wprm-key-label"><?php esc_html_e( 'Label', 'wp-route-manager' ); ?></label></th>
				<td><input type="text" id="wprm-key-label" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Zapier Integration', 'wp-route-manager' ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Allowed Endpoints', 'wp-route-manager' ); ?></th>
				<td>
					<p class="description"><?php esc_html_e( 'Leave all unchecked to allow access to every endpoint.', 'wp-route-manager' ); ?></p>
					<?php foreach ( $endpoints as $ep ) : ?>
					<label style="display:block;margin-top:4px">
						<input type="checkbox" name="wprm_endpoint_ids[]" value="<?php echo (int) $ep->ID; ?>">
						<?php echo esc_html( $ep->post_title ); ?>
						<code style="font-size:11px"><?php echo esc_html( get_post_meta( $ep->ID, 'wprm_route_slug', true ) ); ?></code>
					</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>
		<p>
			<button id="wprm-create-key-btn" class="button button-primary">
				<?php esc_html_e( 'Generate Key', 'wp-route-manager' ); ?>
			</button>
		</p>

		<!-- Key reveal area — shown once after creation -->
		<div id="wprm-key-reveal-area" style="display:none" class="wprm-notice-key notice notice-warning">
			<h3>⚠️ <?php esc_html_e( 'Copy Your Key — It Will Not Be Shown Again', 'wp-route-manager' ); ?></h3>
			<div class="wprm-key-reveal">
				<code id="wprm-new-key-value"></code>
				<button class="button wprm-copy-btn" data-clipboard-target="#wprm-new-key-value">
					<?php esc_html_e( 'Copy', 'wp-route-manager' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Keys table -->
	<table class="wp-list-table widefat fixed striped wprm-keys-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'wp-route-manager' ); ?></th>
				<th><?php esc_html_e( 'Label', 'wp-route-manager' ); ?></th>
				<th><?php esc_html_e( 'Key Prefix', 'wp-route-manager' ); ?></th>
				<th><?php esc_html_e( 'Endpoints', 'wp-route-manager' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-route-manager' ); ?></th>
				<th><?php esc_html_e( 'Last Used', 'wp-route-manager' ); ?></th>
				<th><?php esc_html_e( 'Created', 'wp-route-manager' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wp-route-manager' ); ?></th>
			</tr>
		</thead>
		<tbody id="wprm-keys-tbody">
		<?php if ( $keys ) : foreach ( $keys as $key ) :
			$allowed = $key->allowed_endpoints ? json_decode( $key->allowed_endpoints ) : [];
			$is_active = $key->status === '1' || $key->status === 1;
		?>
			<tr id="wprm-key-row-<?php echo (int) $key->id; ?>">
				<td>#<?php echo (int) $key->id; ?></td>
				<td><?php echo esc_html( $key->label ); ?></td>
				<td><code><?php echo esc_html( $key->key_prefix ); ?>…</code></td>
				<td>
					<?php if ( $allowed ) :
						foreach ( $allowed as $ep_id ) {
							echo '<span class="wprm-badge-small">' . esc_html( get_the_title( (int) $ep_id ) ?: '#' . (int) $ep_id ) . '</span> ';
						}
					else : ?>
						<em><?php esc_html_e( 'All endpoints', 'wp-route-manager' ); ?></em>
					<?php endif; ?>
				</td>
				<td>
					<span class="wprm-status wprm-status--<?php echo $is_active ? 'active' : 'inactive'; ?>">
						<?php echo $is_active ? esc_html__( 'Active', 'wp-route-manager' ) : esc_html__( 'Inactive', 'wp-route-manager' ); ?>
					</span>
				</td>
				<td><?php echo $key->last_used_at ? esc_html( human_time_diff( strtotime( $key->last_used_at ) ) . ' ago' ) : esc_html__( 'Never', 'wp-route-manager' ); ?></td>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $key->created_at ) ) ); ?></td>
				<td>
					<button class="button button-small wprm-toggle-key" data-id="<?php echo (int) $key->id; ?>">
						<?php echo $is_active ? esc_html__( 'Deactivate', 'wp-route-manager' ) : esc_html__( 'Activate', 'wp-route-manager' ); ?>
					</button>
					<button class="button button-small wprm-delete-key" data-id="<?php echo (int) $key->id; ?>">
						<?php esc_html_e( 'Delete', 'wp-route-manager' ); ?>
					</button>
				</td>
			</tr>
		<?php endforeach; else : ?>
			<tr><td colspan="8"><?php esc_html_e( 'No API keys yet. Create one above.', 'wp-route-manager' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>
</div>
