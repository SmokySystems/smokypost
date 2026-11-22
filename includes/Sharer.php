<?php
/**
 * Decides what to share, builds the messages and sends them to SmokyPost.
 *
 * @package SmokyPost
 */

namespace SmokyPost;

defined( 'ABSPATH' ) || exit;

/**
 * Post meta used:
 * - _smokypost_platforms  array  Platforms chosen in the editor (absent = defaults).
 * - _smokypost_message    string Custom text for this item (optional).
 * - _smokypost_shared_at   int    When the item was last sent.
 * - _smokypost_image      string Image chosen for sharing this item (optional URL).
 * - _smokypost_source     string "editor" or "auto": how the item was first published.
 * - _smokypost_auto_done  int    Set once the automatic share on publish ran.
 * - _smokypost_last       array  Last share as returned by the service.
 * - _smokypost_error      string Last error message, when sending failed.
 * - _smokypost_skipped    array  Platforms left out of the last share, platform => reason code.
 */
final class Sharer {

	const SHARE_HOOK = 'smokypost_share_post';
	const DELAY      = 15; // Seconds between publishing and the automatic share.
	const SYNC_HOOK  = 'smokypost_sync';
	const RETRY_HOOK = 'smokypost_retry';
	const REMOVE_HOOK = 'smokypost_remove_post';

	const TRACK_OPTION = 'smokypost_tracking';
	const RETRY_OPTION = 'smokypost_retry_queue';

	public static function init() {
		add_action( 'wp_after_insert_post', array( self::class, 'on_save' ), 20, 4 );
		add_action( self::SHARE_HOOK, array( self::class, 'run_scheduled' ), 10, 2 );
		add_action( 'wp_ajax_nopriv_smokypost_wake', array( self::class, 'wake' ) );
		add_action( 'wp_ajax_smokypost_wake', array( self::class, 'wake' ) );
		add_action( self::SYNC_HOOK, array( self::class, 'sync' ) );
		add_action( 'wp_trash_post', array( self::class, 'on_delete' ) );
		add_action( 'before_delete_post', array( self::class, 'on_delete' ) );
		add_action( self::REMOVE_HOOK, array( self::class, 'run_removal' ), 10, 2 );
		add_action( self::RETRY_HOOK, array( self::class, 'retry' ) );
		add_filter( 'cron_schedules', array( self::class, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
	}

	/**
	 * @param array<string, array<string, mixed>> $schedules Schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function schedules( $schedules ) {
		$schedules['smokypost_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes (SmokyPost)', 'smokypost' ),
		);
		return $schedules;
	}

	/**
	 * First publish of an item: queue the automatic share a few seconds later,
	 * after the editor has saved everything (meta boxes included).
	 *
	 * @param int           $post_id     Post id.
	 * @param \WP_Post      $post        Post.
	 * @param bool          $update      Update or new.
	 * @param \WP_Post|null $post_before Post before the save.
	 */
	public static function on_save( $post_id, $post, $update, $post_before ) {
		if ( 'publish' !== $post->post_status || ( $post_before && 'publish' === $post_before->post_status ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || get_post_meta( $post_id, '_smokypost_auto_done', true ) ) {
			return;
		}
		if ( ! Api::is_connected() || ! self::is_shareable( $post ) ) {
			return;
		}
		if ( '' === (string) get_post_meta( $post_id, '_smokypost_source', true ) ) {
			update_post_meta( $post_id, '_smokypost_source', self::from_editor() ? 'editor' : 'auto' );
		}
		if ( ! wp_next_scheduled( self::SHARE_HOOK, array( $post_id, 'publish' ) ) ) {
			wp_schedule_single_event( time() + self::DELAY, self::SHARE_HOOK, array( $post_id, 'publish' ) );
			self::wake_later( (int) $post_id );
		}
	}

	/**
	 * WP-Cron only runs when the site is visited or a server cron calls it, so on a quiet
	 * site the scheduled share could wait many minutes. Right after the request ends, ask
	 * the site itself (without waiting for the answer) to share the item once the delay is over.
	 * If this request cannot be made, the scheduled cron event still shares the item.
	 *
	 * @param int $post_id Post id.
	 */
	private static function wake_later( $post_id ) {
		$running = (int) get_transient( 'smokypost_wakes' );
		if ( $running >= 3 ) { // Many items at once (an import): leave them to cron.
			return;
		}
		set_transient( 'smokypost_wakes', $running + 1, self::DELAY + 15 );
		add_action(
			'shutdown',
			static function () use ( $post_id ) {
				wp_remote_post(
					admin_url( 'admin-ajax.php' ),
					array(
						'timeout'   => 0.01,
						'blocking'  => false,
						'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
						'body'      => array(
							'action'  => 'smokypost_wake',
							'post_id' => $post_id,
							'token'   => self::wake_token( $post_id ),
						),
					)
				);
			}
		);
	}

	/**
	 * @param int $post_id Post id.
	 * @return string
	 */
	private static function wake_token( $post_id ) {
		return hash_hmac( 'sha256', 'smokypost_wake|' . (int) $post_id, wp_salt( 'auth' ) );
	}

	/**
	 * Loopback request from wake_later(): wait until the delay is over, then share.
	 */
	public static function wake() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Signed token below.
		$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $post_id || ! hash_equals( self::wake_token( $post_id ), $token ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( self::DELAY + 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$args = array( $post_id, 'publish' );
		$due  = wp_next_scheduled( self::SHARE_HOOK, $args );
		if ( $due && $due > time() ) {
			sleep( min( self::DELAY + 2, max( 0, $due - time() + 1 ) ) );
		}
		if ( wp_next_scheduled( self::SHARE_HOOK, $args ) ) {
			wp_clear_scheduled_hook( self::SHARE_HOOK, $args );
			self::share( $post_id, 'publish' );
		}
		wp_die();
	}

	/**
	 * @param int    $post_id Post id.
	 * @param string $trigger "publish" or "manual".
	 */
	public static function run_scheduled( $post_id, $trigger = 'publish' ) {
		self::share( (int) $post_id, (string) $trigger );
	}

	/**
	 * Share an item now.
	 *
	 * @param int        $post_id   Post id.
	 * @param string     $trigger   "publish" (automatic) or "manual".
	 * @param array|null $platforms Platforms; null = from the editor choice or the defaults.
	 * @return array<string, mixed>|\WP_Error|null Share, error, or null when there is nothing to do.
	 */
	public static function share( $post_id, $trigger = 'manual', $platforms = null ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || ! self::is_shareable( $post ) ) {
			return null;
		}
		if ( 'publish' === $trigger ) {
			if ( get_post_meta( $post_id, '_smokypost_auto_done', true ) ) {
				return null;
			}
			update_post_meta( $post_id, '_smokypost_auto_done', time() );
			if ( self::is_old_import( $post ) ) {
				return null;
			}
		}

		$connected = Api::connected_targets();
		if ( null === $platforms ) {
			$platforms = self::chosen_platforms( $post );
		}
		$platforms = array_values( array_intersect( (array) $platforms, array_keys( $connected ) ) );

		$payload = self::payload( $post, $platforms );
		$skipped = array();
		if ( in_array( 'instagram', $platforms, true ) && '' === $payload['image_url'] ) {
			$skipped['instagram'] = 'image_required';
		}
		if ( $skipped ) {
			update_post_meta( $post_id, '_smokypost_skipped', $skipped );
		} else {
			delete_post_meta( $post_id, '_smokypost_skipped' );
		}
		if ( ! $payload['platforms'] ) {
			if ( $skipped ) {
				update_post_meta( $post_id, '_smokypost_error', self::no_image_message() );
			}
			return null;
		}
		$when = self::scheduled_time( $post_id );
		if ( $when ) {
			$payload['scheduled_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $when );
		}
		$payload['idempotency_key'] = 'publish' === $trigger ? 'publish-' . $post_id : $trigger . '-' . $post_id . '-' . time();
		$payload['trigger']         = $trigger;

		$result = Api::request( 'POST', '/shares', $payload );
		if ( is_wp_error( $result ) ) {
			update_post_meta( $post_id, '_smokypost_error', $result->get_error_message() );
			if ( 'unreachable' === $result->get_error_code() ) {
				self::queue_retry( $post_id, $trigger, $platforms );
			}
			return $result;
		}

		delete_post_meta( $post_id, '_smokypost_error' );
		delete_post_meta( $post_id, '_smokypost_retries' );
		delete_post_meta( $post_id, '_smokypost_share_at' );
		update_post_meta( $post_id, '_smokypost_shared_at', time() );
		update_post_meta( $post_id, '_smokypost_last', $result['share'] );
		self::track( (int) $result['share']['id'] );
		delete_transient( 'smokypost_site' );
		/**
		 * An item was sent to the service.
		 *
		 * @param int                  $post_id Post id.
		 * @param array<string, mixed> $share   The share as the service returned it.
		 * @param string               $trigger "publish", "manual" or another trigger.
		 */
		do_action( 'smokypost_shared', $post_id, $result['share'], $trigger );
		return $result['share'];
	}

	/**
	 * The request body for POST /shares.
	 *
	 * @param \WP_Post $post      Post.
	 * @param string[] $platforms Wanted platforms.
	 * @return array<string, mixed>
	 */
	public static function payload( \WP_Post $post, array $platforms ) {
		$image  = self::image_url( $post );
		$custom = trim( (string) get_post_meta( $post->ID, '_smokypost_message', true ) );
		$vars   = self::variables( $post );

		$messages = array();
		foreach ( $platforms as $platform ) {
			if ( 'instagram' === $platform && '' === $image ) {
				continue;
			}
			$template              = '' !== $custom ? $custom : Settings::template_for( $platform );
			$messages[ $platform ] = (string) apply_filters( 'smokypost_message', self::render( $template, $vars ), $platform, $post, $vars );
		}

		$payload = array(
			'post_id'     => $post->ID,
			'url'         => $vars['{url}'],
			'title'       => $vars['{title}'],
			'description' => trim( (string) preg_replace( '/\s+/u', ' ', $vars['{excerpt}'] ) ),
			'image_url'   => $image,
			'platforms'   => array_keys( $messages ),
			'messages'    => $messages,
		);
		/**
		 * The request body for POST /shares, before it is sent.
		 *
		 * @param array<string, mixed> $payload   Request body.
		 * @param \WP_Post             $post      Post.
		 * @param string[]             $platforms Wanted platforms.
		 */
		return (array) apply_filters( 'smokypost_payload', $payload, $post, $platforms );
	}

	/**
	 * Fill a template. Empty lines left by empty placeholders are removed.
	 *
	 * @param string                $template Template.
	 * @param array<string, string> $vars     Placeholder => value.
	 */
	public static function render( $template, array $vars ) {
		$text = strtr( $template, $vars );
		$text = preg_replace( "/[ \t]+\n/", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", (string) $text );
		return trim( (string) $text );
	}

	/**
	 * @param \WP_Post $post Post.
	 * @return array<string, string>
	 */
	public static function variables( \WP_Post $post ) {
		$excerpt = has_excerpt( $post ) ? $post->post_excerpt : self::trim_words( self::plain_text( self::rendered_content( $post ) ), 40 );
		$tags    = array();
		if ( Settings::get( 'hashtags_from_tags' ) ) {
			foreach ( (array) get_the_terms( $post, 'post_tag' ) as $term ) {
				if ( $term instanceof \WP_Term ) {
					$tag = preg_replace( '/[^\p{L}\p{N}_]+/u', '', $term->name );
					if ( '' !== $tag ) {
						$tags[] = '#' . $tag;
					}
				}
			}
		}
		$cats = array();
		foreach ( (array) get_the_terms( $post, 'category' ) as $term ) {
			if ( $term instanceof \WP_Term && 'uncategorized' !== $term->slug ) {
				$cats[] = $term->name;
			}
		}
		return array(
			'{title}'      => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'{excerpt}'    => html_entity_decode( trim( wp_strip_all_tags( $excerpt ) ), ENT_QUOTES, 'UTF-8' ),
			'{url}'        => (string) get_permalink( $post ),
			'{hashtags}'   => implode( ' ', array_unique( $tags ) ),
			'{categories}' => implode( ', ', $cats ),
			'{site_name}'  => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ),
			'{author}'     => (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
			'{date}'       => get_the_date( '', $post ),
		);
	}

	/**
	 * Featured image, else the first image in the content, else the default image.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function image_url( \WP_Post $post ) {
		return self::image( $post )['url'];
	}

	/**
	 * The image that is shared and where it comes from.
	 *
	 * @param \WP_Post $post Post.
	 * @return array{url: string, source: string} source: custom, featured, content, default or none.
	 */
	public static function image( \WP_Post $post ) {
		$candidates = array(
			'custom'   => (string) get_post_meta( $post->ID, '_smokypost_image', true ),
			'featured' => (string) get_the_post_thumbnail_url( $post, 'full' ),
		);
		$found = self::content_images( $post );
		if ( $found ) {
			$candidates['content'] = $found[0];
		}
		$candidates['default'] = (string) Settings::get( 'default_image' );
		foreach ( $candidates as $source => $url ) {
			if ( 0 === strpos( $url, 'http' ) ) {
				return array(
					'url'    => $url,
					'source' => $source,
				);
			}
		}
		return array(
			'url'    => '',
			'source' => 'none',
		);
	}

	/**
	 * The content as visitors see it: blocks, shortcodes (WPBakery, Divi…) and
	 * Elementor layouts rendered. Falls back to the stored content.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function rendered_content( \WP_Post $post ) {
		static $cache = array();
		$key = $post->ID . ':' . md5( $post->post_content . $post->post_modified_gmt );
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$html = '';
		try {
			if ( 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->frontend ) ) {
				$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post->ID, true );
			}
			if ( '' === trim( $html ) ) {
				$previous        = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
				$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				setup_postdata( $post );
				$html = (string) apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
				$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				if ( $previous instanceof \WP_Post ) {
					setup_postdata( $previous );
				}
			}
		} catch ( \Throwable $e ) {
			$html = '';
		}
		if ( '' === trim( $html ) ) {
			$html = do_blocks( $post->post_content );
		}
		$cache[ $key ] = $html;
		return $html;
	}

	/**
	 * Readable text from HTML: no tags, scripts, styles or leftover shortcodes.
	 *
	 * @param string $html HTML.
	 */
	public static function plain_text( $html ) {
		$html = preg_replace( '#<(script|style|noscript|svg)\b[^>]*>.*?</\1>#is', ' ', (string) $html );
		// Block ends become paragraph breaks, so paragraphs do not run together.
		$html = preg_replace( '#<br\s*/?>#i', "\n", (string) $html );
		$html = preg_replace( '#</(p|div|h[1-6]|li|section|figure|blockquote|ul|ol|table|tr)>#i', "$0\n\n", (string) $html );
		$text = wp_strip_all_tags( strip_shortcodes( (string) $html ) );
		$text = preg_replace( '/\[\/?[a-z][\w-]*[^\]]*\]/i', ' ', $text );
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		$text = str_replace( array( "\xC2\xA0", "\r" ), array( ' ', '' ), (string) $text );
		$text = preg_replace( '/[ \t]+/u', ' ', (string) $text );
		$text = preg_replace( '/ ?\n ?/u', "\n", (string) $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", (string) $text );
		return trim( (string) $text );
	}

	/**
	 * Shorten text to a number of words and keep its paragraph breaks.
	 *
	 * @param string $text  Plain text with line breaks.
	 * @param int    $words Maximum number of words.
	 */
	public static function trim_words( $text, $words = 40 ) {
		$parts = preg_split( '/(\s+)/u', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$out   = '';
		$count = 0;
		foreach ( (array) $parts as $part ) {
			if ( preg_match( '/^\s+$/u', $part ) ) {
				$out .= $part;
				continue;
			}
			if ( $count >= $words ) {
				return rtrim( $out ) . '…';
			}
			$out .= $part;
			++$count;
		}
		return trim( $out );
	}

	/**
	 * Image URLs in the content as visitors see it, in order, without duplicates.
	 * Lazy-load attributes are read too; SVG and data: images are left out.
	 *
	 * @param \WP_Post $post Post.
	 * @return string[]
	 */
	public static function content_images( \WP_Post $post ) {
		$out = array();
		foreach ( array( self::rendered_content( $post ), $post->post_content ) as $html ) {
			if ( ! preg_match_all( '/<img\b[^>]*>/i', (string) $html, $tags ) ) {
				continue;
			}
			foreach ( $tags[0] as $tag ) {
				$src = '';
				foreach ( array( 'data-lazy-src', 'data-src', 'data-original', 'src' ) as $attr ) {
					if ( preg_match( '/\s' . $attr . '=["\']([^"\']+)["\']/i', $tag, $m ) && preg_match( '#^(https?:)?//#i', trim( $m[1] ) ) ) {
						$src = trim( $m[1] );
						break;
					}
				}
				if ( '' === $src ) {
					continue;
				}
				if ( 0 === strpos( $src, '//' ) ) {
					$src = ( is_ssl() ? 'https:' : 'http:' ) . $src;
				}
				$src = esc_url_raw( html_entity_decode( $src, ENT_QUOTES ) );
				if ( '' === $src || preg_match( '#\.svg(\?|$)#i', $src ) || in_array( $src, $out, true ) ) {
					continue;
				}
				$out[] = $src;
				if ( count( $out ) >= 12 ) {
					return $out;
				}
			}
		}
		return $out;
	}

	/**
	 * Shown wherever Instagram is left out because the item has no image.
	 */
	public static function no_image_message() {
		return __( 'Not shared to Instagram: Instagram needs an image, and this item has none. Add a featured image or an image in the content.', 'smokypost' );
	}

	/**
	 * The time chosen for this item as a Unix time, when it lies ahead and the plan allows it.
	 *
	 * @param int $post_id Post id.
	 * @return int 0 when the item is shared right away.
	 */
	public static function scheduled_time( $post_id ) {
		$text = (string) get_post_meta( $post_id, '_smokypost_share_at', true );
		if ( '' === $text || ! Api::has_feature( 'scheduling' ) ) {
			return 0;
		}
		$ts = self::parse_site_time( $text );
		return $ts > time() + 60 ? $ts : 0;
	}

	/**
	 * "2026-11-20 18:30" in the site's time zone to a Unix time.
	 *
	 * @param string $text Date and time.
	 * @return int 0 when it is not a valid date.
	 */
	public static function parse_site_time( $text ) {
		$text = trim( (string) $text );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})$/', $text, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) || (int) $m[4] > 23 || (int) $m[5] > 59 ) {
			return 0;
		}
		try {
			$date = new \DateTimeImmutable( $m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4] . ':' . $m[5] . ':00', wp_timezone() );
		} catch ( \Exception $e ) {
			return 0;
		}
		return $date->getTimestamp();
	}

	/**
	 * When the item's last share is due, if it still waits for its time.
	 *
	 * @param int $post_id Post id.
	 * @return int Unix time, 0 when nothing is waiting.
	 */
	public static function waiting_since_schedule( $post_id ) {
		$last = get_post_meta( $post_id, '_smokypost_last', true );
		if ( ! is_array( $last ) || empty( $last['scheduled_at'] ) ) {
			return 0;
		}
		$due = (int) strtotime( (string) $last['scheduled_at'] );
		if ( $due <= time() ) {
			return 0;
		}
		foreach ( (array) $last['targets'] as $t ) {
			if ( 'queued' === $t['status'] ) {
				return $due;
			}
		}
		return 0;
	}

	/**
	 * Platforms for "Share now" from the post list: the item's own choice, else
	 * every connected platform (automatic sharing settings do not apply).
	 *
	 * @param \WP_Post $post Post.
	 * @return string[]
	 */
	public static function manual_platforms( \WP_Post $post ) {
		if ( metadata_exists( 'post', $post->ID, '_smokypost_platforms' ) ) {
			$chosen = array_values( array_diff( (array) get_post_meta( $post->ID, '_smokypost_platforms', true ), array( Editor::NONE ) ) );
			if ( $chosen ) {
				return $chosen;
			}
		}
		return Settings::platforms();
	}

	/**
	 * Platforms for an automatic share.
	 *
	 * @param \WP_Post $post Post.
	 * @return string[]
	 */
	public static function chosen_platforms( \WP_Post $post ) {
		if ( metadata_exists( 'post', $post->ID, '_smokypost_platforms' ) ) {
			return array_values( array_filter( (array) get_post_meta( $post->ID, '_smokypost_platforms', true ) ) );
		}
		if ( 'auto' === get_post_meta( $post->ID, '_smokypost_source', true ) && ! Settings::get( 'share_programmatic' ) ) {
			return array();
		}
		return (array) Settings::get( 'default_platforms' );
	}

	/**
	 * @param \WP_Post $post Post.
	 */
	public static function is_shareable( \WP_Post $post ) {
		return in_array( $post->post_type, (array) Settings::get( 'post_types' ), true )
			&& '' === $post->post_password
			&& 'publish' === get_post_status( $post );
	}

	/**
	 * Items created by an importer with an old date are not shared automatically.
	 *
	 * @param \WP_Post $post Post.
	 */
	private static function is_old_import( \WP_Post $post ) {
		$max = (int) Settings::get( 'import_max_age' );
		if ( $max <= 0 ) {
			return false;
		}
		$age = time() - (int) get_post_time( 'U', true, $post );
		return $age > $max * HOUR_IN_SECONDS && ( defined( 'WP_IMPORTING' ) || 'auto' === get_post_meta( $post->ID, '_smokypost_source', true ) );
	}

	/**
	 * Whether the current request is a person working in wp-admin.
	 */
	private static function from_editor() {
		if ( ! is_user_logged_in() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || defined( 'WP_IMPORTING' ) ) {
			return false;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return true;
		}
		$referer = (string) wp_get_referer();
		return defined( 'REST_REQUEST' ) && REST_REQUEST && false !== strpos( $referer, '/wp-admin/' );
	}

	/* ------------------------------------------------------------------ */
	/* Removal                                                             */
	/* ------------------------------------------------------------------ */

	/** @var array<int, bool> Items whose removal was already asked for in this request. */
	private static $removal_asked = array();

	/**
	 * An item went to the trash or was deleted: ask SmokyPost to take its posts down,
	 * a few seconds later so the admin screen does not wait for the platforms.
	 *
	 * @param int $post_id Post id.
	 */
	public static function on_delete( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! Settings::get( 'delete_with_post' ) || ! Api::is_connected() ) {
			return;
		}
		$last = get_post_meta( $post_id, '_smokypost_last', true );
		if ( ! is_array( $last ) || ! self::has_removable( $last ) ) {
			return;
		}
		// Shared again if the item is restored and published once more.
		delete_post_meta( $post_id, '_smokypost_auto_done' );
		if ( ! wp_next_scheduled( self::REMOVE_HOOK, array( $post_id, 1 ) ) ) {
			wp_schedule_single_event( time() + 5, self::REMOVE_HOOK, array( $post_id, 1 ) );
		}
		// Do not wait for WP-Cron, which some sites run rarely: ask SmokyPost as soon as this request ends.
		if ( ! isset( self::$removal_asked[ $post_id ] ) ) {
			self::$removal_asked[ $post_id ] = true;
			add_action(
				'shutdown',
				static function () use ( $post_id ) {
					if ( wp_next_scheduled( self::REMOVE_HOOK, array( $post_id, 1 ) ) ) {
						wp_clear_scheduled_hook( self::REMOVE_HOOK, array( $post_id, 1 ) );
						self::run_removal( $post_id );
					}
				}
			);
		}
	}

	/**
	 * Whether a share still has a post that SmokyPost can take down.
	 *
	 * @param array<string, mixed> $share Share.
	 */
	public static function has_removable( array $share ) {
		foreach ( (array) ( $share['targets'] ?? array() ) as $t ) {
			if ( 'ok' === $t['status'] && 'unsupported' !== ( $t['removal'] ?? '' ) ) {
				return true;
			}
			if ( in_array( $t['status'], array( 'queued', 'sending', 'removal_queued', 'removing' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * What "Delete shared posts" would do for an item: platforms whose post SmokyPost takes
	 * down, and platforms where the post has to be deleted in the app (Instagram does not
	 * allow other apps to delete posts).
	 *
	 * @param int $post_id Post id.
	 * @return array<string, string[]>|null Labels under "delete" and "manual"; null when there is nothing to delete.
	 */
	public static function removal_plan( $post_id ) {
		$last = get_post_meta( (int) $post_id, '_smokypost_last', true );
		if ( ! is_array( $last ) ) {
			return null;
		}
		/**
		 * Platforms whose posts cannot be deleted by SmokyPost.
		 *
		 * @param string[] $platforms Platform slugs.
		 */
		$by_hand = (array) apply_filters( 'smokypost_undeletable_platforms', array( 'instagram' ) );
		$delete  = array();
		$manual  = array();
		foreach ( (array) ( $last['targets'] ?? array() ) as $t ) {
			$platform = (string) $t['platform'];
			$label    = Settings::label( $platform );
			if ( 'queued' === $t['status'] ) {
				$delete[ $platform ] = $label;
			} elseif ( 'ok' === $t['status'] ) {
				if ( 'unsupported' === ( $t['removal'] ?? '' ) || in_array( $platform, $by_hand, true ) ) {
					$manual[ $platform ] = $label;
				} else {
					$delete[ $platform ] = $label;
				}
			}
		}
		if ( ! $delete ) {
			return null;
		}
		return array(
			'delete' => array_values( $delete ),
			'manual' => array_values( $manual ),
		);
	}

	/**
	 * @param int $post_id Post id.
	 * @param int $attempt 1 to 6; SmokyPost is tried again hourly when it cannot be reached.
	 * @return array<string, mixed>|\WP_Error What SmokyPost answered.
	 */
	public static function run_removal( $post_id, $attempt = 1 ) {
		$result = Api::request( 'POST', '/shares/remove', array( 'post_id' => (int) $post_id ) );
		if ( is_wp_error( $result ) ) {
			if ( (int) $attempt < 6 ) {
				wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::REMOVE_HOOK, array( (int) $post_id, (int) $attempt + 1 ) );
			}
			return $result;
		}
		foreach ( (array) ( $result['shares'] ?? array() ) as $share ) {
			if ( get_post_status( (int) $share['post_id'] ) ) {
				self::store_result( $share );
			}
			if ( ! self::is_finished( $share ) ) {
				self::track( (int) $share['id'] );
			}
		}
		return $result;
	}

	/* ------------------------------------------------------------------ */
	/* Status sync                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * @param int $share_id Share id.
	 */
	private static function track( $share_id ) {
		$ids   = (array) get_option( self::TRACK_OPTION, array() );
		$ids[] = $share_id;
		update_option( self::TRACK_OPTION, array_values( array_unique( array_map( 'intval', $ids ) ) ), false );
		if ( ! wp_next_scheduled( self::SYNC_HOOK ) ) {
			wp_schedule_event( time() + 60, 'smokypost_five_minutes', self::SYNC_HOOK );
		}
	}

	/**
	 * Fetch results of shares that are not finished yet.
	 */
	public static function sync() {
		$ids = (array) get_option( self::TRACK_OPTION, array() );
		if ( ! $ids || ! Api::is_connected() ) {
			wp_clear_scheduled_hook( self::SYNC_HOOK );
			return;
		}
		$since  = (string) get_option( 'smokypost_last_sync', gmdate( 'c', time() - DAY_IN_SECONDS ) );
		$result = Api::request( 'GET', '/shares', array( 'since' => $since ) );
		if ( is_wp_error( $result ) ) {
			return;
		}
		update_option( 'smokypost_last_sync', gmdate( 'c', strtotime( (string) $result['server_time'] ) - 60 ), false );
		foreach ( (array) $result['shares'] as $share ) {
			self::store_result( $share );
			if ( self::is_finished( $share ) ) {
				$ids = array_diff( $ids, array( (int) $share['id'] ) );
			}
		}
		update_option( self::TRACK_OPTION, array_values( $ids ), false );
		if ( ! $ids ) {
			wp_clear_scheduled_hook( self::SYNC_HOOK );
		}
	}

	/**
	 * @param array<string, mixed> $share Share from the service.
	 */
	public static function store_result( array $share ) {
		$post_id = (int) $share['post_id'];
		$last    = get_post_meta( $post_id, '_smokypost_last', true );
		if ( ! is_array( $last ) || (int) $last['id'] <= (int) $share['id'] ) {
			update_post_meta( $post_id, '_smokypost_last', $share );
		}
		Notifier::on_result( $share );
	}

	/**
	 * @param array<string, mixed> $share Share.
	 */
	public static function is_finished( array $share ) {
		foreach ( (array) $share['targets'] as $t ) {
			if ( in_array( $t['status'], array( 'queued', 'sending', 'removal_queued', 'removing' ), true ) ) {
				return false;
			}
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Offline retry                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Keep the share when SmokyPost could not be reached, and try again hourly.
	 *
	 * @param int      $post_id   Post id.
	 * @param string   $trigger   Trigger.
	 * @param string[] $platforms Platforms.
	 */
	private static function queue_retry( $post_id, $trigger, array $platforms ) {
		$attempts = (int) get_post_meta( $post_id, '_smokypost_retries', true ) + 1;
		update_post_meta( $post_id, '_smokypost_retries', $attempts );
		if ( $attempts > 24 ) {
			// A day of failed attempts: give up; the error stays visible in the post list.
			return;
		}
		$queue             = (array) get_option( self::RETRY_OPTION, array() );
		$queue[ $post_id ] = array(
			'trigger'   => $trigger,
			'platforms' => $platforms,
		);
		update_option( self::RETRY_OPTION, $queue, false );
		if ( 'publish' === $trigger ) {
			delete_post_meta( $post_id, '_smokypost_auto_done' );
		}
		if ( ! wp_next_scheduled( self::RETRY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::RETRY_HOOK );
		}
	}

	public static function retry() {
		$queue = (array) get_option( self::RETRY_OPTION, array() );
		update_option( self::RETRY_OPTION, array(), false );
		foreach ( $queue as $post_id => $item ) {
			self::share( (int) $post_id, $item['trigger'], 'publish' === $item['trigger'] ? null : $item['platforms'] );
		}
		if ( ! get_option( self::RETRY_OPTION ) ) {
			wp_clear_scheduled_hook( self::RETRY_HOOK );
		}
	}
}
