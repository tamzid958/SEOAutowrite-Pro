<?php
/**
 * Main plugin bootstrap — singleton, activation, deactivation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOAPW_Plugin {

	/** @var SEOAPW_Plugin|null */
	private static $instance = null;

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

	/**
	 * Return the single instance, creating it if necessary.
	 *
	 * @return SEOAPW_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	private function init() {
		// Reschedule cron whenever the option is updated so changes take effect
		// with the newly saved values (sanitize_options passes the new options
		// directly, but this also covers any direct update_option() calls).
		add_action( 'update_option_seoapw_options', array( $this, 'on_options_updated' ) );

		$settings = new SEOAPW_Settings();
		$settings->init();

		$cron = new SEOAPW_Cron();
		$cron->init();
	}

	// -------------------------------------------------------------------------
	// Activation / deactivation
	// -------------------------------------------------------------------------

	/**
	 * Runs on plugin activation.
	 * Schedules the cron event if the plugin is enabled.
	 */
	public static function activate() {
		$cron = new SEOAPW_Cron();
		$cron->init();
		$cron->schedule();
	}

	/**
	 * Runs on plugin deactivation.
	 * Removes the cron event so no further runs occur while the plugin is off.
	 */
	public static function deactivate() {
		$cron = new SEOAPW_Cron();
		$cron->unschedule();
	}

	// -------------------------------------------------------------------------
	// Hooks
	// -------------------------------------------------------------------------

	/**
	 * Triggered after seoapw_options is saved; reschedule with the new values.
	 */
	public function on_options_updated() {
		$cron = new SEOAPW_Cron();
		$cron->schedule();
	}
}
