<?php
/**
 * Ollama API provider — implements ASAW_Provider_Interface.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASAW_Ollama_Provider implements ASAW_Provider_Interface {

	/** How long to cache the model list (seconds). */
	const MODELS_TRANSIENT_TTL = HOUR_IN_SECONDS;

	/** @var array Plugin options. */
	private $options;

	/**
	 * @param array $options Full plugin options array.
	 */
	public function __construct( array $options ) {
		$this->options = $options;
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 *
	 * Tries the configured model first, then falls back to every other model
	 * returned by /api/tags until one succeeds or all are exhausted.
	 */
	public function generate_article( array $payload ) {
		$category_name        = $payload['category_name']        ?? '';
		$category_description = $payload['category_description'] ?? '';
		$recent_titles        = $payload['recent_titles']        ?? array();

		$prompt = ASAW_Utils::build_prompt( $category_name, $category_description, $this->options, $recent_titles );
		$models = $this->build_model_queue();

		if ( empty( $models ) ) {
			return new WP_Error( 'no_models', __( 'No Ollama models are available.', 'seoautowrite-pro' ) );
		}

		$last_error = new WP_Error( 'no_models', __( 'No Ollama models are available.', 'seoautowrite-pro' ) );

		foreach ( $models as $model ) {
			$result = $this->try_generate_with_model( $prompt, $model );

			if ( ! is_wp_error( $result ) ) {
				ASAW_Logger::info( "Article generated successfully with model: {$model}." );
				return $result;
			}

			$last_error = $result;
			ASAW_Logger::info(
				"Model {$model} failed; trying next fallback.",
				array( 'reason' => $result->get_error_message() )
			);
		}

		ASAW_Logger::error( 'All models failed.', array( 'last_error' => $last_error->get_error_message() ) );
		return $last_error;
	}

	/**
	 * Fetch locally installed models from the Ollama instance via GET /api/tags.
	 * Results are cached in a transient for one hour.
	 *
	 * @return string[]|WP_Error Array of model name strings, or WP_Error on failure.
	 */
	public function fetch_available_models() {
		$tags_url  = $this->get_tags_url();
		$cache_key = 'asaw_local_models_' . md5( $tags_url );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		ASAW_Logger::debug( 'Fetching local Ollama model list.', array( 'url' => $tags_url ) );

		$api_key  = sanitize_text_field( $this->options['ollama_api_key'] ?? '' );
		$response = wp_remote_get( $tags_url, array(
			'timeout'    => 15,
			'user-agent' => 'ASAW/' . ASAW_VERSION . '; ' . get_site_url(),
			'headers'    => array_filter( array(
				'Accept'        => 'application/json',
				'Authorization' => $api_key ? 'Bearer ' . $api_key : '',
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			ASAW_Logger::error( 'Failed to fetch local Ollama model list.', array( 'error' => $response->get_error_message() ) );
			return $response;
		}

		$code      = wp_remote_retrieve_response_code( $response );
		$body_text = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			$msg = sprintf(
				/* translators: %d is the HTTP status code */
				__( 'Ollama /api/tags returned HTTP %d.', 'seoautowrite-pro' ),
				$code
			);
			ASAW_Logger::error( $msg, array( 'body' => substr( $body_text, 0, 300 ) ) );
			return new WP_Error( 'ollama_tags_http_error', $msg );
		}

		$data = json_decode( $body_text, true );

		// /api/tags returns {"models":[{"name":"llama3.3:latest",...},...]}
		if ( ! is_array( $data ) || empty( $data['models'] ) ) {
			$msg = __( 'Could not parse model list from Ollama /api/tags.', 'seoautowrite-pro' );
			ASAW_Logger::error( $msg, array( 'body' => substr( $body_text, 0, 300 ) ) );
			return new WP_Error( 'ollama_tags_parse_error', $msg );
		}

		$models = array_values( array_filter( array_map( static function ( $m ) {
			return $m['name'] ?? ( $m['model'] ?? null );
		}, $data['models'] ) ) );

		set_transient( $cache_key, $models, self::MODELS_TRANSIENT_TTL );

		ASAW_Logger::info(
			'Fetched local Ollama model list.',
			array( 'count' => count( $models ), 'models' => $models )
		);

		return $models;
	}

	/**
	 * Derive the /api/tags URL from the configured generate endpoint.
	 * e.g. http://localhost:11434/api/generate → http://localhost:11434/api/tags
	 *
	 * @return string
	 */
	private function get_tags_url() {
		$endpoint = esc_url_raw( $this->options['ollama_endpoint'] ?? 'http://localhost:11434/api/generate' );
		$parsed   = wp_parse_url( $endpoint );
		$scheme   = $parsed['scheme'] ?? 'http';
		$host     = $parsed['host']   ?? 'localhost';
		$port     = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
		return "{$scheme}://{$host}{$port}/api/tags";
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the ordered list of models to try.
	 * The configured model is always first; every other available model follows as a fallback.
	 *
	 * @return string[]
	 */
	private function build_model_queue() {
		$configured = sanitize_text_field( $this->options['ollama_model'] ?? '' );
		$queue      = $configured ? array( $configured ) : array();

		$available = $this->fetch_available_models();
		if ( is_array( $available ) ) {
			foreach ( $available as $model ) {
				if ( ! in_array( $model, $queue, true ) ) {
					$queue[] = $model;
				}
			}
		}

		return $queue;
	}

	/**
	 * Attempt article generation with one specific model, including one repair retry.
	 *
	 * @param string $prompt
	 * @param string $model
	 * @return array|WP_Error Parsed article array, or WP_Error on failure.
	 */
	private function try_generate_with_model( $prompt, $model ) {
		// --- First attempt ---
		$raw = $this->call_ollama_raw( $prompt, $model );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$article    = $this->parse_model_output( $raw );
		$validation = is_array( $article ) ? ASAW_Utils::validate_schema( $article ) : null;

		if ( ! is_array( $article ) || is_wp_error( $validation ) ) {
			$reason = is_wp_error( $validation ) ? $validation->get_error_message() : 'Output is not valid JSON.';
			ASAW_Logger::info(
				"Model {$model}: first attempt invalid; retrying with repair prompt.",
				array( 'reason' => $reason )
			);

			// --- Repair attempt ---
			$repair_prompt = ASAW_Utils::repair_prompt( $raw );
			$raw2          = $this->call_ollama_raw( $repair_prompt, $model );
			if ( is_wp_error( $raw2 ) ) {
				return $raw2;
			}

			$article    = $this->parse_model_output( $raw2 );
			$validation = is_array( $article ) ? ASAW_Utils::validate_schema( $article ) : null;

			if ( ! is_array( $article ) || is_wp_error( $validation ) ) {
				$reason = is_wp_error( $validation )
					? $validation->get_error_message()
					: 'Could not parse model output as JSON after repair.';
				ASAW_Logger::error( "Model {$model}: invalid JSON after repair.", array( 'reason' => $reason ) );
				return new WP_Error( 'invalid_json_after_repair', $reason );
			}
		}

		return $article;
	}

	/**
	 * POST to Ollama and return the raw `response` string from the API envelope.
	 *
	 * @param string $prompt
	 * @param string $model Model name to send in the request body.
	 * @return string|WP_Error
	 */
	private function call_ollama_raw( $prompt, $model ) {
		$endpoint = esc_url_raw( $this->options['ollama_endpoint'] ?? 'https://ollama.com/api/generate' );
		$api_key  = sanitize_text_field( $this->options['ollama_api_key'] ?? '' );
		$timeout  = max( 10, intval( $this->options['ollama_timeout_seconds'] ?? 60 ) );

		$body = wp_json_encode( array(
			'model'  => $model,
			'prompt' => $prompt,
			'stream' => false,
		) );

		$args = array(
			'timeout'     => $timeout,
			'redirection' => 3,
			'user-agent'  => 'ASAW/' . ASAW_VERSION . '; ' . get_site_url(),
			'headers'     => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body' => $body,
		);

		ASAW_Logger::debug( 'Sending request to Ollama.', array( 'endpoint' => $endpoint, 'model' => $model ) );

		$response = wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			ASAW_Logger::error( 'Ollama HTTP request failed.', array( 'error' => $response->get_error_message() ) );
			return $response;
		}

		$code      = wp_remote_retrieve_response_code( $response );
		$body_text = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			$msg = sprintf(
				/* translators: %d is the HTTP status code */
				__( 'Ollama API returned HTTP %d.', 'seoautowrite-pro' ),
				$code
			);
			ASAW_Logger::error( $msg, array( 'body' => substr( $body_text, 0, 500 ) ) );
			return new WP_Error( 'ollama_http_error', $msg );
		}

		$api_data = json_decode( $body_text, true );

		if ( ! is_array( $api_data ) || ! isset( $api_data['response'] ) ) {
			$msg = __( 'Ollama API response is missing the "response" field.', 'seoautowrite-pro' );
			ASAW_Logger::error( $msg, array( 'body' => substr( $body_text, 0, 500 ) ) );
			return new WP_Error( 'ollama_parse_error', $msg );
		}

		ASAW_Logger::debug( 'Received Ollama response.', array( 'length' => strlen( $api_data['response'] ) ) );

		return $api_data['response'];
	}

	/**
	 * Try to extract a JSON object from the model's raw text output.
	 *
	 * @param string $raw
	 * @return array|null
	 */
	private function parse_model_output( $raw ) {
		$data = json_decode( $raw, true );
		if ( is_array( $data ) ) {
			return $data;
		}

		// Handle leading/trailing text around the JSON object.
		if ( preg_match( '/\{[\s\S]*\}/U', $raw, $matches ) ) {
			$data = json_decode( $matches[0], true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}

		return null;
	}
}
