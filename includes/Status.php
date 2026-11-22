<?php
/**
 * Share status shown in the editor, the post list and the history.
 *
 * @package SmokyPost
 */

namespace SmokyPost;

defined( 'ABSPATH' ) || exit;

final class Status {

	/**
	 * Per-platform lines for a share returned by the service.
	 *
	 * @param array<string, mixed> $share Share.
	 * @return array<int, array<string, string>>
	 */
	public static function summary( array $share ) {
		$out = array();
		foreach ( (array) ( $share['targets'] ?? array() ) as $t ) {
			$out[] = array(
				'platform' => (string) $t['platform'],
				'label'    => Settings::label( (string) $t['platform'] ),
				'status'   => (string) $t['status'],
				'text'     => self::text( $t ),
				// A removed post has no page to open any more.
				'url'      => in_array( $t['status'], array( 'removed', 'removal_queued', 'removing' ), true ) ? '' : (string) ( $t['url'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Status lines for an item: the last share plus platforms that were left out.
	 *
	 * @param int $post_id Post id.
	 * @return array<int, array<string, string>>
	 */
	public static function for_post( $post_id ) {
		$last  = get_post_meta( $post_id, '_smokypost_last', true );
		$lines = is_array( $last ) ? self::summary( $last ) : array();
		foreach ( (array) get_post_meta( $post_id, '_smokypost_skipped', true ) as $platform => $code ) {
			if ( ! is_string( $platform ) || '' === $platform ) {
				continue;
			}
			$lines = array_values(
				array_filter(
					$lines,
					static function ( $line ) use ( $platform ) {
						return $line['platform'] !== $platform;
					}
				)
			);
			$lines[] = array(
				'platform' => $platform,
				'label'    => Settings::label( $platform ),
				'status'   => 'skipped',
				'text'     => 'image_required' === $code ? __( 'Not shared: needs an image', 'smokypost' ) : __( 'Not shared', 'smokypost' ),
				'url'      => '',
			);
		}
		return $lines;
	}

	/**
	 * @param array<string, mixed> $t Share target.
	 */
	public static function text( array $t ) {
		$error = is_array( $t['error'] ?? null ) ? $t['error'] : null;
		switch ( $t['status'] ) {
			case 'ok':
				return 'unsupported' === ( $t['removal'] ?? '' ) ? __( 'Shared (delete it in the app yourself)', 'smokypost' ) : __( 'Shared', 'smokypost' );
			case 'failed':
				return $error ? sprintf( /* translators: %s: reason */ __( 'Failed: %s', 'smokypost' ), self::reason( $error ) ) : __( 'Failed', 'smokypost' );
			case 'cancelled':
				return __( 'Cancelled', 'smokypost' );
			case 'removal_queued':
			case 'removing':
				return __( 'Removing…', 'smokypost' );
			case 'removed':
				return __( 'Removed', 'smokypost' );
			case 'removal_failed':
				return $error ? sprintf( /* translators: %s: reason */ __( 'Could not be removed: %s', 'smokypost' ), self::reason( $error ) ) : __( 'Could not be removed', 'smokypost' );
			case 'sending':
				return __( 'Sending…', 'smokypost' );
		}
		if ( $error && 'quota_deferred' === $error['code'] ) {
			return __( 'Waiting: daily limit reached, shares after midnight', 'smokypost' );
		}
		if ( $error && 'email_not_verified' === $error['code'] ) {
			return __( 'Waiting: please confirm your e-mail address', 'smokypost' );
		}
		if ( ! empty( $t['next_attempt_at'] ) && strtotime( (string) $t['next_attempt_at'] ) > time() + 90 ) {
			return sprintf(
				/* translators: %s: date and time */
				__( 'Scheduled for %s', 'smokypost' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( (string) $t['next_attempt_at'] ) )
			);
		}
		return $error ? __( 'Retrying soon', 'smokypost' ) : __( 'Queued', 'smokypost' );
	}

	/**
	 * The reason for a failed share, in the site's language. The service sends a
	 * code with an English message; known codes are translated here.
	 *
	 * @param array<string, string> $error code and message.
	 */
	public static function reason( array $error ) {
		$code    = (string) ( $error['code'] ?? '' );
		$reasons = array(
			'connection_expired'   => __( 'The connection has expired. Connect again in SmokyPost.', 'smokypost' ),
			'token_invalid'        => __( 'The connection has expired. Connect again in SmokyPost.', 'smokypost' ),
			'token_missing'        => __( 'The connection has expired. Connect again in SmokyPost.', 'smokypost' ),
			'not_connected'        => __( 'No page or account is selected for this platform.', 'smokypost' ),
			'site_removed'         => __( 'This site is no longer connected to SmokyPost.', 'smokypost' ),
			'account_inactive'     => __( 'Your SmokyPost account or license is not active.', 'smokypost' ),
			'platform_not_in_plan' => __( 'This platform is not included in your plan.', 'smokypost' ),
			'permission_missing'   => __( 'The platform did not allow the post. Check that you are still an admin of the page and connect again.', 'smokypost' ),
			'blocked_by_platform'  => __( 'The platform blocked this post.', 'smokypost' ),
			'duplicate'            => __( 'The platform rejected this post as a duplicate.', 'smokypost' ),
			'image_required'       => __( 'Instagram needs an image. Add a featured image.', 'smokypost' ),
			'image_rejected'       => __( 'The platform could not use the image. Try a JPEG or PNG image at least 600 pixels wide.', 'smokypost' ),
			'image_unreadable'     => __( 'The image could not be read. Use a JPEG, PNG or WebP image.', 'smokypost' ),
			'image_too_small'      => __( 'The image is too small. Use an image at least 600 pixels wide.', 'smokypost' ),
			'image_unreachable'    => __( 'SmokyPost could not download the image from your site.', 'smokypost' ),
			'image_save_failed'    => __( 'The image could not be prepared.', 'smokypost' ),
			'platform_busy'        => __( 'The platform was not available. SmokyPost tried several times.', 'smokypost' ),
			'network_error'        => __( 'The platform could not be reached. SmokyPost tried several times.', 'smokypost' ),
			'container_failed'     => __( 'Instagram did not accept the image in time.', 'smokypost' ),
			'rejected'             => __( 'The platform rejected the post.', 'smokypost' ),
			'removal_unavailable'  => __( 'The account that published this post is no longer connected. Delete the post on the platform yourself.', 'smokypost' ),
			'not_supported'        => __( 'This post cannot be deleted automatically. Delete it on the platform yourself.', 'smokypost' ),
		);
		$reasons = (array) apply_filters( 'smokypost_failure_reasons', $reasons );
		return isset( $reasons[ $code ] ) ? $reasons[ $code ] : (string) ( $error['message'] ?? '' );
	}

	/**
	 * Small status list for the classic meta box.
	 *
	 * @param array<string, mixed> $data Editor::data().
	 */
	public static function render_box( array $data ) {
		if ( '' !== $data['error'] ) {
			echo '<p class="smokypost-error">' . esc_html( $data['error'] ) . '</p>';
		}
		if ( ! $data['last'] ) {
			return;
		}
		echo '<ul class="smokypost-status">';
		foreach ( $data['last'] as $line ) {
			echo '<li class="is-' . esc_attr( $line['status'] ) . '"><strong>' . esc_html( $line['label'] ) . ':</strong> ';
			echo '' !== $line['url'] ? '<a href="' . esc_url( $line['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $line['text'] ) . '</a>' : esc_html( $line['text'] );
			echo '</li>';
		}
		echo '</ul>';
	}
}
