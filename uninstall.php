<?php
/**
 * Uninstall: disconnect the site and remove the plugin's settings.
 * Post meta with share results stays, so history is not lost if the plugin is reinstalled.
 *
 * @package SmokyPost
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$smokypost_token = (string) get_option( 'smokypost_site_token', '' );
if ( '' !== $smokypost_token ) {
	$smokypost_url = defined( 'SMOKYPOST_API_URL' ) ? SMOKYPOST_API_URL : 'https://www.smokypost.com/v1';
	wp_remote_request(
		untrailingslashit( $smokypost_url ) . '/site?reason=uninstall',
		array(
			'method'   => 'DELETE',
			'timeout'  => 5,
			// Wait for the answer: a request that is not waited for can be dropped before it is sent.
			'blocking' => true,
			'headers'  => array(
				'Authorization'     => 'Bearer ' . $smokypost_token,
				'X-SmokyPost-Token' => $smokypost_token,
			),
		)
	);
}

foreach ( array( 'smokypost_site_token', 'smokypost_settings', 'smokypost_tracking', 'smokypost_retry_queue', 'smokypost_last_sync', 'smokypost_welcome', 'smokypost_update_required' ) as $smokypost_option ) {
	delete_option( $smokypost_option );
}
delete_transient( 'smokypost_site' );
wp_clear_scheduled_hook( 'smokypost_sync' );
wp_clear_scheduled_hook( 'smokypost_retry' );
wp_unschedule_hook( 'smokypost_share_post' );
