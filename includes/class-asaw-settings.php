<?php
/**
 * Admin settings page and option registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASAW_Settings {

	const OPTION_KEY        = 'asaw_options';
	const MENU_SLUG         = 'seoautowrite-pro';
	const RUN_NONCE         = 'asaw_run_now';
	const FETCH_MODELS_NONCE = 'asaw_fetch_models';
	const SETTINGS_GROUP    = 'asaw_settings_group';

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	public function init() {
		add_action( 'admin_menu',                  array( $this, 'add_menu' ) );
		add_action( 'admin_head',                  array( $this, 'output_menu_icon_css' ) );
		add_action( 'admin_init',                  array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts',       array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_asaw_run_now',        array( $this, 'ajax_run_now' ) );
		add_action( 'wp_ajax_asaw_fetch_models',   array( $this, 'ajax_fetch_models' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ASAW_PLUGIN_FILE ), array( $this, 'add_plugin_action_links' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function add_menu() {
		$menu_icon = ASAW_PLUGIN_URL . 'assets/logo.png';

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
		$defaults = ASAW_Utils::get_default_options();
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

		// Reschedule the cron using the NEW (not yet saved) options.
		$cron = new ASAW_Cron();
		$cron->schedule( $out );

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
			'asaw-admin',
			ASAW_PLUGIN_URL . 'assets/admin.css',
			array(),
			ASAW_VERSION
		);

		wp_enqueue_script(
			'asaw-admin',
			ASAW_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			ASAW_VERSION,
			true
		);

		wp_localize_script( 'asaw-admin', 'asawAdmin', array(
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
		$options             = wp_parse_args( $options, ASAW_Utils::get_default_options() );
		$options['enabled']  = true; // Force-enable for manual runs.

		$generator = new ASAW_Generator( $options );
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
		$options  = wp_parse_args( $options, ASAW_Utils::get_default_options() );
		$provider = new ASAW_Ollama_Provider( $options );
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
		$opts       = wp_parse_args( $options, ASAW_Utils::get_default_options() );
		$logs       = ASAW_Logger::get_logs( 10 );
		$categories = get_categories( array( 'hide_empty' => false ) );
		$authors    = get_users( array( 'capability' => 'edit_posts' ) );
		$next_run   = wp_next_scheduled( ASAW_Cron::HOOK );
		$opt_name   = self::OPTION_KEY;

		?>
		<div class="wrap asaw-settings">
			<h1 class="asaw-settings-title">
				<img src="<?php echo esc_url( ASAW_PLUGIN_URL . 'assets/logo.png' ); ?>" alt="<?php esc_attr_e( 'SEOAutowrite Pro Logo', 'seoautowrite-pro' ); ?>" class="asaw-settings-logo">
				<?php esc_html_e( 'SEOAutowrite Pro', 'seoautowrite-pro' ); ?>
			</h1>
			<?php settings_errors( self::SETTINGS_GROUP ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<!-- ===================== GENERAL ===================== -->
				<h2 class="title"><?php esc_html_e( 'General', 'seoautowrite-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Plugin', 'seoautowrite-pro' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt_name ); ?>[enabled]" value="1" <?php checked( $opts['enabled'] ); ?>>
								<?php esc_html_e( 'Enable scheduled article generation', 'seoautowrite-pro' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'On Invalid JSON', 'seoautowrite-pro' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $opt_name ); ?>[on_invalid_json]">
								<option value="abort" <?php selected( $opts['on_invalid_json'], 'abort' ); ?>><?php esc_html_e( 'Abort run (default)', 'seoautowrite-pro' ); ?></option>
								<option value="draft" <?php selected( $opts['on_invalid_json'], 'draft' ); ?>><?php esc_html_e( 'Create minimal draft', 'seoautowrite-pro' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'What to do when the model returns invalid JSON after the repair retry.', 'seoautowrite-pro' ); ?></p>
						</td>
					</tr>
				</table>

				<!-- ===================== OLLAMA API ===================== -->
				<h2 class="title"><?php esc_html_e( 'Ollama API', 'seoautowrite-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="asaw-ollama-endpoint"><?php esc_html_e( 'Ollama Endpoint', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="url" id="asaw-ollama-endpoint" class="regular-text"
								name="<?php echo esc_attr( $opt_name ); ?>[ollama_endpoint]"
								value="<?php echo esc_attr( $opts['ollama_endpoint'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="asaw-ollama-api-key"><?php esc_html_e( 'Ollama API Key', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="password" id="asaw-ollama-api-key" class="regular-text"
								name="<?php echo esc_attr( $opt_name ); ?>[ollama_api_key]"
								value="<?php echo esc_attr( $opts['ollama_api_key'] ); ?>"
								autocomplete="new-password">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="asaw-ollama-model"><?php esc_html_e( 'Model', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="text" id="asaw-ollama-model" class="regular-text"
								name="<?php echo esc_attr( $opt_name ); ?>[ollama_model]"
								value="<?php echo esc_attr( $opts['ollama_model'] ); ?>">
							<button type="button" id="asaw-fetch-models" class="button" style="margin-left:6px;">
								<?php esc_html_e( 'Fetch Available Models', 'seoautowrite-pro' ); ?>
							</button>
							<span id="asaw-fetch-models-status" style="margin-left:8px;"></span>
							<div id="asaw-models-list" style="margin-top:8px;display:none;"></div>
							<p class="description"><?php esc_html_e( 'Primary model for generation. The plugin automatically falls back to other available models if this one fails.', 'seoautowrite-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="asaw-timeout"><?php esc_html_e( 'Request Timeout (seconds)', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="number" id="asaw-timeout" class="small-text" min="10" max="600"
								name="<?php echo esc_attr( $opt_name ); ?>[ollama_timeout_seconds]"
								value="<?php echo esc_attr( $opts['ollama_timeout_seconds'] ); ?>">
						</td>
					</tr>
				</table>

				<!-- ===================== SCHEDULE ===================== -->
				<h2 class="title"><?php esc_html_e( 'Schedule', 'seoautowrite-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="asaw-frequency"><?php esc_html_e( 'Frequency', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<select id="asaw-schedule-frequency" name="<?php echo esc_attr( $opt_name ); ?>[schedule_frequency]">
								<option value="daily"  <?php selected( $opts['schedule_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'seoautowrite-pro' ); ?></option>
								<option value="weekly" <?php selected( $opts['schedule_frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'seoautowrite-pro' ); ?></option>
								<option value="custom" <?php selected( $opts['schedule_frequency'], 'custom' ); ?>><?php esc_html_e( 'Custom interval', 'seoautowrite-pro' ); ?></option>
							</select>
						</td>
					</tr>
					<tr id="asaw-custom-minutes-row" <?php echo 'custom' !== $opts['schedule_frequency'] ? 'style="display:none"' : ''; ?>>
						<th scope="row"><label for="asaw-custom-minutes"><?php esc_html_e( 'Custom Interval (minutes)', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="number" id="asaw-custom-minutes" class="small-text" min="1"
								name="<?php echo esc_attr( $opt_name ); ?>[schedule_custom_minutes]"
								value="<?php echo esc_attr( $opts['schedule_custom_minutes'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="asaw-schedule-time"><?php esc_html_e( 'Time of Day', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="time" id="asaw-schedule-time"
								name="<?php echo esc_attr( $opt_name ); ?>[schedule_time]"
								value="<?php echo esc_attr( $opts['schedule_time'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Next scheduled run:', 'seoautowrite-pro' ); ?>
								<strong>
									<?php
									if ( $next_run ) {
										echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) );
									} else {
										esc_html_e( 'Not scheduled', 'seoautowrite-pro' );
									}
									?>
								</strong>
							</p>
						</td>
					</tr>
				</table>

				<!-- ===================== CONTENT ===================== -->
				<h2 class="title"><?php esc_html_e( 'Content', 'seoautowrite-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Categories', 'seoautowrite-pro' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $opt_name ); ?>[categories][]" multiple size="8" style="min-width:220px">
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat->term_id ); ?>"
										<?php echo in_array( $cat->term_id, (array) $opts['categories'], true ) ? 'selected' : ''; ?>>
										<?php echo esc_html( $cat->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Hold Ctrl / Cmd to select multiple categories.', 'seoautowrite-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Category Strategy', 'seoautowrite-pro' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $opt_name ); ?>[category_strategy]">
								<option value="rotate" <?php selected( $opts['category_strategy'], 'rotate' ); ?>><?php esc_html_e( 'Rotate (in order)', 'seoautowrite-pro' ); ?></option>
								<option value="random" <?php selected( $opts['category_strategy'], 'random' ); ?>><?php esc_html_e( 'Random', 'seoautowrite-pro' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="asaw-tone"><?php esc_html_e( 'Tone', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="text" id="asaw-tone" class="regular-text"
								name="<?php echo esc_attr( $opt_name ); ?>[tone]"
								value="<?php echo esc_attr( $opts['tone'] ); ?>"
								placeholder="professional">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="asaw-language"><?php esc_html_e( 'Language', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="text" id="asaw-language" class="small-text"
								name="<?php echo esc_attr( $opt_name ); ?>[language]"
								value="<?php echo esc_attr( $opts['language'] ); ?>"
								placeholder="en">
							<p class="description"><?php esc_html_e( 'ISO 639-1 language code, e.g. en, fr, de.', 'seoautowrite-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="asaw-min-words"><?php esc_html_e( 'Min Words', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="number" id="asaw-min-words" class="small-text" min="100"
								name="<?php echo esc_attr( $opt_name ); ?>[min_words]"
								value="<?php echo esc_attr( $opts['min_words'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="asaw-max-words"><?php esc_html_e( 'Max Words', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="number" id="asaw-max-words" class="small-text" min="100"
								name="<?php echo esc_attr( $opt_name ); ?>[max_words]"
								value="<?php echo esc_attr( $opts['max_words'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Include FAQ', 'seoautowrite-pro' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt_name ); ?>[include_faq]" value="1" <?php checked( $opts['include_faq'] ); ?>>
								<?php esc_html_e( 'Add a FAQ section to each article', 'seoautowrite-pro' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Post Status', 'seoautowrite-pro' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $opt_name ); ?>[post_status]">
								<option value="draft"   <?php selected( $opts['post_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'seoautowrite-pro' ); ?></option>
								<option value="publish" <?php selected( $opts['post_status'], 'publish' ); ?>><?php esc_html_e( 'Published', 'seoautowrite-pro' ); ?></option>
								<option value="future"  <?php selected( $opts['post_status'], 'future' ); ?>><?php esc_html_e( 'Scheduled (future)', 'seoautowrite-pro' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Author', 'seoautowrite-pro' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $opt_name ); ?>[author_id]">
								<?php foreach ( $authors as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $opts['author_id'], $user->ID ); ?>>
										<?php echo esc_html( $user->display_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<!-- ===================== LINKS & BACKLINKS ===================== -->
				<h2 class="title"><?php esc_html_e( 'Links &amp; Backlinks', 'seoautowrite-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Internal Link Suggestions', 'seoautowrite-pro' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt_name ); ?>[insert_internal_links]" value="1" <?php checked( $opts['insert_internal_links'] ); ?>>
								<?php esc_html_e( 'Store internal link suggestions in post meta', 'seoautowrite-pro' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="asaw-max-links"><?php esc_html_e( 'Max Internal Links', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="number" id="asaw-max-links" class="small-text" min="1" max="20"
								name="<?php echo esc_attr( $opt_name ); ?>[max_internal_links]"
								value="<?php echo esc_attr( $opts['max_internal_links'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Append Backlink Brief to Post', 'seoautowrite-pro' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt_name ); ?>[include_backlink_brief_in_post]" value="1" <?php checked( $opts['include_backlink_brief_in_post'] ); ?>>
								<?php esc_html_e( 'Add the backlink brief as an HTML section at the end of each post', 'seoautowrite-pro' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<!-- ===================== FEATURED IMAGE ===================== -->
				<h2 class="title"><?php esc_html_e( 'Featured Image', 'seoautowrite-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Image Mode', 'seoautowrite-pro' ); ?></th>
						<td>
							<select id="asaw-image-mode" name="<?php echo esc_attr( $opt_name ); ?>[image_mode]">
								<option value="disabled"    <?php selected( $opts['image_mode'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'seoautowrite-pro' ); ?></option>
								<option value="prompt_only" <?php selected( $opts['image_mode'], 'prompt_only' ); ?>><?php esc_html_e( 'Prompt only (store in meta)', 'seoautowrite-pro' ); ?></option>
								<option value="generate"    <?php selected( $opts['image_mode'], 'generate' ); ?>><?php esc_html_e( 'Generate & set as featured image', 'seoautowrite-pro' ); ?></option>
							</select>
						</td>
					</tr>
					<tr class="asaw-image-generate-row" <?php echo 'generate' !== $opts['image_mode'] ? 'style="display:none"' : ''; ?>>
						<th scope="row"><?php esc_html_e( 'Image Provider', 'seoautowrite-pro' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $opt_name ); ?>[image_provider]">
								<option value="none"   <?php selected( $opts['image_provider'], 'none' ); ?>><?php esc_html_e( 'None', 'seoautowrite-pro' ); ?></option>
								<option value="openai" <?php selected( $opts['image_provider'], 'openai' ); ?>><?php esc_html_e( 'OpenAI DALL-E', 'seoautowrite-pro' ); ?></option>
							</select>
						</td>
					</tr>
					<tr class="asaw-image-generate-row" <?php echo 'generate' !== $opts['image_mode'] ? 'style="display:none"' : ''; ?>>
						<th scope="row"><label for="asaw-image-api-key"><?php esc_html_e( 'Image API Key', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="password" id="asaw-image-api-key" class="regular-text"
								name="<?php echo esc_attr( $opt_name ); ?>[image_api_key]"
								value="<?php echo esc_attr( $opts['image_api_key'] ); ?>"
								autocomplete="new-password">
						</td>
					</tr>
					<tr class="asaw-image-generate-row" <?php echo 'generate' !== $opts['image_mode'] ? 'style="display:none"' : ''; ?>>
						<th scope="row"><label for="asaw-image-model"><?php esc_html_e( 'Image Model', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<input type="text" id="asaw-image-model" class="regular-text"
								name="<?php echo esc_attr( $opt_name ); ?>[image_model]"
								value="<?php echo esc_attr( $opts['image_model'] ); ?>"
								placeholder="dall-e-3">
						</td>
					</tr>
				</table>

				<!-- ===================== LOGGING ===================== -->
				<h2 class="title"><?php esc_html_e( 'Logging', 'seoautowrite-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Logging Level', 'seoautowrite-pro' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $opt_name ); ?>[logging_level]">
								<option value="error" <?php selected( $opts['logging_level'], 'error' ); ?>><?php esc_html_e( 'Error only', 'seoautowrite-pro' ); ?></option>
								<option value="info"  <?php selected( $opts['logging_level'], 'info' ); ?>><?php esc_html_e( 'Info (recommended)', 'seoautowrite-pro' ); ?></option>
								<option value="debug" <?php selected( $opts['logging_level'], 'debug' ); ?>><?php esc_html_e( 'Debug (verbose)', 'seoautowrite-pro' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<!-- ===================== CUSTOM PROMPT ===================== -->
				<h2 class="title"><?php esc_html_e( 'Custom Prompt', 'seoautowrite-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="asaw-custom-prompt"><?php esc_html_e( 'Prompt Template', 'seoautowrite-pro' ); ?></label></th>
						<td>
							<textarea id="asaw-custom-prompt" name="<?php echo esc_attr( $opt_name ); ?>[custom_prompt]"
								rows="20" class="large-text code"
								style="font-family:monospace;white-space:pre;"
							><?php echo esc_textarea( $opts['custom_prompt'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Leave blank to use the built-in prompt. Write your own prompt and use these placeholders — they are replaced automatically at generation time:', 'seoautowrite-pro' ); ?>
								<br><code>{category_name}</code> &mdash; <?php esc_html_e( 'category name', 'seoautowrite-pro' ); ?><br>
								<code>{category_description}</code> &mdash; <?php esc_html_e( 'category description', 'seoautowrite-pro' ); ?><br>
								<code>{min_words}</code> &mdash; <?php esc_html_e( 'minimum word count', 'seoautowrite-pro' ); ?><br>
								<code>{max_words}</code> &mdash; <?php esc_html_e( 'maximum word count', 'seoautowrite-pro' ); ?><br>
								<code>{tone}</code> &mdash; <?php esc_html_e( 'writing tone', 'seoautowrite-pro' ); ?><br>
								<code>{language}</code> &mdash; <?php esc_html_e( 'language code (e.g. en)', 'seoautowrite-pro' ); ?><br>
								<code>{faq_line}</code> &mdash; <?php esc_html_e( 'FAQ instruction (based on Include FAQ setting)', 'seoautowrite-pro' ); ?><br>
								<code>{schema}</code> &mdash; <?php esc_html_e( 'required JSON output schema', 'seoautowrite-pro' ); ?><br>
								<code>{existing_content}</code> &mdash; <?php esc_html_e( 'recent titles block (for deduplication)', 'seoautowrite-pro' ); ?>
							</p>
						</td>
					</tr>
				</table>

			<?php submit_button( __( 'Save Settings', 'seoautowrite-pro' ) ); ?>
			</form>

			<!-- ===================== MANUAL RUN ===================== -->
			<hr>
			<h2><?php esc_html_e( 'Manual Run', 'seoautowrite-pro' ); ?></h2>
			<p><?php esc_html_e( 'Trigger the generator immediately. This ignores the "Enable Plugin" toggle and uses the currently saved settings.', 'seoautowrite-pro' ); ?></p>
			<button id="asaw-run-now" class="button button-primary">
				<?php esc_html_e( 'Run Now', 'seoautowrite-pro' ); ?>
			</button>
			<span id="asaw-run-now-status" style="margin-left:12px;"></span>

			<!-- ===================== LOGS ===================== -->
			<hr>
			<h2><?php esc_html_e( 'Recent Logs (last 10)', 'seoautowrite-pro' ); ?></h2>

			<?php if ( empty( $logs ) ) : ?>
				<p><?php esc_html_e( 'No log entries yet.', 'seoautowrite-pro' ); ?></p>
			<?php else : ?>
				<table class="widefat asaw-logs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'seoautowrite-pro' ); ?></th>
							<th><?php esc_html_e( 'Level', 'seoautowrite-pro' ); ?></th>
							<th><?php esc_html_e( 'Message', 'seoautowrite-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr class="asaw-log-<?php echo esc_attr( $log['level'] ); ?>">
								<td><?php echo esc_html( $log['time'] ); ?></td>
								<td><span class="asaw-level-badge"><?php echo esc_html( strtoupper( $log['level'] ) ); ?></span></td>
								<td>
									<?php echo esc_html( $log['message'] ); ?>
									<?php if ( ! empty( $log['context'] ) ) : ?>
										<pre class="asaw-context"><?php echo esc_html( wp_json_encode( $log['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

		</div><!-- .asaw-settings -->
		<?php
	}
}
