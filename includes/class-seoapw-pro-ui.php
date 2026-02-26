<?php
/**
 * Pro UI: balance indicator, admin notices, and License settings card.
 *
 * @package SEOAutowrite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOAPW_Pro_UI {

	const ACTIVATE_NONCE   = 'seoapw_activate_license';
	const DEACTIVATE_NONCE = 'seoapw_deactivate_license';
	const DISMISS_NONCE    = 'seoapw_dismiss_notice';

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	public static function init(): void {
		add_action( 'seoapw_admin_header_after_title', array( __CLASS__, 'render_balance_indicator' ) );
		add_action( 'admin_notices',                   array( __CLASS__, 'render_queued_notices' ) );
		add_action( 'seoapw_before_settings_form',     array( __CLASS__, 'render_license_card' ) );
		add_action( 'wp_ajax_seoapw_activate_license',   array( __CLASS__, 'ajax_activate_license' ) );
		add_action( 'wp_ajax_seoapw_deactivate_license', array( __CLASS__, 'ajax_deactivate_license' ) );
		add_action( 'wp_ajax_seoapw_dismiss_notice',     array( __CLASS__, 'ajax_dismiss_notice' ) );
		add_action( 'admin_enqueue_scripts',             array( __CLASS__, 'enqueue_assets' ) );
	}

	// -------------------------------------------------------------------------
	// Asset enqueueing
	// -------------------------------------------------------------------------

	public static function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_seoautowrite-pro' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'seoapw-pro',
			ASAW_PLUGIN_URL . 'assets/css/seoapw-pro.css',
			array(),
			ASAW_VERSION . '.' . filemtime( ASAW_PLUGIN_DIR . 'assets/css/seoapw-pro.css' )
		);

		wp_enqueue_script(
			'seoapw-pro',
			ASAW_PLUGIN_URL . 'assets/js/seoapw-pro.js',
			array( 'jquery' ),
			ASAW_VERSION,
			true
		);

		wp_localize_script( 'seoapw-pro', 'seoapwPro', array(
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'activateNonce'    => wp_create_nonce( self::ACTIVATE_NONCE ),
			'deactivateNonce'  => wp_create_nonce( self::DEACTIVATE_NONCE ),
			'dismissNonce'     => wp_create_nonce( self::DISMISS_NONCE ),
			'topUpUrl'         => seoapw_topup_url(),
			'lsPortalUrl'      => esc_url( defined( 'SEOAPW_LS_PORTAL_URL' ) ? SEOAPW_LS_PORTAL_URL : '#' ),
			'strings'          => array(
				'activating'   => __( 'Activating…', 'seoapw' ),
				'deactivating' => __( 'Deactivating…', 'seoapw' ),
				'activated'    => __( 'Pro Active', 'seoapw' ),
				'error'        => __( 'An error occurred. Please try again.', 'seoapw' ),
				'confirmDeact' => __( 'Deactivate your Pro license on this site?', 'seoapw' ),
			),
		) );
	}

	// -------------------------------------------------------------------------
	// Balance indicator (shown in admin header on plugin pages)
	// -------------------------------------------------------------------------

	public static function render_balance_indicator(): void {
		if ( ! seoapw_is_pro() ) {
			return;
		}

		$license  = seoapw_get_license();
		$balance  = (float) ( $license['balance_usd'] ?? 0 );
		$articles = (int) ( $license['articles_remaining'] ?? 0 );
		$low      = ! empty( $license['low_balance'] );
		$top_up   = seoapw_topup_url();

		if ( $balance <= 0 ) {
			$class = 'seoapw-balance seoapw-balance--empty';
			$icon  = '🔴';
			$label = sprintf(
				/* translators: %s: formatted balance */
				__( 'Balance: %s · Running on Ollama', 'seoapw' ),
				'$0.00'
			);
		} elseif ( $low ) {
			$class = 'seoapw-balance seoapw-balance--low';
			$icon  = '⚠️';
			$label = sprintf(
				/* translators: 1: balance, 2: article count */
				__( 'Balance: %1$s · %2$d article remaining', 'seoapw' ),
				'$' . number_format( $balance, 2 ),
				$articles
			);
		} else {
			$class = 'seoapw-balance seoapw-balance--ok';
			$icon  = '💳';
			$label = sprintf(
				/* translators: 1: balance, 2: article count */
				__( 'Balance: %1$s · %2$d articles remaining', 'seoapw' ),
				'$' . number_format( $balance, 2 ),
				$articles
			);
		}
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<span class="seoapw-balance__icon"><?php echo esc_html( $icon ); ?></span>
			<span class="seoapw-balance__label"><?php echo esc_html( $label ); ?></span>
			<a href="<?php echo esc_url( $top_up ); ?>" target="_blank" class="seoapw-balance__topup">
				<?php echo $low || $balance <= 0 ? esc_html__( 'Top Up Now', 'seoapw' ) : esc_html__( 'Top Up', 'seoapw' ); ?>
			</a>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Queued admin notices
	// -------------------------------------------------------------------------

	public static function render_queued_notices(): void {
		// Only show on plugin pages.
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_seoautowrite-pro' !== $screen->id ) {
			return;
		}

		$notices   = get_option( 'seoapw_queued_notices', array() );
		$dismissed = get_option( 'seoapw_dismissed_notices', array() );

		if ( empty( $notices ) ) {
			return;
		}

		$remaining = array();

		foreach ( $notices as $notice ) {
			$type    = $notice['type'] ?? '';
			$context = $notice['context'] ?? array();
			$id      = $type . '_' . ( $notice['time'] ?? 0 );

			if ( in_array( $id, $dismissed, true ) ) {
				continue;
			}

			$message = self::get_notice_message( $type, $context );
			if ( $message ) {
				$css_class = in_array( $type, array( 'zero_balance' ), true ) ? 'notice-error' : 'notice-warning';
				?>
				<div class="notice <?php echo esc_attr( $css_class ); ?> is-dismissible seoapw-notice"
				     data-notice-id="<?php echo esc_attr( $id ); ?>">
					<p><?php echo wp_kses_post( $message ); ?></p>
				</div>
				<?php
			}

			// Keep notices that aren't too old (24 hours).
			if ( time() - ( $notice['time'] ?? 0 ) < DAY_IN_SECONDS ) {
				$remaining[] = $notice;
			}
		}

		update_option( 'seoapw_queued_notices', $remaining, false );
	}

	/**
	 * Build the human-readable message for a given notice type.
	 *
	 * @param string $type    Notice type.
	 * @param array  $context Context data.
	 * @return string HTML message or empty string.
	 */
	private static function get_notice_message( string $type, array $context ): string {
		$top_up_url = isset( $context['top_up_url'] ) ? esc_url( $context['top_up_url'] ) : esc_url( seoapw_topup_url() );

		switch ( $type ) {
			case 'zero_balance':
				return sprintf(
					/* translators: %s: top-up URL */
					__( '🔴 <strong>SEOAutowrite Pro:</strong> Your balance is $0.00. This article was generated by Ollama. <a href="%s" target="_blank">Top Up Now</a> to resume Claude-powered generation.', 'seoapw' ),
					$top_up_url
				);

			case 'low_balance_post':
				$balance = isset( $context['balance'] ) ? '$' . number_format( (float) $context['balance'], 2 ) : '';
				return sprintf(
					/* translators: 1: balance, 2: top-up URL */
					__( '⚠️ <strong>SEOAutowrite Pro:</strong> Pro article published. Balance is low (%1$s remaining). <a href="%2$s" target="_blank">Top Up Now</a>', 'seoapw' ),
					esc_html( $balance ),
					$top_up_url
				);

			case 'auto_topup':
				$amount  = isset( $context['amount'] ) ? '$' . number_format( (float) $context['amount'], 2 ) : '';
				$balance = isset( $context['balance'] ) ? '$' . number_format( (float) $context['balance'], 2 ) : '';
				return sprintf(
					/* translators: 1: charged amount, 2: new balance */
					__( '💳 <strong>SEOAutowrite Pro:</strong> Balance was low. %1$s auto top-up charged to your saved payment method. New balance: %2$s.', 'seoapw' ),
					esc_html( $amount ),
					esc_html( $balance )
				);
		}

		return '';
	}

	// -------------------------------------------------------------------------
	// License settings card
	// -------------------------------------------------------------------------

	public static function render_license_card(): void {
		$license       = seoapw_get_license();
		$is_pro        = seoapw_is_pro();
		$stored_key    = get_option( 'seoapw_license_key', '' );
		$masked_key    = $stored_key ? substr( $stored_key, 0, 4 ) . str_repeat( '•', max( 0, strlen( $stored_key ) - 8 ) ) . substr( $stored_key, -4 ) : '';
		$balance       = (float) ( $license['balance_usd'] ?? 0 );
		$articles_rem  = (int) ( $license['articles_remaining'] ?? 0 );
		$auto_topup    = ! empty( $license['auto_topup_enabled'] );
		$topup_amount  = (float) ( $license['auto_topup_amount_usd'] ?? 0 );
		$top_up_url    = seoapw_topup_url();
		?>
		<section class="asaw-card seoapw-license-card">
			<h2 class="asaw-card-title"><?php esc_html_e( 'Pro License', 'seoapw' ); ?></h2>

			<?php if ( $is_pro ) : ?>

				<!-- Status: Active -->
				<div class="asaw-row">
					<div class="asaw-label"><?php esc_html_e( 'Status', 'seoapw' ); ?></div>
					<div class="asaw-control">
						<span class="seoapw-badge seoapw-badge--active">
							<?php esc_html_e( '✅ Pro Active', 'seoapw' ); ?>
						</span>
						<span class="seoapw-license-key-display">
							<?php echo esc_html( $masked_key ); ?>
						</span>
					</div>
				</div>

				<!-- Balance -->
				<div class="asaw-row">
					<div class="asaw-label"><?php esc_html_e( 'Balance', 'seoapw' ); ?></div>
					<div class="asaw-control">
						<strong>$<?php echo esc_html( number_format( $balance, 2 ) ); ?></strong>
						<span class="seoapw-articles-rem">
							&mdash; <?php echo esc_html( sprintf( _n( '%d article remaining', '%d articles remaining', $articles_rem, 'seoapw' ), $articles_rem ) ); ?>
						</span>
						<a href="<?php echo esc_url( $top_up_url ); ?>" target="_blank" class="asaw-btn seoapw-btn-topup" style="margin-left:12px;">
							<?php esc_html_e( 'Top Up Balance', 'seoapw' ); ?>
						</a>
					</div>
				</div>

				<!-- Auto top-up -->
				<div class="asaw-row">
					<div class="asaw-label"><?php esc_html_e( 'Auto Top-Up', 'seoapw' ); ?></div>
					<div class="asaw-control">
						<?php if ( $auto_topup ) : ?>
							<span class="seoapw-badge seoapw-badge--active">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: dollar amount */
										__( 'Enabled ($%s)', 'seoapw' ),
										number_format( $topup_amount, 2 )
									)
								);
								?>
							</span>
						<?php else : ?>
							<span class="seoapw-badge seoapw-badge--inactive"><?php esc_html_e( 'Disabled', 'seoapw' ); ?></span>
						<?php endif; ?>
						<a href="<?php echo esc_url( defined( 'SEOAPW_LS_PORTAL_URL' ) ? SEOAPW_LS_PORTAL_URL : '#' ); ?>"
						   target="_blank" class="seoapw-manage-billing-link">
							<?php esc_html_e( 'Manage Billing', 'seoapw' ); ?>
						</a>
					</div>
				</div>

				<!-- Deactivate -->
				<div class="asaw-row">
					<div class="asaw-label"></div>
					<div class="asaw-control">
						<button type="button" id="seoapw-deactivate-btn" class="seoapw-btn-link seoapw-btn-deactivate">
							<?php esc_html_e( 'Deactivate license on this site', 'seoapw' ); ?>
						</button>
						<span id="seoapw-deactivate-status" class="asaw-btn-status"></span>
					</div>
				</div>

			<?php else : ?>

				<!-- Status: No license -->
				<div class="asaw-row">
					<div class="asaw-label"><?php esc_html_e( 'Status', 'seoapw' ); ?></div>
					<div class="asaw-control">
						<span class="seoapw-badge seoapw-badge--inactive">
							<?php esc_html_e( '⚠️ No Active License', 'seoapw' ); ?>
						</span>
					</div>
				</div>

				<!-- License key input -->
				<div class="asaw-row">
					<label class="asaw-label" for="seoapw-license-key-input">
						<?php esc_html_e( 'License Key', 'seoapw' ); ?>
					</label>
					<div class="asaw-control">
						<div class="asaw-model-row">
							<input type="text" id="seoapw-license-key-input" class="asaw-input asaw-mono"
							       placeholder="XXXX-XXXX-XXXX-XXXX"
							       value="<?php echo esc_attr( $stored_key ); ?>"
							       autocomplete="off">
							<button type="button" id="seoapw-activate-btn" class="asaw-btn">
								<?php esc_html_e( 'Activate', 'seoapw' ); ?>
							</button>
						</div>
						<span id="seoapw-activate-status" class="asaw-btn-status"></span>
						<p class="asaw-desc">
							<?php esc_html_e( 'Enter your Lemon Squeezy license key to enable Pro features.', 'seoapw' ); ?>
						</p>
					</div>
				</div>

			<?php endif; ?>
		</section>
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX — Activate License
	// -------------------------------------------------------------------------

	public static function ajax_activate_license(): void {
		check_ajax_referer( self::ACTIVATE_NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'seoapw' ) ) );
		}

		$key = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
		if ( empty( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a license key.', 'seoapw' ) ) );
		}

		seoapw_invalidate_license_transient();
		$result = seoapw_validate_license( $key );

		if ( empty( $result['valid'] ) ) {
			$reason = ! empty( $result['reason'] ) ? $result['reason'] : __( 'invalid_key', 'seoapw' );
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: error reason */
					__( '❌ Invalid Key (%s)', 'seoapw' ),
					esc_html( $reason )
				),
			) );
		}

		wp_send_json_success( array(
			'message'      => __( '✅ Pro Active', 'seoapw' ),
			'balance_usd'  => number_format( (float) ( $result['balance_usd'] ?? 0 ), 2 ),
			'articles_rem' => (int) ( $result['articles_remaining'] ?? 0 ),
		) );
	}

	// -------------------------------------------------------------------------
	// AJAX — Deactivate License
	// -------------------------------------------------------------------------

	public static function ajax_deactivate_license(): void {
		check_ajax_referer( self::DEACTIVATE_NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'seoapw' ) ) );
		}

		seoapw_deactivate_license();

		wp_send_json_success( array(
			'message' => __( 'License deactivated. The plugin will now use Ollama for generation.', 'seoapw' ),
		) );
	}

	// -------------------------------------------------------------------------
	// AJAX — Dismiss Notice
	// -------------------------------------------------------------------------

	public static function ajax_dismiss_notice(): void {
		check_ajax_referer( self::DISMISS_NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$notice_id = sanitize_text_field( wp_unslash( $_POST['notice_id'] ?? '' ) );
		if ( empty( $notice_id ) ) {
			wp_send_json_error();
		}

		$dismissed   = get_option( 'seoapw_dismissed_notices', array() );
		$dismissed[] = $notice_id;
		update_option( 'seoapw_dismissed_notices', array_slice( array_unique( $dismissed ), -50 ), false );

		wp_send_json_success();
	}
}

SEOAPW_Pro_UI::init();
