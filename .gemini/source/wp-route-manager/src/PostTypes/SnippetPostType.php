<?php
declare( strict_types=1 );

namespace WP_Route_Manager\PostTypes;

defined( 'ABSPATH' ) || exit;

final class SnippetPostType {

	public const POST_TYPE = 'wprm_snippet';

	public function register(): void {
		register_post_type( self::POST_TYPE, [
			'label'           => __( 'PHP Snippets', 'wp-route-manager' ),
			'labels'          => [
				'name'               => __( 'PHP Snippets', 'wp-route-manager' ),
				'singular_name'      => __( 'PHP Snippet', 'wp-route-manager' ),
				'add_new'            => __( 'Add Snippet', 'wp-route-manager' ),
				'add_new_item'       => __( 'Add New Snippet', 'wp-route-manager' ),
				'edit_item'          => __( 'Edit Snippet', 'wp-route-manager' ),
				'new_item'           => __( 'New Snippet', 'wp-route-manager' ),
				'not_found'          => __( 'No snippets found.', 'wp-route-manager' ),
				'not_found_in_trash' => __( 'No snippets found in Trash.', 'wp-route-manager' ),
			],
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => false,
			'show_in_rest'    => true,
			'rest_base'       => 'wprm-snippets',
			'capability_type' => 'post',
			'capabilities'    => [
				'create_posts' => 'manage_options',
				'edit_posts'   => 'manage_options',
				'delete_posts' => 'manage_options',
			],
			'map_meta_cap'    => true,
			'supports'        => [ 'title', 'revisions', 'custom-fields' ],
			'menu_icon'       => 'dashicons-editor-code',
			'rewrite'         => false,
			'query_var'       => false,
		] );

		// Columns.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns',       [ $this, 'add_columns' ] );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_filter( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );

		// Enqueue CodeMirror on snippet edit screen.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_codemirror' ] );

		// Toggle endpoint via admin-post.
		add_action( 'admin_post_wprm_toggle_snippet',   [ $this, 'handle_toggle' ] );
		add_action( 'admin_post_wprm_toggle_endpoint',  [ $this, 'handle_toggle_endpoint' ] );
	}

	public function enqueue_codemirror( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== self::POST_TYPE ) {
			return;
		}

		// Enqueue Monaco editor from CDN via our loader script.
		wp_enqueue_script(
			'wprm-monaco',
			WPRM_URL . 'assets/js/monaco-editor.js',
			[],
			WPRM_VERSION,
			true
		);

		// Pass snippet type to JS so the correct starter template / context banner is shown.
		$post_id      = absint( get_the_ID() );
		$snippet_type = $post_id ? (string) get_post_meta( $post_id, 'wprm_snippet_type', true ) : 'standalone';

		wp_localize_script( 'wprm-monaco', 'wprmMonaco', [
			'snippetType' => $snippet_type ?: 'standalone',
			'postId'      => $post_id,
		] );

		// Enqueue the admin CSS so the mount container is styled correctly.
		wp_enqueue_style( 'wprm-admin', WPRM_URL . 'assets/css/admin.css', [], WPRM_VERSION );
	}

	/** @param array<string,string> $cols */
	public function add_columns( array $cols ): array {
		unset( $cols['date'] );
		$cols['wprm_snip_type']        = __( 'Type', 'wp-route-manager' );
		$cols['wprm_snip_description'] = __( 'Description', 'wp-route-manager' );
		$cols['wprm_snip_used_by']     = __( 'Used by Endpoints', 'wp-route-manager' );
		$cols['wprm_snip_status']      = __( 'Status', 'wp-route-manager' );
		$cols['wprm_snip_revisions']   = __( 'Revisions', 'wp-route-manager' );
		$cols['wprm_snip_modified']    = __( 'Last Modified', 'wp-route-manager' );
		return $cols;
	}

	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'wprm_snip_type':
				$type   = (string) get_post_meta( $post_id, 'wprm_snippet_type', true );
				$labels = [
					'action_handler' => [ '🔔', 'Action Handler' ],
					'filter_handler' => [ '🔄', 'Filter Handler' ],
					'body_parser'    => [ '📦', 'Body Parser' ],
					'http_transform' => [ '🔁', 'HTTP Transform' ],
					'standalone'     => [ '⚡', 'Standalone' ],
				];
				$lbl = $labels[ $type ] ?? [ '?', $type ];
				printf( '<span title="%s">%s %s</span>', esc_attr( $lbl[1] ), $lbl[0], esc_html( $lbl[1] ) );
				break;

			case 'wprm_snip_description':
				$desc = (string) get_post_meta( $post_id, 'wprm_snippet_description', true );
				if ( $desc ) {
					echo '<span style="font-size:12px;color:#50575e">' . esc_html( wp_trim_words( $desc, 12 ) ) . '</span>';
				} else {
					// Show first line of code as preview.
					$code    = (string) get_post_meta( $post_id, 'wprm_snippet_code', true );
					$preview = trim( strtok( ltrim( $code, "<?php \n\r" ), "\n" ) );
					if ( $preview ) {
						echo '<code style="font-size:10px;color:#826eb4">' . esc_html( substr( $preview, 0, 60 ) ) . '</code>';
					}
				}
				break;

			case 'wprm_snip_used_by':
				global $wpdb;
				$endpoint_ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
					 WHERE meta_value = %d
					   AND meta_key IN ('wprm_action_snippet_id', 'wprm_body_parse_snippet_id', 'wprm_transform_snippet_id')",
					$post_id
				) );
				if ( $endpoint_ids ) {
					foreach ( $endpoint_ids as $ep_id ) {
						$status = get_post_status( (int) $ep_id );
						$dot    = $status === 'publish' ? '🟢' : '⚪';
						printf(
							'<div>%s <a href="%s" style="font-size:12px">%s</a></div>',
							$dot,
							esc_url( get_edit_post_link( (int) $ep_id ) ),
							esc_html( get_the_title( (int) $ep_id ) )
						);
					}
				} else {
					echo '<span style="color:#999;font-size:12px">— not used</span>';
				}
				break;

			case 'wprm_snip_status':
				$active  = ( get_post_status( $post_id ) === 'publish' );
				$toggle  = wp_nonce_url(
					admin_url( 'admin-post.php?action=wprm_toggle_snippet&post_id=' . $post_id ),
					'wprm_toggle_' . $post_id
				);
				printf(
					'<a href="%s" class="wprm-status wprm-status--%s" style="text-decoration:none;display:inline-block">%s</a>',
					esc_url( $toggle ),
					$active ? 'active' : 'inactive',
					$active ? esc_html__( '● Active', 'wp-route-manager' ) : esc_html__( '○ Inactive', 'wp-route-manager' )
				);
				break;

			case 'wprm_snip_revisions':
				$count   = (int) wp_get_post_revisions( $post_id, [ 'count' => true ] );
				$rev_url = admin_url( 'revision.php?post=' . $post_id );
				if ( $count > 0 ) {
					printf( '<a href="%s">%d revision%s</a>', esc_url( $rev_url ), $count, $count !== 1 ? 's' : '' );
				} else {
					echo '<span style="color:#999">—</span>';
				}
				break;

			case 'wprm_snip_modified':
				$ts = strtotime( (string) get_post_modified_time( 'Y-m-d H:i:s', false, $post_id ) );
				printf(
					'<abbr title="%s" style="font-size:12px">%s</abbr>',
					esc_attr( date_i18n( 'Y-m-d H:i', $ts ) ),
					esc_html( human_time_diff( $ts ) . ' ago' )
				);
				break;
		}
	}

	/** @param array<string,string> $actions */
	public function row_actions( array $actions, \WP_Post $post ): array {
		if ( $post->post_type !== self::POST_TYPE ) {
			return $actions;
		}

		$is_active  = ( $post->post_status === 'publish' );
		$toggle_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wprm_toggle_snippet&post_id=' . $post->ID ),
			'wprm_toggle_' . $post->ID
		);
		$log_url = admin_url( 'admin.php?page=wprm-logs&snippet_id=' . $post->ID );

		$actions['wprm_toggle'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $toggle_url ),
			$is_active ? esc_html__( 'Deactivate', 'wp-route-manager' ) : esc_html__( 'Activate', 'wp-route-manager' )
		);
		$actions['wprm_logs'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $log_url ),
			esc_html__( 'View Logs', 'wp-route-manager' )
		);

		return $actions;
	}

	public function handle_toggle(): void {
		$post_id = absint( $_GET['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-route-manager' ) );
		}
		check_admin_referer( 'wprm_toggle_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			wp_die( esc_html__( 'Invalid post.', 'wp-route-manager' ) );
		}

		$new_status = ( $post->post_status === 'publish' ) ? 'draft' : 'publish';
		wp_update_post( [ 'ID' => $post_id, 'post_status' => $new_status ] );
		wp_redirect( admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&wprm_toggled=1' ) );
		exit;
	}

	public function handle_toggle_endpoint(): void {
		$post_id = absint( $_GET['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-route-manager' ) );
		}
		check_admin_referer( 'wprm_toggle_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== EndpointPostType::POST_TYPE ) {
			wp_die( esc_html__( 'Invalid post.', 'wp-route-manager' ) );
		}

		$new_status = ( $post->post_status === 'publish' ) ? 'draft' : 'publish';
		wp_update_post( [ 'ID' => $post_id, 'post_status' => $new_status ] );
		wp_redirect( admin_url( 'edit.php?post_type=' . EndpointPostType::POST_TYPE . '&wprm_toggled=1' ) );
		exit;
	}
}
