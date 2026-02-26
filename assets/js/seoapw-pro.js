/* global seoapwPro, jQuery */
( function ( $ ) {
	'use strict';

	// -------------------------------------------------------------------------
	// License activation
	// -------------------------------------------------------------------------

	$( '#seoapw-activate-btn' ).on( 'click', function () {
		var $btn    = $( this );
		var $status = $( '#seoapw-activate-status' );
		var key     = $( '#seoapw-license-key-input' ).val().trim();

		if ( ! key ) {
			$status.text( 'Please enter a license key.' ).css( 'color', '#dc2626' );
			return;
		}

		$btn.prop( 'disabled', true ).text( seoapwPro.strings.activating );
		$status.text( '' );

		$.post( seoapwPro.ajaxUrl, {
			action:      'seoapw_activate_license',
			nonce:       seoapwPro.activateNonce,
			license_key: key,
		} )
		.done( function ( response ) {
			if ( response.success ) {
				$status.text( response.data.message ).css( 'color', '#15803d' );
				// Reload the page after a short delay so the license card updates.
				setTimeout( function () {
					window.location.reload();
				}, 1200 );
			} else {
				$status.text( response.data.message ).css( 'color', '#dc2626' );
				$btn.prop( 'disabled', false ).text( 'Activate' );
			}
		} )
		.fail( function () {
			$status.text( seoapwPro.strings.error ).css( 'color', '#dc2626' );
			$btn.prop( 'disabled', false ).text( 'Activate' );
		} );
	} );

	// -------------------------------------------------------------------------
	// License deactivation
	// -------------------------------------------------------------------------

	$( '#seoapw-deactivate-btn' ).on( 'click', function () {
		if ( ! window.confirm( seoapwPro.strings.confirmDeact ) ) {
			return;
		}

		var $btn    = $( this );
		var $status = $( '#seoapw-deactivate-status' );

		$btn.prop( 'disabled', true ).text( seoapwPro.strings.deactivating );
		$status.text( '' );

		$.post( seoapwPro.ajaxUrl, {
			action: 'seoapw_deactivate_license',
			nonce:  seoapwPro.deactivateNonce,
		} )
		.done( function ( response ) {
			if ( response.success ) {
				$status.text( response.data.message ).css( 'color', '#15803d' );
				setTimeout( function () {
					window.location.reload();
				}, 1200 );
			} else {
				$status.text( seoapwPro.strings.error ).css( 'color', '#dc2626' );
				$btn.prop( 'disabled', false ).text( 'Deactivate' );
			}
		} )
		.fail( function () {
			$status.text( seoapwPro.strings.error ).css( 'color', '#dc2626' );
			$btn.prop( 'disabled', false ).text( 'Deactivate' );
		} );
	} );

	// -------------------------------------------------------------------------
	// Upgrade modal
	// -------------------------------------------------------------------------

	$( '#seoapw-open-upgrade-modal' ).on( 'click', function () {
		$( '#seoapw-upgrade-modal' ).fadeIn( 150 );
	} );

	$( document ).on( 'click', '.seoapw-modal__close, .seoapw-modal__backdrop', function () {
		$( '#seoapw-upgrade-modal' ).fadeOut( 150 );
	} );

	$( document ).on( 'keydown', function ( e ) {
		if ( 27 === e.which ) {
			$( '#seoapw-upgrade-modal' ).fadeOut( 150 );
		}
	} );

	// -------------------------------------------------------------------------
	// Admin notice dismissal
	// -------------------------------------------------------------------------

	$( document ).on( 'click', '.seoapw-notice .notice-dismiss', function () {
		var $notice   = $( this ).closest( '.seoapw-notice' );
		var noticeId  = $notice.data( 'notice-id' );

		if ( ! noticeId ) {
			return;
		}

		$.post( seoapwPro.ajaxUrl, {
			action:    'seoapw_dismiss_notice',
			nonce:     seoapwPro.dismissNonce,
			notice_id: noticeId,
		} );
	} );

} )( jQuery );
