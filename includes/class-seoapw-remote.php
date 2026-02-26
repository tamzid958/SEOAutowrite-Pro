<?php
/**
 * Pro article generation via the remote Next.js API server.
 *
 * @package SEOAutowrite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate a Pro article by calling the remote server.
 *
 * Returns an article array matching the schema from ASAW_Utils::validate_schema(),
 * or WP_Error on any failure (including insufficient balance, invalid license,
 * network timeout). Callers should fall back to Ollama on WP_Error.
 *
 * @param array   $options  Merged plugin options.
 * @param WP_Term $category The selected WordPress category term.
 * @return array|WP_Error Article data array, or WP_Error on failure.
 */
function seoapw_generate_pro( array $options, WP_Term $category ) {
	$key = get_option( 'seoapw_license_key', '' );

	if ( empty( $key ) ) {
		return new WP_Error( 'no_license', __( 'No Pro license key configured.', 'seoapw' ) );
	}

	// Fetch recent titles for duplicate-topic prevention (same logic as free tier).
	$recent_posts = get_posts(
		array(
			'category'       => intval( $category->term_id ),
			'posts_per_page' => 10,
			'post_status'    => array( 'publish', 'draft', 'future', 'pending' ),
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$recent_titles = array_values( array_map( 'get_the_title', $recent_posts ) );

	$cat_desc = ! empty( $category->description )
		? $category->description
		: 'Articles related to ' . $category->name . '.';

	$payload = array(
		'license_key' => $key,
		'site_url'    => home_url(),
		'settings'    => array(
			'tone'                  => sanitize_text_field( $options['tone'] ?? 'professional' ),
			'language'              => sanitize_text_field( $options['language'] ?? 'en' ),
			'min_words'             => max( 100, intval( $options['min_words'] ?? 800 ) ),
			'max_words'             => max( 100, intval( $options['max_words'] ?? 1500 ) ),
			'max_internal_links'    => max( 1, intval( $options['max_internal_links'] ?? 3 ) ),
			'category_name'         => $category->name,
			'category_description'  => $cat_desc,
			'store_internal_links'  => ! empty( $options['insert_internal_links'] ),
			'append_backlink_brief' => ! empty( $options['include_backlink_brief_in_post'] ),
			'include_faq'           => ! empty( $options['include_faq'] ),
			'recent_titles'         => $recent_titles,
		),
	);

	$response = wp_remote_post(
		SEOAPW_API_URL . '/api/pro/generate',
		array(
			'timeout' => 60,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $payload ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'request_failed',
			sprintf(
				/* translators: %s: HTTP error message */
				__( 'Pro API request failed: %s', 'seoapw' ),
				$response->get_error_message()
			)
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 402 === $code ) {
		// Insufficient balance — server will not charge; fall back to Ollama.
		seoapw_invalidate_license_transient();
		$top_up_url = isset( $body['top_up_url'] ) ? esc_url_raw( $body['top_up_url'] ) : seoapw_topup_url();
		seoapw_queue_admin_notice( 'zero_balance', array( 'top_up_url' => $top_up_url ) );
		return new WP_Error(
			'insufficient_balance',
			__( 'Insufficient Pro balance. Falling back to Ollama.', 'seoapw' )
		);
	}

	if ( 403 === $code ) {
		seoapw_invalidate_license_transient();
		return new WP_Error( 'invalid_license', __( 'Pro license is invalid or expired. Falling back to Ollama.', 'seoapw' ) );
	}

	if ( 200 !== $code || empty( $body['success'] ) || empty( $body['article'] ) || ! is_array( $body['article'] ) ) {
		return new WP_Error( 'generation_failed', __( 'Pro generation failed. Falling back to Ollama.', 'seoapw' ) );
	}

	// Cache balance info for post-meta storage and invalidate license status so
	// the next admin page load reflects the updated balance.
	$balance = is_array( $body['balance'] ) ? $body['balance'] : array();
	set_transient(
		'seoapw_last_pro_balance',
		array(
			'remaining_usd'        => (float) ( $balance['remaining_usd'] ?? 0 ),
			'low_balance'          => ! empty( $balance['low_balance'] ),
			'auto_topup_triggered' => ! empty( $balance['auto_topup_triggered'] ),
		),
		5 * MINUTE_IN_SECONDS
	);

	seoapw_invalidate_license_transient();

	// Queue contextual admin notices.
	if ( ! empty( $balance['auto_topup_triggered'] ) ) {
		seoapw_queue_admin_notice(
			'auto_topup',
			array(
				'amount'  => (float) ( $balance['auto_topup_amount_usd'] ?? 5.0 ),
				'balance' => (float) ( $balance['remaining_usd'] ?? 0 ),
			)
		);
	} elseif ( ! empty( $balance['low_balance'] ) ) {
		seoapw_queue_admin_notice(
			'low_balance_post',
			array( 'balance' => (float) ( $balance['remaining_usd'] ?? 0 ) )
		);
	}

	return $body['article'];
}

/**
 * Save Pro-specific post meta after a Pro article has been inserted.
 *
 * @param int   $post_id Post ID.
 * @param array $article Article data returned from the server.
 * @param array $options Merged plugin options.
 * @return void
 */
function seoapw_save_pro_post_meta( int $post_id, array $article, array $options ): void {
	update_post_meta( $post_id, '_seoapw_pro_generated_at', current_time( 'mysql' ) );

	$pro_balance = get_transient( 'seoapw_last_pro_balance' );
	if ( is_array( $pro_balance ) ) {
		update_post_meta( $post_id, '_seoapw_balance_after', (float) ( $pro_balance['remaining_usd'] ?? 0 ) );
	}

	if ( ! empty( $options['insert_internal_links'] ) && ! empty( $article['internal_link_suggestions'] ) ) {
		update_post_meta( $post_id, '_seoapw_internal_links', wp_json_encode( $article['internal_link_suggestions'] ) );
	}
}

/**
 * Build the top-up URL with the license key pre-filled.
 *
 * @return string URL.
 */
function seoapw_topup_url(): string {
	$key = get_option( 'seoapw_license_key', '' );
	return add_query_arg( 'key', rawurlencode( $key ), SEOAPW_API_URL . '/topup' );
}

/**
 * Queue an admin notice to be displayed on the next admin page load.
 *
 * Stores up to 10 notices in the seoapw_queued_notices option.
 *
 * @param string $type    Notice type identifier.
 * @param array  $context Additional context data.
 * @return void
 */
function seoapw_queue_admin_notice( string $type, array $context = array() ): void {
	$notices   = get_option( 'seoapw_queued_notices', array() );
	$notices[] = array(
		'type'    => sanitize_key( $type ),
		'context' => $context,
		'time'    => time(),
	);
	update_option( 'seoapw_queued_notices', array_slice( $notices, -10 ), false );
}
