<?php
/**
 * Pro license validation and balance management.
 *
 * @package SEOAutowrite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check whether a valid Pro license is active.
 *
 * @return bool
 */
function seoapw_is_pro(): bool {
	$license = seoapw_get_license();
	return isset( $license['valid'] ) && true === $license['valid'];
}

/**
 * Get cached license status, refreshing from API if the transient has expired.
 *
 * @return array License data array. Always contains at minimum ['valid' => bool].
 */
function seoapw_get_license(): array {
	$cached = get_transient( 'seoapw_license_status' );
	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	$key = get_option( 'seoapw_license_key', '' );
	if ( empty( $key ) ) {
		return array( 'valid' => false );
	}

	return seoapw_validate_license( $key );
}

/**
 * Validate a license key against the Pro API and cache the result.
 *
 * @param string $key License key to validate.
 * @return array License data or ['valid' => false] on failure.
 */
function seoapw_validate_license( string $key ): array {
	$default = array( 'valid' => false );

	if ( empty( $key ) ) {
		return $default;
	}

	$response = wp_remote_post(
		SEOAPW_API_URL . '/api/license/validate',
		array(
			'timeout'     => 15,
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode(
				array(
					'license_key'    => $key,
					'site_url'       => home_url(),
					'plugin_version' => SEOAPW_VERSION,
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $default;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || ! is_array( $body ) ) {
		return $default;
	}

	$result = array(
		'valid'                 => ! empty( $body['valid'] ),
		'tier'                  => sanitize_text_field( $body['tier'] ?? '' ),
		'balance_usd'           => (float) ( $body['balance_usd'] ?? 0 ),
		'articles_remaining'    => (int) ( $body['articles_remaining'] ?? 0 ),
		'low_balance'           => ! empty( $body['low_balance'] ),
		'auto_topup_enabled'    => ! empty( $body['auto_topup_enabled'] ),
		'auto_topup_amount_usd' => (float) ( $body['auto_topup_amount_usd'] ?? 0 ),
		'site_limit'            => (int) ( $body['site_limit'] ?? 1 ),
		'reason'                => sanitize_text_field( $body['reason'] ?? '' ),
	);

	if ( $result['valid'] ) {
		set_transient( 'seoapw_license_status', $result, 6 * HOUR_IN_SECONDS );
		update_option( 'seoapw_license_key', sanitize_text_field( $key ), false );
	}

	return $result;
}

/**
 * Deactivate the stored license: notify the server and clear local data.
 *
 * @return void
 */
function seoapw_deactivate_license(): void {
	$key = get_option( 'seoapw_license_key', '' );

	if ( ! empty( $key ) ) {
		wp_remote_post(
			SEOAPW_API_URL . '/api/license/deactivate',
			array(
				'timeout'  => 10,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode(
					array(
						'license_key' => $key,
						'site_url'    => home_url(),
					)
				),
			)
		);
	}

	delete_option( 'seoapw_license_key' );
	delete_transient( 'seoapw_license_status' );
}

/**
 * Get the current credit balance in USD.
 *
 * @return float
 */
function seoapw_get_balance(): float {
	$license = seoapw_get_license();
	return (float) ( $license['balance_usd'] ?? 0.0 );
}

/**
 * Check whether the balance is low (< $3.00).
 *
 * @return bool
 */
function seoapw_is_low_balance(): bool {
	$license = seoapw_get_license();
	return ! empty( $license['low_balance'] );
}

/**
 * Check whether the balance is sufficient for one Pro generation (>= $1.50).
 *
 * @return bool
 */
function seoapw_has_sufficient_balance(): bool {
	return seoapw_get_balance() >= 1.50;
}

/**
 * Invalidate the cached license status transient so the next call re-fetches.
 *
 * @return void
 */
function seoapw_invalidate_license_transient(): void {
	delete_transient( 'seoapw_license_status' );
}
