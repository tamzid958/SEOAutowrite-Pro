<?php
/**
 * Core article generation orchestrator — follows the 14-step flow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOAPW_Generator {

	const LOCK_TRANSIENT = 'seoapw_generation_lock';
	const LOCK_DURATION  = 600; // 10 minutes in seconds.

	/** @var array */
	private $options;

	/**
	 * @param array $options Full merged plugin options.
	 */
	public function __construct( array $options ) {
		$this->options = $options;
	}

	// -------------------------------------------------------------------------
	// Public entry point
	// -------------------------------------------------------------------------

	/**
	 * Run the generation flow with locking and error handling.
	 */
	public function run() {
		// Step 1 — Guard checks.
		if ( empty( $this->options['enabled'] ) ) {
			SEOAPW_Logger::info( 'Generator skipped: plugin is not enabled.' );
			return;
		}

		// Pro users with sufficient balance do not need Ollama configured — the
		// remote server handles generation. Ollama config is only required when
		// the free tier (or Pro fallback) will actually call the local API.
		$is_pro_ready = function_exists( 'seoapw_is_pro' )
			&& seoapw_is_pro()
			&& function_exists( 'seoapw_has_sufficient_balance' )
			&& seoapw_has_sufficient_balance();

		if ( ! $is_pro_ready ) {
			if (
				empty( $this->options['ollama_api_key'] ) ||
				empty( $this->options['ollama_endpoint'] ) ||
				empty( $this->options['ollama_model'] )
			) {
				SEOAPW_Logger::error( 'Generator aborted: Ollama API key, endpoint, or model is not configured.' );
				return;
			}
		}

		// Step 2 — Acquire concurrency lock.
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			SEOAPW_Logger::info( 'Generator skipped: another generation run is already in progress.' );
			return;
		}

		set_transient( self::LOCK_TRANSIENT, true, self::LOCK_DURATION );

		try {
			$this->generate();
		} catch ( Exception $e ) {
			SEOAPW_Logger::error( 'Unexpected exception during generation.', array( 'message' => $e->getMessage() ) );
		}

		// Step 14 — Release lock.
		delete_transient( self::LOCK_TRANSIENT );
	}

	// -------------------------------------------------------------------------
	// Private generation pipeline
	// -------------------------------------------------------------------------

	private function generate() {
		$options = $this->options;

		// Step 3 — Choose category.
		$categories = isset( $options['categories'] ) ? array_filter( array_map( 'intval', (array) $options['categories'] ) ) : array();

		if ( empty( $categories ) ) {
			SEOAPW_Logger::error( 'Generator aborted: no categories configured.' );
			return;
		}

		$categories = array_values( $categories ); // Re-index after filter.
		$strategy   = $options['category_strategy'] ?? 'rotate';
		$cat_id     = $this->choose_category( $categories, $strategy );

		if ( ! $cat_id ) {
			SEOAPW_Logger::error( 'Generator aborted: could not select a valid category.' );
			return;
		}

		// Step 4 — Get category name + description.
		$category = get_term( $cat_id, 'category' );

		if ( is_wp_error( $category ) || ! $category ) {
			SEOAPW_Logger::error( "Generator aborted: category ID {$cat_id} not found." );
			return;
		}

		$cat_name = $category->name;
		$cat_desc = ! empty( $category->description ) ? $category->description : "Articles related to {$cat_name}.";

		SEOAPW_Logger::info( "Starting generation for category: {$cat_name} (ID: {$cat_id})." );

		// Fetch recent titles to avoid duplicate content.
		$recent_titles = $this->get_recent_titles( $cat_id );

		if ( ! empty( $recent_titles ) ) {
			SEOAPW_Logger::info( 'Recent titles fetched for deduplication.', array( 'titles' => $recent_titles ) );
		}

		// Pro generation check — skip Ollama entirely when Pro is active with
		// sufficient balance. Falls back to Ollama silently on any failure.
		$article              = null;
		$is_pro_article       = false;
		$pro_featured_img_url = null;

		if (
			function_exists( 'seoapw_is_pro' ) && seoapw_is_pro() &&
			function_exists( 'seoapw_has_sufficient_balance' ) && seoapw_has_sufficient_balance()
		) {
			SEOAPW_Logger::info( 'Pro license detected — attempting Pro generation.' );
			$pro_result = seoapw_generate_pro( $options, $category );

			if ( is_wp_error( $pro_result ) ) {
				SEOAPW_Logger::info( 'Pro generation failed, falling back to Ollama: ' . $pro_result->get_error_message() );
			} else {
				$article              = $pro_result['article'];
				$pro_featured_img_url = $pro_result['featured_image_url'] ?? null;
				$is_pro_article       = true;
				SEOAPW_Logger::info( 'Pro generation succeeded.' );
			}
		}

		if ( null === $article ) {
			// Steps 5–8 — Build prompt, call Ollama, parse, validate (all inside provider).
			$provider = new SEOAPW_Ollama_Provider( $options );
			$article  = $provider->generate_article( array(
				'category_name'        => $cat_name,
				'category_description' => $cat_desc,
				'recent_titles'        => $recent_titles,
			) );
		}

		if ( is_wp_error( $article ) ) {
			SEOAPW_Logger::error( 'Article generation failed: ' . $article->get_error_message() );
			if ( 'draft' === ( $options['on_invalid_json'] ?? 'abort' ) ) {
				$this->create_minimal_draft( $cat_id, $article->get_error_message() );
			}
			return;
		}

		// Step 9 — Create the post.
		$post_id = $this->create_post( $article, $cat_id );

		if ( is_wp_error( $post_id ) ) {
			SEOAPW_Logger::error( 'wp_insert_post failed: ' . $post_id->get_error_message() );
			return;
		}

		SEOAPW_Logger::info( "Post created. ID: {$post_id}." );

		// Step 10 — Store internal link suggestions.
		if ( ! empty( $options['insert_internal_links'] ) && ! empty( $article['internal_link_suggestions'] ) ) {
			update_post_meta( $post_id, '_seoapw_internal_link_suggestions', wp_json_encode( $article['internal_link_suggestions'] ) );
		}

		// Pro-specific post meta (generated_at timestamp, balance after, Pro internal links).
		if ( $is_pro_article && function_exists( 'seoapw_save_pro_post_meta' ) ) {
			seoapw_save_pro_post_meta( $post_id, $article, $options );
		}

		// Step 11 — Featured image.
		if ( $is_pro_article && ! empty( $pro_featured_img_url ) ) {
			// Server already generated the image — sideload it directly.
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$attachment_id = media_sideload_image( $pro_featured_img_url, $post_id, null, 'id' );

			if ( is_wp_error( $attachment_id ) ) {
				SEOAPW_Logger::error( 'Pro image sideload failed (non-fatal): ' . $attachment_id->get_error_message() );
			} else {
				set_post_thumbnail( $post_id, $attachment_id );
				SEOAPW_Logger::info( "Pro featured image sideloaded for post {$post_id} (attachment ID: {$attachment_id})." );
			}
		} else {
			// Free tier or Pro without server-side image — use local image provider.
			$image_provider = $this->resolve_image_provider();
			$image_handler  = new SEOAPW_Image( $image_provider, $options );
			$image_result   = $image_handler->handle( $post_id, $article['featured_image_prompt'] ?? '' );

			if ( is_wp_error( $image_result ) ) {
				SEOAPW_Logger::error( 'Image handling error (non-fatal): ' . $image_result->get_error_message() );
			}
		}

		// Step 12 — Store post meta.
		$this->store_post_meta( $post_id, $article, $cat_id );

		// Step 13 — Log summary.
		SEOAPW_Logger::info( 'Article generation complete.', array(
			'post_id'  => $post_id,
			'title'    => $article['title'] ?? '',
			'category' => $cat_name,
		) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Select which category to use next.
	 *
	 * @param int[]  $categories
	 * @param string $strategy   rotate|random
	 * @return int
	 */
	private function choose_category( array $categories, $strategy ) {
		if ( 'random' === $strategy ) {
			return $categories[ array_rand( $categories ) ];
		}

		// Rotate: cycle through categories in order using stored category ID.
		$last_cat_id  = intval( get_option( 'seoapw_last_category_id', 0 ) );
		$last_index   = array_search( $last_cat_id, $categories, true );
		$next_index   = ( false === $last_index ) ? 0 : ( $last_index + 1 ) % count( $categories );
		$next_cat_id  = $categories[ $next_index ];
		update_option( 'seoapw_last_category_id', $next_cat_id, false );

		return $next_cat_id;
	}

	/**
	 * Retrieve the titles of the last 10 posts in a given category.
	 * Includes publish, draft, future, and pending statuses.
	 *
	 * @param int $cat_id
	 * @return string[]
	 */
	private function get_recent_titles( $cat_id ) {
		$posts = get_posts( array(
			'category'       => intval( $cat_id ),
			'posts_per_page' => 10,
			'post_status'    => array( 'publish', 'draft', 'future', 'pending' ),
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		return array_map( 'get_the_title', $posts );
	}

	/**
	 * Create the WordPress post from the structured article data.
	 *
	 * @param array $article
	 * @param int   $cat_id
	 * @return int|WP_Error Post ID or WP_Error.
	 */
	private function create_post( array $article, $cat_id ) {
		$title   = sanitize_text_field( $article['title']   ?? 'Auto-generated Article' );
		$slug    = sanitize_title( $article['slug']         ?? $title );
		$excerpt = wp_kses_post( $article['excerpt']        ?? '' );
		$content = wp_kses_post( $article['content_html']   ?? '' );
		$content = $this->normalize_content_html( $content, $title );

		// Optionally append backlink brief to content.
		if ( ! empty( $this->options['include_backlink_brief_in_post'] ) && ! empty( $article['backlink_brief'] ) ) {
			$content .= $this->format_backlink_brief( $article['backlink_brief'] );
		}

		$tags = isset( $article['tags'] ) && is_array( $article['tags'] )
			? array_map( 'sanitize_text_field', $article['tags'] )
			: array();

		$post_data = array(
			'post_title'    => $title,
			'post_name'     => $slug,
			'post_excerpt'  => $excerpt,
			'post_content'  => $content,
			'post_status'   => sanitize_key( $this->options['post_status'] ?? 'draft' ),
			'post_author'   => intval( $this->options['author_id'] ?? 1 ),
			'post_category' => array( intval( $cat_id ) ),
			'tags_input'    => $tags,
		);

		return wp_insert_post( $post_data, true );
	}

	/**
	 * Remove duplicated top title markers from generated HTML body.
	 *
	 * @param string $content
	 * @param string $title
	 * @return string
	 */
	private function normalize_content_html( $content, $title ) {
		$content = trim( (string) $content );
		$title   = trim( (string) $title );

		if ( '' === $content || '' === $title ) {
			return $content;
		}

		$quoted_title = preg_quote( $title, '/' );

		// Remove a leading heading that duplicates post title.
		$content = preg_replace( '/^\s*<h[1-2][^>]*>\s*' . $quoted_title . '\s*<\/h[1-2]>\s*/iu', '', $content, 1 );

		// Remove a leading paragraph that only repeats the title.
		$content = preg_replace( '/^\s*<p[^>]*>\s*' . $quoted_title . '\s*<\/p>\s*/iu', '', $content, 1 );

		// Remove a raw leading text line that repeats title.
		$content = preg_replace( '/^\s*' . $quoted_title . '\s*/iu', '', $content, 1 );

		return ltrim( $content );
	}

	/**
	 * Save all SEOAPW-specific post meta.
	 *
	 * @param int   $post_id
	 * @param array $article
	 * @param int   $cat_id
	 */
	private function store_post_meta( $post_id, array $article, $cat_id ) {
		update_post_meta( $post_id, '_seoapw_category_id',         intval( $cat_id ) );
		update_post_meta( $post_id, '_seoapw_keywords',            wp_json_encode( $article['keywords']            ?? array() ) );
		update_post_meta( $post_id, '_seoapw_meta_description',    sanitize_text_field( $article['meta_description'] ?? '' ) );
		update_post_meta( $post_id, '_seoapw_meta_title',          sanitize_text_field( $article['meta_title']       ?? '' ) );
		update_post_meta( $post_id, '_seoapw_primary_keyword',     sanitize_text_field( $article['primary_keyword']  ?? '' ) );
		update_post_meta( $post_id, '_seoapw_semantic_keywords',   wp_json_encode( $article['semantic_keywords']   ?? array() ) );
		update_post_meta( $post_id, '_seoapw_backlink_brief',      wp_json_encode( $article['backlink_brief']      ?? array() ) );
		update_post_meta( $post_id, '_seoapw_generated_at',        current_time( 'mysql' ) );
		update_post_meta( $post_id, '_seoapw_generation_prompt_hash',
			md5( ( $article['title'] ?? '' ) . ( $article['meta_description'] ?? '' ) ) );

		if ( ! empty( $article['external_link_suggestions'] ) ) {
			update_post_meta( $post_id, '_seoapw_external_link_suggestions', wp_json_encode( $article['external_link_suggestions'] ) );
		}

		// Best-effort: populate Yoast SEO and RankMath fields.
		$meta_desc       = sanitize_text_field( $article['meta_description'] ?? '' );
		$meta_title      = sanitize_text_field( $article['meta_title']       ?? '' );
		$primary_keyword = sanitize_text_field( $article['primary_keyword']  ?? '' );

		if ( $meta_desc ) {
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_desc );
			update_post_meta( $post_id, 'rank_math_description', $meta_desc );
		}

		if ( $meta_title ) {
			update_post_meta( $post_id, '_yoast_wpseo_title',  $meta_title );
			update_post_meta( $post_id, 'rank_math_title',     $meta_title );
		}

		if ( $primary_keyword ) {
			update_post_meta( $post_id, '_yoast_wpseo_focuskw',      $primary_keyword );
			update_post_meta( $post_id, 'rank_math_focus_keyword',   $primary_keyword );
		}
	}

	/**
	 * Resolve the correct image provider instance based on settings.
	 *
	 * @return SEOAPW_Image_Provider_Interface
	 */
	private function resolve_image_provider() {
		$provider_name = $this->options['image_provider'] ?? 'none';

		switch ( $provider_name ) {
			case 'openai':
				return new SEOAPW_OpenAI_Image_Provider();
			default:
				return new SEOAPW_None_Image_Provider();
		}
	}

	/**
	 * Format the backlink brief as an HTML section to append to the post.
	 *
	 * @param array $brief
	 * @return string HTML
	 */
	private function format_backlink_brief( array $brief ) {
		$html = '<hr /><h2>' . esc_html__( 'Backlink Brief', 'seoautowrite-pro' ) . '</h2>';

		if ( ! empty( $brief['linkable_angles'] ) ) {
			$html .= '<h3>' . esc_html__( 'Linkable Angles', 'seoautowrite-pro' ) . '</h3><ul>';
			foreach ( (array) $brief['linkable_angles'] as $item ) {
				$html .= '<li>' . esc_html( $item ) . '</li>';
			}
			$html .= '</ul>';
		}

		return $html;
	}

	/**
	 * Create a minimal placeholder draft when the generator fails and
	 * on_invalid_json is set to 'draft'.
	 *
	 * @param int    $cat_id
	 * @param string $error_message
	 */
	private function create_minimal_draft( $cat_id, $error_message ) {
		$post_id = wp_insert_post( array(
			'post_title'    => __( 'Auto-generated Article (Generation Failed)', 'seoautowrite-pro' ),
			'post_content'  => '<!-- SEOAPW generation error: ' . esc_html( $error_message ) . ' -->',
			'post_status'   => 'draft',
			'post_author'   => intval( $this->options['author_id'] ?? 1 ),
			'post_category' => array( intval( $cat_id ) ),
		) );

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			SEOAPW_Logger::info( "Created minimal draft post (ID: {$post_id}) after generation failure." );
		}
	}
}
