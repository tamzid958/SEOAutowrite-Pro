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

// Remove plugin options.
delete_option( 'asaw_options' );
delete_option( 'asaw_logs' );
delete_option( 'asaw_last_category_index' );

// Clear the cron hook so no orphaned events remain.
wp_clear_scheduled_hook( 'asaw_generate_scheduled_post' );
