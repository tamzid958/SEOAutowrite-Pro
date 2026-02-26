<?php
/**
 * Upsell banner and upgrade modal for free-tier users.
 *
 * @package SEOAutowrite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOAPW_Upsell {

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	public static function init(): void {
		add_action( 'seoapw_admin_header_after_title', array( __CLASS__, 'render_upsell_banner' ) );
		add_action( 'seoapw_before_settings_form',     array( __CLASS__, 'render_upgrade_modal' ) );
	}

	// -------------------------------------------------------------------------
	// Upsell banner (shown in header when no Pro license)
	// -------------------------------------------------------------------------

	public static function render_upsell_banner(): void {
		if ( seoapw_is_pro() ) {
			return;
		}

		$buy_url = 'https://seoautowrite.pro/get-started';
		?>
		<div class="seoapw-upsell-banner">
			<span class="seoapw-upsell-banner__text">
				<?php esc_html_e( 'You\'re on the free plan. Upgrade to Pro — SEOAutowrite AI articles at $1.50 each. Try 3 articles for $5. Less than a coffee per publish.', 'seoautowrite-pro' ); ?>
			</span>
			<button type="button" id="seoapw-open-upgrade-modal" class="seoapw-upsell-banner__btn">
				<?php esc_html_e( 'Try Pro — $5', 'seoautowrite-pro' ); ?>
			</button>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Upgrade modal
	// -------------------------------------------------------------------------

	public static function render_upgrade_modal(): void {
		if ( seoapw_is_pro() ) {
			return;
		}

		$buy_url = 'https://seoautowrite.pro/get-started';
		?>
		<div id="seoapw-upgrade-modal" class="seoapw-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="seoapw-modal-title">
			<div class="seoapw-modal__backdrop"></div>
			<div class="seoapw-modal__content">

				<button type="button" class="seoapw-modal__close" aria-label="<?php esc_attr_e( 'Close', 'seoautowrite-pro' ); ?>">&times;</button>

				<h2 id="seoapw-modal-title"><?php esc_html_e( 'Upgrade to SEOAutowrite Pro', 'seoautowrite-pro' ); ?></h2>
				<p class="seoapw-modal__subtitle">
					<?php esc_html_e( 'SEOAutowrite AI articles at $1.50 each. Start with $5 — that\'s 3 full articles. No subscription, no lock-in.', 'seoautowrite-pro' ); ?>
				</p>

				<table class="seoapw-comparison">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feature', 'seoautowrite-pro' ); ?></th>
							<th><?php esc_html_e( 'Free (Ollama)', 'seoautowrite-pro' ); ?></th>
							<th class="seoapw-comparison__pro"><?php esc_html_e( 'Pro (SEOAutowrite AI)', 'seoautowrite-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Article quality', 'seoautowrite-pro' ); ?></td>
							<td><?php esc_html_e( 'Good', 'seoautowrite-pro' ); ?></td>
							<td class="seoapw-comparison__pro"><?php esc_html_e( 'World-class', 'seoautowrite-pro' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'AI model', 'seoautowrite-pro' ); ?></td>
							<td><?php esc_html_e( 'Local Ollama model', 'seoautowrite-pro' ); ?></td>
							<td class="seoapw-comparison__pro"><?php esc_html_e( 'SEOAutowrite AI', 'seoautowrite-pro' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'E-E-A-T optimisation', 'seoautowrite-pro' ); ?></td>
							<td><?php esc_html_e( 'Basic', 'seoautowrite-pro' ); ?></td>
							<td class="seoapw-comparison__pro"><?php esc_html_e( 'Advanced (Phase 1 analysis)', 'seoautowrite-pro' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Server-side prompt', 'seoautowrite-pro' ); ?></td>
							<td>&mdash;</td>
							<td class="seoapw-comparison__pro"><?php esc_html_e( 'Hidden, optimised by us', 'seoautowrite-pro' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Semantic keyword strategy', 'seoautowrite-pro' ); ?></td>
							<td>&mdash;</td>
							<td class="seoapw-comparison__pro"><?php esc_html_e( '✓ Woven in naturally', 'seoautowrite-pro' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Featured image generation', 'seoautowrite-pro' ); ?></td>
							<td><?php esc_html_e( 'DALL·E (basic prompt)', 'seoautowrite-pro' ); ?></td>
							<td class="seoapw-comparison__pro"><?php esc_html_e( 'Precision image generation', 'seoautowrite-pro' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Auto top-up', 'seoautowrite-pro' ); ?></td>
							<td>&mdash;</td>
							<td class="seoapw-comparison__pro"><?php esc_html_e( '✓ Never miss a scheduled run', 'seoautowrite-pro' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Low balance alerts', 'seoautowrite-pro' ); ?></td>
							<td>&mdash;</td>
							<td class="seoapw-comparison__pro"><?php esc_html_e( '✓ Email alerts', 'seoautowrite-pro' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Cost', 'seoautowrite-pro' ); ?></td>
							<td><?php esc_html_e( 'Free (self-hosted)', 'seoautowrite-pro' ); ?></td>
							<td class="seoapw-comparison__pro"><?php esc_html_e( '$1.50 / article', 'seoautowrite-pro' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Minimum balance', 'seoautowrite-pro' ); ?></td>
							<td>&mdash;</td>
							<td class="seoapw-comparison__pro"><?php esc_html_e( '$5.00 to start', 'seoautowrite-pro' ); ?></td>
						</tr>
					</tbody>
				</table>

				<div class="seoapw-modal__cta">
					<a href="<?php echo esc_url( $buy_url ); ?>" target="_blank" class="seoapw-btn-primary">
						<?php esc_html_e( 'Start with $5 — 3 articles →', 'seoautowrite-pro' ); ?>
					</a>
					<p class="seoapw-modal__cta-note">
						<?php esc_html_e( '$1.50 per article · no subscription · top up any time. License key arrives by email — paste it above to activate.', 'seoautowrite-pro' ); ?>
					</p>
				</div>

			</div>
		</div>
		<?php
	}
}

SEOAPW_Upsell::init();
