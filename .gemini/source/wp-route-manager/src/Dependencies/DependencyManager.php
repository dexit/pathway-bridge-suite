<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Dependencies;

defined( 'ABSPATH' ) || exit;

/**
 * Handles runtime dependency checking and admin notifications.
 *
 * Covers three dependency scenarios:
 *   1. Missing  — plugin not installed at all → shows Install Now button
 *   2. Inactive — plugin installed but not activated → shows Activate Now button
 *   3. Active   — everything fine, plugin boots normally
 *
 * Also shows a WP Admin Pointer on the Plugins screen on first run.
 */
final class DependencyManager {

	/** User meta key used to track per-user notice dismissal. */
	private const DISMISSED_META = 'wprm_dep_notice_dismissed_v1';

	/** WP Pointer ID — increment suffix if notice content changes. */
	private const POINTER_ID = 'wprm_dep_pointer_v1';

	/**
	 * Dependency definitions.
	 *
	 * 'slugs'   → one or more plugin file paths that satisfy this dependency.
	 *             The FIRST slug in the array is used for Install / Activate links.
	 * 'wporg'   → wordpress.org slug used to build the Install URL.
	 * 'name'    → human-readable name shown in notices.
	 * 'reason'  → one-line explanation of why it is needed.
	 * 'required'→ if false the plugin still loads but shows a warning.
	 *
	 * @var array<int, array{slugs: string[], wporg: string, name: string, reason: string, required: bool}>
	 */
	private const DEPS = [
		[
			'slugs'    => [
				'secure-custom-fields/secure-custom-fields.php',
				'advanced-custom-fields/acf.php',
				'advanced-custom-fields-pro/acf.php',
			],
			'wporg'    => 'secure-custom-fields',
			'name'     => 'Secure Custom Fields (or ACF)',
			'reason'   => 'Powers all admin forms for endpoints, snippets and settings.',
			'required' => true,
		],
	];

	/** @var array<string, 'active'|'inactive'|'missing'> */
	private array $status = [];

	public function __construct() {
		foreach ( self::DEPS as $dep ) {
			$this->status[ $dep['wporg'] ] = $this->detect( $dep['slugs'] );
		}
	}

	// ── Public API ─────────────────────────────────────────────────────────────

	/** Returns true only if every required dependency is active. */
	public function all_met(): bool {
		foreach ( self::DEPS as $dep ) {
			if ( $dep['required'] && $this->status[ $dep['wporg'] ] !== 'active' ) {
				return false;
			}
		}
		return true;
	}

	/** Register all notice + pointer hooks (call only when !all_met()). */
	public function init_notices(): void {
		add_action( 'admin_notices',         [ $this, 'render_admin_notice' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_render_pointer' ] );
		add_action( 'wp_ajax_wprm_dismiss_dep_notice', [ $this, 'ajax_dismiss' ] );
	}

	// ── Status detection ───────────────────────────────────────────────────────

	/**
	 * @param string[] $slugs
	 * @return 'active'|'inactive'|'missing'
	 */
	private function detect( array $slugs ): string {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( $slugs as $slug ) {
			if ( is_plugin_active( $slug ) ) {
				return 'active';
			}
		}

		foreach ( $slugs as $slug ) {
			$folder = dirname( $slug );
			if (
				file_exists( WP_PLUGIN_DIR . '/' . $slug )
				|| ( $folder !== '.' && is_dir( WP_PLUGIN_DIR . '/' . $folder ) )
			) {
				return 'inactive';
			}
		}

		return 'missing';
	}

	// ── Admin notice ───────────────────────────────────────────────────────────

	public function render_admin_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Respect per-user dismissal (stored in user meta).
		if ( get_user_meta( get_current_user_id(), self::DISMISSED_META, true ) ) {
			return;
		}

		$rows = $this->build_notice_rows();
		if ( empty( $rows ) ) {
			return;
		}

		$nonce = wp_create_nonce( 'wprm_dismiss_dep_notice' );
		?>
		<div class="notice notice-error wprm-dep-notice" style="border-left-color:#d63638;padding:0">
			<div style="display:flex;align-items:stretch">

				<!-- Red left stripe with icon -->
				<div style="background:#d63638;display:flex;align-items:center;justify-content:center;padding:0 16px;flex-shrink:0">
					<span class="dashicons dashicons-rest-api" style="color:#fff;font-size:24px;width:24px;height:24px"></span>
				</div>

				<!-- Content -->
				<div style="padding:12px 16px;flex:1">
					<p style="margin:0 0 6px;font-size:14px;font-weight:600">
						<?php esc_html_e( 'WP Route Manager — Action Required', 'wp-route-manager' ); ?>
					</p>
					<p style="margin:0 0 10px;color:#50575e">
						<?php esc_html_e( 'The following plugin(s) must be installed and active before WP Route Manager can run:', 'wp-route-manager' ); ?>
					</p>

					<?php foreach ( $rows as $row ) : ?>
					<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;padding:8px 12px;background:#fff3f3;border:1px solid #f5aaaa;border-radius:4px">
						<?php if ( $row['status'] === 'missing' ) : ?>
							<span class="dashicons dashicons-warning" style="color:#d63638;flex-shrink:0"></span>
						<?php else : ?>
							<span class="dashicons dashicons-info-outline" style="color:#f0b849;flex-shrink:0"></span>
						<?php endif; ?>

						<div style="flex:1">
							<strong><?php echo esc_html( $row['name'] ); ?></strong>
							<span style="color:#50575e;margin-left:6px;font-size:12px">
								— <?php echo esc_html( $row['reason'] ); ?>
							</span>
						</div>

						<?php if ( $row['status'] === 'missing' && $row['install_url'] ) : ?>
							<a href="<?php echo esc_url( $row['install_url'] ); ?>"
							   class="button button-primary button-small"
							   style="flex-shrink:0;white-space:nowrap">
								<span class="dashicons dashicons-download" style="font-size:14px;line-height:1.6;margin-right:4px"></span>
								<?php esc_html_e( 'Install Now', 'wp-route-manager' ); ?>
							</a>
						<?php elseif ( $row['status'] === 'inactive' && $row['activate_url'] ) : ?>
							<a href="<?php echo esc_url( $row['activate_url'] ); ?>"
							   class="button button-primary button-small"
							   style="flex-shrink:0;white-space:nowrap;background:#f0b849;border-color:#f0b849;color:#000">
								<span class="dashicons dashicons-controls-play" style="font-size:14px;line-height:1.6;margin-right:4px"></span>
								<?php esc_html_e( 'Activate Now', 'wp-route-manager' ); ?>
							</a>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>

					<p style="margin:8px 0 0">
						<a href="#" class="wprm-dep-dismiss" style="font-size:12px;color:#787c82;text-decoration:underline"
						   data-nonce="<?php echo esc_attr( $nonce ); ?>">
							<?php esc_html_e( 'Dismiss this notice', 'wp-route-manager' ); ?>
						</a>
					</p>
				</div>

			</div><!-- /flex -->
		</div>

		<script>
		( function() {
			var btn = document.querySelector( '.wprm-dep-dismiss' );
			if ( ! btn ) return;
			btn.addEventListener( 'click', function( e ) {
				e.preventDefault();
				var nonce = this.dataset.nonce;
				var notice = this.closest( '.wprm-dep-notice' );
				fetch( '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=wprm_dismiss_dep_notice&nonce=' + encodeURIComponent( nonce )
				} ).then( function() {
					if ( notice ) notice.style.display = 'none';
				} );
			} );
		} )();
		</script>
		<?php
	}

	/** @return array<int, array{name:string, reason:string, status:string, install_url:string, activate_url:string}> */
	private function build_notice_rows(): array {
		$rows = [];

		foreach ( self::DEPS as $dep ) {
			$s = $this->status[ $dep['wporg'] ];

			if ( $s === 'active' ) {
				continue; // Nothing to show.
			}

			$install_url  = '';
			$activate_url = '';

			if ( $s === 'missing' ) {
				$install_url = wp_nonce_url(
					admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $dep['wporg'] ) ),
					'install-plugin_' . $dep['wporg']
				);
			} else {
				// Find the actual installed slug for the activate link.
				foreach ( $dep['slugs'] as $slug ) {
					if ( file_exists( WP_PLUGIN_DIR . '/' . $slug ) ) {
						$activate_url = wp_nonce_url(
							admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $slug ) ),
							'activate-plugin_' . $slug
						);
						break;
					}
				}
			}

			$rows[] = [
				'name'         => $dep['name'],
				'reason'       => $dep['reason'],
				'status'       => $s,
				'install_url'  => $install_url,
				'activate_url' => $activate_url,
			];
		}

		return $rows;
	}

	public function ajax_dismiss(): void {
		if ( ! check_ajax_referer( 'wprm_dismiss_dep_notice', 'nonce', false ) ) {
			wp_send_json_error( 'bad_nonce', 403 );
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}
		update_user_meta( get_current_user_id(), self::DISMISSED_META, 1 );
		wp_send_json_success();
	}

	// ── WP Admin Pointer ───────────────────────────────────────────────────────

	/**
	 * Shows a WP Admin Pointer anchored to the first plugin row on the
	 * Plugins screen — guides the user to install SCF on first visit.
	 */
	public function maybe_render_pointer( string $hook ): void {
		if ( $hook !== 'plugins.php' ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Check WP's own dismissed pointer list.
		$dismissed = array_filter( explode(
			',',
			(string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true )
		) );
		if ( in_array( self::POINTER_ID, $dismissed, true ) ) {
			return;
		}

		// Build a short dep list for the pointer body.
		$items = '';
		foreach ( self::DEPS as $dep ) {
			if ( $this->status[ $dep['wporg'] ] !== 'active' ) {
				$items .= '<li><strong>' . esc_html( $dep['name'] ) . '</strong></li>';
			}
		}
		if ( ! $items ) {
			return;
		}

		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		$pointer_id = esc_js( self::POINTER_ID );
		$ajax_url   = esc_js( admin_url( 'admin-ajax.php' ) );
		$title      = esc_js( __( 'WP Route Manager needs a dependency', 'wp-route-manager' ) );
		$body       = esc_js(
			'<p>' . esc_html__( 'Please install and activate the following plugin(s) to use WP Route Manager:', 'wp-route-manager' ) . '</p>'
			. '<ul style="margin:.5em 0 0 1.5em;list-style:disc">' . $items . '</ul>'
		);

		wp_add_inline_script( 'wp-pointer', <<<JS
( function( \$ ) {
	\$( function() {
		var \$target = \$( '#the-list tr:first-child' );
		if ( ! \$target.length ) { \$target = \$( '#wpbody-content' ); }
		\$target.pointer( {
			content:  '<h3>{$title}</h3>{$body}',
			position: { edge: 'top', align: 'left' },
			close: function() {
				\$.post( '{$ajax_url}', {
					action:  'dismiss-wp-pointer',
					pointer: '{$pointer_id}'
				} );
			}
		} ).pointer( 'open' );
	} );
} )( jQuery );
JS );
	}
}
