<?php
declare( strict_types=1 );

namespace WP_Route_Manager\PostTypes;

defined( 'ABSPATH' ) || exit;

final class EndpointPostType {

	public const POST_TYPE = 'wprm_endpoint';

	public function register(): void {
		register_post_type( self::POST_TYPE, [
			'label'               => __( 'Endpoints', 'wp-route-manager' ),
			'labels'              => [
				'name'               => __( 'Endpoints', 'wp-route-manager' ),
				'singular_name'      => __( 'Endpoint', 'wp-route-manager' ),
				'add_new'            => __( 'Add Endpoint', 'wp-route-manager' ),
				'add_new_item'       => __( 'Add New Endpoint', 'wp-route-manager' ),
				'edit_item'          => __( 'Edit Endpoint', 'wp-route-manager' ),
				'new_item'           => __( 'New Endpoint', 'wp-route-manager' ),
				'view_item'          => __( 'View Endpoint', 'wp-route-manager' ),
				'search_items'       => __( 'Search Endpoints', 'wp-route-manager' ),
				'not_found'          => __( 'No endpoints found.', 'wp-route-manager' ),
				'not_found_in_trash' => __( 'No endpoints found in Trash.', 'wp-route-manager' ),
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => true,
			'rest_base'           => 'wprm-endpoints',
			'capability_type'     => 'post',
			'capabilities'        => [
				'create_posts' => 'manage_options',
				'edit_posts'   => 'manage_options',
				'delete_posts' => 'manage_options',
			],
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => [ 'title', 'revisions', 'custom-fields' ],
			'menu_icon'           => 'dashicons-rest-api',
			'rewrite'             => false,
			'query_var'           => false,
		] );

		// Status column in admin list.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns',       [ $this, 'add_columns' ] );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_filter( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );
	}

	/** @param array<string,string> $cols */
	public function add_columns( array $cols ): array {
		unset( $cols['date'] );
		$cols['wprm_url']      = __( 'Endpoint URL', 'wp-route-manager' );
		$cols['wprm_method']   = __( 'Method', 'wp-route-manager' );
		$cols['wprm_action']   = __( 'Action', 'wp-route-manager' );
		$cols['wprm_hook']     = __( 'Hook / Target', 'wp-route-manager' );
		$cols['wprm_auth']     = __( 'Auth', 'wp-route-manager' );
		$cols['wprm_logs']     = __( 'Requests', 'wp-route-manager' );
		$cols['wprm_status']   = __( 'Status', 'wp-route-manager' );
		return $cols;
	}

	public function render_column( string $column, int $post_id ): void {
		global $wpdb;

		$ns     = get_option( 'wprm_namespace', 'wprm' );
		$ver    = get_option( 'wprm_api_version', 'v1' );
		$slug   = (string) get_post_meta( $post_id, 'wprm_route_slug', true );
		$method = strtoupper( (string) get_post_meta( $post_id, 'wprm_method', true ) );
		$action = (string) get_post_meta( $post_id, 'wprm_action_type', true );
		$auth   = (bool) get_post_meta( $post_id, 'wprm_require_auth', true );
		$status = get_post_status( $post_id );

		switch ( $column ) {
			case 'wprm_url':
				$url = rest_url( $ns . '/' . $ver . '/' . ltrim( $slug, '/' ) );
				printf(
					'<code class="wprm-route-url" style="font-size:11px;word-break:break-all">%s</code>
					<button class="button button-small wprm-copy-btn" style="margin-top:4px;display:block" data-clipboard-text="%s">📋 Copy</button>',
					esc_html( $url ),
					esc_attr( $url )
				);
				break;

			case 'wprm_method':
				$colours = [
					'GET'    => '#0073aa',
					'POST'   => '#46b450',
					'PUT'    => '#f56e28',
					'PATCH'  => '#826eb4',
					'DELETE' => '#dc3232',
					'ANY'    => '#666',
				];
				$colour = $colours[ $method ] ?? '#666';
				printf(
					'<span class="wprm-badge" style="background:%s">%s</span>',
					esc_attr( $colour ),
					esc_html( $method ?: 'ANY' )
				);
				break;

			case 'wprm_action':
				$labels = [
					'wp_action'     => [ '🔔', 'WP Action' ],
					'wp_filter'     => [ '🔄', 'WP Filter' ],
					'http_forward'  => [ '🌐', 'HTTP Forward' ],
					'php_snippet'   => [ '⚡', 'PHP Snippet' ],
					'dispatch'      => [ '📡', 'Dispatch' ],
					'store_payload' => [ '💾', 'Store Payload' ],
				];
				$lbl = $labels[ $action ] ?? [ '?', $action ];
				printf(
					'<span title="%s">%s %s</span>',
					esc_attr( $lbl[1] ),
					$lbl[0],
					esc_html( $lbl[1] )
				);
				break;

			case 'wprm_hook':
				// Show hook name, forward URL, or snippet title depending on action type.
				switch ( $action ) {
					case 'wp_action':
					case 'wp_filter':
						$hook = get_post_meta( $post_id, 'wprm_action_hook', true );
						printf( '<code>%s</code>', esc_html( (string) $hook ) );
						break;
					case 'http_forward':
						$fwd = get_post_meta( $post_id, 'wprm_forward_url', true );
						printf(
							'<span title="%s" style="font-size:11px;word-break:break-all">%s</span>',
							esc_attr( (string) $fwd ),
							esc_html( strlen( (string) $fwd ) > 50 ? substr( (string) $fwd, 0, 50 ) . '…' : (string) $fwd )
						);
						break;
					case 'php_snippet':
						$snip_id = (int) get_post_meta( $post_id, 'wprm_action_snippet_id', true );
						if ( $snip_id ) {
							printf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $snip_id ) ), esc_html( get_the_title( $snip_id ) ) );
						} else {
							echo '<span style="color:#d63638">⚠ No snippet</span>';
						}
						break;
					case 'dispatch':
						$targets = get_post_meta( $post_id, 'wprm_dispatch_targets', true );
						$count   = is_array( $targets ) ? count( $targets ) : 0;
						printf( '<span>%d targets</span>', $count );
						break;
					case 'store_payload':
						$dests = get_post_meta( $post_id, 'wprm_store_destinations', true );
						$dests = is_array( $dests ) ? $dests : [];
						echo esc_html( implode( ', ', $dests ) );
						break;
					default:
						echo '—';
				}
				break;

			case 'wprm_auth':
				if ( $auth ) {
					echo '<span class="dashicons dashicons-lock" style="color:#0073aa" title="' . esc_attr__( 'API key required', 'wp-route-manager' ) . '"></span>';
				} else {
					echo '<span class="dashicons dashicons-unlock" style="color:#666" title="' . esc_attr__( 'Public endpoint', 'wp-route-manager' ) . '"></span>';
				}
				break;

			case 'wprm_logs':
				// Count log entries for this endpoint.
				$count = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}wprm_logs WHERE endpoint_id = %d",
					$post_id
				) );
				$log_url = admin_url( 'admin.php?page=wprm-logs&endpoint_id=' . $post_id );
				$errors  = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}wprm_logs WHERE endpoint_id = %d AND response_code >= 400",
					$post_id
				) );
				printf( '<a href="%s">%d</a>', esc_url( $log_url ), $count );
				if ( $errors > 0 ) {
					printf( '<br><small style="color:#d63638">%d errors</small>', $errors );
				}
				break;

			case 'wprm_status':
				$active = ( $status === 'publish' );
				printf(
					'<span class="wprm-status wprm-status--%s">%s</span>',
					$active ? 'active' : 'inactive',
					$active ? esc_html__( 'Active', 'wp-route-manager' ) : esc_html__( 'Inactive', 'wp-route-manager' )
				);
				break;
		}
	}

	/** @param array<string,string> $actions */
	public function row_actions( array $actions, \WP_Post $post ): array {
		if ( $post->post_type !== self::POST_TYPE ) {
			return $actions;
		}

		$is_active = ( $post->post_status === 'publish' );
		$toggle_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wprm_toggle_endpoint&post_id=' . $post->ID ),
			'wprm_toggle_' . $post->ID
		);

		$actions['wprm_toggle'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $toggle_url ),
			$is_active ? esc_html__( 'Deactivate', 'wp-route-manager' ) : esc_html__( 'Activate', 'wp-route-manager' )
		);

		$log_url = admin_url( 'admin.php?page=wprm-logs&endpoint_id=' . $post->ID );
		$actions['wprm_logs'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $log_url ),
			esc_html__( 'View Logs', 'wp-route-manager' )
		);

		return $actions;
	}
}
