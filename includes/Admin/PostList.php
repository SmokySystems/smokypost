<?php
/**
 * Status column, "Share with SmokyPost" row action and bulk action.
 *
 * @package SmokyPost
 */

namespace SmokyPost\Admin;

use SmokyPost\Api;
use SmokyPost\Editor;
use SmokyPost\Settings;
use SmokyPost\Sharer;
use SmokyPost\Status;

defined( 'ABSPATH' ) || exit;

final class PostList {

	public static function init() {
		add_action( 'admin_init', array( self::class, 'register' ) );
	}

	public static function register() {
		if ( ! Api::is_connected() ) {
			return;
		}
		foreach ( (array) Settings::get( 'post_types' ) as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( self::class, 'column' ) );
			add_action( "manage_{$type}_posts_custom_column", array( self::class, 'column_content' ), 10, 2 );
			add_filter( "bulk_actions-edit-{$type}", array( self::class, 'bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$type}", array( self::class, 'handle_bulk' ), 10, 3 );
		}
		add_filter( 'post_row_actions', array( self::class, 'row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( self::class, 'row_action' ), 10, 2 );
		add_action( 'admin_notices', array( self::class, 'bulk_notice' ) );
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function column( $columns ) {
		$columns['smokypost'] = 'SmokyPost';
		return $columns;
	}

	/**
	 * @param string $column  Column.
	 * @param int    $post_id Post id.
	 */
	public static function column_content( $column, $post_id ) {
		if ( 'smokypost' !== $column ) {
			return;
		}
		$lines = Status::for_post( $post_id );
		$error = (string) get_post_meta( $post_id, '_smokypost_error', true );
		if ( '' !== $error && ! $lines ) {
			echo '<span class="smokypost-error" title="' . esc_attr( $error ) . '">' . esc_html__( 'Not shared', 'smokypost' ) . '</span>';
			return;
		}
		if ( ! $lines ) {
			echo '<span aria-hidden="true">—</span>';
			return;
		}
		echo '<span class="smokypost-badges">';
		foreach ( $lines as $line ) {
			$badge = '<span class="smokypost-badge is-' . esc_attr( $line['status'] ) . '" title="' . esc_attr( $line['label'] . ': ' . $line['text'] ) . '">' . esc_html( self::short_label( $line['platform'] ) ) . '</span>';
			echo '' !== $line['url'] ? '<a href="' . esc_url( $line['url'] ) . '" target="_blank" rel="noopener">' . $badge . '</a>' : $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</span>';
	}

	/**
	 * @param string $platform Platform.
	 */
	private static function short_label( $platform ) {
		$short = array(
			'facebook'  => 'FB',
			'instagram' => 'IG',
			'linkedin'  => 'LI',
			'bluesky'   => 'BS',
			'mastodon'  => 'MA',
			'threads'   => 'TH',
			'tumblr'    => 'TB',
		);
		$short = (array) apply_filters( 'smokypost_platform_badges', $short );
		return isset( $short[ $platform ] ) ? $short[ $platform ] : strtoupper( substr( $platform, 0, 2 ) );
	}

	/**
	 * @param array<string, string> $actions Row actions.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public static function row_action( $actions, $post ) {
		if ( 'publish' !== $post->post_status || ! in_array( $post->post_type, (array) Settings::get( 'post_types' ), true ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		$actions['smokypost'] = '<a href="#" class="smokypost-share-now" data-post="' . (int) $post->ID . '" data-nonce="' . esc_attr( wp_create_nonce( 'smokypost_share_now' ) ) . '">' . esc_html__( 'Share with SmokyPost', 'smokypost' ) . '</a><span class="smokypost-share-result" aria-live="polite"></span>';
		$delete = Editor::delete_html( $post->ID, wp_create_nonce( 'smokypost_share_now' ) );
		if ( '' !== $delete ) {
			$actions['smokypost_delete'] = $delete;
		}
		return $actions;
	}

	/**
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string>
	 */
	public static function bulk_actions( $actions ) {
		$actions['smokypost_share'] = __( 'Share with SmokyPost', 'smokypost' );
		return $actions;
	}

	/**
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action.
	 * @param int[]  $ids      Post ids.
	 */
	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( 'smokypost_share' !== $action ) {
			return $redirect;
		}
		$ok     = 0;
		$failed = 0;
		foreach ( array_slice( (array) $ids, 0, 50 ) as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$post   = get_post( (int) $id );
			$result = $post ? Sharer::share( $post->ID, 'manual', Sharer::manual_platforms( $post ) ) : null;
			is_array( $result ) ? $ok++ : $failed++;
		}
		return add_query_arg(
			array(
				'smokypost_shared' => $ok,
				'smokypost_failed' => $failed,
			),
			$redirect
		);
	}

	public static function bulk_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['smokypost_shared'] ) ) {
			return;
		}
		$ok     = absint( $_GET['smokypost_shared'] );
		$failed = isset( $_GET['smokypost_failed'] ) ? absint( $_GET['smokypost_failed'] ) : 0;
		// phpcs:enable
		/* translators: %d: number of items */
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d item queued for sharing.', '%d items queued for sharing.', $ok, 'smokypost' ), $ok ) );
		if ( $failed ) {
			/* translators: %d: number of items */
			echo ' ' . esc_html( sprintf( _n( '%d item could not be shared; see the SmokyPost column.', '%d items could not be shared; see the SmokyPost column.', $failed, 'smokypost' ), $failed ) );
		}
		echo '</p></div>';
	}
}
