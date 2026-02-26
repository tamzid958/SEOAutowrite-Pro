/* global seoapwAdmin, jQuery */
( function ( $ ) {
	'use strict';

	$( function () {

		// ── Schedule frequency toggle ────────────────────────────────
		$( '#seoapw-schedule-frequency' ).on( 'change', function () {
			if ( 'custom' === $( this ).val() ) {
				$( '#seoapw-custom-minutes-row' ).show();
			} else {
				$( '#seoapw-custom-minutes-row' ).hide();
			}
		} );

		// ── Image mode toggle ────────────────────────────────────────
		$( '#seoapw-image-mode' ).on( 'change', function () {
			if ( 'generate' === $( this ).val() ) {
				$( '.seoapw-image-generate-row' ).show();
			} else {
				$( '.seoapw-image-generate-row' ).hide();
			}
		} );

		// ── Fetch Available Models ───────────────────────────────────
		$( '#seoapw-fetch-models' ).on( 'click', function () {
			var $btn    = $( this );
			var $status = $( '#seoapw-fetch-models-status' );
			var $list   = $( '#seoapw-models-list' );

			$btn.prop( 'disabled', true );
			$status.css( 'color', '' ).text( seoapwAdmin.strings.fetchingModels );
			$list.hide().empty();

			$.post(
				seoapwAdmin.ajaxUrl,
				{
					action: 'seoapw_fetch_models',
					nonce:  seoapwAdmin.fetchModelsNonce,
				},
				function ( response ) {
					$btn.prop( 'disabled', false );

					if ( ! response.success || ! response.data.models.length ) {
						$status.css( 'color', '#b91c1c' ).text(
							( response.data && response.data.message )
								? response.data.message
								: seoapwAdmin.strings.noModels
						);
						return;
					}

					$status.css( 'color', '' ).text( '' );

					var html = '<ul style="margin:0;list-style:disc;padding-left:1.4em;">';
					$.each( response.data.models, function ( i, model ) {
						html += '<li>' + $( '<span>' ).text( model ).html() +
							' <a href="#" class="seoapw-use-model" data-model="' +
							$( '<span>' ).text( model ).html() + '">[' +
							seoapwAdmin.strings.useModel + ']</a></li>';
					} );
					html += '</ul>';

					$list.html( html ).show();
				}
			).fail( function () {
				$btn.prop( 'disabled', false );
				$status.css( 'color', '#b91c1c' ).text( seoapwAdmin.strings.error );
			} );
		} );

		// Click "Use" next to a model name to populate the model field.
		$( document ).on( 'click', '.seoapw-use-model', function ( e ) {
			e.preventDefault();
			$( '#seoapw-ollama-model' ).val( $( this ).data( 'model' ) );
		} );

		// ── Run Now button ───────────────────────────────────────────
		$( '#seoapw-run-now' ).on( 'click', function ( e ) {
			e.preventDefault();

			var $btn       = $( this );
			var $status    = $( '#seoapw-run-now-status' );
			var runLabel   = '&#9654; ' + ( seoapwAdmin.strings.running || 'Running\u2026' );
			var resetLabel = '&#9654; Run Now';

			$btn.prop( 'disabled', true ).html( runLabel );
			$status.css( 'color', '' ).text( '' );

			$.post(
				seoapwAdmin.ajaxUrl,
				{
					action: 'seoapw_run_now',
					nonce:  seoapwAdmin.runNonce,
				},
				function ( response ) {
					$btn.prop( 'disabled', false ).html( resetLabel );

					if ( response.success ) {
						$status.css( 'color', '#16a34a' ).text(
							( response.data && response.data.message )
								? response.data.message
								: seoapwAdmin.strings.done
						);
					} else {
						$status.css( 'color', '#b91c1c' ).text(
							( response.data && response.data.message )
								? response.data.message
								: seoapwAdmin.strings.error
						);
					}

					// Reload after 2.5 s so the logs section reflects the run.
					setTimeout( function () {
						location.reload();
					}, 2500 );
				}
			).fail( function () {
				$btn.prop( 'disabled', false ).html( resetLabel );
				$status.css( 'color', '#b91c1c' ).text( seoapwAdmin.strings.error );
			} );
		} );

	} );
}( jQuery ) );
