<?php
/**
 * Bootstrap.
 *
 * @package SmokyPost
 */

namespace SmokyPost;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot() {
		add_action( 'init', array( self::class, 'load_translations' ) );
		Settings::add_fediverse_to_defaults();
		Settings::add_threads_tumblr_to_defaults();
		Sharer::init();
		Editor::init();
		// Some sites strip "?ver=" from script URLs; browsers then keep old SmokyPost files after an update.
		add_filter( 'script_loader_src', array( self::class, 'keep_version' ), PHP_INT_MAX );
		add_filter( 'style_loader_src', array( self::class, 'keep_version' ), PHP_INT_MAX );
		if ( is_admin() ) {
			Admin\Page::init();
			Admin\PostList::init();
		}
		/**
		 * Fires when SmokyPost is ready. Add-ons hook in here.
		 */
		do_action( 'smokypost_loaded' );
	}

	/**
	 * Bundled translations, used until WordPress has a language pack for this plugin.
	 */
	public static function load_translations() {
		load_plugin_textdomain( 'smokypost', false, dirname( plugin_basename( SMOKYPOST_FILE ) ) . '/languages' );
	}

	/**
	 * Put the plugin version back on SmokyPost's own files.
	 *
	 * @param string $src Script or style URL.
	 */
	public static function keep_version( $src ) {
		if ( is_string( $src ) && 0 === strpos( $src, SMOKYPOST_URL ) && false === strpos( $src, 'ver=' ) ) {
			$src = add_query_arg( 'ver', SMOKYPOST_VERSION, $src );
		}
		return $src;
	}

	public static function activate() {
		add_option( 'smokypost_welcome', 1, '', false );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( Sharer::SYNC_HOOK );
		wp_clear_scheduled_hook( Sharer::RETRY_HOOK );
		wp_unschedule_hook( Sharer::SHARE_HOOK );
	}
}
