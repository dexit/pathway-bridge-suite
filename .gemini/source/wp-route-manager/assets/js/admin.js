/* global wprmAdmin, wprmCodeEditor, ClipboardJS */
( function ( $, cfg ) {
	'use strict';

	// ── CodeMirror on Snippet edit screen ─────────────────────────────────────
	if ( typeof wprmCodeEditor !== 'undefined' && wprmCodeEditor.settings ) {
		// ACF/SCF renders textarea with id="acf-{field_key}".
		// Also try the wrapper class as a fallback (finds first textarea inside it).
		let ta = document.querySelector( wprmCodeEditor.selector );
		if ( ! ta && wprmCodeEditor.wrapperClass ) {
			const wrapper = document.querySelector( '.' + wprmCodeEditor.wrapperClass );
			if ( wrapper ) ta = wrapper.querySelector( 'textarea' );
		}
		if ( ta ) {
			wp.codeEditor.initialize( ta, wprmCodeEditor.settings );
		}
	}

	// ── Clipboard ────────────────────────────────────────────────────────────
	if ( typeof ClipboardJS !== 'undefined' ) {
		const cb = new ClipboardJS( '.wprm-copy-btn' );
		cb.on( 'success', function ( e ) {
			const btn = e.trigger;
			const orig = btn.textContent;
			btn.textContent = cfg.i18n.copied;
			btn.classList.add( 'copied' );
			setTimeout( function () {
				btn.textContent = orig;
				btn.classList.remove( 'copied' );
			}, 2000 );
			e.clearSelection();
		} );
	}

	// ── Log modal ─────────────────────────────────────────────────────────────
	const modal = $( '#wprm-log-modal' );

	$( document ).on( 'click', '.wprm-view-log', function () {
		const id = $( this ).data( 'id' );
		$.get( cfg.ajaxUrl, {
			action: 'wprm_get_log',
			nonce:  cfg.nonce,
			id:     id,
		}, function ( res ) {
			if ( ! res.success ) return;
			const log = res.data;
			const fmt = function ( str ) {
				try { return JSON.stringify( JSON.parse( str ), null, 2 ); } catch ( e ) { return str || '—'; }
			};

			$( '#wprm-log-method' ).text( log.method ).css( 'background', methodColor( log.method ) ).addClass( 'wprm-badge' );
			$( '#wprm-log-code' ).text( log.response_code )
				.removeClass( 'wprm-status--active wprm-status--error' )
				.addClass( log.response_code >= 400 ? 'wprm-status--error' : 'wprm-status--active' );
			$( '#wprm-log-time' ).text( log.created_at );
			$( '#wprm-log-ip' ).text( 'IP: ' + log.caller_ip );
			$( '#wprm-log-duration' ).text( log.duration_ms + 'ms' );

			$( '#wprm-req-headers' ).text( fmt( log.request_headers ) );
			$( '#wprm-req-params' ).text( fmt( log.request_params ) );
			$( '#wprm-req-body-raw' ).text( log.request_body_raw || '—' );
			$( '#wprm-req-body-parsed' ).text( fmt( log.request_body_parsed ) );
			$( '#wprm-response-body' ).text( fmt( log.response_body ) );
			$( '#wprm-snippet-output' ).text( log.snippet_output || '—' );
			$( '#wprm-error-output' ).text( log.error || '—' );

			// Activate first tab.
			$( '.wprm-tab-btn' ).first().trigger( 'click' );
			modal.show();
		} );
	} );

	$( document ).on( 'click', '.wprm-modal-close, .wprm-modal-backdrop', function () {
		modal.hide();
	} );

	$( document ).on( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) modal.hide();
	} );

	// Tabs inside modal.
	$( document ).on( 'click', '.wprm-tab-btn', function () {
		const tab = $( this ).data( 'tab' );
		$( '.wprm-tab-btn' ).removeClass( 'active' );
		$( '.wprm-tab-panel' ).removeClass( 'active' );
		$( this ).addClass( 'active' );
		$( '#wprm-tab-' + tab ).addClass( 'active' );
	} );

	// ── Delete single log ─────────────────────────────────────────────────────
	$( document ).on( 'click', '.wprm-delete-log', function () {
		if ( ! confirm( cfg.i18n.confirmDelete ) ) return;
		const btn = $( this );
		const id  = btn.data( 'id' );
		$.post( cfg.ajaxUrl, {
			action: 'wprm_delete_log',
			nonce:  cfg.nonce,
			id:     id,
		}, function ( res ) {
			if ( res.success ) {
				btn.closest( 'tr' ).fadeOut( 300, function () { $( this ).remove(); } );
				modal.hide();
			}
		} );
	} );

	// ── Clear all logs ────────────────────────────────────────────────────────
	$( document ).on( 'click', '#wprm-clear-all-logs', function () {
		const msg = $( this ).data( 'confirm' ) || cfg.i18n.confirmClear;
		if ( ! confirm( msg ) ) return;
		$.post( cfg.ajaxUrl, {
			action: 'wprm_clear_logs',
			nonce:  cfg.nonce,
		}, function ( res ) {
			if ( res.success ) location.reload();
		} );
	} );

	// ── API Key create ────────────────────────────────────────────────────────
	$( '#wprm-create-key-btn' ).on( 'click', function () {
		const label = $( '#wprm-key-label' ).val().trim();
		if ( ! label ) {
			alert( 'Please enter a label for the key.' );
			return;
		}

		const endpoint_ids = [];
		$( 'input[name="wprm_endpoint_ids[]"]:checked' ).each( function () {
			endpoint_ids.push( $( this ).val() );
		} );

		$.post( cfg.ajaxUrl, {
			action:       'wprm_create_key',
			nonce:        cfg.nonce,
			label:        label,
			endpoint_ids: endpoint_ids,
		}, function ( res ) {
			if ( ! res.success ) return alert( res.data );
			const key = res.data;

			// Show the reveal area with the plain key.
			$( '#wprm-new-key-value' ).text( key.plain_key );
			$( '#wprm-key-reveal-area' ).show();

			// Add row to the table.
			const tbody = $( '#wprm-keys-tbody' );
			if ( tbody.find( 'td[colspan]' ).length ) tbody.empty();
			tbody.prepend(
				'<tr id="wprm-key-row-' + key.id + '">' +
				'<td>#' + key.id + '</td>' +
				'<td>' + escHtml( key.label ) + '</td>' +
				'<td><code>' + escHtml( key.prefix ) + '</code></td>' +
				'<td><em>All endpoints</em></td>' +
				'<td><span class="wprm-status wprm-status--active">Active</span></td>' +
				'<td>Never</td>' +
				'<td>Just now</td>' +
				'<td>' +
				'<button class="button button-small wprm-toggle-key" data-id="' + key.id + '">Deactivate</button> ' +
				'<button class="button button-small wprm-delete-key" data-id="' + key.id + '">Delete</button>' +
				'</td></tr>'
			);

			// Reset form.
			$( '#wprm-key-label' ).val( '' );
			$( 'input[name="wprm_endpoint_ids[]"]' ).prop( 'checked', false );
		} );
	} );

	// ── API Key toggle ────────────────────────────────────────────────────────
	$( document ).on( 'click', '.wprm-toggle-key', function () {
		const btn = $( this );
		const id  = btn.data( 'id' );
		$.post( cfg.ajaxUrl, { action: 'wprm_toggle_key', nonce: cfg.nonce, id: id }, function ( res ) {
			if ( res.success ) location.reload();
		} );
	} );

	// ── API Key delete ────────────────────────────────────────────────────────
	$( document ).on( 'click', '.wprm-delete-key', function () {
		if ( ! confirm( cfg.i18n.confirmDelete ) ) return;
		const btn = $( this );
		const id  = btn.data( 'id' );
		$.post( cfg.ajaxUrl, { action: 'wprm_delete_key', nonce: cfg.nonce, id: id }, function ( res ) {
			if ( res.success ) {
				$( '#wprm-key-row-' + id ).fadeOut( 300, function () { $( this ).remove(); } );
			}
		} );
	} );

	// ── Helpers ───────────────────────────────────────────────────────────────
	function methodColor( method ) {
		const map = { GET: '#0073aa', POST: '#46b450', PUT: '#f56e28', PATCH: '#826eb4', DELETE: '#dc3232' };
		return map[ method ] || '#666';
	}

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

} )( jQuery, wprmAdmin );

	// ── Queue page ─────────────────────────────────────────────────────────────
	function prettyJson( str ) {
		try { return JSON.stringify( JSON.parse( str ), null, 2 ); } catch ( e ) { return str || '—'; }
	}

	// Queue item view modal.
	$( document ).on( 'click', '.wprm-queue-view', function () {
		var btn = $( this );
		$( '#wprm-qm-id' ).text( '#' + btn.data( 'id' ) );
		$( '#wprm-qm-payload' ).text( prettyJson( btn.data( 'payload' ) ) );
		$( '#wprm-qm-result' ).text( prettyJson( btn.data( 'result' ) ) );
		$( '#wprm-qm-error' ).text( btn.data( 'error' ) || '—' );
		$( '.wprm-tab-btn' ).first().trigger( 'click' );
		$( '#wprm-queue-modal' ).show();
	} );

	$( document ).on( 'click', '#wprm-queue-modal .wprm-modal-close, #wprm-queue-modal .wprm-modal-backdrop', function () {
		$( '#wprm-queue-modal' ).hide();
	} );

	// Queue item release (held → pending).
	$( document ).on( 'click', '.wprm-queue-release', function () {
		var id = $( this ).data( 'id' );
		$.post( cfg.ajaxUrl, { action: 'wprm_queue_release_item', nonce: cfg.nonce, id: id }, function () {
			location.reload();
		} );
	} );

	// Queue item delete.
	$( document ).on( 'click', '.wprm-queue-delete', function () {
		if ( ! confirm( cfg.i18n.confirmDelete ) ) return;
		var id = $( this ).data( 'id' );
		$.post( cfg.ajaxUrl, { action: 'wprm_delete_queue_item', nonce: cfg.nonce, id: id }, function () {
			$( '#wprm-queue-row-' + id ).fadeOut( 300, function () { $( this ).remove(); } );
		} );
	} );

	// Run chunk.
	$( '#wprm-run-chunk' ).on( 'click', function () {
		var btn = $( this );
		btn.prop( 'disabled', true ).text( 'Running…' );
		$.post( cfg.ajaxUrl, {
			action: 'wprm_run_queue_chunk',
			nonce: cfg.nonce,
			chunk_size: $( '#wprm-chunk-size' ).val(),
		}, function ( res ) {
			btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-controls-play" style="line-height:1.8;margin-right:4px"></span> Run Now' );
			if ( res.success ) {
				$( '#wprm-chunk-result' ).text( 'Done ✓' ).show();
				setTimeout( function () { location.reload(); }, 800 );
			}
		} );
	} );

	// Release all held.
	$( '#wprm-release-held' ).on( 'click', function () {
		$.post( cfg.ajaxUrl, { action: 'wprm_release_held', nonce: cfg.nonce }, function () {
			location.reload();
		} );
	} );

	// Clear by status.
	$( document ).on( 'click', '[data-status]', function () {
		var msg = $( this ).data( 'confirm' ) || cfg.i18n.confirmDelete;
		if ( ! confirm( msg ) ) return;
		$.post( cfg.ajaxUrl, { action: 'wprm_clear_queue', nonce: cfg.nonce, status: $( this ).data( 'status' ) }, function () {
			location.reload();
		} );
	} );

