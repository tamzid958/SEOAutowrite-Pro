<?php
/**
 * Orchestrates featured image handling based on image_mode setting.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASAW_Image {

	/** @var ASAW_Image_Provider_Interface */
	private $provider;

	/** @var array */
	private $options;

	/**
	 * @param ASAW_Image_Provider_Interface $provider
	 * @param array                         $options
	 */
	public function __construct( ASAW_Image_Provider_Interface $provider, array $options ) {
		$this->provider = $provider;
		$this->options  = $options;
	}

	/**
	 * Handle the featured image for a post based on image_mode.
	 *
	 * @param int    $post_id
	 * @param string $image_prompt
	 * @return true|WP_Error
	 */
	public function handle( $post_id, $image_prompt ) {
		$mode = $this->options['image_mode'] ?? 'disabled';

		if ( 'disabled' === $mode ) {
			return true;
		}

		// Always store the prompt in meta.
		update_post_meta( $post_id, '_asaw_image_prompt', sanitize_text_field( $image_prompt ) );

		if ( 'prompt_only' === $mode ) {
			ASAW_Logger::info( "Image prompt stored in meta for post {$post_id} (prompt_only mode)." );
			return true;
		}

		if ( 'generate' === $mode ) {
			return $this->generate_and_attach( $post_id, $image_prompt );
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Generate an image via the provider and set it as the post thumbnail.
	 *
	 * @param int    $post_id
	 * @param string $image_prompt
	 * @return true|WP_Error
	 */
	private function generate_and_attach( $post_id, $image_prompt ) {
		$image_url = $this->provider->generate_image( $image_prompt, $this->options );

		if ( is_wp_error( $image_url ) ) {
			ASAW_Logger::error( 'Image generation failed.', array( 'error' => $image_url->get_error_message() ) );
			return $image_url;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			ASAW_Logger::error( 'Failed to sideload image.', array( 'error' => $attachment_id->get_error_message() ) );
			return $attachment_id;
		}

		set_post_thumbnail( $post_id, $attachment_id );
		ASAW_Logger::info( "Featured image set for post {$post_id} (attachment ID: {$attachment_id})." );

		return true;
	}
}
