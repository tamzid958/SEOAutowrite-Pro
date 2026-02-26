<?php
/**
 * Admin settings page and option registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOAPW_Settings {

	const OPTION_KEY        = 'seoapw_options';
	const MENU_SLUG         = 'seoautowrite-pro';
	const RUN_NONCE         = 'seoapw_run_now';
	const FETCH_MODELS_NONCE = 'seoapw_fetch_models';
	const SETTINGS_GROUP    = 'seoapw_settings_group';

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	public function init() {
		add_action( 'admin_menu',                    array( $this, 'add_menu' ) );
		add_action( 'admin_head',                    array( $this, 'output_menu_icon_css' ) );
		add_action( 'admin_init',                    array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts',         array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_seoapw_run_now',        array( $this, 'ajax_run_now' ) );
		add_action( 'wp_ajax_seoapw_fetch_models',   array( $this, 'ajax_fetch_models' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SEOAPW_PLUGIN_FILE ), array( $this, 'add_plugin_action_links' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function add_menu() {
		$menu_icon = SEOAPW_PLUGIN_URL . 'assets/logo.svg';

		add_menu_page(
			__( 'SEOAutowrite Pro', 'seoautowrite-pro' ),
			__( 'SEOAutowrite', 'seoautowrite-pro' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' ),
			$menu_icon,
			5
		);
	}

	/**
	 * Add a "Manage" link to the plugin's row on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function add_plugin_action_links( array $links ) {
		$manage_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ),
			esc_html__( 'Manage', 'seoautowrite-pro' )
		);
		array_unshift( $links, $manage_link );
		return $links;
	}

	/**
	 * Keep the custom top-level menu logo at standard WordPress menu icon size.
	 */
	public function output_menu_icon_css() {
		?>
		<style>
			#adminmenu .toplevel_page_<?php echo esc_attr( self::MENU_SLUG ); ?> .wp-menu-image img {
				width: 20px;
				height: 20px;
				padding-top: 7px;
				object-fit: contain;
			}
		</style>
		<?php
	}

	// -------------------------------------------------------------------------
	// Settings registration & sanitisation
	// -------------------------------------------------------------------------

	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_KEY,
			array( $this, 'sanitize_options' )
		);
	}

	/**
	 * Sanitize and validate all incoming settings values.
	 *
	 * @param array $input Raw POST data.
	 * @return array Sanitized options.
	 */
	public function sanitize_options( $input ) {
		$defaults = SEOAPW_Utils::get_default_options();
		$out      = $defaults;

		// General.
		$out['enabled']        = ! empty( $input['enabled'] );
		$out['on_invalid_json'] = in_array( $input['on_invalid_json'] ?? '', array( 'abort', 'draft' ), true )
			? $input['on_invalid_json'] : 'abort';

		// Ollama API.
		$out['ollama_endpoint']        = esc_url_raw( $input['ollama_endpoint'] ?? $defaults['ollama_endpoint'] );
		$out['ollama_api_key']         = sanitize_text_field( $input['ollama_api_key'] ?? '' );
		$out['ollama_model']           = sanitize_text_field( $input['ollama_model']   ?? $defaults['ollama_model'] );
		$out['ollama_timeout_seconds'] = max( 10, intval( $input['ollama_timeout_seconds'] ?? $defaults['ollama_timeout_seconds'] ) );

		// Schedule.
		$out['schedule_frequency'] = in_array( $input['schedule_frequency'] ?? '', array( 'daily', 'weekly', 'custom' ), true )
			? $input['schedule_frequency'] : 'daily';
		$out['schedule_custom_minutes'] = max( 1, intval( $input['schedule_custom_minutes'] ?? $defaults['schedule_custom_minutes'] ) );
		$out['schedule_time'] = preg_match( '/^\d{2}:\d{2}$/', $input['schedule_time'] ?? '' )
			? sanitize_text_field( $input['schedule_time'] ) : '08:00';

		// Content.
		$out['categories']        = isset( $input['categories'] ) ? array_map( 'intval', (array) $input['categories'] ) : array();
		$out['category_strategy'] = in_array( $input['category_strategy'] ?? '', array( 'rotate', 'random' ), true )
			? $input['category_strategy'] : 'rotate';
		$out['post_status'] = in_array( $input['post_status'] ?? '', array( 'draft', 'publish', 'future' ), true )
			? $input['post_status'] : 'draft';
		$out['author_id']   = intval( $input['author_id'] ?? 1 );
		$out['min_words']   = max( 100, intval( $input['min_words'] ?? $defaults['min_words'] ) );
		$out['max_words']   = max( $out['min_words'], intval( $input['max_words'] ?? $defaults['max_words'] ) );
		$out['tone']        = sanitize_text_field( $input['tone']     ?? $defaults['tone'] );
		$out['language']    = sanitize_text_field( $input['language'] ?? 'en' );
		$out['include_faq'] = ! empty( $input['include_faq'] );

		// Links.
		$out['insert_internal_links']          = ! empty( $input['insert_internal_links'] );
		$out['max_internal_links']             = max( 1, intval( $input['max_internal_links'] ?? $defaults['max_internal_links'] ) );
		$out['include_backlink_brief_in_post'] = ! empty( $input['include_backlink_brief_in_post'] );

		// Image.
		$out['image_mode']     = in_array( $input['image_mode'] ?? '', array( 'disabled', 'prompt_only', 'generate' ), true )
			? $input['image_mode'] : 'disabled';
		$out['image_provider'] = in_array( $input['image_provider'] ?? '', array( 'none', 'openai' ), true )
			? $input['image_provider'] : 'none';
		$out['image_api_key']  = sanitize_text_field( $input['image_api_key'] ?? '' );
		$out['image_model']    = sanitize_text_field( $input['image_model']   ?? 'dall-e-3' );

		// Logging.
		$out['logging_level'] = in_array( $input['logging_level'] ?? '', array( 'error', 'info', 'debug' ), true )
			? $input['logging_level'] : 'info';

		// Custom prompt.
		$out['custom_prompt'] = sanitize_textarea_field( $input['custom_prompt'] ?? '' );

		return $out;
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'seoapw-inter',
			'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'seoapw-admin',
			SEOAPW_PLUGIN_URL . 'assets/admin.css',
			array( 'seoapw-inter' ),
			SEOAPW_VERSION . '.' . filemtime( SEOAPW_PLUGIN_DIR . 'assets/admin.css' )
		);

		wp_enqueue_script(
			'seoapw-admin',
			SEOAPW_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			SEOAPW_VERSION,
			true
		);

		wp_localize_script( 'seoapw-admin', 'seoapwAdmin', array(
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'runNonce'         => wp_create_nonce( self::RUN_NONCE ),
			'fetchModelsNonce' => wp_create_nonce( self::FETCH_MODELS_NONCE ),
			'strings'          => array(
				'running'       => __( 'Running…', 'seoautowrite-pro' ),
				'done'          => __( 'Done! Check the logs below.', 'seoautowrite-pro' ),
				'error'         => __( 'An error occurred.', 'seoautowrite-pro' ),
				'fetchingModels' => __( 'Fetching…', 'seoautowrite-pro' ),
				'useModel'       => __( 'Use', 'seoautowrite-pro' ),
				'noModels'       => __( 'No models found.', 'seoautowrite-pro' ),
			),
		) );
	}

	// -------------------------------------------------------------------------
	// AJAX — Run Now
	// -------------------------------------------------------------------------

	public function ajax_run_now() {
		check_ajax_referer( self::RUN_NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'seoautowrite-pro' ) ) );
		}

		$options             = get_option( self::OPTION_KEY, array() );
		$options             = wp_parse_args( $options, SEOAPW_Utils::get_default_options() );
		$options['enabled']  = true; // Force-enable for manual runs.

		$generator = new SEOAPW_Generator( $options );
		$generator->run();

		wp_send_json_success( array(
			'message' => __( 'Generation complete. See the logs section for details.', 'seoautowrite-pro' ),
		) );
	}

	// -------------------------------------------------------------------------
	// AJAX — Fetch Available Models
	// -------------------------------------------------------------------------

	public function ajax_fetch_models() {
		check_ajax_referer( self::FETCH_MODELS_NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'seoautowrite-pro' ) ) );
		}

		$options  = get_option( self::OPTION_KEY, array() );
		$options  = wp_parse_args( $options, SEOAPW_Utils::get_default_options() );
		$provider = new SEOAPW_Ollama_Provider( $options );
		$models   = $provider->fetch_available_models();

		if ( is_wp_error( $models ) ) {
			wp_send_json_error( array( 'message' => $models->get_error_message() ) );
		}

		wp_send_json_success( array( 'models' => $models ) );
	}

	// -------------------------------------------------------------------------
	// Settings page rendering
	// -------------------------------------------------------------------------

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seoautowrite-pro' ) );
		}

		$options    = get_option( self::OPTION_KEY, array() );
		$opts       = wp_parse_args( $options, SEOAPW_Utils::get_default_options() );
		$logs       = SEOAPW_Logger::get_logs( 10 );
		$categories = get_categories( array( 'hide_empty' => false ) );
		$authors    = get_users( array( 'capability' => 'edit_posts' ) );
		$next_run   = wp_next_scheduled( SEOAPW_Cron::HOOK );
		$opt_name   = self::OPTION_KEY;

		?>
		<div class="wrap seoapw-wrap">

			<!-- Header -->
			<div class="seoapw-header">
				<?php
				/**
				 * Hook: seoapw_admin_header_after_title
				 * Pro UI uses this to render the balance indicator or upsell banner.
				 */
				do_action( 'seoapw_admin_header_after_title' );
				?>
			</div>

			<?php settings_errors( self::SETTINGS_GROUP ); ?>

			<?php
			/**
			 * Hook: seoapw_before_settings_form
			 * Pro UI uses this to render the License card and upgrade modal.
			 */
			do_action( 'seoapw_before_settings_form' );
			?>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<!-- ===================== GENERAL ===================== -->
				<section class="seoapw-card">
					<h2 class="seoapw-card-title"><?php esc_html_e( 'General', 'seoautowrite-pro' ); ?></h2>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Enable Plugin', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<label class="seoapw-check-label">
								<input type="checkbox" name="<?php echo esc_attr( $opt_name ); ?>[enabled]" value="1" <?php checked( $opts['enabled'] ); ?>>
								<?php esc_html_e( 'Enable scheduled article generation', 'seoautowrite-pro' ); ?>
							</label>
						</div>
					</div>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'On Invalid JSON', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<select name="<?php echo esc_attr( $opt_name ); ?>[on_invalid_json]" class="seoapw-select">
								<option value="abort" <?php selected( $opts['on_invalid_json'], 'abort' ); ?>><?php esc_html_e( 'Abort run (default)', 'seoautowrite-pro' ); ?></option>
								<option value="draft" <?php selected( $opts['on_invalid_json'], 'draft' ); ?>><?php esc_html_e( 'Create minimal draft', 'seoautowrite-pro' ); ?></option>
							</select>
							<p class="seoapw-desc"><?php esc_html_e( 'What to do when the model returns invalid JSON after the repair retry.', 'seoautowrite-pro' ); ?></p>
						</div>
					</div>
				</section>

				<!-- ===================== OLLAMA API ===================== -->
				<section class="seoapw-card">
					<h2 class="seoapw-card-title"><?php esc_html_e( 'Ollama API', 'seoautowrite-pro' ); ?></h2>

					<div class="seoapw-row">
						<label class="seoapw-label" for="seoapw-ollama-endpoint"><?php esc_html_e( 'Endpoint', 'seoautowrite-pro' ); ?></label>
						<div class="seoapw-control">
							<input type="url" id="seoapw-ollama-endpoint" class="seoapw-input"
								name="<?php echo esc_attr( $opt_name ); ?>[ollama_endpoint]"
								value="<?php echo esc_attr( $opts['ollama_endpoint'] ); ?>">
						</div>
					</div>

					<div class="seoapw-row">
						<label class="seoapw-label" for="seoapw-ollama-api-key"><?php esc_html_e( 'API Key', 'seoautowrite-pro' ); ?></label>
						<div class="seoapw-control">
							<input type="password" id="seoapw-ollama-api-key" class="seoapw-input"
								name="<?php echo esc_attr( $opt_name ); ?>[ollama_api_key]"
								value="<?php echo esc_attr( $opts['ollama_api_key'] ); ?>"
								autocomplete="new-password">
							<p class="seoapw-hint">
								<?php
								printf(
									/* translators: %s: link to Ollama API keys page */
									esc_html__( 'Get your API key from %s.', 'seoautowrite-pro' ),
									'<a href="https://ollama.com/settings/keys" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Ollama Settings', 'seoautowrite-pro' ) . '</a>'
								);
								?>
							</p>
						</div>
					</div>

					<div class="seoapw-row">
						<label class="seoapw-label" for="seoapw-ollama-model"><?php esc_html_e( 'Model', 'seoautowrite-pro' ); ?></label>
						<div class="seoapw-control">
							<div class="seoapw-model-row">
								<input type="text" id="seoapw-ollama-model" class="seoapw-input seoapw-mono"
									name="<?php echo esc_attr( $opt_name ); ?>[ollama_model]"
									value="<?php echo esc_attr( $opts['ollama_model'] ); ?>">
								<button type="button" id="seoapw-fetch-models" class="seoapw-btn">
									<?php esc_html_e( 'Fetch', 'seoautowrite-pro' ); ?>
								</button>
							</div>
							<span id="seoapw-fetch-models-status" class="seoapw-btn-status"></span>
							<div id="seoapw-models-list" class="seoapw-models-list"></div>
							<p class="seoapw-desc"><?php esc_html_e( 'Primary model for generation. Falls back to other available models if this one fails.', 'seoautowrite-pro' ); ?></p>
						</div>
					</div>

					<div class="seoapw-row">
						<label class="seoapw-label" for="seoapw-timeout"><?php esc_html_e( 'Timeout (seconds)', 'seoautowrite-pro' ); ?></label>
						<div class="seoapw-control">
							<input type="number" id="seoapw-timeout" class="seoapw-input seoapw-small" min="10" max="600"
								name="<?php echo esc_attr( $opt_name ); ?>[ollama_timeout_seconds]"
								value="<?php echo esc_attr( $opts['ollama_timeout_seconds'] ); ?>">
						</div>
					</div>
				</section>

				<!-- ===================== SCHEDULE ===================== -->
				<section class="seoapw-card">
					<h2 class="seoapw-card-title"><?php esc_html_e( 'Schedule', 'seoautowrite-pro' ); ?></h2>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Schedule Window', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<div class="seoapw-grid-2">
								<div class="seoapw-field-group">
									<label for="seoapw-schedule-frequency"><?php esc_html_e( 'Frequency', 'seoautowrite-pro' ); ?></label>
									<select id="seoapw-schedule-frequency" class="seoapw-select"
										name="<?php echo esc_attr( $opt_name ); ?>[schedule_frequency]">
										<option value="daily"  <?php selected( $opts['schedule_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'seoautowrite-pro' ); ?></option>
										<option value="weekly" <?php selected( $opts['schedule_frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'seoautowrite-pro' ); ?></option>
										<option value="custom" <?php selected( $opts['schedule_frequency'], 'custom' ); ?>><?php esc_html_e( 'Custom interval', 'seoautowrite-pro' ); ?></option>
									</select>
								</div>
								<div class="seoapw-field-group">
									<label for="seoapw-schedule-time"><?php esc_html_e( 'Time of Day', 'seoautowrite-pro' ); ?></label>
									<input type="time" id="seoapw-schedule-time" class="seoapw-input"
										name="<?php echo esc_attr( $opt_name ); ?>[schedule_time]"
										value="<?php echo esc_attr( $opts['schedule_time'] ); ?>">
								</div>
							</div>
						</div>
					</div>

					<div class="seoapw-row<?php echo 'custom' !== $opts['schedule_frequency'] ? ' is-hidden' : ''; ?>" id="seoapw-custom-minutes-row">
						<label class="seoapw-label" for="seoapw-custom-minutes"><?php esc_html_e( 'Custom Interval (min)', 'seoautowrite-pro' ); ?></label>
						<div class="seoapw-control">
							<input type="number" id="seoapw-custom-minutes" class="seoapw-input seoapw-small" min="1"
								name="<?php echo esc_attr( $opt_name ); ?>[schedule_custom_minutes]"
								value="<?php echo esc_attr( $opts['schedule_custom_minutes'] ); ?>">
						</div>
					</div>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Next Scheduled Run', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<p class="seoapw-desc" style="margin-top:8px;font-size:13px;color:#334155;">
								<strong>
									<?php
									if ( $next_run ) {
										echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) );
									} else {
										esc_html_e( 'Not scheduled', 'seoautowrite-pro' );
									}
									?>
								</strong>
							</p>
						</div>
					</div>
				</section>

				<!-- ===================== CONTENT ===================== -->
				<section class="seoapw-card">
					<h2 class="seoapw-card-title"><?php esc_html_e( 'Content', 'seoautowrite-pro' ); ?></h2>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Categories', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<select class="seoapw-select" style="min-height:130px;max-width:460px;"
								name="<?php echo esc_attr( $opt_name ); ?>[categories][]" multiple>
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat->term_id ); ?>"
										<?php echo in_array( $cat->term_id, (array) $opts['categories'], true ) ? 'selected' : ''; ?>>
										<?php echo esc_html( $cat->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="seoapw-desc"><?php esc_html_e( 'Hold Ctrl / Cmd to select multiple categories.', 'seoautowrite-pro' ); ?></p>
						</div>
					</div>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Category Strategy', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<select name="<?php echo esc_attr( $opt_name ); ?>[category_strategy]" class="seoapw-select">
								<option value="rotate" <?php selected( $opts['category_strategy'], 'rotate' ); ?>><?php esc_html_e( 'Rotate (in order)', 'seoautowrite-pro' ); ?></option>
								<option value="random" <?php selected( $opts['category_strategy'], 'random' ); ?>><?php esc_html_e( 'Random', 'seoautowrite-pro' ); ?></option>
							</select>
						</div>
					</div>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Writing Profile', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<div class="seoapw-grid-2">
								<div class="seoapw-field-group">
									<label for="seoapw-tone"><?php esc_html_e( 'Tone', 'seoautowrite-pro' ); ?></label>
									<input type="text" id="seoapw-tone" class="seoapw-input"
										name="<?php echo esc_attr( $opt_name ); ?>[tone]"
										value="<?php echo esc_attr( $opts['tone'] ); ?>"
										placeholder="professional">
								</div>
								<div class="seoapw-field-group">
									<label for="seoapw-language"><?php esc_html_e( 'Language', 'seoautowrite-pro' ); ?></label>
									<input type="text" id="seoapw-language" class="seoapw-input"
										name="<?php echo esc_attr( $opt_name ); ?>[language]"
										value="<?php echo esc_attr( $opts['language'] ); ?>"
										placeholder="en">
								</div>
							</div>
							<p class="seoapw-desc"><?php esc_html_e( 'ISO 639-1 language code, e.g. en, fr, de.', 'seoautowrite-pro' ); ?></p>
						</div>
					</div>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Word Range', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<div class="seoapw-grid-2">
								<div class="seoapw-field-group">
									<label for="seoapw-min-words"><?php esc_html_e( 'Min Words', 'seoautowrite-pro' ); ?></label>
									<input type="number" id="seoapw-min-words" class="seoapw-input" min="100"
										name="<?php echo esc_attr( $opt_name ); ?>[min_words]"
										value="<?php echo esc_attr( $opts['min_words'] ); ?>">
								</div>
								<div class="seoapw-field-group">
									<label for="seoapw-max-words"><?php esc_html_e( 'Max Words', 'seoautowrite-pro' ); ?></label>
									<input type="number" id="seoapw-max-words" class="seoapw-input" min="100"
										name="<?php echo esc_attr( $opt_name ); ?>[max_words]"
										value="<?php echo esc_attr( $opts['max_words'] ); ?>">
								</div>
							</div>
						</div>
					</div>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Include FAQ', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<label class="seoapw-check-label">
								<input type="checkbox" name="<?php echo esc_attr( $opt_name ); ?>[include_faq]" value="1" <?php checked( $opts['include_faq'] ); ?>>
								<?php esc_html_e( 'Add a FAQ section to each article', 'seoautowrite-pro' ); ?>
							</label>
						</div>
					</div>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Publishing', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<div class="seoapw-grid-2">
								<div class="seoapw-field-group">
									<label for="seoapw-post-status"><?php esc_html_e( 'Post Status', 'seoautowrite-pro' ); ?></label>
									<select id="seoapw-post-status" class="seoapw-select" name="<?php echo esc_attr( $opt_name ); ?>[post_status]">
										<option value="draft"   <?php selected( $opts['post_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'seoautowrite-pro' ); ?></option>
										<option value="publish" <?php selected( $opts['post_status'], 'publish' ); ?>><?php esc_html_e( 'Published', 'seoautowrite-pro' ); ?></option>
										<option value="future"  <?php selected( $opts['post_status'], 'future' ); ?>><?php esc_html_e( 'Scheduled (future)', 'seoautowrite-pro' ); ?></option>
									</select>
								</div>
								<div class="seoapw-field-group">
									<label for="seoapw-author-id"><?php esc_html_e( 'Author', 'seoautowrite-pro' ); ?></label>
									<select id="seoapw-author-id" class="seoapw-select" name="<?php echo esc_attr( $opt_name ); ?>[author_id]">
										<?php foreach ( $authors as $user ) : ?>
											<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $opts['author_id'], $user->ID ); ?>>
												<?php echo esc_html( $user->display_name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>
						</div>
					</div>
				</section>

				<!-- ===================== LINKS & BACKLINKS ===================== -->
				<section class="seoapw-card">
					<h2 class="seoapw-card-title"><?php esc_html_e( 'Links &amp; Backlinks', 'seoautowrite-pro' ); ?></h2>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Internal Links', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<label class="seoapw-check-label">
								<input type="checkbox" name="<?php echo esc_attr( $opt_name ); ?>[insert_internal_links]" value="1" <?php checked( $opts['insert_internal_links'] ); ?>>
								<?php esc_html_e( 'Store internal link suggestions in post meta', 'seoautowrite-pro' ); ?>
							</label>
						</div>
					</div>

					<div class="seoapw-row">
						<label class="seoapw-label" for="seoapw-max-links"><?php esc_html_e( 'Max Internal Links', 'seoautowrite-pro' ); ?></label>
						<div class="seoapw-control">
							<input type="number" id="seoapw-max-links" class="seoapw-input seoapw-small" min="1" max="20"
								name="<?php echo esc_attr( $opt_name ); ?>[max_internal_links]"
								value="<?php echo esc_attr( $opts['max_internal_links'] ); ?>">
						</div>
					</div>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Backlink Brief', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<label class="seoapw-check-label">
								<input type="checkbox" name="<?php echo esc_attr( $opt_name ); ?>[include_backlink_brief_in_post]" value="1" <?php checked( $opts['include_backlink_brief_in_post'] ); ?>>
								<?php esc_html_e( 'Add the backlink brief as an HTML section at the end of each post', 'seoautowrite-pro' ); ?>
							</label>
						</div>
					</div>
				</section>

				<!-- ===================== FEATURED IMAGE ===================== -->
				<section class="seoapw-card">
					<h2 class="seoapw-card-title"><?php esc_html_e( 'Featured Image', 'seoautowrite-pro' ); ?></h2>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Image Mode', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<select id="seoapw-image-mode" class="seoapw-select" name="<?php echo esc_attr( $opt_name ); ?>[image_mode]">
								<option value="disabled"    <?php selected( $opts['image_mode'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'seoautowrite-pro' ); ?></option>
								<option value="prompt_only" <?php selected( $opts['image_mode'], 'prompt_only' ); ?>><?php esc_html_e( 'Prompt only (store in meta)', 'seoautowrite-pro' ); ?></option>
								<option value="generate"    <?php selected( $opts['image_mode'], 'generate' ); ?>><?php esc_html_e( 'Generate &amp; set as featured image', 'seoautowrite-pro' ); ?></option>
							</select>
						</div>
					</div>

					<div class="seoapw-row seoapw-image-generate-row<?php echo 'generate' !== $opts['image_mode'] ? ' is-hidden' : ''; ?>">
						<div class="seoapw-label"><?php esc_html_e( 'Image Provider', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<select class="seoapw-select" name="<?php echo esc_attr( $opt_name ); ?>[image_provider]">
								<option value="none"   <?php selected( $opts['image_provider'], 'none' ); ?>><?php esc_html_e( 'None', 'seoautowrite-pro' ); ?></option>
								<option value="openai" <?php selected( $opts['image_provider'], 'openai' ); ?>><?php esc_html_e( 'OpenAI DALL-E', 'seoautowrite-pro' ); ?></option>
							</select>
						</div>
					</div>

					<div class="seoapw-row seoapw-image-generate-row<?php echo 'generate' !== $opts['image_mode'] ? ' is-hidden' : ''; ?>">
						<label class="seoapw-label" for="seoapw-image-api-key"><?php esc_html_e( 'Image API Key', 'seoautowrite-pro' ); ?></label>
						<div class="seoapw-control">
							<input type="password" id="seoapw-image-api-key" class="seoapw-input"
								name="<?php echo esc_attr( $opt_name ); ?>[image_api_key]"
								value="<?php echo esc_attr( $opts['image_api_key'] ); ?>"
								autocomplete="new-password">
						</div>
					</div>

					<div class="seoapw-row seoapw-image-generate-row<?php echo 'generate' !== $opts['image_mode'] ? ' is-hidden' : ''; ?>">
						<label class="seoapw-label" for="seoapw-image-model"><?php esc_html_e( 'Image Model', 'seoautowrite-pro' ); ?></label>
						<div class="seoapw-control">
							<input type="text" id="seoapw-image-model" class="seoapw-input"
								name="<?php echo esc_attr( $opt_name ); ?>[image_model]"
								value="<?php echo esc_attr( $opts['image_model'] ); ?>"
								placeholder="dall-e-3">
						</div>
					</div>
				</section>

				<!-- ===================== LOGGING ===================== -->
				<section class="seoapw-card">
					<h2 class="seoapw-card-title"><?php esc_html_e( 'Logging', 'seoautowrite-pro' ); ?></h2>

					<div class="seoapw-row">
						<div class="seoapw-label"><?php esc_html_e( 'Logging Level', 'seoautowrite-pro' ); ?></div>
						<div class="seoapw-control">
							<select class="seoapw-select" name="<?php echo esc_attr( $opt_name ); ?>[logging_level]">
								<option value="error" <?php selected( $opts['logging_level'], 'error' ); ?>><?php esc_html_e( 'Error only', 'seoautowrite-pro' ); ?></option>
								<option value="info"  <?php selected( $opts['logging_level'], 'info' ); ?>><?php esc_html_e( 'Info (recommended)', 'seoautowrite-pro' ); ?></option>
								<option value="debug" <?php selected( $opts['logging_level'], 'debug' ); ?>><?php esc_html_e( 'Debug (verbose)', 'seoautowrite-pro' ); ?></option>
							</select>
						</div>
					</div>
				</section>

				<!-- ===================== CUSTOM PROMPT ===================== -->
				<section class="seoapw-card">
					<h2 class="seoapw-card-title"><?php esc_html_e( 'Custom Prompt', 'seoautowrite-pro' ); ?></h2>

					<textarea id="seoapw-custom-prompt" class="seoapw-textarea"
						name="<?php echo esc_attr( $opt_name ); ?>[custom_prompt]"
						rows="18"
					><?php echo esc_textarea( $opts['custom_prompt'] ); ?></textarea>
					<p class="seoapw-desc" style="margin-top:10px;">
						<?php esc_html_e( 'Leave blank to use the built-in prompt. Available placeholders:', 'seoautowrite-pro' ); ?>
						<code>{category_name}</code>, <code>{category_description}</code>, <code>{selected_topic}</code>,
						<code>{min_words}</code>, <code>{max_words}</code>, <code>{tone}</code>, <code>{language}</code>,
						<code>{faq_line}</code>, <code>{schema}</code>, <code>{existing_content}</code>
					</p>
				</section>

				<div class="seoapw-submit-row">
					<button type="submit" class="seoapw-save-btn">
						<?php esc_html_e( 'Save Settings', 'seoautowrite-pro' ); ?>
					</button>
				</div>

			</form>

			<!-- ===================== LOGS + RUN NOW ===================== -->
			<div class="seoapw-action-panel">
				<div class="seoapw-action-panel-header">
					<h2><?php esc_html_e( 'Recent Logs', 'seoautowrite-pro' ); ?></h2>
					<div class="seoapw-run-area">
						<button id="seoapw-run-now">
							&#9654; <?php esc_html_e( 'Run Now', 'seoautowrite-pro' ); ?>
						</button>
						<span id="seoapw-run-now-status"></span>
					</div>
				</div>

				<div class="seoapw-logs-wrap">
					<div class="seoapw-logs-header">
						<span><?php esc_html_e( 'Time', 'seoautowrite-pro' ); ?></span>
						<span><?php esc_html_e( 'Level', 'seoautowrite-pro' ); ?></span>
						<span><?php esc_html_e( 'Message', 'seoautowrite-pro' ); ?></span>
					</div>

					<?php if ( empty( $logs ) ) : ?>
						<div class="seoapw-no-logs"><?php esc_html_e( 'No log entries yet.', 'seoautowrite-pro' ); ?></div>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<div class="seoapw-log-row">
								<span class="seoapw-log-time"><?php echo esc_html( $log['time'] ); ?></span>
								<span>
									<span class="seoapw-level-badge seoapw-level-<?php echo esc_attr( $log['level'] ); ?>">
										<?php echo esc_html( strtoupper( $log['level'] ) ); ?>
									</span>
								</span>
								<span class="seoapw-log-msg">
									<?php echo esc_html( $log['message'] ); ?>
									<?php if ( ! empty( $log['context'] ) ) : ?>
										<pre class="seoapw-context"><?php echo esc_html( wp_json_encode( $log['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
									<?php endif; ?>
								</span>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- .seoapw-wrap -->
		<?php
	}
}
