/* global asawAdmin, jQuery */
( function ( $ ) {
	'use strict';

	$( function () {

		// ── Schedule frequency toggle ────────────────────────────────
		$( '#asaw-schedule-frequency' ).on( 'change', function () {
			if ( 'custom' === $( this ).val() ) {
				$( '#asaw-custom-minutes-row' ).show();
			} else {
				$( '#asaw-custom-minutes-row' ).hide();
			}
		} );

		// ── Image mode toggle ────────────────────────────────────────
		$( '#asaw-image-mode' ).on( 'change', function () {
			if ( 'generate' === $( this ).val() ) {
				$( '.asaw-image-generate-row' ).show();
			} else {
				$( '.asaw-image-generate-row' ).hide();
			}
		} );

		// ── Fetch Available Models ───────────────────────────────────
		$( '#asaw-fetch-models' ).on( 'click', function () {
			var $btn    = $( this );
			var $status = $( '#asaw-fetch-models-status' );
			var $list   = $( '#asaw-models-list' );

			$btn.prop( 'disabled', true );
			$status.css( 'color', '' ).text( asawAdmin.strings.fetchingModels );
			$list.hide().empty();

			$.post(
				asawAdmin.ajaxUrl,
				{
					action: 'asaw_fetch_models',
					nonce:  asawAdmin.fetchModelsNonce,
				},
				function ( response ) {
					$btn.prop( 'disabled', false );

					if ( ! response.success || ! response.data.models.length ) {
						$status.css( 'color', '#d63638' ).text(
							( response.data && response.data.message )
								? response.data.message
								: asawAdmin.strings.noModels
						);
						return;
					}

					$status.css( 'color', '#46b450' ).text( '' );

					var html = '<ul style="margin:0;list-style:disc;padding-left:1.4em;">';
					$.each( response.data.models, function ( i, model ) {
						html += '<li>' + $( '<span>' ).text( model ).html() +
							' <a href="#" class="asaw-use-model" data-model="' +
							$( '<span>' ).text( model ).html() + '">[' +
							asawAdmin.strings.useModel + ']</a></li>';
					} );
					html += '</ul>';

					$list.html( html ).show();
				}
			).fail( function () {
				$btn.prop( 'disabled', false );
				$status.css( 'color', '#d63638' ).text( asawAdmin.strings.error );
			} );
		} );

		// Click "Use" next to a model name to populate the model field.
		$( document ).on( 'click', '.asaw-use-model', function ( e ) {
			e.preventDefault();
			$( '#asaw-ollama-model' ).val( $( this ).data( 'model' ) );
		} );

		// ── Run Now button ───────────────────────────────────────────
		$( '#asaw-run-now' ).on( 'click', function ( e ) {
			e.preventDefault();

			var $btn    = $( this );
			var $status = $( '#asaw-run-now-status' );

			$btn.prop( 'disabled', true ).text( asawAdmin.strings.running );
			$status.css( 'color', '' ).text( '' );

			$.post(
				asawAdmin.ajaxUrl,
				{
					action: 'asaw_run_now',
					nonce:  asawAdmin.runNonce,
				},
				function ( response ) {
					$btn.prop( 'disabled', false ).text( 'Run Now' );

					if ( response.success ) {
						$status.css( 'color', '#46b450' ).text(
							( response.data && response.data.message )
								? response.data.message
								: asawAdmin.strings.done
						);
					} else {
						$status.css( 'color', '#d63638' ).text(
							( response.data && response.data.message )
								? response.data.message
								: asawAdmin.strings.error
						);
					}

					// Reload after 2.5 s so the logs section reflects the run.
					setTimeout( function () {
						location.reload();
					}, 2500 );
				}
			).fail( function () {
				$btn.prop( 'disabled', false ).text( 'Run Now' );
				$status.css( 'color', '#d63638' ).text( asawAdmin.strings.error );
			} );
		} );

	} );
}( jQuery ) );
