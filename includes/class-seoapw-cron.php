<?php
/**
 * WP-Cron scheduling manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOAPW_Cron {

	const HOOK = 'seoapw_generate_scheduled_post';

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	public function init() {
		add_action( self::HOOK, array( $this, 'run_generation' ) );
		add_filter( 'cron_schedules', array( $this, 'add_custom_schedules' ) );
	}

	// -------------------------------------------------------------------------
	// Cron callback
	// -------------------------------------------------------------------------

	public function run_generation() {
		$options   = get_option( 'seoapw_options', array() );
		$options   = wp_parse_args( $options, SEOAPW_Utils::get_default_options() );
		$generator = new SEOAPW_Generator( $options );
		$generator->run();
	}

	// -------------------------------------------------------------------------
	// Custom schedule intervals
	// -------------------------------------------------------------------------

	/**
	 * Register the 'seoapw_custom' schedule interval.
	 *
	 * @param array $schedules
	 * @return array
	 */
	public function add_custom_schedules( $schedules ) {
		$options        = get_option( 'seoapw_options', array() );
		$custom_minutes = max( 1, intval( $options['schedule_custom_minutes'] ?? 1440 ) );

		$schedules['seoapw_custom'] = array(
			'interval' => $custom_minutes * MINUTE_IN_SECONDS,
			/* translators: %d is the number of minutes */
			'display'  => sprintf( __( 'Every %d minutes (SEOAPW)', 'seoautowrite-pro' ), $custom_minutes ),
		);

		return $schedules;
	}

	// -------------------------------------------------------------------------
	// Schedule / unschedule
	// -------------------------------------------------------------------------

	/**
	 * Schedule or reschedule the cron event.
	 * Accepts optional $options so it can be called before the option is saved.
	 *
	 * @param array|null $options If null, reads from the database.
	 */
	public function schedule( $options = null ) {
		// Clear any existing schedule first.
		wp_clear_scheduled_hook( self::HOOK );

		if ( null === $options ) {
			$options = get_option( 'seoapw_options', array() );
			$options = wp_parse_args( $options, SEOAPW_Utils::get_default_options() );
		}

		if ( empty( $options['enabled'] ) ) {
			return;
		}

		$recurrence = $this->get_recurrence( $options );
		$timestamp  = $this->get_next_run_timestamp( $options['schedule_time'] ?? '08:00' );

		wp_schedule_event( $timestamp, $recurrence, self::HOOK );

		SEOAPW_Logger::info( 'Cron scheduled.', array(
			'recurrence' => $recurrence,
			'first_run'  => gmdate( 'Y-m-d H:i:s', $timestamp ),
		) );
	}

	/**
	 * Remove the scheduled cron event.
	 */
	public function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Map schedule_frequency to a WP cron recurrence string.
	 *
	 * @param array $options
	 * @return string
	 */
	private function get_recurrence( array $options ) {
		switch ( $options['schedule_frequency'] ?? 'daily' ) {
			case 'weekly': return 'weekly';
			case 'custom': return 'seoapw_custom';
			default:       return 'daily';
		}
	}

	/**
	 * Calculate the Unix timestamp for the next occurrence of a given HH:MM time.
	 * Uses the WordPress site timezone.
	 *
	 * @param string $time_string HH:MM
	 * @return int Unix timestamp
	 */
	private function get_next_run_timestamp( $time_string ) {
		list( $hours, $minutes ) = array_map( 'intval', explode( ':', $time_string . ':00' ) );

		$timezone = wp_timezone();
		$now      = new DateTime( 'now', $timezone );
		$target   = clone $now;
		$target->setTime( $hours, $minutes, 0 );

		if ( $target <= $now ) {
			$target->modify( '+1 day' );
		}

		return $target->getTimestamp();
	}
}
