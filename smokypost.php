<?php
/**
 * Plugin Name:       SmokyPost – Social Auto Share for Posts and Pages
 * Plugin URI:        https://www.smokypost.com/
 * Description:       Automatically share new and old posts and pages to your social media accounts. No developer accounts or API keys needed.
 * Version:           0.3.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            SmokySystems
 * Author URI:        https://www.smokysystems.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smokypost
 * Domain Path:       /languages
 *
 * @package SmokyPost
 */

defined( 'ABSPATH' ) || exit;

define( 'SMOKYPOST_VERSION', '0.3.1' );
define( 'SMOKYPOST_FILE', __FILE__ );
define( 'SMOKYPOST_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMOKYPOST_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'SmokyPost\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$file = SMOKYPOST_DIR . 'includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'SmokyPost\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SmokyPost\\Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'SmokyPost\\Plugin', 'boot' ) );
