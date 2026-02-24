<?php
/**
 * OpenAI DALL-E image provider.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASAW_OpenAI_Image_Provider implements ASAW_Image_Provider_Interface {

	public function generate_image( $prompt, array $options ) {
		$api_key = sanitize_text_field( $options['image_api_key'] ?? '' );
		$model   = sanitize_text_field( $options['image_model']   ?? 'dall-e-3' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'OpenAI image API key is not configured.', 'seoautowrite-pro' ) );
		}

		$body = wp_json_encode( array(
			'model'           => $model,
			'prompt'          => $prompt,
			'n'               => 1,
			'size'            => '1024x1024',
			'response_format' => 'url',
		) );

		$response = wp_remote_post( 'https://api.openai.com/v1/images/generations', array(
			'timeout' => 120,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body' => $body,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code      = wp_remote_retrieve_response_code( $response );
		$body_text = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body_text, true );

		if ( 200 !== $code || empty( $data['data'][0]['url'] ) ) {
			$err = isset( $data['error']['message'] ) ? $data['error']['message'] : $body_text;
			return new WP_Error( 'openai_image_error', sprintf( __( 'OpenAI image API error (HTTP %d): %s', 'seoautowrite-pro' ), $code, $err ) );
		}

		return $data['data'][0]['url'];
	}
}
