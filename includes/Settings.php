<?php
/**
 * Plugin settings.
 *
 * @package SmokyPost
 */

namespace SmokyPost;

defined( 'ABSPATH' ) || exit;

final class Settings {

	const OPTION = 'smokypost_settings';

	/** Platforms in the free plugin. Add-ons can add more through the "smokypost_platforms" filter. */
	const PLATFORMS = array( 'facebook', 'instagram', 'linkedin', 'bluesky', 'mastodon', 'threads', 'tumblr' );

	const DEFAULT_TEMPLATE = "{title}\n\n{excerpt}\n\n{hashtags}";

	/**
	 * Every platform this site can share to.
	 *
	 * @return string[]
	 */
	public static function platforms() {
		return array_values( array_unique( array_map( 'sanitize_key', (array) apply_filters( 'smokypost_platforms', self::PLATFORMS ) ) ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'post_types'         => array( 'post' ),
			'default_platforms'  => self::platforms(),
			'share_programmatic' => true,
			'import_max_age'     => 24,
			'template'           => self::DEFAULT_TEMPLATE,
			'default_image'      => '',
			'hashtags_from_tags' => true,
			'delete_with_post'   => false,
			'notify_success'     => true,
			'notify_failure'     => true,
			'notify_emails'      => '',
			'smtp_enabled'       => false,
			'smtp_host'          => '',
			'smtp_port'          => 587,
			'smtp_secure'        => 'tls',
			'smtp_user'          => '',
			'smtp_pass'          => '',
			'smtp_from'          => '',
			'smtp_from_name'     => '',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * @param string $key Setting.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Validate and store the settings form.
	 *
	 * @param array<string, mixed> $input Raw form input (unslashed).
	 */
	public static function save( array $input ) {
		$types   = array_keys( self::shareable_post_types() );
		$clean   = array(
			'post_types'         => array_values( array_intersect( (array) ( $input['post_types'] ?? array() ), $types ) ),
			'default_platforms'  => array_values( array_intersect( (array) ( $input['default_platforms'] ?? array() ), self::platforms() ) ),
			'share_programmatic' => ! empty( $input['share_programmatic'] ),
			'import_max_age'     => max( 0, min( 720, absint( $input['import_max_age'] ?? 24 ) ) ),
			'template'           => self::clean_template( $input['template'] ?? '' ),
			'default_image'      => esc_url_raw( (string) ( $input['default_image'] ?? '' ) ),
			'hashtags_from_tags' => ! empty( $input['hashtags_from_tags'] ),
			'delete_with_post'   => ! empty( $input['delete_with_post'] ),
		);
		if ( '' === $clean['template'] ) {
			$clean['template'] = self::DEFAULT_TEMPLATE;
		}
		// Keep what other forms store (notifications, SMTP).
		$saved = get_option( self::OPTION, array() );
		update_option( self::OPTION, array_merge( is_array( $saved ) ? $saved : array(), $clean ), false );
	}

	/**
	 * The e-mail addresses that get notifications: the ones typed in, or the site's admin address.
	 *
	 * @param string|null $text Comma separated addresses; null reads the setting.
	 * @return string[]
	 */
	public static function notify_recipients( $text = null ) {
		$out = self::typed_addresses( null === $text ? (string) self::get( 'notify_emails' ) : $text );
		if ( ! $out ) {
			$admin = sanitize_email( (string) get_option( 'admin_email' ) );
			$out   = is_email( $admin ) ? array( $admin ) : array();
		}
		return $out;
	}

	/**
	 * Valid addresses in a comma separated text.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function typed_addresses( $text ) {
		$out = array();
		foreach ( preg_split( '/[\s,;]+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY ) as $address ) {
			$address = sanitize_email( $address );
			if ( is_email( $address ) ) {
				$out[] = $address;
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, 20 );
	}

	/**
	 * Validate and store the Notifications form (e-mail and SMTP settings).
	 *
	 * @param array<string, mixed> $input Raw form input (unslashed).
	 */
	public static function save_notifications( array $input ) {
		$saved = self::all();
		$clean = array(
			'notify_success' => ! empty( $input['notify_success'] ),
			'notify_failure' => ! empty( $input['notify_failure'] ),
			'notify_emails'  => implode( ', ', self::typed_addresses( (string) ( $input['notify_emails'] ?? '' ) ) ),
			'smtp_enabled'   => ! empty( $input['smtp_enabled'] ),
			'smtp_host'      => sanitize_text_field( (string) ( $input['smtp_host'] ?? '' ) ),
			'smtp_port'      => max( 1, min( 65535, absint( $input['smtp_port'] ?? 587 ) ) ),
			'smtp_secure'    => in_array( $input['smtp_secure'] ?? 'tls', array( 'ssl', 'tls', '' ), true ) ? (string) $input['smtp_secure'] : 'tls',
			'smtp_user'      => sanitize_text_field( (string) ( $input['smtp_user'] ?? '' ) ),
			'smtp_from'      => sanitize_email( (string) ( $input['smtp_from'] ?? '' ) ),
			'smtp_from_name' => sanitize_text_field( (string) ( $input['smtp_from_name'] ?? '' ) ),
			// An empty password field keeps the stored password.
			'smtp_pass'      => '' === (string) ( $input['smtp_pass'] ?? '' ) ? $saved['smtp_pass'] : Mailer::encrypt( (string) $input['smtp_pass'] ),
		);
		if ( ! empty( $input['smtp_pass_clear'] ) ) {
			$clean['smtp_pass'] = '';
		}
		$stored = get_option( self::OPTION, array() );
		update_option( self::OPTION, array_merge( is_array( $stored ) ? $stored : array(), $clean ), false );
	}

	/**
	 * Sites that saved their settings before Bluesky and Mastodon existed get the same
	 * "automatic sharing on" default as the other platforms. Nothing is shared until an
	 * account is chosen.
	 */
	public static function add_fediverse_to_defaults() {
		if ( get_option( 'smokypost_bluesky_mastodon_default' ) ) {
			return;
		}
		$saved = get_option( self::OPTION, null );
		if ( is_array( $saved ) && isset( $saved['default_platforms'] ) ) {
			$saved['default_platforms'] = array_values( array_unique( array_merge( (array) $saved['default_platforms'], array( 'bluesky', 'mastodon' ) ) ) );
			update_option( self::OPTION, $saved, false );
		}
		update_option( 'smokypost_bluesky_mastodon_default', '1', false );
	}

	/**
	 * Same for Threads and Tumblr.
	 */
	public static function add_threads_tumblr_to_defaults() {
		if ( get_option( 'smokypost_threads_tumblr_default' ) ) {
			return;
		}
		$saved = get_option( self::OPTION, null );
		if ( is_array( $saved ) && isset( $saved['default_platforms'] ) ) {
			$saved['default_platforms'] = array_values( array_unique( array_merge( (array) $saved['default_platforms'], array( 'threads', 'tumblr' ) ) ) );
			update_option( self::OPTION, $saved, false );
		}
		update_option( 'smokypost_threads_tumblr_default', '1', false );
	}

	/**
	 * Turn automatic sharing to one platform on or off.
	 *
	 * @param string $platform Platform.
	 * @param bool   $on       On.
	 */
	public static function set_auto( $platform, $on ) {
		$all  = self::all();
		$list = array_diff( (array) $all['default_platforms'], array( $platform ) );
		if ( $on ) {
			$list[] = $platform;
		}
		$all['default_platforms'] = array_values( array_intersect( self::platforms(), $list ) );
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Public post types except attachments.
	 *
	 * @return array<string, string> name => label
	 */
	public static function shareable_post_types() {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' !== $type->name ) {
				$out[ $type->name ] = $type->labels->singular_name;
			}
		}
		return $out;
	}

	/**
	 * Message template for a platform. Add-ons can give a platform its own.
	 *
	 * @param string $platform Platform.
	 */
	public static function template_for( $platform ) {
		$all = self::all();
		return (string) apply_filters( 'smokypost_template', $all['template'], $platform );
	}

	public static function label( $platform ) {
		$labels = array(
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'linkedin'  => 'LinkedIn',
			'bluesky'   => 'Bluesky',
			'mastodon'  => 'Mastodon',
			'threads'   => 'Threads',
			'tumblr'    => 'Tumblr',
		);
		$labels = (array) apply_filters( 'smokypost_platform_labels', $labels );
		return isset( $labels[ $platform ] ) ? $labels[ $platform ] : ucfirst( $platform );
	}

	/**
	 * @param mixed $value Raw template.
	 */
	private static function clean_template( $value ) {
		$value = sanitize_textarea_field( (string) $value );
		return mb_substr( trim( $value ), 0, 3000 );
	}
}
