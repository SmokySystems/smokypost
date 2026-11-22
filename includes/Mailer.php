<?php
/**
 * Sends SmokyPost's own e-mails, optionally through the SMTP account set in SmokyPost.
 *
 * The SMTP settings apply to SmokyPost's e-mails only. Other mail of the site is never touched.
 *
 * @package SmokyPost
 */

namespace SmokyPost;

defined( 'ABSPATH' ) || exit;

final class Mailer {

	/** @var string Last error of a send, for the test e-mail. */
	private static $error = '';


	/**
	 * @param string[] $to      Recipients.
	 * @param string   $subject Subject.
	 * @param string   $body    Plain text body.
	 * @return bool
	 */
	public static function send( array $to, $subject, $body ) {
		self::$error = '';
		if ( ! $to ) {
			return false;
		}
		$smtp = self::smtp();
		add_action( 'wp_mail_failed', array( self::class, 'failed' ) );
		if ( $smtp ) {
			add_action( 'phpmailer_init', array( self::class, 'configure' ), PHP_INT_MAX );
			add_filter( 'wp_mail_from', array( self::class, 'from' ), PHP_INT_MAX );
			add_filter( 'wp_mail_from_name', array( self::class, 'from_name' ), PHP_INT_MAX );
		}
		$sent = wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
		remove_action( 'wp_mail_failed', array( self::class, 'failed' ) );
		remove_action( 'phpmailer_init', array( self::class, 'configure' ), PHP_INT_MAX );
		remove_filter( 'wp_mail_from', array( self::class, 'from' ), PHP_INT_MAX );
		remove_filter( 'wp_mail_from_name', array( self::class, 'from_name' ), PHP_INT_MAX );
		return (bool) $sent;
	}

	/**
	 * @param \WP_Error $error Error from wp_mail().
	 */
	public static function failed( $error ) {
		self::$error = is_wp_error( $error ) ? $error->get_error_message() : '';
	}

	public static function last_error() {
		return self::$error;
	}


	/**
	 * The SMTP account, or an empty array when SmokyPost sends with the site's own mail.
	 *
	 * @return array<string, mixed>
	 */
	public static function smtp() {
		$s = Settings::all();
		if ( empty( $s['smtp_enabled'] ) || '' === trim( (string) $s['smtp_host'] ) ) {
			return array();
		}
		return array(
			'host'   => trim( (string) $s['smtp_host'] ),
			'port'   => max( 1, (int) $s['smtp_port'] ),
			'secure' => in_array( $s['smtp_secure'], array( 'ssl', 'tls', '' ), true ) ? (string) $s['smtp_secure'] : 'tls',
			'user'   => (string) $s['smtp_user'],
			'pass'   => self::password(),
		);
	}

	/**
	 * @param \PHPMailer\PHPMailer\PHPMailer $mailer Mailer.
	 */
	public static function configure( $mailer ) {
		$smtp = self::smtp();
		if ( ! $smtp ) {
			return;
		}
		$mailer->isSMTP();
		$mailer->Host       = $smtp['host'];
		$mailer->Port       = $smtp['port'];
		$mailer->SMTPSecure = $smtp['secure'];
		// With no encryption chosen, do not let PHPMailer switch to it on its own.
		$mailer->SMTPAutoTLS = '' !== $smtp['secure'];
		$mailer->SMTPAuth    = '' !== $smtp['user'];
		if ( $mailer->SMTPAuth ) {
			$mailer->Username = $smtp['user'];
			$mailer->Password = $smtp['pass'];
		}
	}

	public static function from( $email ) {
		$from = (string) Settings::all()['smtp_from'];
		return is_email( $from ) ? $from : $email;
	}

	public static function from_name( $name ) {
		$from = trim( (string) Settings::all()['smtp_from_name'] );
		return '' !== $from ? $from : $name;
	}

	/* ------------------------------------------------------------------ */
	/* Password                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * The SMTP password: a wp-config constant wins, otherwise the stored, encrypted value.
	 */
	public static function password() {
		if ( defined( 'SMOKYPOST_SMTP_PASSWORD' ) && '' !== (string) SMOKYPOST_SMTP_PASSWORD ) {
			return (string) SMOKYPOST_SMTP_PASSWORD;
		}
		return self::decrypt( (string) Settings::get( 'smtp_pass' ) );
	}

	public static function password_from_constant() {
		return defined( 'SMOKYPOST_SMTP_PASSWORD' ) && '' !== (string) SMOKYPOST_SMTP_PASSWORD;
	}

	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ) . 'smokypost-smtp', true );
	}

	/**
	 * @param string $plain Password.
	 * @return string Encrypted text, safe to store.
	 */
	public static function encrypt( $plain ) {
		if ( '' === $plain ) {
			return '';
		}
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 'sp1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * @param string $stored Value from encrypt().
	 */
	public static function decrypt( $stored ) {
		if ( 0 !== strpos( $stored, 'sp1:' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::key() );
		return false === $plain ? '' : $plain;
	}
}
