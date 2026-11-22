<?php
/**
 * E-mail notifications about shares: one e-mail per finished share, with limits so a broken
 * connection cannot flood the inbox.
 *
 * @package SmokyPost
 */

namespace SmokyPost;

defined( 'ABSPATH' ) || exit;

final class Notifier {

	/** Last share id e-mailed for an item. */
	const MAILED_META = '_smokypost_mailed';

	/** Failures older than this are history, not news (for example after a long pause of the site). */
	const MAX_AGE = 3 * DAY_IN_SECONDS;

	/** Codes that mean the platform connection has to be made again. */
	const RECONNECT_CODES = array( 'connection_expired', 'token_invalid', 'token_missing' );

	/**
	 * Called with every share result fetched from the service.
	 *
	 * @param array<string, mixed> $share Share.
	 */
	public static function on_result( array $share ) {
		$targets = array_values(
			array_filter(
				(array) ( $share['targets'] ?? array() ),
				static function ( $t ) {
					return in_array( $t['status'] ?? '', array( 'queued', 'sending', 'ok', 'failed' ), true );
				}
			)
		);
		if ( ! $targets ) {
			return;
		}
		if ( ! Sharer::is_finished( $share ) ) {
			foreach ( $targets as $t ) {
				if ( 'quota_deferred' === ( $t['error']['code'] ?? '' ) ) {
					self::quota_notice();
					break;
				}
			}
			return;
		}

		$post_id = (int) ( $share['post_id'] ?? 0 );
		$id      = (int) ( $share['id'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || $id <= (int) get_post_meta( $post_id, self::MAILED_META, true ) ) {
			return;
		}
		update_post_meta( $post_id, self::MAILED_META, $id );
		if ( self::is_old( $share ) ) {
			return;
		}

		$ok        = array();
		$failed    = array();
		$reconnect = array();
		foreach ( $targets as $t ) {
			if ( 'ok' === $t['status'] ) {
				if ( self::wanted( $t['platform'], 'success', $share ) ) {
					$ok[] = $t;
				}
				continue;
			}
			$code = (string) ( $t['error']['code'] ?? '' );
			if ( ! in_array( $code, self::RECONNECT_CODES, true ) && ! self::wanted( $t['platform'], 'failure', $share ) ) {
				continue;
			}
			// The same problem on the same platform is reported once a day.
			if ( self::throttled( 'fail_' . $t['platform'] . '_' . $code ) ) {
				$failed[] = $t + array( 'quiet' => true );
				continue;
			}
			if ( in_array( $code, self::RECONNECT_CODES, true ) ) {
				$reconnect[] = $t;
			} else {
				$failed[] = $t;
			}
		}

		$sent = false;
		foreach ( $reconnect as $t ) {
			$label = Settings::label( (string) $t['platform'] );
			/* translators: %s: platform name */
			$subject = sprintf( __( 'Connect %s again', 'smokypost' ), $label );
			/* translators: 1: platform name, 2: link */
			$body = sprintf( __( 'The connection to %1$s has expired, so new items cannot be shared there. Connect it again: %2$s', 'smokypost' ), $label, Admin\Page::url( array( 'tab' => 'platforms' ) ) );
			// The SmokyPost service already e-mails the account address about expired connections.
			$sent = self::send( $subject, $body, array( self::account_email() ) ) || $sent;
		}

		$loud = array_filter(
			$failed,
			static function ( $t ) {
				return empty( $t['quiet'] );
			}
		);
		if ( $loud && Settings::get( 'notify_failure' ) ) {
			/* translators: %s: item title */
			$subject = sprintf( __( 'Could not share: %s', 'smokypost' ), self::title( $post ) );
			/* translators: %s: item title */
			$intro = sprintf( __( '"%s" could not be shared everywhere:', 'smokypost' ), self::title( $post ) );
			$sent  = self::send( $subject, $intro . "\n\n" . self::lines( array_merge( $ok, $loud ) ) . "\n\n" . self::links( $post_id ) ) || $sent;
		} elseif ( ! $loud && ! $reconnect && $ok && ! $failed && Settings::get( 'notify_success' ) ) {
			/* translators: %s: item title */
			$subject = sprintf( __( 'Shared: %s', 'smokypost' ), self::title( $post ) );
			/* translators: %s: item title */
			$intro = sprintf( __( '"%s" was shared:', 'smokypost' ), self::title( $post ) );
			self::send( $subject, $intro . "\n\n" . self::lines( $ok ) . "\n\n" . self::links( $post_id ) );
		}
		unset( $sent );
	}

	/**
	 * Add-ons can switch notifications off for a platform.
	 *
	 * @param string               $platform Platform.
	 * @param string               $kind     success or failure.
	 * @param array<string, mixed> $share    Share.
	 */
	private static function wanted( $platform, $kind, array $share ) {
		return (bool) apply_filters( 'smokypost_notify_platform', true, (string) $platform, $kind, $share );
	}

	/**
	 * Once a day at most: the plan's daily limit is reached.
	 */
	private static function quota_notice() {
		if ( ! Settings::get( 'notify_failure' ) || self::throttled( 'quota' ) ) {
			return;
		}
		self::send(
			__( 'SmokyPost: daily limit reached', 'smokypost' ),
			__( 'The daily limit of your plan is reached. The remaining items are shared after midnight.', 'smokypost' )
		);
	}

	/**
	 * @param array<string, mixed> $share Share.
	 */
	private static function is_old( array $share ) {
		$latest = (int) strtotime( (string) ( $share['created_at'] ?? '' ) );
		foreach ( (array) $share['targets'] as $t ) {
			$latest = max( $latest, (int) strtotime( (string) ( $t['sent_at'] ?? '' ) ) );
		}
		return $latest && $latest < time() - self::MAX_AGE;
	}

	/**
	 * True when this key was already used in the last 24 hours; otherwise marks it as used.
	 *
	 * @param string $key Key.
	 */
	private static function throttled( $key ) {
		$name = 'smokypost_mt_' . md5( $key );
		if ( get_transient( $name ) ) {
			return true;
		}
		set_transient( $name, 1, DAY_IN_SECONDS );
		return false;
	}

	/**
	 * @param array<int, array<string, mixed>> $targets Targets.
	 */
	private static function lines( array $targets ) {
		$lines = array();
		foreach ( $targets as $t ) {
			$label = Settings::label( (string) $t['platform'] );
			if ( 'ok' === $t['status'] ) {
				$lines[] = '- ' . $label . ( ! empty( $t['url'] ) ? ': ' . $t['url'] : '' );
			} else {
				$lines[] = '- ' . $label . ': ' . Status::text( $t );
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * @param int $post_id Item.
	 */
	private static function links( $post_id ) {
		/* translators: %s: link */
		$edit = sprintf( __( 'Open the item: %s', 'smokypost' ), admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ) );
		/* translators: %s: link */
		$history = sprintf( __( 'History: %s', 'smokypost' ), Admin\Page::url( array( 'tab' => 'history' ) ) );
		return $edit . "\n" . $history;
	}

	/**
	 * @param \WP_Post $post Item.
	 */
	private static function title( \WP_Post $post ) {
		$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );
		return '' !== $title ? $title : (string) $post->ID;
	}

	/**
	 * The e-mail address of the SmokyPost account, from the cached site data.
	 */
	private static function account_email() {
		$site = Api::site();
		return is_array( $site ) ? (string) ( $site['account']['email'] ?? '' ) : '';
	}

	/**
	 * Send one notification to the chosen addresses, with the footer.
	 *
	 * @param string $subject Subject.
	 * @param string $body    Text.
	 * @param string[] $skip    Addresses that must not get this e-mail.
	 */
	public static function send( $subject, $body, array $skip = array() ) {
		$to = Settings::notify_recipients();
		if ( $skip ) {
			$skip = array_map( 'strtolower', array_filter( $skip ) );
			$to   = array_values(
				array_filter(
					$to,
					static function ( $address ) use ( $skip ) {
						return ! in_array( strtolower( $address ), $skip, true );
					}
				)
			);
		}
		$to = (array) apply_filters( 'smokypost_notify_recipients', $to, $subject );
		if ( ! $to ) {
			return false;
		}
		$site   = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$footer = sprintf(
			/* translators: 1: site name, 2: link */
			__( 'You get this e-mail because SmokyPost notifications are on for %1$s. Change who gets them, or turn them off, here: %2$s', 'smokypost' ),
			$site,
			Admin\Page::url( array( 'tab' => 'notifications' ) )
		);
		return Mailer::send( $to, '[' . $site . '] ' . $subject, $body . "\n\n--\n" . $footer );
	}
}
