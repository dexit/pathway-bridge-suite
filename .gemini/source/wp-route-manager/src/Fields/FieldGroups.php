<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Fields;

use WP_Route_Manager\PostTypes\EndpointPostType;
use WP_Route_Manager\PostTypes\SnippetPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Registers all admin fields using Secure Custom Fields (SCF) / ACF.
 * Fields are registered via PHP using acf_add_local_field_group().
 *
 * Degrades gracefully with a notice if SCF/ACF is not active.
 */
final class FieldGroups {

	public function register(): void {
		// DependencyManager has already confirmed SCF/ACF is active before
		// Plugin::init() is called, so we can safely add field groups here.
		add_action( 'acf/init', [ $this, 'register_endpoint_fields' ] );
		add_action( 'acf/init', [ $this, 'register_snippet_fields' ] );
		add_action( 'acf/init', [ $this, 'register_settings_page_fields' ] );

		// Inject Monaco mount container after the (hidden) textarea field.
		add_action( 'acf/render_field/key=field_wprm_snippet_code', [ $this, 'render_monaco_mount' ] );
	}

	/**
	 * Renders the Monaco editor mount div immediately after the SCF textarea.
	 * The textarea is hidden (acf-hidden class). Monaco syncs changes back to it.
	 *
	 * @param array<string,mixed> $field
	 */
	public function render_monaco_mount( array $field ): void {
		global $post;
		$snippet_type = $post ? (string) get_post_meta( $post->ID, 'wprm_snippet_type', true ) : 'standalone';
		?>
		<div
			class="wprm-snippet-editor-mount"
			data-snippet-type="<?php echo esc_attr( $snippet_type ?: 'standalone' ); ?>"
			style="position:relative;border:1px solid #1e1e1e;border-radius:4px;overflow:hidden;margin-top:8px"
		>
			<textarea
				name="<?php echo esc_attr( $field['name'] ); ?>"
				class="wprm-snippet-textarea"
				style="display:none"
				id="acf-<?php echo esc_attr( $field['key'] ); ?>-monaco-sync"
			><?php echo esc_textarea( (string) ( $field['value'] ?? '' ) ); ?></textarea>
			<div class="wprm-monaco-container" style="height:600px;width:100%"></div>
		</div>
		<p class="description" style="margin-top:6px">
			<kbd>Ctrl+Space</kbd> autocomplete &nbsp;|&nbsp;
			<kbd>Ctrl+Shift+F</kbd> format &nbsp;|&nbsp;
			<kbd>Ctrl+Z</kbd> undo &nbsp;|&nbsp;
			<strong>Dark theme, PHP-aware, WPRM context-aware suggestions built in.</strong>
		</p>
		<?php
	}

	// ── Endpoint Fields ───────────────────────────────────────────────────────

	public function register_endpoint_fields(): void {
		acf_add_local_field_group( [
			'key'      => 'group_wprm_endpoint',
			'title'    => __( 'Endpoint Configuration', 'wp-route-manager' ),
			'location' => [
				[ [ 'param' => 'post_type', 'operator' => '==', 'value' => EndpointPostType::POST_TYPE ] ],
			],
			'position'      => 'normal',
			'style'         => 'seamless',
			'label_placement' => 'top',
			'instruction_placement' => 'label',
			'fields' => [

				// ── Route ──────────────────────────────────────────────────
				[
					'key'               => 'field_wprm_section_route',
					'label'             => __( 'Route', 'wp-route-manager' ),
					'name'              => '',
					'type'              => 'tab',
					'placement'         => 'top',
					'endpoint'          => 0,
				],
				[
					'key'          => 'field_wprm_route_slug',
					'label'        => __( 'Route Slug', 'wp-route-manager' ),
					'name'         => 'wprm_route_slug',
					'type'         => 'text',
					'required'     => 1,
					'instructions' => __( 'E.g. <code>my-hook</code> or <code>product/(?P&lt;id&gt;\d+)</code>. Appended to <code>/wp-json/{namespace}/{version}/</code>', 'wp-route-manager' ),
					'placeholder'  => 'my-endpoint',
				],
				[
					'key'          => 'field_wprm_method',
					'label'        => __( 'HTTP Method', 'wp-route-manager' ),
					'name'         => 'wprm_method',
					'type'         => 'select',
					'required'     => 1,
					'choices'      => [
						'GET'    => 'GET',
						'POST'   => 'POST',
						'PUT'    => 'PUT',
						'PATCH'  => 'PATCH',
						'DELETE' => 'DELETE',
						'ANY'    => 'ANY (all methods)',
					],
					'default_value' => 'POST',
					'allow_null'    => 0,
					'ui'            => 1,
				],
				[
					'key'          => 'field_wprm_require_auth',
					'label'        => __( 'Require API Key', 'wp-route-manager' ),
					'name'         => 'wprm_require_auth',
					'type'         => 'true_false',
					'default_value' => 1,
					'ui'           => 1,
					'ui_on_text'   => __( 'Required', 'wp-route-manager' ),
					'ui_off_text'  => __( 'Public', 'wp-route-manager' ),
					'instructions' => __( 'When enabled, callers must send <code>X-WRM-Key</code> header or <code>?api_key=</code> query param.', 'wp-route-manager' ),
				],
				[
					'key'          => 'field_wprm_log_requests',
					'label'        => __( 'Log Requests', 'wp-route-manager' ),
					'name'         => 'wprm_log_requests',
					'type'         => 'true_false',
					'default_value' => 1,
					'ui'           => 1,
				],

				// ── Body Parsing ───────────────────────────────────────────
				[
					'key'       => 'field_wprm_tab_body',
					'label'     => __( 'Body Parsing', 'wp-route-manager' ),
					'name'      => '',
					'type'      => 'tab',
					'placement' => 'top',
					'endpoint'  => 0,
				],
				[
					'key'          => 'field_wprm_body_parse_mode',
					'label'        => __( 'Body Parse Mode', 'wp-route-manager' ),
					'name'         => 'wprm_body_parse_mode',
					'type'         => 'select',
					'choices'      => [
						'auto' => 'Auto (detect from Content-Type)',
						'json' => 'Force JSON',
						'form' => 'Force Form Data',
						'raw'  => 'Raw (string)',
						'php'  => 'Custom PHP Parser',
					],
					'default_value' => 'auto',
					'ui'            => 1,
					'instructions'  => __( 'How to parse the incoming request body before passing to the action handler.', 'wp-route-manager' ),
				],
				[
					'key'               => 'field_wprm_body_parse_snippet_id',
					'label'             => __( 'Body Parser Snippet', 'wp-route-manager' ),
					'name'              => 'wprm_body_parse_snippet_id',
					'type'              => 'post_object',
					'post_type'         => [ SnippetPostType::POST_TYPE ],
					'return_format'     => 'id',
					'ui'                => 1,
					'instructions'      => __( 'PHP snippet that receives <code>$raw</code> (string) and must <code>return</code> an array.', 'wp-route-manager' ),
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_body_parse_mode', 'operator' => '==', 'value' => 'php' ] ],
					],
				],

				// ── Action Handler ─────────────────────────────────────────
				[
					'key'       => 'field_wprm_tab_action',
					'label'     => __( 'Action Handler', 'wp-route-manager' ),
					'name'      => '',
					'type'      => 'tab',
					'placement' => 'top',
					'endpoint'  => 0,
				],
				[
					'key'          => 'field_wprm_action_type',
					'label'        => __( 'Action Type', 'wp-route-manager' ),
					'name'         => 'wprm_action_type',
					'type'         => 'select',
					'required'     => 1,
					'choices'      => [
						'wp_action'     => '🔔 WP Action Hook — fire do_action()',
						'wp_filter'     => '🔄 WP Filter Hook — apply_filters() → return JSON',
						'http_forward'  => '🌐 HTTP Forward — relay to external URL',
						'php_snippet'   => '⚡ PHP Snippet — run custom code',
						'dispatch'      => '📡 Dispatch — fan-out to multiple targets simultaneously',
						'store_payload' => '💾 Store Payload — save to Log / CPT / Queue / Transient',
					],
					'default_value' => 'wp_action',
					'ui'            => 1,
				],

				// WP Action / Filter fields.
				[
					'key'               => 'field_wprm_action_hook',
					'label'             => __( 'Hook Name', 'wp-route-manager' ),
					'name'              => 'wprm_action_hook',
					'type'              => 'text',
					'instructions'      => __( 'The WordPress action/filter hook name to fire. E.g. <code>my_plugin_ingest</code>', 'wp-route-manager' ),
					'placeholder'       => 'my_plugin_webhook',
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'wp_action' ] ],
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'wp_filter' ] ],
					],
				],

				// PHP Snippet action.
				[
					'key'               => 'field_wprm_action_snippet_id',
					'label'             => __( 'Action Snippet', 'wp-route-manager' ),
					'name'              => 'wprm_action_snippet_id',
					'type'              => 'post_object',
					'post_type'         => [ SnippetPostType::POST_TYPE ],
					'return_format'     => 'id',
					'ui'                => 1,
					'required'          => 0,
					'instructions'      => __( 'PHP snippet receives <code>$data</code> (parsed body array) and <code>$request</code> (WP_REST_Request). Must <code>return</code> the response array or a WP_Error.', 'wp-route-manager' ),
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'php_snippet' ] ],
					],
				],

				// HTTP Forward fields.
				[
					'key'               => 'field_wprm_forward_url',
					'label'             => __( 'Forward URL', 'wp-route-manager' ),
					'name'              => 'wprm_forward_url',
					'type'              => 'url',
					'required'          => 0,
					'instructions'      => __( 'The external URL to forward the request to.', 'wp-route-manager' ),
					'placeholder'       => 'https://hooks.zapier.com/hooks/catch/...',
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'http_forward' ] ],
					],
				],
				[
					'key'               => 'field_wprm_forward_method',
					'label'             => __( 'Forward Method', 'wp-route-manager' ),
					'name'              => 'wprm_forward_method',
					'type'              => 'select',
					'choices'           => [ 'POST' => 'POST', 'GET' => 'GET', 'PUT' => 'PUT', 'PATCH' => 'PATCH' ],
					'default_value'     => 'POST',
					'ui'                => 1,
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'http_forward' ] ],
					],
				],
				[
					'key'               => 'field_wprm_forward_timeout',
					'label'             => __( 'Timeout (seconds)', 'wp-route-manager' ),
					'name'              => 'wprm_forward_timeout',
					'type'              => 'number',
					'default_value'     => 10,
					'min'               => 1,
					'max'               => 60,
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'http_forward' ] ],
					],
				],
				[
					'key'               => 'field_wprm_forward_headers',
					'label'             => __( 'Extra Headers', 'wp-route-manager' ),
					'name'              => 'wprm_forward_headers',
					'type'              => 'repeater',
					'instructions'      => __( 'Additional headers to send with the forwarded request.', 'wp-route-manager' ),
					'layout'            => 'table',
					'button_label'      => __( 'Add Header', 'wp-route-manager' ),
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'http_forward' ] ],
					],
					'sub_fields' => [
						[
							'key'         => 'field_wprm_fwd_header_key',
							'label'       => __( 'Header Name', 'wp-route-manager' ),
							'name'        => 'header_key',
							'type'        => 'text',
							'placeholder' => 'Authorization',
						],
						[
							'key'         => 'field_wprm_fwd_header_val',
							'label'       => __( 'Value', 'wp-route-manager' ),
							'name'        => 'header_value',
							'type'        => 'text',
							'placeholder' => 'Bearer token',
						],
					],
				],
				[
					'key'               => 'field_wprm_forward_transform_snippet_id',
					'label'             => __( 'Transform Snippet (optional)', 'wp-route-manager' ),
					'name'              => 'wprm_transform_snippet_id',
					'type'              => 'post_object',
					'post_type'         => [ SnippetPostType::POST_TYPE ],
					'return_format'     => 'id',
					'ui'                => 1,
					'instructions'      => __( 'Run a PHP snippet to transform <code>$data</code> before forwarding. Must <code>return</code> the modified data array.', 'wp-route-manager' ),
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'http_forward' ] ],
					],
				],

				// ── Store Payload config ──────────────────────────────────
				[
					'key'               => 'field_wprm_store_destinations',
					'label'             => __( 'Store To', 'wp-route-manager' ),
					'name'              => 'wprm_store_destinations',
					'type'              => 'checkbox',
					'choices'           => [
						'log'   => '📋 Request Log (wprm_logs)',
						'cpt'   => '📝 Custom Post Type',
						'queue' => '⏳ Payload Queue (deferred processing)',
						'temp'  => '⚡ Transient (temporary hold)',
					],
					'default_value'     => [ 'log', 'queue' ],
					'layout'            => 'vertical',
					'instructions'      => __( 'Select one or more storage destinations. Data is saved to ALL selected.', 'wp-route-manager' ),
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'store_payload' ] ],
					],
				],
				[
					'key'               => 'field_wprm_store_cpt_type',
					'label'             => __( 'CPT Slug', 'wp-route-manager' ),
					'name'              => 'wprm_store_cpt_type',
					'type'              => 'text',
					'placeholder'       => 'e.g. my_lead',
					'instructions'      => __( 'The post_type slug to save payload into. Must already be registered.', 'wp-route-manager' ),
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'store_payload' ] ],
					],
				],
				[
					'key'               => 'field_wprm_store_cpt_title_key',
					'label'             => __( 'CPT Title from Field', 'wp-route-manager' ),
					'name'              => 'wprm_store_cpt_title_key',
					'type'              => 'text',
					'placeholder'       => 'e.g. name or email',
					'instructions'      => __( 'Payload key to use as post title. Leave empty for auto-generated title.', 'wp-route-manager' ),
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'store_payload' ] ],
					],
				],
				[
					'key'               => 'field_wprm_store_queue_status',
					'label'             => __( 'Queue Initial Status', 'wp-route-manager' ),
					'name'              => 'wprm_store_queue_status',
					'type'              => 'select',
					'choices'           => [
						'pending' => 'Pending — auto-dispatch when scheduled',
						'held'    => 'Held — manual release required',
					],
					'default_value'     => 'pending',
					'ui'                => 1,
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'store_payload' ] ],
					],
				],
				[
					'key'               => 'field_wprm_store_queue_dispatch',
					'label'             => __( 'Dispatch Queue Immediately', 'wp-route-manager' ),
					'name'              => 'wprm_store_queue_dispatch',
					'type'              => 'true_false',
					'default_value'     => 0,
					'ui'                => 1,
					'ui_on_text'        => 'Yes — schedule chunk run now',
					'ui_off_text'       => 'No — wait for scheduled run',
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'store_payload' ] ],
					],
				],
				[
					'key'               => 'field_wprm_store_transient_ttl',
					'label'             => __( 'Transient TTL (seconds)', 'wp-route-manager' ),
					'name'              => 'wprm_store_transient_ttl',
					'type'              => 'number',
					'default_value'     => 3600,
					'min'               => 60,
					'instructions'      => __( '3600 = 1 hour. 86400 = 1 day.', 'wp-route-manager' ),
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'store_payload' ] ],
					],
				],

				// ── Dispatch config ─────────────────────────────────────────
				[
					'key'               => 'field_wprm_dispatch_mode',
					'label'             => __( 'Dispatch Mode', 'wp-route-manager' ),
					'name'              => 'wprm_dispatch_mode',
					'type'              => 'select',
					'choices'           => [
						'parallel'   => 'Parallel — all targets triggered (HTTP targets are synchronous)',
						'async'      => 'Async — schedule each target via Action Scheduler',
					],
					'default_value'     => 'parallel',
					'ui'                => 1,
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'dispatch' ] ],
					],
				],
				[
					'key'               => 'field_wprm_dispatch_targets',
					'label'             => __( 'Dispatch Targets', 'wp-route-manager' ),
					'name'              => 'wprm_dispatch_targets',
					'type'              => 'repeater',
					'instructions'      => __( 'Define each target to dispatch to. All targets receive the same parsed payload.', 'wp-route-manager' ),
					'layout'            => 'block',
					'button_label'      => __( 'Add Target', 'wp-route-manager' ),
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'dispatch' ] ],
					],
					'sub_fields' => [
						[
							'key'     => 'field_wprm_dt_label',
							'label'   => __( 'Label', 'wp-route-manager' ),
							'name'    => 'label',
							'type'    => 'text',
							'placeholder' => 'e.g. CRM Hook, Slack Webhook',
						],
						[
							'key'     => 'field_wprm_dt_type',
							'label'   => __( 'Type', 'wp-route-manager' ),
							'name'    => 'type',
							'type'    => 'select',
							'choices' => [
								'hook'  => '🔔 WP Action Hook',
								'url'   => '🌐 HTTP URL',
								'async' => '⏳ Async Action Scheduler Hook',
							],
							'default_value' => 'hook',
							'ui'            => 1,
						],
						[
							'key'         => 'field_wprm_dt_value',
							'label'       => __( 'Hook Name / URL', 'wp-route-manager' ),
							'name'        => 'value',
							'type'        => 'text',
							'placeholder' => 'my_action_hook or https://...',
						],
					],
				],
				[
					'key'               => 'field_wprm_dispatch_timeout',
					'label'             => __( 'HTTP Timeout (seconds)', 'wp-route-manager' ),
					'name'              => 'wprm_dispatch_timeout',
					'type'              => 'number',
					'default_value'     => 10,
					'min'               => 1,
					'max'               => 60,
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_action_type', 'operator' => '==', 'value' => 'dispatch' ] ],
					],
				],

				// ── Response ───────────────────────────────────────────────
				[
					'key'       => 'field_wprm_tab_response',
					'label'     => __( 'Response', 'wp-route-manager' ),
					'name'      => '',
					'type'      => 'tab',
					'placement' => 'top',
					'endpoint'  => 0,
				],
				[
					'key'          => 'field_wprm_response_type',
					'label'        => __( 'Response Format', 'wp-route-manager' ),
					'name'         => 'wprm_response_type',
					'type'         => 'select',
					'choices'      => [
						'json'     => 'JSON (default)',
						'text'     => 'Plain text',
						'redirect' => 'Redirect',
						'none'     => 'Empty 200',
					],
					'default_value' => 'json',
					'ui'            => 1,
				],
				[
					'key'               => 'field_wprm_redirect_url',
					'label'             => __( 'Redirect URL', 'wp-route-manager' ),
					'name'              => 'wprm_redirect_url',
					'type'              => 'url',
					'conditional_logic' => [
						[ [ 'field' => 'field_wprm_response_type', 'operator' => '==', 'value' => 'redirect' ] ],
					],
				],
				[
					'key'          => 'field_wprm_response_code',
					'label'        => __( 'Success HTTP Code', 'wp-route-manager' ),
					'name'         => 'wprm_response_code',
					'type'         => 'number',
					'default_value' => 200,
					'min'          => 200,
					'max'          => 299,
				],
			],
		] );
	}

	// ── Snippet Fields ────────────────────────────────────────────────────────

	public function register_snippet_fields(): void {
		acf_add_local_field_group( [
			'key'      => 'group_wprm_snippet',
			'title'    => __( 'Snippet Configuration', 'wp-route-manager' ),
			'location' => [
				[ [ 'param' => 'post_type', 'operator' => '==', 'value' => SnippetPostType::POST_TYPE ] ],
			],
			'position'        => 'normal',
			'style'           => 'seamless',
			'label_placement' => 'top',
			'fields'          => [

				[
					'key'          => 'field_wprm_snippet_type',
					'label'        => __( 'Snippet Type', 'wp-route-manager' ),
					'name'         => 'wprm_snippet_type',
					'type'         => 'select',
					'required'     => 1,
					'choices'      => [
						'action_handler' => '🔔 Action Handler — receives $data, $request; return ignored',
						'filter_handler' => '🔄 Filter Handler — receives $data, $request; must return response',
						'body_parser'    => '📦 Body Parser — receives $raw string; must return array',
						'http_transform' => '🔁 HTTP Transform — receives $data; must return modified array',
						'standalone'     => '⚡ Standalone — full control, return anything',
					],
					'default_value' => 'standalone',
					'ui'            => 1,
				],
				[
					'key'          => 'field_wprm_snippet_description',
					'label'        => __( 'Description', 'wp-route-manager' ),
					'name'         => 'wprm_snippet_description',
					'type'         => 'textarea',
					'rows'         => 3,
					'instructions' => __( 'What does this snippet do? Available variables? Expected return value?', 'wp-route-manager' ),
				],
				[
					'key'          => 'field_wprm_snippet_code',
					'label'        => __( 'PHP Code', 'wp-route-manager' ),
					'name'         => 'wprm_snippet_code',
					'type'         => 'textarea',
					'required'     => 1,
					'rows'         => 4,
					'new_lines'    => '',
					'instructions' => __( 'The Monaco editor loads below. Use Ctrl+Space for autocomplete, Ctrl+Shift+F to format.', 'wp-route-manager' ),
					'wrapper'      => [ 'class' => 'wprm-code-field-wrap acf-hidden' ],
				],
				[
					'key'          => 'field_wprm_snippet_timeout',
					'label'        => __( 'Execution Timeout (seconds)', 'wp-route-manager' ),
					'name'         => 'wprm_snippet_timeout',
					'type'         => 'number',
					'default_value' => 10,
					'min'          => 1,
					'max'          => 60,
					'instructions' => __( 'Maximum allowed execution time for this snippet.', 'wp-route-manager' ),
				],
				[
					'key'          => 'field_wprm_snippet_catch_errors',
					'label'        => __( 'Catch & Log Errors', 'wp-route-manager' ),
					'name'         => 'wprm_snippet_catch_errors',
					'type'         => 'true_false',
					'default_value' => 1,
					'ui'           => 1,
					'instructions' => __( 'When enabled, PHP errors and exceptions are caught, logged, and a 500 error response is returned rather than crashing.', 'wp-route-manager' ),
				],
			],
		] );
	}

	private function snippet_instructions(): string {
		return '<strong>Available variables:</strong><br>
		<code>$data</code> — parsed request body (array)<br>
		<code>$request</code> — WP_REST_Request object<br>
		<code>$endpoint</code> — endpoint post ID (int)<br>
		<br>
		<strong>Return values:</strong><br>
		<code>return array()</code> — sent as JSON response<br>
		<code>return new WP_Error(\'code\', \'msg\', [\'status\' => 422])</code> — HTTP error<br>
		<code>return null</code> — empty 200 OK<br>
		<br>
		⚠ <strong>Must be opted-in:</strong> Add <code>define(\'WPRM_ALLOW_PHP_SNIPPETS\', true);</code> to wp-config.php';
	}

	// ── Settings Page Fields ──────────────────────────────────────────────────

	public function register_settings_page_fields(): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return; // ACF Pro or SCF needed for options pages.
		}

		// If the option page was already registered (by AdminLoader), just add fields.
		acf_add_local_field_group( [
			'key'      => 'group_wprm_settings',
			'title'    => __( 'API Settings', 'wp-route-manager' ),
			'location' => [
				[ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'wprm-settings' ] ],
			],
			'fields' => [
				[
					'key'           => 'field_wprm_namespace',
					'label'         => __( 'API Namespace', 'wp-route-manager' ),
					'name'          => 'wprm_namespace',
					'type'          => 'text',
					'default_value' => 'wprm',
					'instructions'  => __( 'The namespace prefix for all routes: <code>/wp-json/{namespace}/{version}/{slug}</code>', 'wp-route-manager' ),
				],
				[
					'key'           => 'field_wprm_api_version',
					'label'         => __( 'API Version', 'wp-route-manager' ),
					'name'          => 'wprm_api_version',
					'type'          => 'text',
					'default_value' => 'v1',
				],
				[
					'key'           => 'field_wprm_global_logging',
					'label'         => __( 'Enable Request Logging', 'wp-route-manager' ),
					'name'          => 'wprm_global_logging',
					'type'          => 'true_false',
					'default_value' => 1,
					'ui'            => 1,
				],
				[
					'key'           => 'field_wprm_log_retention_days',
					'label'         => __( 'Log Retention (days)', 'wp-route-manager' ),
					'name'          => 'wprm_log_retention_days',
					'type'          => 'select',
					'choices'       => [ '3' => '3 days', '7' => '7 days', '14' => '14 days', '30' => '30 days', '0' => 'Forever' ],
					'default_value' => '7',
					'ui'            => 1,
				],
				[
					'key'          => 'field_wprm_enable_cron_purge',
					'label'        => __( 'Enable CRON Log Purge', 'wp-route-manager' ),
					'name'         => 'wprm_enable_cron_purge',
					'type'         => 'true_false',
					'default_value' => 1,
					'ui'           => 1,
				],
			],
		] );
	}
}
