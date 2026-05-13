/**
 * WP Route Manager — Monaco Editor Integration
 *
 * Replaces the plain <textarea> on snippet edit screens with Monaco Editor
 * loaded from CDN. Includes PHP-aware completions for all WPRM context
 * variables ($data, $request, $endpoint, $wpdb) and common WP functions.
 */
( function () {
	'use strict';

	// Only run on snippet post type edit screens.
	if ( ! document.querySelector( '.wprm-snippet-editor-mount' ) ) {
		return;
	}

	var MONACO_VERSION = '0.45.0';
	var CDN = 'https://cdn.jsdelivr.net/npm/monaco-editor@' + MONACO_VERSION + '/min';

	// ── Load Monaco from CDN ──────────────────────────────────────────────────
	window.MonacoEnvironment = {
		getWorkerUrl: function () {
			// Inline worker via blob — avoids CORS issues with CDN workers.
			return URL.createObjectURL( new Blob( [
				'self.MonacoEnvironment={getWorkerUrl:function(){}};',
				'importScripts("' + CDN + '/vs/base/worker/workerMain.js");'
			], { type: 'text/javascript' } ) );
		}
	};

	var loader = document.createElement( 'script' );
	loader.src = CDN + '/vs/loader.js';
	loader.onload = function () {
		require.config( { paths: { vs: CDN + '/vs' } } );
		require( [ 'vs/editor/editor.main' ], initEditor );
	};
	document.head.appendChild( loader );

	// ── Init editor ───────────────────────────────────────────────────────────
	function initEditor() {
		var mounts = document.querySelectorAll( '.wprm-snippet-editor-mount' );

		mounts.forEach( function ( mount ) {
			var textarea = mount.querySelector( 'textarea.wprm-snippet-textarea' );
			if ( ! textarea ) return;

			var initialValue = textarea.value || getStarterTemplate( mount );
			var snippetType  = mount.dataset.snippetType || 'standalone';

			// Register WPRM PHP completions.
			registerWprmCompletions();

			var editor = monaco.editor.create( mount.querySelector( '.wprm-monaco-container' ) || mount, {
				value:             initialValue,
				language:          'php',
				theme:             'vs-dark',
				fontSize:          14,
				fontFamily:        '"Fira Code", "JetBrains Mono", "Cascadia Code", Consolas, monospace',
				fontLigatures:     true,
				lineNumbers:       'on',
				minimap:           { enabled: true },
				wordWrap:          'on',
				automaticLayout:   true,
				scrollBeyondLastLine: false,
				folding:           true,
				formatOnPaste:     true,
				tabSize:           4,
				insertSpaces:      true,
				suggestOnTriggerCharacters: true,
				quickSuggestions:  { other: true, comments: false, strings: false },
				parameterHints:    { enabled: true },
				renderWhitespace:  'selection',
				bracketPairColorization: { enabled: true },
				guides: {
					bracketPairs: true,
					indentation:  true,
				},
				scrollbar: {
					verticalScrollbarSize: 8,
					horizontalScrollbarSize: 8,
				},
			} );

			// Show context helper banner.
			renderContextBanner( mount, snippetType );

			// Sync back to textarea on every change (form submit reads textarea).
			editor.onDidChangeModelContent( function () {
				textarea.value = editor.getValue();
			} );

			// Keyboard shortcut: Ctrl+Shift+F = format document.
			editor.addCommand(
				monaco.KeyMod.CtrlCmd | monaco.KeyMod.Shift | monaco.KeyCode.KeyF,
				function () { editor.getAction( 'editor.action.formatDocument' ).run(); }
			);

			// Store on mount so external code can access it.
			mount._monacoEditor = editor;
		} );
	}

	// ── WPRM-specific PHP completions ─────────────────────────────────────────
	function registerWprmCompletions() {
		// Prevent registering twice.
		if ( window._wprmCompletionsRegistered ) return;
		window._wprmCompletionsRegistered = true;

		monaco.languages.registerCompletionItemProvider( 'php', {
			triggerCharacters: [ '$', '-', '>' ],
			provideCompletionItems: function ( model, position ) {
				var word    = model.getWordUntilPosition( position );
				var range   = {
					startLineNumber: position.lineNumber,
					endLineNumber:   position.lineNumber,
					startColumn:     word.startColumn,
					endColumn:       word.endColumn,
				};

				var suggestions = [];

				// ── WPRM context variables ────────────────────────────────────
				var contextVars = [
					{
						label:         '$data',
						kind:          monaco.languages.CompletionItemKind.Variable,
						detail:        'array — Parsed request body',
						documentation: 'The parsed request body as an associative array.\nKeys depend on what the caller sent.\nExample: $data[\'email\'], $data[\'name\']',
						insertText:    '\\$data',
					},
					{
						label:         '$request',
						kind:          monaco.languages.CompletionItemKind.Variable,
						detail:        'WP_REST_Request — Full REST request',
						documentation: 'The full WordPress REST request object.\nUseful for reading headers, method, params.\nExample: $request->get_header(\'X-Custom\'), $request->get_method()',
						insertText:    '\\$request',
					},
					{
						label:         '$endpoint',
						kind:          monaco.languages.CompletionItemKind.Variable,
						detail:        'int — Endpoint post ID',
						documentation: 'The post ID of the wprm_endpoint that triggered this snippet.',
						insertText:    '\\$endpoint',
					},
					{
						label:         '$wpdb',
						kind:          monaco.languages.CompletionItemKind.Variable,
						detail:        'wpdb — WordPress database object',
						documentation: 'Global WordPress database object.\nExample: $wpdb->get_results($wpdb->prepare(...)), $wpdb->insert(...)',
						insertText:    '\\$wpdb',
					},
					{
						label:         '$wp_query',
						kind:          monaco.languages.CompletionItemKind.Variable,
						detail:        'WP_Query — Current WP query',
						insertText:    '\\$wp_query',
					},
				];

				contextVars.forEach( function ( v ) {
					suggestions.push( Object.assign( {}, v, { range: range } ) );
				} );

				// ── Common WP functions with snippets ─────────────────────────
				var wpFunctions = [
					{ label: 'get_post_meta', snippet: 'get_post_meta( ${1:$post_id}, ${2:\'meta_key\'}, ${3:true} )' },
					{ label: 'update_post_meta', snippet: 'update_post_meta( ${1:$post_id}, ${2:\'meta_key\'}, ${3:$value} )' },
					{ label: 'wp_insert_post', snippet: 'wp_insert_post( [\n\t\'post_type\'   => ${1:\'post\'},\n\t\'post_title\'  => ${2:$title},\n\t\'post_status\' => \'publish\',\n], true )' },
					{ label: 'wp_update_post', snippet: 'wp_update_post( [\n\t\'ID\'         => ${1:$post_id},\n\t${2:\'post_title\' => $title},\n] )' },
					{ label: 'get_user_by', snippet: 'get_user_by( ${1:\'email\'}, ${2:$email} )' },
					{ label: 'wp_insert_user', snippet: 'wp_insert_user( [\n\t\'user_email\' => ${1:$email},\n\t\'user_login\' => ${2:$email},\n\t\'role\'       => ${3:\'subscriber\'},\n] )' },
					{ label: 'sanitize_text_field', snippet: 'sanitize_text_field( ${1:$value} )' },
					{ label: 'sanitize_email', snippet: 'sanitize_email( ${1:$email} )' },
					{ label: 'wp_json_encode', snippet: 'wp_json_encode( ${1:$data} )' },
					{ label: 'current_time', snippet: 'current_time( ${1:\'mysql\'} )' },
					{ label: 'set_transient', snippet: 'set_transient( ${1:\'key\'}, ${2:$value}, ${3:HOUR_IN_SECONDS} )' },
					{ label: 'get_transient', snippet: 'get_transient( ${1:\'key\'} )' },
					{ label: 'do_action', snippet: 'do_action( ${1:\'my_action\'}, ${2:$data} )' },
					{ label: 'apply_filters', snippet: 'apply_filters( ${1:\'my_filter\'}, ${2:$data} )' },
					{ label: 'wp_remote_post', snippet: 'wp_remote_post( ${1:$url}, [\n\t\'headers\' => [ \'Content-Type\' => \'application/json\' ],\n\t\'body\'    => wp_json_encode( ${2:$data} ),\n\t\'timeout\' => 10,\n] )' },
					{ label: 'is_wp_error', snippet: 'is_wp_error( ${1:$result} )' },
					{ label: 'WP_Error', snippet: 'new \\WP_Error( ${1:\'error_code\'}, ${2:\'Error message\'}, [ \'status\' => ${3:422} ] )' },
					{ label: 'as_schedule_single_action', snippet: 'as_schedule_single_action( time(), ${1:\'my_hook\'}, [ ${2:$data} ], \'wp-route-manager\' )' },
					{ label: '$wpdb->prepare', snippet: '\\$wpdb->prepare( ${1:"SELECT * FROM {\\$wpdb->posts} WHERE ID = %d"}, ${2:$id} )' },
					{ label: '$wpdb->get_results', snippet: '\\$wpdb->get_results( \\$wpdb->prepare( ${1:"SELECT * FROM {\\$wpdb->posts} WHERE post_type = %s"}, ${2:\'post\'} ) )' },
					{ label: '$wpdb->insert', snippet: '\\$wpdb->insert(\n\t\\$wpdb->prefix . ${1:\'my_table\'},\n\t[\n\t\t${2:\'column\' => \\$value},\n\t],\n\t[ ${3:\'%s\'} ]\n)' },
					{ label: '$request->get_header', snippet: '\\$request->get_header( ${1:\'X-Custom-Header\'} )' },
					{ label: '$request->get_param', snippet: '\\$request->get_param( ${1:\'param_name\'} )' },
					{ label: '$request->get_method', snippet: '\\$request->get_method()' },
					{ label: '$data[key]', snippet: '\\$data[${1:\'key\'}]' },
				];

				wpFunctions.forEach( function ( fn ) {
					suggestions.push( {
						label:      fn.label,
						kind:       monaco.languages.CompletionItemKind.Function,
						insertText: fn.snippet,
						insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
						range:      range,
					} );
				} );

				// ── Return value snippets ─────────────────────────────────────
				var returnSnippets = [
					{
						label:  'return array (success)',
						detail: 'Return a success response array',
						snippet: 'return [\n\t\'status\'  => \'ok\',\n\t\'message\' => ${1:\'Processed successfully\'},\n\t\'data\'    => ${2:$result},\n];',
					},
					{
						label:  'return WP_Error',
						detail: 'Return an error response',
						snippet: 'return new \\WP_Error(\n\t${1:\'validation_error\'},\n\t${2:\'Invalid data provided.\'},\n\t[ \'status\' => ${3:422} ]\n);',
					},
					{
						label:  'return null (empty 200)',
						detail: 'Return empty 200 OK',
						snippet: 'return null;',
					},
				];

				returnSnippets.forEach( function ( s ) {
					suggestions.push( {
						label:      s.label,
						kind:       monaco.languages.CompletionItemKind.Snippet,
						detail:     s.detail,
						insertText: s.snippet,
						insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
						range:      range,
					} );
				} );

				return { suggestions: suggestions };
			},
		} );
	}

	// ── Context banner ─────────────────────────────────────────────────────────
	function renderContextBanner( mount, snippetType ) {
		var banners = {
			action_handler: {
				color: '#0073aa',
				vars:  '$data (array), $request (WP_REST_Request), $endpoint (int)',
				note:  'Return value is ignored. Use do_action() to trigger downstream logic.',
			},
			filter_handler: {
				color: '#46b450',
				vars:  '$data (array), $request (WP_REST_Request), $endpoint (int)',
				note:  'Must return array or WP_Error — becomes the HTTP response.',
			},
			body_parser: {
				color: '#826eb4',
				vars:  '$raw (string — raw request body)',
				note:  'Must return array — becomes $data for the action handler.',
			},
			http_transform: {
				color: '#f56e28',
				vars:  '$data (array), $request (WP_REST_Request), $endpoint (int)',
				note:  'Must return array — sent to the external HTTP endpoint.',
			},
			standalone: {
				color: '#666',
				vars:  '$data (array), $request (WP_REST_Request), $endpoint (int), $wpdb',
				note:  'Return anything (array/string/null/WP_Error) or nothing.',
			},
		};

		var b = banners[ snippetType ] || banners.standalone;

		var banner = document.createElement( 'div' );
		banner.style.cssText = [
			'background:' + b.color,
			'color:#fff',
			'font-family:monospace',
			'font-size:12px',
			'padding:8px 14px',
			'display:flex',
			'gap:20px',
			'align-items:center',
			'flex-wrap:wrap',
		].join( ';' );

		banner.innerHTML =
			'<strong>📦 Available:</strong> <code style="background:rgba(0,0,0,.25);padding:2px 6px;border-radius:3px">' + escHtml( b.vars ) + '</code>' +
			'<span style="opacity:.8">→ ' + escHtml( b.note ) + '</span>' +
			'<span style="margin-left:auto;opacity:.7;font-size:11px">Ctrl+Space = autocomplete &nbsp;|&nbsp; Ctrl+Shift+F = format</span>';

		mount.insertBefore( banner, mount.firstChild );
	}

	// ── Starter template by snippet type ──────────────────────────────────────
	function getStarterTemplate( mount ) {
		var type = mount.dataset.snippetType || 'standalone';

		var templates = {
			action_handler: [
				'<?php',
				'// Action Handler Snippet',
				'// Receives: $data (array), $request (WP_REST_Request), $endpoint (int)',
				'// Return value is ignored.',
				'',
				'$email = sanitize_email( $data[\'email\'] ?? \'\' );',
				'',
				'if ( ! is_email( $email ) ) {',
				'\terror_log( \'[WPRM] Invalid email: \' . $email );',
				'\treturn;',
				'}',
				'',
				'// Your action logic here...',
				'do_action( \'my_plugin_process\', $data );',
			].join( '\n' ),

			filter_handler: [
				'<?php',
				'// Filter Handler Snippet',
				'// Receives: $data (array), $request (WP_REST_Request), $endpoint (int)',
				'// Must return array or WP_Error.',
				'',
				'if ( empty( $data[\'email\'] ) ) {',
				'\treturn new WP_Error( \'missing_email\', \'Email is required.\', [ \'status\' => 422 ] );',
				'}',
				'',
				'return [',
				'\t\'status\'    => \'ok\',',
				'\t\'received\'  => $data,',
				'\t\'timestamp\' => current_time( \'c\' ),',
				'];',
			].join( '\n' ),

			body_parser: [
				'<?php',
				'// Body Parser Snippet',
				'// Receives: $raw (string — raw request body)',
				'// Must return array.',
				'',
				'// Example: parse JSON with fallback',
				'$parsed = json_decode( $raw, true );',
				'',
				'if ( ! is_array( $parsed ) ) {',
				'\t// Try form-encoded',
				'\tparse_str( $raw, $parsed );',
				'}',
				'',
				'return $parsed ?: [ \'raw\' => $raw ];',
			].join( '\n' ),

			http_transform: [
				'<?php',
				'// HTTP Transform Snippet',
				'// Receives: $data (array), $request (WP_REST_Request), $endpoint (int)',
				'// Must return array — sent to external URL.',
				'',
				'return [',
				'\t\'contact\' => [',
				'\t\t\'email\'      => $data[\'email\'] ?? \'\',',
				'\t\t\'first_name\' => $data[\'first_name\'] ?? \'\',',
				'\t\t\'last_name\'  => $data[\'last_name\'] ?? \'\',',
				'\t],',
				'\t\'source\'    => \'wordpress\',',
				'\t\'timestamp\' => current_time( \'c\' ),',
				'];',
			].join( '\n' ),

			standalone: [
				'<?php',
				'// Standalone Snippet',
				'// Receives: $data, $request, $endpoint, $wpdb',
				'// Return: array (JSON response), null (empty 200), or WP_Error.',
				'',
				'return [',
				'\t\'status\'  => \'ok\',',
				'\t\'data\'    => $data,',
				'\t\'time\'    => current_time( \'c\' ),',
				'];',
			].join( '\n' ),
		};

		return templates[ type ] || templates.standalone;
	}

	function escHtml( str ) {
		var d = document.createElement( 'div' );
		d.textContent = str;
		return d.innerHTML;
	}

} )();
