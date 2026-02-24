<?php
/**
 * Null image provider — used when image generation is disabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASAW_None_Image_Provider implements ASAW_Image_Provider_Interface {

	public function generate_image( $prompt, array $options ) {
		return new WP_Error( 'no_image_provider', __( 'No image provider configured.', 'seoautowrite-pro' ) );
	}
}
