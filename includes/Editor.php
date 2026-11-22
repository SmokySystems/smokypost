<?php
/**
 * SmokyPost panel in the block editor and meta box in the classic editor.
 *
 * @package SmokyPost
 */

namespace SmokyPost;

defined( 'ABSPATH' ) || exit;

final class Editor {

	/** Stored when every platform is unticked, so "none" differs from "not chosen". */
	const NONE = 'none';

	public static function init() {
		add_action( 'init', array( self::class, 'register_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'block_editor_assets' ) );
		add_action( 'add_meta_boxes', array( self::class, 'add_meta_box' ), 10, 2 );
		add_action( 'save_post', array( self::class, 'save_meta_box' ), 10, 2 );
		add_action( 'wp_ajax_smokypost_share_now', array( self::class, 'ajax_share_now' ) );
		add_action( 'wp_ajax_smokypost_share_status', array( self::class, 'ajax_share_status' ) );
		add_action( 'wp_ajax_smokypost_share_cancel', array( self::class, 'ajax_share_cancel' ) );
		add_action( 'wp_ajax_smokypost_unshare', array( self::class, 'ajax_unshare' ) );
	}

	public static function register_meta() {
		foreach ( (array) Settings::get( 'post_types' ) as $type ) {
			register_post_meta(
				$type,
				'_smokypost_platforms',
				array(
					'type'          => 'array',
					'single'        => true,
					'show_in_rest'  => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'auth_callback' => static function ( $allowed, $key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
			register_post_meta(
				$type,
				'_smokypost_image',
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( self::class, 'clean_image' ),
					'auth_callback'     => static function ( $allowed, $key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
			register_post_meta(
				$type,
				'_smokypost_message',
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_textarea_field',
					'auth_callback'     => static function ( $allowed, $key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
			register_post_meta(
				$type,
				'_smokypost_share_at',
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( self::class, 'clean_share_at' ),
					'auth_callback'     => static function ( $allowed, $key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * Data both editors need.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	public static function data( \WP_Post $post ) {
		$targets = array();
		foreach ( Api::connected_targets() as $platform => $t ) {
			$targets[] = array(
				'platform' => $platform,
				'label'    => Settings::label( $platform ),
				'name'     => $t['name'],
				'expired'  => 'active' !== $t['status'],
			);
		}
		$published = 'publish' === $post->post_status;
		return array(
			'connected'   => Api::is_connected(),
			'settingsUrl' => admin_url( 'admin.php?page=smokypost' ),
			'targets'     => $targets,
			'defaults'    => $published ? Sharer::manual_platforms( $post ) : array_values( (array) Settings::get( 'default_platforms' ) ),
			'hasImage'    => '' !== Sharer::image_url( $post ),
			'hasDefault'  => '' !== (string) Settings::get( 'default_image' ),
			'defaultImage' => (string) Settings::get( 'default_image' ),
			'image'       => Sharer::image( $post ),
			'contentImages' => Sharer::content_images( $post ),
			'imageLabels' => array(
				'title'    => __( 'Image to share', 'smokypost' ),
				'custom'   => __( 'Chosen for sharing', 'smokypost' ),
				'change'   => __( 'Choose another image', 'smokypost' ),
				'choose'   => __( 'Choose an image', 'smokypost' ),
				'reset'    => __( 'Use the automatic choice', 'smokypost' ),
				'inItem'   => __( 'Images in this item:', 'smokypost' ),
				'pick'     => __( 'Use this image', 'smokypost' ),
				'media'    => __( 'Image to share', 'smokypost' ),
				'noUpdate' => __( 'Updating this item does not share it again. Click "Share now" to share it.', 'smokypost' ),
				'featured' => __( 'Featured image', 'smokypost' ),
				'content'  => __( 'First image in the content (this item has no featured image)', 'smokypost' ),
				'default'  => __( 'Default image from the SmokyPost settings (this item has no image)', 'smokypost' ),
			),
			'noImage'     => __( 'Instagram needs an image, and this item has none. Add a featured image or an image in the content, otherwise it is not shared to Instagram.', 'smokypost' ),
			'scheduling'  => Api::has_feature( 'scheduling' ),
			'shareAt'     => (string) get_post_meta( $post->ID, '_smokypost_share_at', true ),
			'waiting'     => Sharer::waiting_since_schedule( $post->ID ) > 0,
			'labels'      => array(
				'shareAt'     => __( 'Share at (optional)', 'smokypost' ),
				/* translators: 1: example date and time, 2: time zone name */
				'shareAtHelp' => sprintf( __( 'Format: %1$s, in your site time (%2$s). Leave empty to share right away.', 'smokypost' ), '2026-12-31 18:30', wp_timezone_string() ),
				'shareAtBad'  => __( 'This is not a valid date and time. Use the format YYYY-MM-DD HH:MM, for example 2026-12-31 18:30.', 'smokypost' ),
				'schedule'    => __( 'Schedule', 'smokypost' ),
				'cancel'      => __( 'Cancel the scheduled share', 'smokypost' ),
			),
			'last'        => Status::for_post( $post->ID ),
			'error'       => (string) get_post_meta( $post->ID, '_smokypost_error', true ),
			'alreadyAuto' => (bool) get_post_meta( $post->ID, '_smokypost_auto_done', true ),
			'remove'      => $published ? self::delete_plan( $post->ID ) : null,
			'removeLabels' => array(
				'open'    => __( 'Delete shared posts', 'smokypost' ),
				'yes'     => __( 'Yes, delete', 'smokypost' ),
				'cancel'  => __( 'Cancel', 'smokypost' ),
				'working' => __( 'Deleting…', 'smokypost' ),
			),
			'nonce'       => wp_create_nonce( 'smokypost_share_now' ),
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'none'        => self::NONE,
		);
	}

	public static function block_editor_assets() {
		$screen = get_current_screen();
		$post   = get_post();
		if ( ! $post || ! $screen || ! in_array( $screen->post_type, (array) Settings::get( 'post_types' ), true ) ) {
			return;
		}
		wp_enqueue_script(
			'smokypost-editor',
			SMOKYPOST_URL . 'assets/js/editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-api-fetch', 'wp-hooks' ),
			SMOKYPOST_VERSION,
			true
		);
		wp_set_script_translations( 'smokypost-editor', 'smokypost', SMOKYPOST_DIR . 'languages' );
		wp_enqueue_style( 'smokypost-admin', SMOKYPOST_URL . 'assets/css/admin.css', array(), SMOKYPOST_VERSION );
		wp_localize_script( 'smokypost-editor', 'smokypostEditor', self::data( $post ) );
	}

	/**
	 * Classic editor only; the block editor uses the sidebar panel.
	 *
	 * @param string   $post_type Post type.
	 * @param \WP_Post $post      Post.
	 */
	public static function add_meta_box( $post_type, $post ) {
		if ( ! in_array( $post_type, (array) Settings::get( 'post_types' ), true ) ) {
			return;
		}
		if ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post ) ) {
			return;
		}
		add_meta_box( 'smokypost', 'SmokyPost', array( self::class, 'render_meta_box' ), $post_type, 'side', 'default' );
	}

	/**
	 * @param \WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		$data = self::data( $post );
		wp_nonce_field( 'smokypost_meta', 'smokypost_meta_nonce' );
		if ( ! $data['connected'] || ! $data['targets'] ) {
			echo '<p>' . esc_html__( 'Connect your pages to share this item.', 'smokypost' ) . ' <a href="' . esc_url( $data['settingsUrl'] ) . '">' . esc_html__( 'Open SmokyPost', 'smokypost' ) . '</a></p>';
			return;
		}
		$chosen = metadata_exists( 'post', $post->ID, '_smokypost_platforms' ) ? (array) get_post_meta( $post->ID, '_smokypost_platforms', true ) : $data['defaults'];
		$has_ig = in_array( 'instagram', wp_list_pluck( $data['targets'], 'platform' ), true );
		if ( $has_ig && ! $data['hasImage'] ) {
			echo '<div class="notice notice-warning inline smokypost-warning"><p>' . esc_html( $data['noImage'] ) . '</p></div>';
		}
		self::render_image( $data );
		echo '<p class="smokypost-heading">' . esc_html( 'publish' === $post->post_status ? __( 'Share now to:', 'smokypost' ) : __( 'Share when published to:', 'smokypost' ) ) . '</p>';
		echo '<input type="hidden" name="smokypost_platforms_sent" value="1">';
		foreach ( $data['targets'] as $t ) {
			$off = 'instagram' === $t['platform'] && ! $data['hasImage'];
			echo '<p><label><input type="checkbox" name="smokypost_platforms[]" value="' . esc_attr( $t['platform'] ) . '"' . checked( ! $off && in_array( $t['platform'], $chosen, true ), true, false ) . disabled( $off, true, false ) . '> ' . esc_html( $t['label'] . ': ' . $t['name'] ) . '</label></p>';
		}
		echo '<p><label for="smokypost_message">' . esc_html__( 'Custom message (optional)', 'smokypost' ) . '</label><textarea id="smokypost_message" name="smokypost_message" rows="4" class="widefat">' . esc_textarea( (string) get_post_meta( $post->ID, '_smokypost_message', true ) ) . '</textarea></p>';
		/**
		 * Add-ons add their own fields to the classic editor box here.
		 *
		 * @param \WP_Post             $post Post.
		 * @param array<string, mixed> $data Editor data.
		 */
		do_action( 'smokypost_meta_box', $post, $data );
		if ( $data['scheduling'] ) {
			echo '<p><label for="smokypost_share_at">' . esc_html( $data['labels']['shareAt'] ) . '</label><input type="text" id="smokypost_share_at" name="smokypost_share_at" class="widefat" placeholder="2026-12-31 18:30" value="' . esc_attr( $data['shareAt'] ) . '" autocomplete="off"><span class="description">' . esc_html( $data['labels']['shareAtHelp'] ) . '</span></p>';
		}
		Status::render_box( $data );
		if ( $data['waiting'] ) {
			echo '<p><button type="button" class="button smokypost-share-cancel" data-post="' . (int) $post->ID . '" data-nonce="' . esc_attr( $data['nonce'] ) . '">' . esc_html( $data['labels']['cancel'] ) . '</button> <span class="smokypost-share-result" aria-live="polite"></span></p>';
			Admin\Page::enqueue_admin_js();
		}
		if ( 'publish' === $post->post_status ) {
			echo '<p class="description">' . esc_html( $data['imageLabels']['noUpdate'] ) . '</p>';
			echo '<p><button type="button" class="button smokypost-share-now" data-post="' . (int) $post->ID . '" data-nonce="' . esc_attr( $data['nonce'] ) . '">' . esc_html__( 'Share now', 'smokypost' ) . '</button> <span class="smokypost-share-result" aria-live="polite"></span></p>';
			echo self::delete_html( $post->ID, $data['nonce'], 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in delete_html().
			Admin\Page::enqueue_admin_js();
		}
	}

	/**
	 * Which image goes with the share (classic editor).
	 *
	 * @param array<string, mixed> $data self::data().
	 */
	private static function render_image( array $data ) {
		$image  = $data['image'];
		$labels = $data['imageLabels'];
		wp_enqueue_media();
		Admin\Page::enqueue_admin_js();
		echo '<div class="smokypost-image" data-title="' . esc_attr( $labels['media'] ) . '" data-button="' . esc_attr( $labels['pick'] ) . '" data-custom="' . esc_attr( $labels['custom'] ) . '">';
		echo '<p class="smokypost-heading">' . esc_html( $labels['title'] ) . '</p>';
		echo '<input type="hidden" name="smokypost_image" value="' . esc_attr( 'custom' === $image['source'] ? $image['url'] : '' ) . '">';
		if ( '' !== $image['url'] ) {
			echo '<img src="' . esc_url( $image['url'] ) . '" alt="" class="smokypost-avatar smokypost-image-preview">';
			echo '<p class="description">' . esc_html( $labels[ $image['source'] ] ) . '</p>';
		}
		if ( count( $data['contentImages'] ) > 1 ) {
			echo '<p class="description">' . esc_html( $labels['inItem'] ) . '</p><div class="smokypost-thumbs">';
			foreach ( $data['contentImages'] as $src ) {
				echo '<button type="button" class="smokypost-thumb' . ( $src === $image['url'] ? ' is-selected' : '' ) . '" data-url="' . esc_url( $src ) . '" title="' . esc_attr( $labels['pick'] ) . '"><img src="' . esc_url( $src ) . '" alt="" class="smokypost-avatar"></button>';
			}
			echo '</div>';
		}
		echo '<p class="smokypost-image-actions"><button type="button" class="button smokypost-image-choose">' . esc_html( '' !== $image['url'] ? $labels['change'] : $labels['choose'] ) . '</button>';
		if ( 'custom' === $image['source'] ) {
			echo ' <button type="button" class="button-link smokypost-image-reset">' . esc_html( $labels['reset'] ) . '</button>';
		}
		echo '</p><p class="description">' . esc_html__( 'Save the item to apply the change.', 'smokypost' ) . '</p></div>';
	}

	/**
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 */
	public static function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['smokypost_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['smokypost_meta_nonce'] ) ), 'smokypost_meta' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['smokypost_platforms_sent'] ) ) {
			$platforms = isset( $_POST['smokypost_platforms'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['smokypost_platforms'] ) ) : array();
			$platforms = array_values( array_intersect( $platforms, Settings::platforms() ) );
			update_post_meta( $post_id, '_smokypost_platforms', $platforms ? $platforms : array( self::NONE ) );
		}
		if ( isset( $_POST['smokypost_message'] ) ) {
			update_post_meta( $post_id, '_smokypost_message', sanitize_textarea_field( wp_unslash( $_POST['smokypost_message'] ) ) );
		}
		/**
		 * The classic editor box was saved (nonce and capability are checked).
		 *
		 * @param int $post_id Post id.
		 */
		do_action( 'smokypost_save_meta_box', $post_id );
		if ( isset( $_POST['smokypost_share_at'] ) ) {
			self::save_share_at( $post_id, sanitize_text_field( wp_unslash( $_POST['smokypost_share_at'] ) ) );
		}
		if ( isset( $_POST['smokypost_image'] ) ) {
			self::save_image( $post_id, wp_unslash( $_POST['smokypost_image'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned in save_image().
		}
	}

	/**
	 * @param mixed $value "YYYY-MM-DD HH:MM" or empty.
	 */
	public static function clean_share_at( $value ) {
		$value = trim( (string) $value );
		return Sharer::parse_site_time( $value ) ? substr( str_replace( 'T', ' ', $value ), 0, 16 ) : '';
	}

	/**
	 * @param int    $post_id Post id.
	 * @param string $value   Date and time or empty.
	 */
	private static function save_share_at( $post_id, $value ) {
		$clean = self::clean_share_at( $value );
		if ( '' === $clean ) {
			delete_post_meta( $post_id, '_smokypost_share_at' );
		} else {
			update_post_meta( $post_id, '_smokypost_share_at', $clean );
		}
	}

	/**
	 * @param mixed $value Image URL.
	 */
	public static function clean_image( $value ) {
		$url = esc_url_raw( trim( (string) $value ) );
		return preg_match( '#^https?://#i', $url ) ? $url : '';
	}

	/**
	 * @param int   $post_id Post id.
	 * @param mixed $value   Image URL; empty = automatic choice.
	 */
	private static function save_image( $post_id, $value ) {
		$url = self::clean_image( $value );
		if ( '' === $url ) {
			delete_post_meta( $post_id, '_smokypost_image' );
		} else {
			update_post_meta( $post_id, '_smokypost_image', $url );
		}
	}


	/**
	 * "Share now" button in both editors and the post list.
	 */
	public static function ajax_share_now() {
		check_ajax_referer( 'smokypost_share_now', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to share this item.', 'smokypost' ) ), 403 );
		}
		$platforms = isset( $_POST['platforms'] ) ? array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['platforms'] ) ), Settings::platforms() ) ) : null;
		if ( isset( $_POST['message'] ) ) {
			update_post_meta( $post_id, '_smokypost_message', sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) );
		}
		/**
		 * "Share now" was asked for (nonce and capability are checked). Add-ons read and store their own fields here.
		 *
		 * @param int $post_id Post id.
		 */
		do_action( 'smokypost_share_now_request', $post_id );
		if ( isset( $_POST['share_at'] ) ) {
			self::save_share_at( $post_id, sanitize_text_field( wp_unslash( $_POST['share_at'] ) ) );
		}
		if ( isset( $_POST['image'] ) ) {
			self::save_image( $post_id, wp_unslash( $_POST['image'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned in save_image().
		}
		$result = Sharer::share( $post_id, 'manual', null !== $platforms ? $platforms : Sharer::manual_platforms( get_post( $post_id ) ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		if ( null === $result ) {
			$error = (string) get_post_meta( $post_id, '_smokypost_error', true );
			wp_send_json_error(
				array(
					'message' => '' !== $error ? $error : __( 'Nothing to share: choose at least one connected platform.', 'smokypost' ),
					'last'    => Status::for_post( $post_id ),
				)
			);
		}
		$message   = __( 'Sending… You can leave this page, sharing continues in the background.', 'smokypost' );
		$last      = get_post_meta( $post_id, '_smokypost_last', true );
		$scheduled = is_array( $last ) && ! empty( $last['scheduled_at'] ) && strtotime( (string) $last['scheduled_at'] ) > time() + 90;
		if ( $scheduled ) {
			/* translators: %s: date and time */
			$message = sprintf( __( 'Scheduled for %s. You can leave this page.', 'smokypost' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( (string) $last['scheduled_at'] ) ) );
		}
		if ( get_post_meta( $post_id, '_smokypost_skipped', true ) ) {
			$message .= ' ' . Sharer::no_image_message();
		}
		$response = array(
			'message'  => $message,
			'last'     => Status::for_post( $post_id ),
			'finished' => $scheduled,
			'waiting'  => $scheduled,
		);
		/**
		 * The answer to "Share now", before it is sent to the editor.
		 *
		 * @param array<string, mixed> $response Message, status lines and "finished".
		 * @param int                  $post_id  Post id.
		 */
		wp_send_json_success( apply_filters( 'smokypost_share_now_response', $response, $post_id ) );
	}

	/**
	 * Take back a share that still waits for its time.
	 */
	public static function ajax_share_cancel() {
		check_ajax_referer( 'smokypost_share_now', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to share this item.', 'smokypost' ) ), 403 );
		}
		$last = get_post_meta( $post_id, '_smokypost_last', true );
		if ( ! is_array( $last ) || empty( $last['id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Nothing is scheduled for this item.', 'smokypost' ) ) );
		}
		$result = Api::request( 'DELETE', '/shares/' . (int) $last['id'] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		$share = (array) $result['share'];
		Sharer::store_result( $share );
		update_post_meta( $post_id, '_smokypost_last', $share );
		delete_post_meta( $post_id, '_smokypost_share_at' );
		$cancelled = false;
		foreach ( (array) $share['targets'] as $t ) {
			if ( 'cancelled' === $t['status'] ) {
				$cancelled = true;
			}
		}
		wp_send_json_success(
			array(
				'message' => $cancelled ? __( 'The scheduled share is cancelled.', 'smokypost' ) : __( 'Nothing to cancel: it was already sent.', 'smokypost' ),
				'last'    => Status::for_post( $post_id ),
				'waiting' => false,
			)
		);
	}

	/**
	 * What "Delete shared posts" asks the person to confirm, or null when there is nothing to delete.
	 *
	 * @param int $post_id Post id.
	 * @return array<string, mixed>|null
	 */
	public static function delete_plan( $post_id ) {
		$plan = Sharer::removal_plan( $post_id );
		if ( ! $plan ) {
			return null;
		}
		/* translators: %s: platform names, for example "Facebook, Bluesky" */
		$text = sprintf( __( 'Delete the shared posts from %s? The item itself stays on your site.', 'smokypost' ), implode( ', ', $plan['delete'] ) );
		if ( $plan['manual'] ) {
			/* translators: %s: platform names, for example "Instagram" */
			$text .= ' ' . sprintf( __( 'Posts on %s cannot be deleted from here. Delete them in the app yourself.', 'smokypost' ), implode( ', ', $plan['manual'] ) );
		}
		$plan['text'] = $text;
		return $plan;
	}

	/**
	 * The "Delete shared posts" button with its inline confirmation (no browser dialog).
	 *
	 * @param int    $post_id Post id.
	 * @param string $nonce   Nonce for smokypost_share_now.
	 * @param string $style   "button" or "link", how the opening control looks.
	 * @return string Escaped HTML; empty when there is nothing to delete.
	 */
	public static function delete_html( $post_id, $nonce, $style = 'link' ) {
		$plan = self::delete_plan( $post_id );
		if ( ! $plan ) {
			return '';
		}
		$html  = '<details class="smokypost-confirm smokypost-unshare"><summary class="' . ( 'button' === $style ? 'button' : 'button-link' ) . '">' . esc_html__( 'Delete shared posts', 'smokypost' ) . '</summary>';
		$html .= '<div class="notice notice-warning inline"><p>' . esc_html( $plan['text'] ) . '</p><p>';
		$html .= '<button type="button" class="button button-primary smokypost-unshare-now" data-post="' . (int) $post_id . '" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html__( 'Yes, delete', 'smokypost' ) . '</button> ';
		$html .= '<button type="button" class="button-link smokypost-confirm-cancel">' . esc_html__( 'Cancel', 'smokypost' ) . '</button> ';
		$html .= '<span class="smokypost-share-result" aria-live="polite"></span></p></div></details>';
		return $html;
	}

	/**
	 * "Delete shared posts": take the item's posts down without deleting the item.
	 */
	public static function ajax_unshare() {
		check_ajax_referer( 'smokypost_share_now', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change this item.', 'smokypost' ) ), 403 );
		}
		$plan = Sharer::removal_plan( $post_id );
		if ( ! $plan ) {
			wp_send_json_error(
				array(
					'message' => __( 'There is nothing to delete for this item.', 'smokypost' ),
					'last'    => Status::for_post( $post_id ),
				)
			);
		}
		$result = Sharer::run_removal( $post_id, 6 );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		$message = __( 'Deleting… You can leave this page, it continues in the background.', 'smokypost' );
		if ( $plan['manual'] ) {
			/* translators: %s: platform names, for example "Instagram" */
			$message .= ' ' . sprintf( __( 'Posts on %s cannot be deleted from here. Delete them in the app yourself.', 'smokypost' ), implode( ', ', $plan['manual'] ) );
		}
		$last = get_post_meta( $post_id, '_smokypost_last', true );
		wp_send_json_success(
			array(
				'message'  => $message,
				'last'     => Status::for_post( $post_id ),
				'finished' => ! is_array( $last ) || Sharer::is_finished( $last ),
			)
		);
	}

	/**
	 * Progress of the last share of an item, asked for every few seconds while "Share now" is running.
	 */
	public static function ajax_share_status() {
		check_ajax_referer( 'smokypost_share_now', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to share this item.', 'smokypost' ) ), 403 );
		}
		$last = get_post_meta( $post_id, '_smokypost_last', true );
		if ( is_array( $last ) && ! empty( $last['id'] ) ) {
			$fresh = Api::request( 'GET', '/shares/' . (int) $last['id'] );
			if ( ! is_wp_error( $fresh ) && ! empty( $fresh['share'] ) && is_array( $fresh['share'] ) ) {
				Sharer::store_result( $fresh['share'] );
				$last = $fresh['share'];
			}
		}
		$finished = ! is_array( $last ) || Sharer::is_finished( $last );
		$lines    = Status::for_post( $post_id );
		$failed   = false;
		foreach ( $lines as $line ) {
			if ( 'failed' === $line['status'] ) {
				$failed = true;
			}
		}
		$message = '';
		if ( $finished ) {
			$message = $failed ? __( 'Finished, but something failed. See below.', 'smokypost' ) : __( 'Done.', 'smokypost' );
		}
		wp_send_json_success(
			array(
				'finished' => $finished,
				'message'  => $message,
				'last'     => $lines,
				'remove'   => $finished ? self::delete_plan( $post_id ) : null,
			)
		);
	}
}
