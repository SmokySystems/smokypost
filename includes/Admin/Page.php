<?php
/**
 * The SmokyPost screen in wp-admin.
 *
 * @package SmokyPost
 */

namespace SmokyPost\Admin;

use SmokyPost\Api;
use SmokyPost\Mailer;
use SmokyPost\Settings;
use SmokyPost\Status;

defined( 'ABSPATH' ) || exit;

final class Page {

	const SLUG = 'smokypost';

	const ACTIONS = array( 'register', 'login', 'license', 'disconnect', 'resend', 'forgot', 'connect', 'connect_login', 'select', 'unselect', 'settings', 'dismiss_welcome', 'autoshare', 'checkout', 'confirm_plan', 'cancel_plan', 'refund', 'portal', 'clear_history', 'download_pro', 'notifications' );

	public static function init() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_notices', array( self::class, 'global_notices' ) );
		foreach ( self::ACTIONS as $action ) {
			add_action( 'admin_post_smokypost_' . $action, array( self::class, 'handle_' . $action ) );
		}
		add_filter( 'allowed_redirect_hosts', array( self::class, 'redirect_hosts' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SMOKYPOST_FILE ), array( self::class, 'action_links' ) );
	}

	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="92 65.5 618 618"><path fill="black" d="M 93.98 460.21 L 203.69 460.21 C 205.29 475.89 210.71 487.84 219.96 496.07 C 229.20 504.32 241.26 508.44 256.13 508.44 C 271.40 508.44 283.45 504.92 292.29 497.88 C 301.13 490.85 305.55 481.11 305.55 468.66 C 305.55 458.21 302.03 449.57 295.00 442.73 C 287.97 435.91 279.33 430.28 269.08 425.85 C 258.83 421.43 244.27 416.41 225.39 410.78 C 198.06 402.35 175.76 393.91 158.48 385.47 C 141.20 377.03 126.33 364.57 113.87 348.10 C 101.42 331.62 95.19 310.13 95.19 283.61 C 95.19 244.22 109.45 213.38 137.98 191.08 C 166.51 168.78 203.69 157.62 249.50 157.62 C 296.11 157.62 333.68 168.78 362.21 191.08 C 390.74 213.38 406.01 244.42 408.02 284.21 L 296.51 284.21 C 295.71 270.54 290.69 259.79 281.44 251.96 C 272.20 244.12 260.35 240.21 245.87 240.21 C 233.42 240.21 223.37 243.52 215.74 250.15 C 208.10 256.78 204.29 266.32 204.29 278.78 C 204.29 292.44 210.72 303.09 223.58 310.73 C 236.43 318.36 256.53 326.60 283.85 335.44 C 311.18 344.69 333.38 353.53 350.46 361.96 C 367.53 370.40 382.30 382.66 394.76 398.73 C 407.22 414.81 413.45 435.50 413.45 460.82 C 413.45 484.93 407.32 506.83 395.06 526.52 C 382.80 546.21 365.02 561.88 341.72 573.53 C 318.41 585.19 290.89 591.01 259.14 591.01 C 228.19 591.01 200.47 585.99 175.96 575.94 C 151.44 565.90 131.85 551.03 117.19 531.34 C 102.52 511.65 94.78 487.94 93.98 460.21 "/><path fill="black" fill-rule="evenodd" d="M 483.94 589.34 C 484.47 589.10 485.10 588.47 485.35 587.94 C 485.66 587.25 485.79 566.48 485.81 513.55 C 485.82 457.74 485.94 439.80 486.30 438.76 C 486.58 437.97 487.28 437.11 487.97 436.70 C 489.09 436.05 491.83 435.99 528.30 435.83 C 570.03 435.65 573.23 435.53 585.42 433.61 C 589.45 432.98 593.63 432.34 594.71 432.19 C 597.94 431.76 610.12 428.69 615.87 426.87 C 642.79 418.34 667.36 401.46 682.70 380.97 C 692.93 367.30 700.72 350.07 704.49 332.81 C 707.05 321.06 707.44 317.81 707.86 304.31 C 708.26 291.28 707.31 279.33 704.98 268.13 C 698.23 235.77 682.80 211.14 657.75 192.71 C 641.61 180.85 617.77 170.89 596.97 167.32 C 593.99 166.81 589.42 166.03 586.81 165.58 C 576.61 163.83 575.29 163.81 479.26 163.58 C 428.81 163.46 386.96 163.50 386.26 163.67 C 385.57 163.84 384.63 164.44 384.19 165.00 C 383.42 165.98 383.38 166.54 383.39 176.75 C 383.40 189.50 383.23 188.90 388.28 194.37 C 399.52 206.56 408.94 221.82 414.38 236.64 C 416.87 243.41 419.61 252.82 420.33 257.06 C 420.66 259.02 421.28 262.22 421.69 264.18 C 422.76 269.18 423.39 275.38 423.45 281.70 C 423.51 286.77 423.45 287.25 422.48 289.31 C 421.23 291.98 418.12 295.18 415.54 296.45 C 413.66 297.37 413.36 297.40 399.79 297.59 C 389.66 297.73 385.72 297.92 385.07 298.29 C 384.59 298.56 384.00 299.45 383.77 300.27 C 383.47 301.29 383.38 311.61 383.46 334.01 L 383.58 366.29 L 384.57 367.65 C 385.11 368.39 387.69 370.98 390.30 373.39 C 397.37 379.92 406.05 389.84 410.68 396.67 C 419.26 409.34 424.22 421.83 427.07 437.95 C 429.13 449.60 429.35 452.50 429.14 465.58 C 428.96 476.81 428.89 477.65 427.50 485.79 C 425.00 500.45 421.40 511.83 415.42 523.95 C 408.51 537.97 401.81 547.45 390.13 559.74 C 387.14 562.90 384.38 566.06 384.01 566.77 C 383.42 567.91 383.36 569.27 383.46 577.70 C 383.57 586.68 383.63 587.39 384.37 588.21 C 384.80 588.69 385.51 589.23 385.95 589.41 C 387.23 589.93 482.79 589.87 483.94 589.34 Z M 487.88 353.77 C 487.01 353.23 486.48 352.49 486.19 351.42 C 485.90 350.34 485.81 334.15 485.88 299.29 C 485.98 251.34 486.02 248.67 486.70 247.64 C 487.09 247.04 487.89 246.37 488.47 246.14 C 489.91 245.60 552.63 245.67 557.08 246.23 C 560.23 246.62 568.96 248.73 572.23 249.89 C 580.76 252.92 588.98 259.13 594.37 266.62 C 598.29 272.07 602.14 282.04 603.20 289.48 C 603.73 293.19 603.72 307.05 603.18 310.89 C 602.61 315.00 600.38 322.62 598.67 326.32 C 594.21 335.98 585.76 344.39 576.05 348.82 C 572.66 350.37 565.14 352.70 560.51 353.65 C 558.02 354.16 552.07 354.28 523.35 354.40 C 489.79 354.55 489.12 354.54 487.88 353.77 Z"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		add_menu_page( 'SmokyPost', 'SmokyPost', 'manage_options', self::SLUG, array( self::class, 'render' ), $icon, 81 );
	}

	/**
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'smokypost' ) . '</a>' );
		return $links;
	}

	/**
	 * @param string[] $hosts Hosts.
	 * @return string[]
	 */
	public static function redirect_hosts( $hosts ) {
		$api_host = wp_parse_url( Api::base_url(), PHP_URL_HOST );
		return array_merge( $hosts, array( 'www.facebook.com', 'www.linkedin.com', 'customer-portal.paddle.com', 'sandbox-customer-portal.paddle.com' ), $api_host ? array( $api_host ) : array() );
	}

	/**
	 * @param string $hook Admin page hook.
	 */
	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG === $hook || 'edit.php' === $hook ) {
			wp_enqueue_style( 'smokypost-admin', SMOKYPOST_URL . 'assets/css/admin.css', array(), SMOKYPOST_VERSION );
			self::enqueue_admin_js();
		}
	}

	public static function enqueue_admin_js() {
		wp_enqueue_style( 'smokypost-admin', SMOKYPOST_URL . 'assets/css/admin.css', array(), SMOKYPOST_VERSION );
		wp_enqueue_script( 'smokypost-admin', SMOKYPOST_URL . 'assets/js/admin.js', array(), SMOKYPOST_VERSION, true );
		wp_localize_script(
			'smokypost-admin',
			'smokypostAdmin',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'working'           => __( 'Sending…', 'smokypost' ),
				'deleting'          => __( 'Deleting…', 'smokypost' ),
				'stillSending'      => __( 'Still sending. Check the History tab in a few minutes.', 'smokypost' ),
			)
		);
	}

	/**
	 * @param array<string, string> $args Query args.
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Rendering                                                           */
	/* ------------------------------------------------------------------ */

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$site = Api::is_connected() ? Api::site( true ) : null;
		if ( is_wp_error( $site ) && ! Api::is_connected() ) {
			$site = null; // The token was just found invalid.
		}
		if ( ! Api::is_connected() ) {
			$tab = 'account';
		} elseif ( '' === $tab ) {
			$tab = 'platforms';
		}

		echo '<div class="wrap smokypost">';
		echo '<h1>SmokyPost</h1>';
		self::flash();
		self::welcome();
		if ( get_option( 'smokypost_update_required' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'A newer version of SmokyPost is required. Please update the plugin.', 'smokypost' ) . '</p></div>';
		}
		if ( is_wp_error( $site ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $site->get_error_message() ) . '</p></div>';
		}

		if ( Api::is_connected() ) {
			$tabs = array(
				'platforms' => __( 'Platforms', 'smokypost' ),
				'settings'  => __( 'Settings', 'smokypost' ),
				'history'   => __( 'History', 'smokypost' ),
				'notifications' => __( 'Notifications', 'smokypost' ),
				'account'   => __( 'Account', 'smokypost' ),
			);
			echo '<nav class="nav-tab-wrapper">';
			foreach ( $tabs as $key => $label ) {
				echo '<a class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( array( 'tab' => $key ) ) ) . '">' . esc_html( $label ) . '</a>';
			}
			echo '</nav>';
		}

		switch ( $tab ) {
			case 'platforms':
				self::render_platforms( is_array( $site ) ? $site : array() );
				break;
			case 'settings':
				self::render_settings( is_array( $site ) ? $site : array() );
				break;
			case 'history':
				self::render_history( is_array( $site ) ? $site : array() );
				break;
			case 'notifications':
				self::render_notifications();
				break;
			default:
				Api::is_connected() ? self::render_account( is_array( $site ) ? $site : array() ) : self::render_connect();
		}
		echo '</div>';
	}

	private static function render_connect() {
		$email = (string) get_transient( 'smokypost_email_' . get_current_user_id() );
		delete_transient( 'smokypost_email_' . get_current_user_id() );
		$terms   = '<a href="https://www.smokypost.com/terms/" target="_blank" rel="noopener">' . esc_html__( 'Terms of Service', 'smokypost' ) . '</a>';
		$privacy = '<a href="https://www.smokypost.com/privacy-policy/" target="_blank" rel="noopener">' . esc_html__( 'Privacy Policy', 'smokypost' ) . '</a>';
		echo '<p class="smokypost-lead">' . esc_html__( 'Share your posts and pages to your social media accounts automatically. Create a free account or sign in to connect this site.', 'smokypost' ) . '</p>';
		echo '<div class="notice notice-info inline smokypost-privacy"><p>' . esc_html__( 'When you connect, this site sends its address, WordPress and PHP versions, language and time zone to the SmokyPost service (www.smokypost.com). The content of each item you share is sent when it is shared.', 'smokypost' ) . '</p></div>';

		echo '<div class="smokypost-cards">';

		echo '<div class="smokypost-card"><h2>' . esc_html__( 'Create a free account', 'smokypost' ) . '</h2>';
		self::form_open( 'register' );
		echo '<p><label>' . esc_html__( 'E-mail', 'smokypost' ) . '<br><input type="email" name="email" class="regular-text" required autocomplete="email" value="' . esc_attr( $email ) . '"></label></p>';
		echo '<p><label>' . esc_html__( 'Password (at least 8 characters)', 'smokypost' ) . '<br><input type="password" name="password" class="regular-text" minlength="8" required autocomplete="new-password"></label></p>';
		/* translators: 1: Terms of Service link, 2: Privacy Policy link */
		echo '<p><label><input type="checkbox" name="accept_terms" value="1" required> ' . wp_kses( sprintf( __( 'I accept the %1$s and the %2$s.', 'smokypost' ), $terms, $privacy ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ) . '</label></p>';
		submit_button( __( 'Create account and connect', 'smokypost' ), 'primary', 'submit', false );
		echo '</form></div>';

		echo '<div class="smokypost-card"><h2>' . esc_html__( 'I already have an account', 'smokypost' ) . '</h2>';
		self::form_open( 'login' );
		echo '<p><label>' . esc_html__( 'E-mail', 'smokypost' ) . '<br><input type="email" name="email" class="regular-text" required autocomplete="email" value="' . esc_attr( $email ) . '"></label></p>';
		echo '<p><label>' . esc_html__( 'Password', 'smokypost' ) . '<br><input type="password" name="password" class="regular-text" required autocomplete="current-password"></label></p>';
		submit_button( __( 'Sign in and connect', 'smokypost' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<details><summary>' . esc_html__( 'Use a license key instead', 'smokypost' ) . '</summary>';
		self::form_open( 'license' );
		echo '<p><input type="text" name="license_key" class="regular-text" placeholder="SP-XXXX-XXXX-XXXX-XXXX" required></p>';
		submit_button( __( 'Connect with license key', 'smokypost' ), 'secondary', 'submit', false );
		echo '</form></details>';

		echo '<details><summary>' . esc_html__( 'Forgot your password?', 'smokypost' ) . '</summary>';
		self::form_open( 'forgot' );
		echo '<p><input type="email" name="email" class="regular-text" placeholder="' . esc_attr__( 'E-mail', 'smokypost' ) . '" required></p>';
		submit_button( __( 'Send reset link', 'smokypost' ), 'secondary', 'submit', false );
		echo '</form></details>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $site Site data.
	 */
	private static function render_platforms( array $site ) {
		self::account_warnings( $site );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$choose = isset( $_GET['choose'] ) ? sanitize_key( wp_unslash( $_GET['choose'] ) ) : '';
		if ( isset( $_GET['smokypost_oauth'] ) && 'ok' === $_GET['smokypost_oauth'] && isset( $_GET['platform'] ) ) {
			$choose = sanitize_key( wp_unslash( $_GET['platform'] ) );
		}
		// phpcs:enable

		echo '<p class="smokypost-lead">' . esc_html__( 'Connect each platform and choose the page or account this site shares to.', 'smokypost' ) . '</p>';
		$by_platform = array();
		foreach ( (array) ( $site['connections'] ?? array() ) as $c ) {
			$by_platform[ $c['platform'] ] = $c;
		}
		echo '<div class="smokypost-cards">';
		$known = Settings::platforms();
		foreach ( (array) ( $site['connections'] ?? array() ) as $c ) {
			$platform = (string) $c['platform'];
			if ( ! in_array( $platform, $known, true ) ) {
				continue;
			}
			echo '<div class="smokypost-card smokypost-platform is-' . esc_attr( $platform ) . '"><h2>' . esc_html( Settings::label( $platform ) ) . '</h2>';
			echo '<p class="description smokypost-about">' . esc_html( self::platform_about( $platform ) ) . '</p>';
			if ( empty( $c['in_plan'] ) ) {
				echo '<p>' . esc_html__( 'Not included in your plan.', 'smokypost' ) . '</p></div>';
				continue;
			}
			if ( empty( $c['available'] ) ) {
				echo '<p>' . esc_html__( 'Temporarily unavailable. Please try again later.', 'smokypost' ) . '</p></div>';
				continue;
			}
			if ( ! empty( $c['target'] ) && $choose !== $platform ) {
				$t = $c['target'];
				echo '<p class="smokypost-target">';
				if ( ! empty( $t['avatar_url'] ) ) {
					echo '<img src="' . esc_url( $t['avatar_url'] ) . '" alt="" width="32" height="32" class="smokypost-avatar" referrerpolicy="no-referrer"> ';
				}
				echo '<strong>' . esc_html( $t['name'] ) . '</strong></p>';
				if ( 'active' !== $t['status'] ) {
					echo '<p class="smokypost-error">' . esc_html__( 'The connection has expired. Connect again to keep sharing.', 'smokypost' ) . '</p>';
					self::connect_button( $platform, __( 'Connect again', 'smokypost' ) );
				}
				self::auto_toggle( $platform );
				echo '<div class="smokypost-actions"><a class="button" href="' . esc_url( self::url( array( 'tab' => 'platforms', 'choose' => $platform ) ) ) . '">' . esc_html__( 'Change', 'smokypost' ) . '</a> ';
				self::form_open( 'unselect', true );
				echo '<input type="hidden" name="platform" value="' . esc_attr( $platform ) . '"><button class="button-link button-link-delete">' . esc_html__( 'Remove', 'smokypost' ) . '</button></form></div>';
			} elseif ( $choose === $platform ) {
				self::render_picker( $platform );
			} else {
				echo '<p>' . esc_html__( 'Not connected.', 'smokypost' ) . '</p>';
				echo '<div class="smokypost-actions">';
				self::connect_button( $platform, __( 'Connect', 'smokypost' ) );
				// Facebook and Instagram share one Meta login: offer the list without signing in again.
				$sibling = array( 'facebook' => 'instagram', 'instagram' => 'facebook' );
				if ( isset( $sibling[ $platform ] ) && isset( $by_platform[ $sibling[ $platform ] ]['target'] ) ) {
					echo ' <a href="' . esc_url( self::url( array( 'tab' => 'platforms', 'choose' => $platform ) ) ) . '">' . esc_html__( 'Choose from your Facebook login', 'smokypost' ) . '</a>';
				}
				echo '</div>';
				/**
				 * Under the Connect button of a platform that is not connected yet.
				 *
				 * @param string $platform Platform.
				 */
				do_action( 'smokypost_platform_not_connected', $platform );
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * One short sentence under the title of a platform card.
	 *
	 * @param string $platform Platform.
	 * @return string
	 */
	private static function platform_about( $platform ) {
		switch ( $platform ) {
			case 'facebook':
				return __( 'Posts your text and a link to one of your Facebook Pages. You sign in with Facebook.', 'smokypost' );
			case 'instagram':
				return __( 'Needs an Instagram professional account linked to a Facebook Page. You sign in with Facebook.', 'smokypost' );
			case 'linkedin':
				return __( 'Posts your text and a link to one of your LinkedIn company pages. You sign in with LinkedIn.', 'smokypost' );
			case 'bluesky':
				return __( 'Posts your text, hashtags and a link card with the picture to your Bluesky account. You connect with your handle and an app password.', 'smokypost' );
			case 'mastodon':
				return __( 'Posts your text, hashtags and a link to your Mastodon account on any Mastodon server. You approve SmokyPost on your own server.', 'smokypost' );
			case 'threads':
				return __( 'Posts your text and a link card to your Threads profile. You sign in with Threads.', 'smokypost' );
			case 'tumblr':
				return __( 'Posts your text, tags and a link card with the picture to one of your Tumblr blogs. You sign in with Tumblr.', 'smokypost' );
		}
		return (string) apply_filters( 'smokypost_platform_about', '', $platform );
	}

	/**
	 * "Automatic sharing: On / Off" line on a connected platform card.
	 *
	 * @param string $platform Platform.
	 */
	private static function auto_toggle( $platform ) {
		$on = in_array( $platform, (array) Settings::get( 'default_platforms' ), true );
		echo '<div class="smokypost-auto ' . ( $on ? 'is-on' : 'is-off' ) . '">';
		self::form_open( 'autoshare', true );
		echo '<input type="hidden" name="platform" value="' . esc_attr( $platform ) . '"><input type="hidden" name="enable" value="' . ( $on ? '0' : '1' ) . '">';
		echo '<span class="smokypost-auto-label">' . esc_html__( 'Automatic sharing:', 'smokypost' ) . '</span> <span class="smokypost-pill">' . esc_html( $on ? __( 'On', 'smokypost' ) : __( 'Off', 'smokypost' ) ) . '</span> ';
		echo '<button class="button-link">' . esc_html( $on ? __( 'Turn off', 'smokypost' ) : __( 'Turn on', 'smokypost' ) ) . '</button></form>';
		echo '<p class="description">' . esc_html( $on ? __( 'New items are shared here when they are published.', 'smokypost' ) : __( 'Only shared when you click "Share now".', 'smokypost' ) ) . '</p></div>';
	}

	/**
	 * Page/account list for one platform.
	 *
	 * @param string $platform Platform.
	 */
	private static function render_picker( $platform ) {
		$result = Api::request( 'GET', '/connections/' . $platform . '/targets' );
		if ( is_wp_error( $result ) ) {
			echo '<p class="smokypost-error">' . esc_html( $result->get_error_message() ) . '</p>';
			return;
		}
		if ( ! empty( $result['error'] ) && empty( $result['targets'] ) ) {
			/* translators: %s: reason */
			echo '<p class="smokypost-error">' . esc_html( sprintf( __( 'You are signed in, but your accounts could not be listed: %s', 'smokypost' ), Status::reason( (array) $result['error'] ) ) ) . '</p>';
			echo '<div class="smokypost-actions"><a class="button" href="' . esc_url( self::url( array( 'tab' => 'platforms', 'choose' => $platform ) ) ) . '">' . esc_html__( 'Try again', 'smokypost' ) . '</a> <a href="' . esc_url( self::url( array( 'tab' => 'platforms' ) ) ) . '">' . esc_html__( 'Cancel', 'smokypost' ) . '</a></div>';
			return;
		}
		if ( ! empty( $result['needs_login'] ) || ! $result['targets'] ) {
			echo '<p>' . ( ! empty( $result['needs_login'] )
				? ( in_array( $platform, array( 'bluesky', 'mastodon' ), true ) ? esc_html__( 'Connect your account first.', 'smokypost' ) : esc_html__( 'Sign in first to see your pages.', 'smokypost' ) )
				: esc_html__( 'No page or account with posting rights was found for this login.', 'smokypost' ) ) . '</p>';
			if ( 'instagram' === $platform && empty( $result['needs_login'] ) ) {
				echo '<p class="description">' . esc_html__( 'Check that your Instagram account is a professional account and is linked to one of your Facebook Pages.', 'smokypost' ) . '</p>';
			}
			if ( empty( $result['needs_login'] ) ) {
				/**
				 * A login worked but it has no page or account to share to.
				 *
				 * @param string $platform Platform.
				 */
				do_action( 'smokypost_picker_empty', $platform );
			}
			self::connect_button( $platform, __( 'Connect', 'smokypost' ) );
			return;
		}
		self::form_open( 'select' );
		echo '<input type="hidden" name="platform" value="' . esc_attr( $platform ) . '">';
		echo '<fieldset class="smokypost-picker">';
		foreach ( $result['targets'] as $i => $t ) {
			echo '<label><input type="radio" name="target_id" value="' . esc_attr( $t['id'] ) . '"' . checked( 0, $i, false ) . '> ';
			if ( ! empty( $t['avatar_url'] ) ) {
				echo '<img src="' . esc_url( $t['avatar_url'] ) . '" alt="" width="24" height="24" class="smokypost-avatar" referrerpolicy="no-referrer"> ';
			}
			echo esc_html( $t['name'] );
			if ( ! empty( $t['login_name'] ) ) {
				/* translators: %s: name of the person who signed in */
				echo ' <span class="description">' . esc_html( sprintf( __( '(via %s)', 'smokypost' ), $t['login_name'] ) ) . '</span>';
			}
			echo '</label>';
		}
		echo '</fieldset>';
		submit_button( __( 'Use this account', 'smokypost' ), 'primary', 'submit', false );
		echo ' <a href="' . esc_url( self::url( array( 'tab' => 'platforms' ) ) ) . '">' . esc_html__( 'Cancel', 'smokypost' ) . '</a>';
		echo '</form>';
		echo '<div class="smokypost-actions description">' . esc_html__( 'Missing a page?', 'smokypost' ) . ' ';
		self::connect_button( $platform, __( 'Sign in with another account', 'smokypost' ), true );
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $site Site data.
	 */
	private static function render_settings( array $site ) {
		$s         = Settings::all();
		$connected = array_keys( Api::connected_targets() );
		self::form_open( 'settings' );
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Content types', 'smokypost' ) . '</th><td><fieldset>';
		foreach ( Settings::shareable_post_types() as $name => $label ) {
			echo '<label><input type="checkbox" name="post_types[]" value="' . esc_attr( $name ) . '"' . checked( in_array( $name, $s['post_types'], true ), true, false ) . '> ' . esc_html( $label ) . '</label><br>';
		}
		echo '<p class="description">' . esc_html__( 'Only these types are shared automatically and get the SmokyPost box in the editor.', 'smokypost' ) . '</p></fieldset></td></tr>';

		echo '<tr id="automatic-sharing"><th scope="row">' . esc_html__( 'Automatic sharing', 'smokypost' ) . '</th><td><fieldset>';
		echo '<p class="smokypost-field-intro">' . esc_html__( 'Share new items automatically when they are published to:', 'smokypost' ) . '</p>';
		foreach ( Settings::platforms() as $p ) {
			$note = in_array( $p, $connected, true ) ? '' : ' <span class="description">(' . esc_html__( 'not connected', 'smokypost' ) . ')</span>';
			echo '<label><input type="checkbox" name="default_platforms[]" value="' . esc_attr( $p ) . '"' . checked( in_array( $p, $s['default_platforms'], true ), true, false ) . '> ' . esc_html( Settings::label( $p ) ) . $note . '</label><br>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<p class="description">' . esc_html__( 'Untick a platform to stop automatic sharing to it. Untick all to turn automatic sharing off. You can still share any item yourself with "Share now" in the editor or the post list, and untick a platform for a single item in the editor.', 'smokypost' ) . '</p></fieldset></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Automatically created items', 'smokypost' ) . '</th><td><label><input type="checkbox" name="share_programmatic" value="1"' . checked( $s['share_programmatic'], true, false ) . '> ' . esc_html__( 'Also share items published by other plugins, importers, the REST API or WP-CLI', 'smokypost' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'On by default. Items created by code (for example an autoblog or a feed importer) are shared to the platforms above. Turn this off if you only want to share what you publish in the editor.', 'smokypost' ) . '</p>';
		echo '<p><label>' . esc_html__( 'Skip imported items older than', 'smokypost' ) . ' <input type="number" name="import_max_age" min="0" max="720" value="' . esc_attr( (string) $s['import_max_age'] ) . '" class="small-text"> ' . esc_html__( 'hours', 'smokypost' ) . '</label></p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Deleting items', 'smokypost' ) . '</th><td><label><input type="checkbox" name="delete_with_post" value="1"' . checked( $s['delete_with_post'], true, false ) . '> ' . esc_html__( 'Delete the shared posts when the item is deleted', 'smokypost' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Off by default. When you move an item to the trash or delete it, SmokyPost also removes its posts from Facebook, LinkedIn, Bluesky, Mastodon, Threads and Tumblr. Instagram does not allow other apps to delete posts, so those have to be deleted in the Instagram app. Restoring an item from the trash does not bring the social posts back.', 'smokypost' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="smokypost-template">' . esc_html__( 'Message template', 'smokypost' ) . '</label></th><td>';
		echo '<textarea id="smokypost-template" name="template" rows="5" class="large-text code">' . esc_textarea( $s['template'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Placeholders:', 'smokypost' ) . ' <code>{title}</code> <code>{excerpt}</code> <code>{url}</code> <code>{hashtags}</code> <code>{categories}</code> <code>{site_name}</code> <code>{author}</code> <code>{date}</code><br>' . esc_html__( 'The link is added by each platform as a preview card; on Instagram it is added to the end of the text.', 'smokypost' ) . '</p>';
		echo '<p><label><input type="checkbox" name="hashtags_from_tags" value="1"' . checked( $s['hashtags_from_tags'], true, false ) . '> ' . esc_html__( 'Turn post tags into hashtags for {hashtags}', 'smokypost' ) . '</label></p></td></tr>';

		echo '<tr><th scope="row"><label for="smokypost-image">' . esc_html__( 'Default image', 'smokypost' ) . '</label></th><td><input type="url" id="smokypost-image" name="default_image" class="large-text" value="' . esc_attr( $s['default_image'] ) . '" placeholder="https://">';
		echo '<p class="description">' . esc_html__( 'Used when an item has no featured image and no image in its content. Instagram always needs an image.', 'smokypost' ) . '</p></td></tr>';

		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}


	private static function render_notifications() {
		$s     = Settings::all();
		$admin = (string) get_option( 'admin_email' );
		self::form_open( 'notifications' );
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'E-mail me when', 'smokypost' ) . '</th><td><fieldset>';
		echo '<label><input type="checkbox" name="notify_failure" value="1"' . checked( $s['notify_failure'], true, false ) . '> ' . esc_html__( 'a share fails', 'smokypost' ) . '</label><br>';
		echo '<label><input type="checkbox" name="notify_success" value="1"' . checked( $s['notify_success'], true, false ) . '> ' . esc_html__( 'an item was shared', 'smokypost' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'You get one e-mail per item. A failed share is reported after SmokyPost has tried several times, and the same problem on the same platform is reported once a day. An e-mail that a connection has expired is always sent.', 'smokypost' ) . '</p></fieldset></td></tr>';

		echo '<tr><th scope="row"><label for="smokypost-notify-emails">' . esc_html__( 'Send e-mails to', 'smokypost' ) . '</label></th><td>';
		echo '<input type="text" id="smokypost-notify-emails" name="notify_emails" class="large-text" value="' . esc_attr( (string) $s['notify_emails'] ) . '" placeholder="' . esc_attr( $admin ) . '">';
		/* translators: %s: e-mail address */
		echo '<p class="description">' . esc_html( sprintf( __( 'Separate several addresses with commas. Leave empty to use the administrator address of this site (%s).', 'smokypost' ), $admin ) ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'SMTP', 'smokypost' ) . '</th><td><fieldset>';
		echo '<label><input type="checkbox" name="smtp_enabled" value="1"' . checked( $s['smtp_enabled'], true, false ) . '> ' . esc_html__( 'Send SmokyPost e-mails through an SMTP account', 'smokypost' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Use this when e-mails from your site do not arrive. Only the e-mails of SmokyPost use this account, other e-mails of your site stay as they are. You do not need a separate SMTP plugin for SmokyPost.', 'smokypost' ) . '</p></fieldset></td></tr>';

		echo '<tr><th scope="row"><label for="smokypost-smtp-host">' . esc_html__( 'SMTP server', 'smokypost' ) . '</label></th><td><input type="text" id="smokypost-smtp-host" name="smtp_host" class="regular-text" autocomplete="off" value="' . esc_attr( (string) $s['smtp_host'] ) . '" placeholder="smtp.example.com"></td></tr>';
		echo '<tr><th scope="row"><label for="smokypost-smtp-port">' . esc_html__( 'Port', 'smokypost' ) . '</label></th><td><input type="number" id="smokypost-smtp-port" name="smtp_port" class="small-text" min="1" max="65535" value="' . esc_attr( (string) $s['smtp_port'] ) . '">';
		echo ' <select name="smtp_secure" aria-label="' . esc_attr__( 'Encryption', 'smokypost' ) . '">';
		foreach ( array(
			'tls' => __( 'TLS (usually port 587)', 'smokypost' ),
			'ssl' => __( 'SSL (usually port 465)', 'smokypost' ),
			''    => __( 'No encryption', 'smokypost' ),
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $s['smtp_secure'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="smokypost-smtp-user">' . esc_html__( 'Username', 'smokypost' ) . '</label></th><td><input type="text" id="smokypost-smtp-user" name="smtp_user" class="regular-text" autocomplete="off" value="' . esc_attr( (string) $s['smtp_user'] ) . '"></td></tr>';

		echo '<tr><th scope="row"><label for="smokypost-smtp-pass">' . esc_html__( 'Password', 'smokypost' ) . '</label></th><td>';
		if ( Mailer::password_from_constant() ) {
			echo '<p>' . esc_html__( 'The password is set in wp-config.php.', 'smokypost' ) . '</p>';
		} else {
			echo '<input type="password" id="smokypost-smtp-pass" name="smtp_pass" class="regular-text" autocomplete="new-password" value="">';
			if ( '' !== (string) $s['smtp_pass'] ) {
				echo '<p class="description">' . esc_html__( 'A password is saved. Leave this empty to keep it.', 'smokypost' ) . ' <label><input type="checkbox" name="smtp_pass_clear" value="1"> ' . esc_html__( 'Remove the saved password', 'smokypost' ) . '</label></p>';
			}
			echo '<p class="description">' . esc_html__( 'The password is stored encrypted and never shown again. For Gmail and Outlook use an app password.', 'smokypost' ) . '</p>';
		}
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="smokypost-smtp-from">' . esc_html__( 'From e-mail', 'smokypost' ) . '</label></th><td><input type="text" id="smokypost-smtp-from" name="smtp_from" class="regular-text" value="' . esc_attr( (string) $s['smtp_from'] ) . '" placeholder="' . esc_attr( $admin ) . '">';
		echo '<p class="description">' . esc_html__( 'Most providers only accept an address that belongs to the account.', 'smokypost' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="smokypost-smtp-from-name">' . esc_html__( 'From name', 'smokypost' ) . '</label></th><td><input type="text" id="smokypost-smtp-from-name" name="smtp_from_name" class="regular-text" value="' . esc_attr( (string) $s['smtp_from_name'] ) . '" placeholder="' . esc_attr( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) ) . '"></td></tr>';

		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save Changes', 'smokypost' ) . '</button> ';
		echo '<button type="submit" name="smokypost_test" value="1" class="button">' . esc_html__( 'Send a test e-mail', 'smokypost' ) . '</button></p>';
		echo '<p class="description">' . esc_html__( 'The test e-mail saves the values above first and then uses them.', 'smokypost' ) . '</p>';
		echo '</form>';
	}

	/**
	 * @param array<string, mixed> $site Site data.
	 */
	private static function render_history( array $site ) {
		$days   = (int) ( $site['license']['plan']['history_days'] ?? 7 );
		$result = Api::request( 'GET', '/shares', array( 'since' => gmdate( 'c', time() - $days * DAY_IN_SECONDS ) ) );
		if ( is_wp_error( $result ) ) {
			echo '<p class="smokypost-error">' . esc_html( $result->get_error_message() ) . '</p>';
			return;
		}
		/* translators: %d: number of days */
		echo '<p>' . esc_html( sprintf( _n( 'Shares of the last %d day.', 'Shares of the last %d days.', $days, 'smokypost' ), $days ) ) . '</p>';
		$shares = (array) $result['shares'];
		if ( $shares ) {
			self::render_clear_history();
		}
		// Newest first.
		usort(
			$shares,
			static function ( $a, $b ) {
				return strcmp( (string) $b['created_at'], (string) $a['created_at'] ) ?: (int) $b['id'] - (int) $a['id'];
			}
		);
		echo '<table class="widefat striped smokypost-history"><thead><tr><th>' . esc_html__( 'Date', 'smokypost' ) . '</th><th>' . esc_html__( 'Item', 'smokypost' ) . '</th><th>' . esc_html__( 'Result', 'smokypost' ) . '</th></tr></thead><tbody>';
		foreach ( $shares as $share ) {
			\SmokyPost\Sharer::store_result( $share );
			$post  = get_post( (int) $share['post_id'] );
			$title = $post ? get_the_title( $post ) : '#' . (int) $share['post_id'];
			echo '<tr><td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( (string) $share['created_at'] ) ) ) . '</td>';
			echo '<td>' . ( $post ? '<a href="' . esc_url( (string) get_edit_post_link( $post ) ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</td><td>';
			Status::render_box(
				array(
					'error' => '',
					'last'  => Status::summary( $share ),
				)
			);
			echo '</td></tr>';
		}
		if ( ! $shares ) {
			echo '<tr><td colspan="3">' . esc_html__( 'Nothing shared yet.', 'smokypost' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Clear the History list: a date range or everything. Confirmed inline, never with a browser dialog.
	 */
	private static function render_clear_history() {
		echo '<details class="smokypost-confirm"><summary class="button">' . esc_html__( 'Clear history', 'smokypost' ) . '</summary><div class="notice notice-warning inline"><p>' . esc_html__( 'Clear the history for the dates you choose, or all of it? What was already shared stays on your social accounts. Cleared items no longer show in this list.', 'smokypost' ) . '</p>';
		self::form_open( 'clear_history' );
		echo '<p><strong class="smokypost-date-label">' . esc_html__( 'From', 'smokypost' ) . '</strong> ';
		self::date_fields( 'from' );
		echo '</p><p><strong class="smokypost-date-label">' . esc_html__( 'To', 'smokypost' ) . '</strong> ';
		self::date_fields( 'to' );
		echo '</p>';
		echo '<p class="description">' . esc_html__( 'Leave the date empty to start at the first item or end today.', 'smokypost' ) . '</p>';
		echo '<p><button class="button button-primary" name="scope" value="range">' . esc_html__( 'Clear these dates', 'smokypost' ) . '</button> <button class="button" name="scope" value="all">' . esc_html__( 'Clear all history', 'smokypost' ) . '</button> <button type="button" class="button-link smokypost-confirm-cancel">' . esc_html__( 'Cancel', 'smokypost' ) . '</button></p></form></div></details>';
	}

	/**
	 * Day, month and year lists. A native date field would follow the browser language, these follow the site language.
	 *
	 * @param string $name Field prefix (from or to).
	 */
	private static function date_fields( $name ) {
		global $wp_locale;
		echo '<select name="' . esc_attr( $name ) . '_d" aria-label="' . esc_attr__( 'Day', 'smokypost' ) . '"><option value="">' . esc_html__( 'Day', 'smokypost' ) . '</option>';
		for ( $d = 1; $d <= 31; $d++ ) {
			echo '<option value="' . (int) $d . '">' . (int) $d . '</option>';
		}
		echo '</select> <select name="' . esc_attr( $name ) . '_m" aria-label="' . esc_attr__( 'Month', 'smokypost' ) . '"><option value="">' . esc_html__( 'Month', 'smokypost' ) . '</option>';
		for ( $m = 1; $m <= 12; $m++ ) {
			echo '<option value="' . (int) $m . '">' . esc_html( $wp_locale->get_month( $m ) ) . '</option>';
		}
		echo '</select> <select name="' . esc_attr( $name ) . '_y" aria-label="' . esc_attr__( 'Year', 'smokypost' ) . '"><option value="">' . esc_html__( 'Year', 'smokypost' ) . '</option>';
		$year = (int) wp_date( 'Y' );
		for ( $y = $year; $y >= $year - 3; $y-- ) {
			echo '<option value="' . (int) $y . '">' . (int) $y . '</option>';
		}
		echo '</select>';
	}

	/**
	 * @param array<string, mixed> $site Site data.
	 */
	private static function render_account( array $site ) {
		self::account_warnings( $site );
		$plan  = $site['license']['plan'] ?? array();
		$quota = $site['quota'] ?? array();
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Account', 'smokypost' ) . '</th><td>' . esc_html( (string) ( $site['account']['email'] ?? '' ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Plan', 'smokypost' ) . '</th><td>' . esc_html( self::plan_name( $plan ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'License key', 'smokypost' ) . '</th><td><code>' . esc_html( (string) ( $site['license']['key_masked'] ?? '' ) ) . '</code></td></tr>';
		echo '<tr><th>' . esc_html__( 'Shared today', 'smokypost' ) . '</th><td>';
		if ( isset( $quota['limit'] ) && null !== $quota['limit'] ) {
			/* translators: 1: items used, 2: daily limit */
			echo esc_html( sprintf( __( '%1$d of %2$d items', 'smokypost' ), (int) $quota['used'], (int) $quota['limit'] ) );
		} else {
			echo esc_html( sprintf( __( '%1$d of %2$d items', 'smokypost' ), (int) ( $quota['site_used'] ?? 0 ), (int) ( $quota['fair_use_cap'] ?? 100 ) ) );
		}
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'This site', 'smokypost' ) . '</th><td>' . esc_html( (string) ( $site['site']['domain'] ?? '' ) ) . '</td></tr>';
		echo '</tbody></table>';
		self::render_paid_notice( $site );
		self::render_pro_card();
		self::render_plans( (string) ( $plan['slug'] ?? '' ) );
		echo '<details class="smokypost-confirm"><summary class="button">' . esc_html__( 'Disconnect this site', 'smokypost' ) . '</summary><div class="notice notice-warning inline"><p>' . esc_html__( 'Disconnect this site from SmokyPost? Sharing stops until you sign in again.', 'smokypost' ) . '</p><p>';
		self::form_open( 'disconnect', true );
		echo '<button class="button button-primary">' . esc_html__( 'Yes, disconnect', 'smokypost' ) . '</button></form> <button type="button" class="button-link smokypost-confirm-cancel">' . esc_html__( 'Cancel', 'smokypost' ) . '</button></p></div></details>';
	}

	/**
	 * What happens after paying, said before the money is asked for and again after.
	 *
	 * @param bool $paid Whether the plan is paid already (the download button is there).
	 */
	private static function render_pro_steps( $paid ) {
		echo '<div class="notice notice-info inline smokypost-pro-steps"><p><strong>' . esc_html__( 'How Pro works', 'smokypost' ) . '</strong></p><ol>';
		echo '<li>' . esc_html__( 'Pay securely through Paddle.', 'smokypost' ) . '</li>';
		echo '<li>' . esc_html( $paid ? __( 'Download SmokyPost Pro with the button below.', 'smokypost' ) : __( 'Come back to this page. A "Download SmokyPost Pro" button appears, and we e-mail you the same link.', 'smokypost' ) ) . '</li>';
		echo '<li>' . esc_html__( 'Install it under Plugins > Add New > Upload Plugin and activate it. Your license is picked up automatically, there is no key to enter.', 'smokypost' ) . '</li></ol>';
		echo '<p>' . esc_html__( 'SmokyPost Pro is a second plugin that works together with SmokyPost: keep both active. It takes about a minute. Full refund within 14 days of your first payment.', 'smokypost' ) . '</p></div>';
	}

	/**
	 * Right after paying: the plan can take a minute to arrive. Say so and check again.
	 *
	 * @param array<string, mixed> $site Site data.
	 */
	private static function render_paid_notice( array $site ) {
		$tries = isset( $_GET['paid'] ) ? absint( $_GET['paid'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $tries < 1 || Api::has_feature( 'pro_plugin' ) ) {
			return;
		}
		if ( $tries > 12 ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Your payment is taking longer than usual to show here. Reload this page in a few minutes. If your plan is still Free after an hour, write to info@smokypost.com.', 'smokypost' ) . '</p></div>';
			return;
		}
		delete_transient( 'smokypost_site' );
		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Payment received. Your plan will be active in a moment, this page checks again by itself.', 'smokypost' ) . '</p></div>';
		$next = esc_url_raw( self::url( array( 'tab' => 'account', 'paid' => $tries + 1 ) ) );
		echo '<script>window.setTimeout(function(){window.location.href=' . wp_json_encode( $next ) . ';},5000);</script>';
	}

	/**
	 * The SmokyPost Pro plugin: download it, or see that it is there.
	 */
	private static function render_pro_card() {
		if ( ! Api::has_feature( 'pro_plugin' ) ) {
			return;
		}
		echo '<h2>' . esc_html__( 'SmokyPost Pro', 'smokypost' ) . '</h2>';
		if ( defined( 'SMOKYPOST_PRO_VERSION' ) ) {
			/* translators: %s: version number */
			echo '<p>' . esc_html( sprintf( __( 'SmokyPost Pro %s is installed and active.', 'smokypost' ), SMOKYPOST_PRO_VERSION ) ) . '</p>';
			echo '<p class="description">' . esc_html__( 'Its settings are under SmokyPost > Pro, the calendar under SmokyPost > Calendar. It updates itself.', 'smokypost' ) . '</p>';
			return;
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( isset( get_plugins()['smokypost-pro/smokypost-pro.php'] ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'SmokyPost Pro is installed but not active. Activate it on the Plugins page.', 'smokypost' ) . ' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Open Plugins', 'smokypost' ) . '</a></p></div>';
			return;
		}
		self::render_pro_steps( true );
		self::form_open( 'download_pro', true );
		echo '<button class="button button-primary">' . esc_html__( 'Download SmokyPost Pro', 'smokypost' ) . '</button></form>';
		echo '<p class="description">' . esc_html__( 'The link works for an hour. You can come back here and get a new one any time.', 'smokypost' ) . '</p>';
	}

	/**
	 * A fresh download link for the Pro plugin.
	 */
	public static function handle_download_pro() {
		self::verify( 'download_pro' );
		$result = Api::request( 'POST', '/pro/download-link' );
		if ( is_wp_error( $result ) || empty( $result['url'] ) ) {
			self::notice( 'error', is_wp_error( $result ) ? $result->get_error_message() : __( 'Something went wrong. Please try again.', 'smokypost' ) );
			self::back( array( 'tab' => 'account' ) );
		}
		wp_safe_redirect( (string) $result['url'] );
		exit;
	}

	/**
	 * Plans that can be bought, with an Upgrade / Switch button each. Nothing is
	 * shown while payments are not available on the service.
	 *
	 * @param string $current Slug of the current plan.
	 */
	private static function render_plans( $current ) {
		$billing = Api::request( 'GET', '/billing' );
		if ( is_wp_error( $billing ) || empty( $billing['available'] ) || empty( $billing['plans'] ) ) {
			return;
		}
		$subscribed = ! empty( $billing['subscribed'] );
		echo '<h2>' . esc_html__( 'Plans', 'smokypost' ) . '</h2>';
		self::render_plan_confirmation();
		if ( ! $subscribed ) {
			self::render_pro_steps( false );
		}
		echo '<table class="widefat striped smokypost-plans"><thead><tr><th>' . esc_html__( 'Plan', 'smokypost' ) . '</th><th>' . esc_html__( 'Sites', 'smokypost' ) . '</th><th>' . esc_html__( 'Items per day', 'smokypost' ) . '</th><th>' . esc_html__( 'Price', 'smokypost' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $billing['plans'] as $item ) {
			$slug  = (string) $item['slug'];
			$price = 'USD' === $item['currency'] ? '$' . $item['price'] : $item['price'] . ' ' . $item['currency'];
			echo '<tr><td><strong>' . esc_html( self::plan_name( $item ) ) . '</strong></td>';
			echo '<td>' . esc_html( (string) (int) $item['max_sites'] ) . '</td>';
			echo '<td>' . esc_html( isset( $item['daily_items'] ) ? (string) (int) $item['daily_items'] : '' ) . '</td>';
			/* translators: %s: price, for example $39 */
			echo '<td>' . esc_html( sprintf( __( '%s per year', 'smokypost' ), $price ) ) . '</td><td>';
			if ( $slug === $current ) {
				echo esc_html__( 'Current plan', 'smokypost' );
			} else {
				self::form_open( 'checkout', true );
				echo '<input type="hidden" name="plan" value="' . esc_attr( $slug ) . '"><button class="button' . ( $subscribed ? '' : ' button-primary' ) . '">' . esc_html( $subscribed ? __( 'Switch to this plan', 'smokypost' ) : __( 'Upgrade', 'smokypost' ) ) . '</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Payments are handled securely by Paddle. Prices are in US dollars, taxes are added at checkout where they apply.', 'smokypost' ) . '</p>';
		if ( $subscribed ) {
			echo '<p class="description">' . esc_html__( 'A plan change applies right away. You will see the exact amount and confirm it before anything is charged.', 'smokypost' ) . '</p><p>';
			self::form_open( 'portal', true );
			echo '<button class="button">' . esc_html__( 'Manage subscription', 'smokypost' ) . '</button></form></p>';
			self::render_refund( isset( $billing['refund'] ) && is_array( $billing['refund'] ) ? $billing['refund'] : array() );
		}
	}

	/**
	 * Cancel and refund from the panel, while the 14-day window is open.
	 *
	 * @param array<string, mixed> $refund Refund state from the service.
	 */
	private static function render_refund( array $refund ) {
		echo '<h2>' . esc_html__( 'Cancel and refund', 'smokypost' ) . '</h2>';
		if ( empty( $refund['eligible'] ) ) {
			echo '<p class="description">' . esc_html__( 'Refunds are available within 14 days of your first payment. You can stop the renewal at any time from Manage subscription.', 'smokypost' ) . '</p>';
			echo '<p class="description">' . esc_html__( 'Questions about a payment? Write to info@smokypost.com.', 'smokypost' ) . '</p>';
			return;
		}
		$until = ! empty( $refund['until'] ) ? date_i18n( get_option( 'date_format' ), (int) strtotime( (string) $refund['until'] ) ) : '';
		if ( '' !== $until ) {
			/* translators: %s: date */
			echo '<p class="description">' . esc_html( sprintf( __( 'You can cancel and get a full refund until %s.', 'smokypost' ), $until ) ) . '</p>';
		}
		echo '<details class="smokypost-confirm"><summary class="button">' . esc_html__( 'Cancel and get a refund', 'smokypost' ) . '</summary><div class="notice notice-warning inline"><p>' . esc_html__( 'Cancel your subscription and get a full refund? Everything you paid is returned to the card you paid with. Nothing is deducted for the days you used. Your plan ends right away and this site goes back to Free.', 'smokypost' ) . '</p><p>';
		self::form_open( 'refund', true );
		echo '<button class="button button-primary">' . esc_html__( 'Yes, cancel and refund', 'smokypost' ) . '</button></form> <button type="button" class="button-link smokypost-confirm-cancel">' . esc_html__( 'Cancel', 'smokypost' ) . '</button></p></div></details>';
		echo '<p class="description">' . esc_html__( 'Questions about a payment? Write to info@smokypost.com.', 'smokypost' ) . '</p>';
	}

	/**
	 * The question shown before a plan change is charged: exact amount, the card
	 * that will be used, and a way to use another card.
	 */
	private static function render_plan_confirmation() {
		$preview = get_transient( 'smokypost_plan_preview_' . get_current_user_id() );
		if ( ! is_array( $preview ) || empty( $preview['plan'] ) ) {
			return;
		}
		$names = array(
			'starter' => __( 'Starter', 'smokypost' ),
			'pro'     => __( 'Pro', 'smokypost' ),
			'agency'  => __( 'Agency', 'smokypost' ),
		);
		$plan  = isset( $names[ $preview['plan'] ] ) ? $names[ $preview['plan'] ] : (string) $preview['plan'];
		$money = ( 'USD' === ( $preview['currency'] ?? 'USD' ) ? '$' : (string) $preview['currency'] . ' ' ) . (string) $preview['amount'];
		$date  = ! empty( $preview['renews_at'] ) ? date_i18n( get_option( 'date_format' ), (int) strtotime( (string) $preview['renews_at'] ) ) : '';
		$up    = 'upgrade' === ( $preview['direction'] ?? '' );

		echo '<div class="notice notice-info inline smokypost-plan-confirm"><p><strong>';
		/* translators: %s: plan name */
		echo esc_html( sprintf( __( 'Switch to the %s plan', 'smokypost' ), $plan ) );
		echo '</strong></p><p>';
		if ( $up ) {
			$card = ! empty( $preview['card']['last4'] ) ? self::card_label( $preview['card'] ) : '';
			if ( '' !== $card ) {
				/* translators: 1: amount, for example $30.00, 2: card, for example Visa ending in 4242 */
				echo esc_html( sprintf( __( 'You will be charged %1$s today on the card you paid with (%2$s).', 'smokypost' ), $money, $card ) );
			} else {
				/* translators: %s: amount, for example $30.00 */
				echo esc_html( sprintf( __( 'You will be charged %s today on the card you paid with.', 'smokypost' ), $money ) );
			}
			if ( '' !== $date ) {
				echo ' ';
				/* translators: %s: date */
				echo esc_html( sprintf( __( 'Your next renewal stays on %s.', 'smokypost' ), $date ) );
			}
		} else {
			echo esc_html__( 'Nothing is charged today.', 'smokypost' );
			echo ' ';
			if ( '' !== $date ) {
				/* translators: %s: date */
				echo esc_html( sprintf( __( 'The price difference is credited at your next renewal on %s.', 'smokypost' ), $date ) );
			} else {
				echo esc_html__( 'The price difference is credited at your next renewal.', 'smokypost' );
			}
		}
		echo '</p><p>' . esc_html__( 'Shall we continue?', 'smokypost' ) . '</p><p>';
		self::form_open( 'confirm_plan', true );
		/* translators: %s: amount, for example $30.00 */
		echo '<button class="button button-primary">' . esc_html( $up ? sprintf( __( 'Yes, charge %s', 'smokypost' ), $money ) : __( 'Yes, switch plan', 'smokypost' ) ) . '</button></form> ';
		self::form_open( 'cancel_plan', true );
		echo '<button class="button">' . esc_html__( 'Cancel', 'smokypost' ) . '</button></form></p>';
		if ( $up ) {
			echo '<p class="description">' . esc_html__( 'Want to pay with another card? Open Manage subscription, update your payment method, then switch plans again.', 'smokypost' ) . '</p><p>';
			self::form_open( 'portal', true );
			echo '<button class="button">' . esc_html__( 'Manage subscription', 'smokypost' ) . '</button></form></p>';
		}
		echo '</div>';
	}

	/**
	 * "Visa ending in 4242".
	 *
	 * @param array<string, string> $card Brand and last four digits.
	 */
	private static function card_label( array $card ) {
		$brands = array(
			'visa'             => 'Visa',
			'mastercard'       => 'Mastercard',
			'american_express' => 'American Express',
			'amex'             => 'American Express',
			'discover'         => 'Discover',
			'diners_club'      => 'Diners Club',
			'jcb'              => 'JCB',
			'maestro'          => 'Maestro',
			'unionpay'         => 'UnionPay',
		);
		$brand = strtolower( (string) ( $card['brand'] ?? '' ) );
		$name  = isset( $brands[ $brand ] ) ? $brands[ $brand ] : '';
		if ( '' === $name ) {
			/* translators: %s: last four digits of a card */
			return sprintf( __( 'ending in %s', 'smokypost' ), $card['last4'] );
		}
		/* translators: 1: card brand, for example Visa, 2: last four digits */
		return sprintf( __( '%1$s ending in %2$s', 'smokypost' ), $name, $card['last4'] );
	}

	/**
	 * @param array<string, mixed> $plan Plan from the service.
	 */
	private static function plan_name( array $plan ) {
		$names = array(
			'free'    => __( 'Free', 'smokypost' ),
			'starter' => __( 'Starter', 'smokypost' ),
			'pro'     => __( 'Pro', 'smokypost' ),
			'agency'  => __( 'Agency', 'smokypost' ),
		);
		$slug = (string) ( $plan['slug'] ?? '' );
		return isset( $names[ $slug ] ) ? $names[ $slug ] : (string) ( $plan['name'] ?? '' );
	}

	/**
	 * @param array<string, mixed> $site Site data.
	 */
	private static function account_warnings( array $site ) {
		foreach ( (array) ( $site['warnings'] ?? array() ) as $w ) {
			if ( 'email_not_verified' !== $w['code'] ) {
				continue;
			}
			$text = ! empty( $w['sharing_paused'] )
				? __( 'Sharing is paused until you confirm your e-mail address.', 'smokypost' )
				/* translators: %d: days left */
				: sprintf( _n( 'Please confirm your e-mail address. Sharing pauses in %d day if you do not.', 'Please confirm your e-mail address. Sharing pauses in %d days if you do not.', (int) $w['days_left'], 'smokypost' ), (int) $w['days_left'] );
			echo '<div class="notice notice-warning inline"><p>' . esc_html( $text ) . ' ';
			self::form_open( 'resend', true );
			echo '<button class="button-link">' . esc_html__( 'Send the link again', 'smokypost' ) . '</button></form></p></div>';
		}
	}

	/**
	 * @param string $platform Platform.
	 * @param string $label    Button text.
	 * @param bool   $link     Render as a link-style button.
	 */
	private static function connect_button( $platform, $label, $link = false ) {
		if ( in_array( $platform, array( 'bluesky', 'mastodon' ), true ) ) {
			self::connect_fields( $platform, $label, $link );
			return;
		}
		self::form_open( 'connect', true );
		echo '<input type="hidden" name="platform" value="' . esc_attr( $platform ) . '"><button class="' . ( $link ? 'button-link' : 'button button-primary' ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Bluesky (handle + app password) and Mastodon (server address) are connected with a few fields
	 * instead of one button.
	 *
	 * @param string $platform Platform.
	 * @param string $label    Button label.
	 * @param bool   $link     Fold the form away behind the label.
	 */
	private static function connect_fields( $platform, $label, $link ) {
		if ( $link ) {
			echo '<details class="smokypost-more"><summary>' . esc_html( $label ) . '</summary>';
		}
		if ( 'bluesky' === $platform ) {
			self::form_open( 'connect_login' );
			echo '<input type="hidden" name="platform" value="bluesky">';
			echo '<p><label>' . esc_html__( 'Bluesky handle', 'smokypost' ) . '<br><input type="text" name="handle" class="regular-text" placeholder="name.bsky.social" autocomplete="off" autocapitalize="off" spellcheck="false"></label></p>';
			echo '<p><label>' . esc_html__( 'App password', 'smokypost' ) . '<br><input type="password" name="password" class="regular-text" placeholder="xxxx-xxxx-xxxx-xxxx" autocomplete="new-password"></label></p>';
			echo '<p class="description">' . esc_html__( 'Use an app password, not your main Bluesky password. It can only be used by apps like this one and you can delete it in Bluesky at any time.', 'smokypost' ) . '</p>';
			echo '<details><summary>' . esc_html__( 'How do I get an app password?', 'smokypost' ) . '</summary><ol class="smokypost-steps">';
			echo '<li>' . wp_kses( sprintf( /* translators: %s: link to the Bluesky app password page */ __( 'Open %s (or Settings > Privacy and security > App passwords in Bluesky).', 'smokypost' ), '<a href="https://bsky.app/settings/app-passwords" target="_blank" rel="noopener">bsky.app/settings/app-passwords</a>' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ) . '</li>';
			echo '<li>' . esc_html__( 'Click "Add App Password" and name it SmokyPost.', 'smokypost' ) . '</li>';
			echo '<li>' . esc_html__( 'Copy the password Bluesky shows and paste it here.', 'smokypost' ) . '</li></ol></details>';
			submit_button( $label, 'primary', 'submit', false );
			echo '</form>';
		} else {
			self::form_open( 'connect' );
			echo '<input type="hidden" name="platform" value="mastodon">';
			echo '<p><label>' . esc_html__( 'Your Mastodon server', 'smokypost' ) . '<br><input type="text" name="instance" class="regular-text" placeholder="mastodon.social" autocomplete="off" autocapitalize="off" spellcheck="false"></label></p>';
			echo '<p class="description">' . esc_html__( 'The address of the server your account is on, for example mastodon.social, or your full name like @name@mastodon.social. You will approve SmokyPost on that server.', 'smokypost' ) . '</p>';
			submit_button( $label, 'primary', 'submit', false );
			echo '</form>';
		}
		if ( $link ) {
			echo '</details>';
		}
	}

	/**
	 * @param string $action Action without prefix.
	 * @param bool   $inline Inline form.
	 * @param string $id     Form id.
	 */
	private static function form_open( $action, $inline = false, $id = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate' . ( $inline ? ' class="smokypost-inline"' : '' ) . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>';
		echo '<input type="hidden" name="action" value="smokypost_' . esc_attr( $action ) . '">';
		wp_nonce_field( 'smokypost_' . $action );
	}

	/* ------------------------------------------------------------------ */
	/* Notices                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * @param string $type    success, error, warning or info.
	 * @param string $message Message.
	 */
	private static function notice( $type, $message ) {
		set_transient( 'smokypost_notice_' . get_current_user_id(), array( $type, $message ), MINUTE_IN_SECONDS );
	}

	private static function flash() {
		$key    = 'smokypost_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! $notice && isset( $_GET['smokypost_oauth'] ) ) {
			$status = sanitize_key( wp_unslash( $_GET['smokypost_oauth'] ) );
			$notice = 'ok' === $status
				? array( 'success', __( 'Signed in. Now choose the account this site shares to.', 'smokypost' ) )
				: ( 'cancelled' === $status
					? array( 'warning', __( 'The sign-in was cancelled.', 'smokypost' ) )
					: array( 'error', __( 'The sign-in did not work. Please try again.', 'smokypost' ) ) );
		}
		// phpcs:enable
		if ( $notice ) {
			delete_transient( $key );
			echo '<div class="notice notice-' . esc_attr( $notice[0] ) . ' is-dismissible"><p>' . esc_html( $notice[1] ) . '</p></div>';
		}
	}

	/**
	 * First-run notice: what SmokyPost shares automatically.
	 */
	private static function welcome() {
		if ( ! get_option( 'smokypost_welcome' ) || ! Api::is_connected() ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Good to know:', 'smokypost' ) . '</strong> ' . esc_html__( 'SmokyPost shares new posts automatically when they are published, including posts created by other plugins, importers or the REST API. You can turn automatic sharing on or off for each platform on its card below or under Settings.', 'smokypost' ) . '</p><div class="smokypost-actions" style="margin-bottom:10px">';
		self::form_open( 'dismiss_welcome', true );
		echo '<button class="button">' . esc_html__( 'Got it', 'smokypost' ) . '</button></form> <a href="' . esc_url( self::url( array( 'tab' => 'settings' ) ) ) . '">' . esc_html__( 'Open settings', 'smokypost' ) . '</a></div></div>';
	}

	/**
	 * Reminder on other admin screens until the site is connected.
	 */
	public static function global_notices() {
		$screen = get_current_screen();
		if ( ! current_user_can( 'manage_options' ) || Api::is_connected() || ( $screen && 'toplevel_page_' . self::SLUG === $screen->id ) ) {
			return;
		}
		if ( $screen && in_array( $screen->id, array( 'plugins', 'dashboard' ), true ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'SmokyPost is almost ready.', 'smokypost' ) . ' <a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Connect this site', 'smokypost' ) . '</a></p></div>';
		}
	}

	/* ------------------------------------------------------------------ */
	/* Form handlers                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * @param string $action Action.
	 */
	private static function verify( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'smokypost' ), 403 );
		}
		check_admin_referer( 'smokypost_' . $action );
	}

	/**
	 * @param string $field Field.
	 */
	private static function field( $field ) {
		// Nonce is verified in verify(). Values are sanitized by each caller; passwords must stay untouched.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return isset( $_POST[ $field ] ) ? trim( (string) wp_unslash( $_POST[ $field ] ) ) : '';
	}

	/**
	 * @param array<string, string> $args Query args.
	 */
	private static function back( array $args = array() ) {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	/**
	 * @param array<string, mixed>|\WP_Error $result   API result.
	 * @param string                         $success  Success message.
	 */
	private static function connected( $result, $success ) {
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
			// Keep the typed address so it does not have to be entered again.
			set_transient( 'smokypost_email_' . get_current_user_id(), sanitize_email( self::field( 'email' ) ), MINUTE_IN_SECONDS );
			self::back();
		}
		Api::save_token( (string) $result['site_token'] );
		self::notice( 'success', $success );
		self::back( array( 'tab' => 'platforms' ) );
	}

	/**
	 * Check the sign-in forms here, so the person gets a precise message.
	 *
	 * @param bool $register Registration form (password length and terms).
	 */
	private static function check_credentials( $register ) {
		$error = '';
		if ( ! is_email( self::field( 'email' ) ) ) {
			$error = __( 'Please enter a valid e-mail address.', 'smokypost' );
		} elseif ( '' === self::field( 'password' ) ) {
			$error = __( 'Please enter your password.', 'smokypost' );
		} elseif ( $register && strlen( self::field( 'password' ) ) < 8 ) {
			$error = __( 'Please use a password with at least 8 characters.', 'smokypost' );
		} elseif ( $register && '1' !== self::field( 'accept_terms' ) ) {
			$error = __( 'Please accept the Terms of Service and the Privacy Policy.', 'smokypost' );
		}
		if ( '' !== $error ) {
			self::connected( new \WP_Error( 'invalid', $error ), '' );
		}
	}

	public static function handle_register() {
		self::verify( 'register' );
		self::check_credentials( true );
		$result = Api::request(
			'POST',
			'/account/register',
			array(
				'email'        => sanitize_email( self::field( 'email' ) ),
				'password'     => self::field( 'password' ),
				'accept_terms' => '1' === self::field( 'accept_terms' ),
				'locale'       => get_locale(),
				'timezone'     => wp_timezone_string(),
				'site'         => Api::site_info(),
			),
			false
		);
		self::connected( $result, __( 'Your account is ready and this site is connected. We sent you an e-mail to confirm your address.', 'smokypost' ) );
	}

	public static function handle_login() {
		self::verify( 'login' );
		self::check_credentials( false );
		$result = Api::request(
			'POST',
			'/account/login',
			array(
				'email'    => sanitize_email( self::field( 'email' ) ),
				'password' => self::field( 'password' ),
				'site'     => Api::site_info(),
			),
			false
		);
		self::connected( $result, __( 'Signed in. This site is connected.', 'smokypost' ) );
	}

	public static function handle_license() {
		self::verify( 'license' );
		if ( '' === self::field( 'license_key' ) ) {
			self::connected( new \WP_Error( 'invalid', __( 'Please enter your license key.', 'smokypost' ) ), '' );
		}
		$result = Api::request(
			'POST',
			'/license/activate',
			array(
				'license_key' => sanitize_text_field( self::field( 'license_key' ) ),
				'site'        => Api::site_info(),
			),
			false
		);
		self::connected( $result, __( 'License activated. This site is connected.', 'smokypost' ) );
	}

	public static function handle_forgot() {
		self::verify( 'forgot' );
		if ( ! is_email( self::field( 'email' ) ) ) {
			self::connected( new \WP_Error( 'invalid', __( 'Please enter a valid e-mail address.', 'smokypost' ) ), '' );
		}
		Api::request( 'POST', '/account/password/forgot', array( 'email' => sanitize_email( self::field( 'email' ) ) ), false );
		self::notice( 'success', __( 'If an account exists for this address, we sent a link to reset the password.', 'smokypost' ) );
		self::back();
	}

	public static function handle_disconnect() {
		self::verify( 'disconnect' );
		Api::request( 'DELETE', '/site?reason=disconnect' );
		Api::forget_token();
		self::notice( 'success', __( 'This site is disconnected from SmokyPost.', 'smokypost' ) );
		self::back();
	}

	public static function handle_clear_history() {
		self::verify( 'clear_history' );
		$data = array( 'all' => true );
		if ( 'range' === self::field( 'scope' ) ) {
			$zone  = wp_timezone();
			// Day, month and year are chosen separately. All three empty means "from the first item" or "until today".
			$parse = static function ( $prefix, $default, $time ) use ( $zone ) {
				$d = (int) self::field( $prefix . '_d' );
				$m = (int) self::field( $prefix . '_m' );
				$y = (int) self::field( $prefix . '_y' );
				if ( ! $d && ! $m && ! $y ) {
					return new \DateTimeImmutable( $default . ' ' . $time, $zone );
				}
				if ( ! $d || ! $m || ! $y || ! checkdate( $m, $d, $y ) ) {
					return null;
				}
				return new \DateTimeImmutable( sprintf( '%04d-%02d-%02d %s', $y, $m, $d, $time ), $zone );
			};
			$from  = $parse( 'from', '1970-01-02', '00:00:00' );
			$to    = $parse( 'to', wp_date( 'Y-m-d' ), '23:59:59' );
			if ( ! $from || ! $to ) {
				self::notice( 'error', __( 'Please choose valid dates.', 'smokypost' ) );
				self::back( array( 'tab' => 'history' ) );
			}
			if ( $from > $to ) {
				self::notice( 'error', __( 'The first date must be before the last date.', 'smokypost' ) );
				self::back( array( 'tab' => 'history' ) );
			}
			$data = array(
				'from' => $from->format( 'c' ),
				'to'   => $to->format( 'c' ),
			);
		}
		$result = Api::request( 'POST', '/shares/clear', $data );
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
		} else {
			$count = (int) $result['cleared'];
			/* translators: %d: number of items */
			self::notice( 'success', $count ? sprintf( _n( 'Cleared %d item from the history.', 'Cleared %d items from the history.', $count, 'smokypost' ), $count ) : __( 'There was nothing to clear. Items that are still being sent stay in the list.', 'smokypost' ) );
		}
		self::back( array( 'tab' => 'history' ) );
	}

	public static function handle_resend() {
		self::verify( 'resend' );
		$result = Api::request( 'POST', '/account/verify/resend' );
		self::notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'We sent the confirmation link again.', 'smokypost' ) );
		self::back( array( 'tab' => 'platforms' ) );
	}

	public static function handle_connect() {
		self::verify( 'connect' );
		$platform = sanitize_key( self::field( 'platform' ) );
		$data     = array( 'return_url' => self::url( array( 'tab' => 'platforms' ) ) );
		if ( 'mastodon' === $platform ) {
			$data['instance'] = sanitize_text_field( self::field( 'instance' ) );
		}
		$result = Api::request( 'POST', '/connections/' . $platform . '/start', $data );
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
			self::back( array( 'tab' => 'platforms' ) );
		}
		// The login page is chosen by the SmokyPost service (for Mastodon: the customer's own server).
		$target = (string) $result['authorize_url'];
		$host   = (string) wp_parse_url( $target, PHP_URL_HOST );
		if ( 'https' === wp_parse_url( $target, PHP_URL_SCHEME ) && '' !== $host ) {
			add_filter(
				'allowed_redirect_hosts',
				static function ( $hosts ) use ( $host ) {
					$hosts[] = $host;
					return $hosts;
				}
			);
		}
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Bluesky: handle and app password, checked by the service.
	 */
	public static function handle_connect_login() {
		self::verify( 'connect_login' );
		$platform = sanitize_key( self::field( 'platform' ) );
		$result   = Api::request(
			'POST',
			'/connections/' . $platform . '/login',
			array(
				'handle'   => sanitize_text_field( self::field( 'handle' ) ),
				'password' => self::field( 'password' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
			self::back( array( 'tab' => 'platforms' ) );
		}
		self::notice( 'success', __( 'Connected. Now choose the account this site shares to.', 'smokypost' ) );
		self::back( array( 'tab' => 'platforms', 'choose' => $platform ) );
	}

	/**
	 * Ask the service what a plan change would cost. A new purchase goes straight
	 * to Paddle's checkout, where the customer enters a card anyway; a change to an
	 * existing subscription first shows the amount and waits for a yes.
	 */
	public static function handle_checkout() {
		self::verify( 'checkout' );
		$result = Api::request( 'POST', '/billing/checkout', array(
			'plan'       => sanitize_key( self::field( 'plan' ) ),
			'return_url' => self::url( array( 'tab' => 'account', 'paid' => 1 ) ),
		) );
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
			self::back( array( 'tab' => 'account' ) );
		}
		if ( ! empty( $result['url'] ) ) {
			wp_safe_redirect( (string) $result['url'] );
			exit;
		}
		if ( ! empty( $result['preview'] ) && is_array( $result['preview'] ) ) {
			set_transient( 'smokypost_plan_preview_' . get_current_user_id(), $result['preview'], 10 * MINUTE_IN_SECONDS );
		}
		self::back( array( 'tab' => 'account' ) );
	}

	public static function handle_confirm_plan() {
		self::verify( 'confirm_plan' );
		$key     = 'smokypost_plan_preview_' . get_current_user_id();
		$preview = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $preview ) || empty( $preview['plan'] ) ) {
			self::notice( 'warning', __( 'That plan change has expired. Please choose the plan again.', 'smokypost' ) );
			self::back( array( 'tab' => 'account' ) );
		}
		$result = Api::request( 'POST', '/billing/checkout', array(
			'plan'    => sanitize_key( (string) $preview['plan'] ),
			'confirm' => true,
		) );
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
			self::back( array( 'tab' => 'account' ) );
		}
		delete_transient( 'smokypost_site' );
		self::notice( 'success', __( 'Your plan is being changed. It can take a minute to show here.', 'smokypost' ) );
		self::back( array( 'tab' => 'account' ) );
	}

	public static function handle_cancel_plan() {
		self::verify( 'cancel_plan' );
		delete_transient( 'smokypost_plan_preview_' . get_current_user_id() );
		self::back( array( 'tab' => 'account' ) );
	}

	public static function handle_refund() {
		self::verify( 'refund' );
		$result = Api::request( 'POST', '/billing/refund' );
		delete_transient( 'smokypost_site' );
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
		} else {
			self::notice( 'success', __( 'Your refund has been requested and your subscription is cancelled. Paddle returns the money to the card you paid with; it can take a few days to show.', 'smokypost' ) );
		}
		self::back( array( 'tab' => 'account' ) );
	}

	public static function handle_portal() {
		self::verify( 'portal' );
		$result = Api::request( 'POST', '/billing/portal' );
		if ( is_wp_error( $result ) || empty( $result['url'] ) ) {
			self::notice( 'error', is_wp_error( $result ) ? $result->get_error_message() : __( 'Something went wrong. Please try again.', 'smokypost' ) );
			self::back( array( 'tab' => 'account' ) );
		}
		wp_safe_redirect( (string) $result['url'] );
		exit;
	}

	public static function handle_select() {
		self::verify( 'select' );
		$platform = sanitize_key( self::field( 'platform' ) );
		$result   = Api::request( 'PUT', '/connections/' . $platform, array( 'target_id' => sanitize_text_field( self::field( 'target_id' ) ) ) );
		delete_transient( 'smokypost_site' );
		/* translators: 1: page name, 2: platform name */
		self::notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : sprintf( __( '%1$s is now connected to %2$s.', 'smokypost' ), $result['target']['name'], Settings::label( $platform ) ) );
		self::back( array( 'tab' => 'platforms' ) );
	}

	public static function handle_unselect() {
		self::verify( 'unselect' );
		$platform = sanitize_key( self::field( 'platform' ) );
		Api::request( 'DELETE', '/connections/' . $platform );
		delete_transient( 'smokypost_site' );
		self::back( array( 'tab' => 'platforms' ) );
	}

	public static function handle_settings() {
		self::verify( 'settings' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above; sanitized in Settings::save().
		Settings::save( wp_unslash( $_POST ) );
		self::notice( 'success', __( 'Settings saved.', 'smokypost' ) );
		self::back( array( 'tab' => 'settings' ) );
	}

	public static function handle_notifications() {
		self::verify( 'notifications' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above; sanitized in Settings.
		$input = wp_unslash( $_POST );
		Settings::save_notifications( $input );
		if ( empty( $input['smokypost_test'] ) ) {
			self::notice( 'success', __( 'Settings saved.', 'smokypost' ) );
			self::back( array( 'tab' => 'notifications' ) );
		}

		// The test saves the form first, so nothing typed is lost when the test fails.
		$to   = Settings::notify_recipients();
		$sent = Mailer::send(
			$to,
			__( 'SmokyPost test e-mail', 'smokypost' ),
			__( 'This is a test e-mail from SmokyPost. If you can read it, e-mails from SmokyPost reach you.', 'smokypost' )
		);
		if ( $sent ) {
			/* translators: %s: e-mail addresses */
			self::notice( 'success', sprintf( __( 'The test e-mail was sent to %s. Check your inbox and spam folder.', 'smokypost' ), implode( ', ', $to ) ) );
		} else {
			$error = Mailer::last_error();
			self::notice(
				'error',
				'' !== $error
					/* translators: %s: error from the mail server */
					? sprintf( __( 'The test e-mail could not be sent: %s Check the server, port and encryption.', 'smokypost' ), $error )
					: __( 'The test e-mail could not be sent. Check the server, port and encryption.', 'smokypost' )
			);
		}
		self::back( array( 'tab' => 'notifications' ) );
	}

	public static function handle_autoshare() {
		self::verify( 'autoshare' );
		$platform = sanitize_key( self::field( 'platform' ) );
		if ( in_array( $platform, Settings::platforms(), true ) ) {
			Settings::set_auto( $platform, '1' === self::field( 'enable' ) );
			self::notice(
				'success',
				'1' === self::field( 'enable' )
					/* translators: %s: platform name */
					? sprintf( __( 'Automatic sharing to %s is on.', 'smokypost' ), Settings::label( $platform ) )
					/* translators: %s: platform name */
					: sprintf( __( 'Automatic sharing to %s is off.', 'smokypost' ), Settings::label( $platform ) )
			);
		}
		self::back( array( 'tab' => 'platforms' ) );
	}

	public static function handle_dismiss_welcome() {
		self::verify( 'dismiss_welcome' );
		delete_option( 'smokypost_welcome' );
		self::back( array( 'tab' => 'platforms' ) );
	}
}
