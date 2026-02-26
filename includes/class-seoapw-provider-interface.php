<?php
/**
 * Contract for all LLM content providers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SEOAPW_Provider_Interface {

	/**
	 * Generate a full article.
	 *
	 * @param array $payload {
	 *     @type string $category_name        The category name.
	 *     @type string $category_description The category description used as context.
	 * }
	 * @return array|WP_Error Structured article data matching the schema, or WP_Error on failure.
	 */
	public function generate_article( array $payload );
}
