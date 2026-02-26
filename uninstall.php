<?php
/**
 * Uninstall script — runs when the plugin is deleted via the WP admin UI.
 *
 * Removes all plugin options and clears the scheduled cron event.
 * Does NOT delete generated posts or media attachments.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove free-tier plugin options.
delete_option( 'seoapw_options' );
delete_option( 'seoapw_logs' );
delete_option( 'seoapw_last_category_index' );

// Remove Pro options and transients.
delete_option( 'seoapw_license_key' );
delete_option( 'seoapw_queued_notices' );
delete_option( 'seoapw_dismissed_notices' );
delete_transient( 'seoapw_license_status' );
delete_transient( 'seoapw_last_pro_balance' );

// Clear the cron hook so no orphaned events remain.
wp_clear_scheduled_hook( 'seoapw_generate_scheduled_post' );
