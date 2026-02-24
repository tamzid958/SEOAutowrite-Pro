<?php
/**
 * Contract for all image generation providers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ASAW_Image_Provider_Interface {

	/**
	 * Generate an image from a text prompt.
	 *
	 * @param string $prompt  The image generation prompt.
	 * @param array  $options Provider-specific options (api key, model, etc.).
	 * @return string|WP_Error URL of the generated image, or WP_Error on failure.
	 */
	public function generate_image( $prompt, array $options );
}
