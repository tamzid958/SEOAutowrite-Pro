<?php
/**
 * Logger class — stores run logs in a WordPress option.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASAW_Logger {

	const OPTION_KEY = 'asaw_logs';
	const MAX_LOGS   = 100;

	/**
	 * Write a log entry.
	 *
	 * @param string $level   error|info|debug
	 * @param string $message Human-readable message.
	 * @param array  $context Optional extra data.
	 */
	public static function log( $level, $message, $context = array() ) {
		$logs = get_option( self::OPTION_KEY, array() );

		array_unshift( $logs, array(
			'time'    => current_time( 'mysql' ),
			'level'   => $level,
			'message' => $message,
			'context' => $context,
		) );

		if ( count( $logs ) > self::MAX_LOGS ) {
			$logs = array_slice( $logs, 0, self::MAX_LOGS );
		}

		update_option( self::OPTION_KEY, $logs, false );
	}

	public static function info( $message, $context = array() ) {
		self::log( 'info', $message, $context );
	}

	public static function error( $message, $context = array() ) {
		self::log( 'error', $message, $context );
	}

	public static function debug( $message, $context = array() ) {
		$options = get_option( 'asaw_options', array() );
		$level   = isset( $options['logging_level'] ) ? $options['logging_level'] : 'info';
		if ( 'debug' === $level ) {
			self::log( 'debug', $message, $context );
		}
	}

	/**
	 * Retrieve the most recent log entries.
	 *
	 * @param int $limit Number of entries to return.
	 * @return array
	 */
	public static function get_logs( $limit = 10 ) {
		$logs = get_option( self::OPTION_KEY, array() );
		return array_slice( $logs, 0, $limit );
	}
}
