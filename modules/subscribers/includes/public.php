<?php
/**
 * Subscribers: signup shortcode, confirm and unsubscribe pages.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Text for a public notice key.
 *
 * @param string $notice Notice key.
 * @return string
 */
function classicpack_subscribers_notice_text( $notice ) {
	$texts = [
		'check_email'  => __( 'Check your email to confirm your subscription.', 'classicpack' ),
		'confirmed'    => __( 'Your subscription is confirmed.', 'classicpack' ),
		'unsubscribed' => __( 'You have been unsubscribed.', 'classicpack' ),
		'invalid'      => __( 'That link is invalid or has expired.', 'classicpack' ),
	];
	return $texts[ $notice ] ?? '';
}

/**
 * [classicpack_subscribe] signup form.
 *
 * @return string
 */
function classicpack_subscribers_shortcode() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect query arg; it only selects the notice.
	$notice = isset( $_GET['classicpack_subscribers_notice'] ) ? classicpack_subscribers_notice_text( sanitize_key( wp_unslash( $_GET['classicpack_subscribers_notice'] ) ) ) : '';

	$html = '<div class="classicpack-subscribe-wrap">';
	if ( $notice !== '' ) {
		$html .= '<p class="classicpack-subscribe-notice">' . esc_html( $notice ) . '</p>';
	}
	$html .= '<form class="classicpack-subscribe" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	$html .= '<input type="hidden" name="action" value="classicpack_subscribers_subscribe" />';
	$html .= wp_nonce_field( 'classicpack_subscribers_subscribe', 'classicpack_subscribers_nonce', true, false );
	$html .= '<p class="classicpack-subscribe-field"><label for="classicpack-subscribe-email">' . esc_html__( 'Email address', 'classicpack' ) . '</label> ';
	$html .= '<input type="email" id="classicpack-subscribe-email" name="classicpack_subscribers_email" required="required" autocomplete="email" /></p>';
	$html .= '<p class="classicpack-subscribe-hp" style="position:absolute;left:-9999px;" aria-hidden="true"><label>' . esc_html__( 'Website', 'classicpack' ) . '<input type="text" name="classicpack_subscribers_website" value="" tabindex="-1" autocomplete="off" /></label></p>';
	$html .= '<p class="classicpack-subscribe-submit"><button type="submit">' . esc_html__( 'Subscribe', 'classicpack' ) . '</button></p>';
	$html .= '</form></div>';

	return $html;
}

/**
 * Signup form handler (admin-post.php).
 *
 * @return void
 */
function classicpack_subscribers_handle_subscribe() {
	$referer = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$notice  = 'invalid';

	if ( isset( $_POST['classicpack_subscribers_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['classicpack_subscribers_nonce'] ) ), 'classicpack_subscribers_subscribe' ) ) {
		$honeypot = isset( $_POST['classicpack_subscribers_website'] ) ? sanitize_text_field( wp_unslash( $_POST['classicpack_subscribers_website'] ) ) : '';
		$email    = isset( $_POST['classicpack_subscribers_email'] ) ? classicpack_subscribers_normalize_email( wp_unslash( $_POST['classicpack_subscribers_email'] ) ) : '';

		if ( $honeypot !== '' ) {
			$notice = 'check_email'; // Pretend success so bots learn nothing.
		} elseif ( is_email( $email ) ) {
			$subscriber = classicpack_subscribers_create_pending( $email );
			if ( $subscriber && $subscriber->status === 'pending' ) {
				classicpack_subscribers_send_confirmation( $subscriber );
			}
			$notice = 'check_email';
		}
	}

	wp_safe_redirect( add_query_arg( 'classicpack_subscribers_notice', $notice, $referer ) );
	exit;
}

/**
 * Standalone public page (no theme), then exit.
 *
 * @param string $title   Title.
 * @param string $message Message.
 * @param string $extra   Optional extra HTML (already escaped).
 * @return void
 */
function classicpack_subscribers_render_public_page( $title, $message, $extra = '' ) {
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=UTF-8' );

	echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( $title ) . '</title></head>';
	echo '<body style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem;color:#111;">';
	echo '<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p>';
	echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts by the caller.
	echo '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to the site', 'classicpack' ) . '</a></p></body></html>';
	exit;
}

/**
 * Keep the canonical redirect from stripping the confirm/unsubscribe query args.
 *
 * @param string|false $redirect Canonical URL.
 * @return string|false
 */
function classicpack_subscribers_disable_canonical_redirect( $redirect ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only.
	return isset( $_GET['classicpack_subscribe'] ) ? false : $redirect;
}

/**
 * Confirm, unsubscribe and one-click requests: ?classicpack_subscribe=<action>&token=<token>.
 *
 * @return void
 */
function classicpack_subscribers_handle_public_requests() {
	// phpcs:disable WordPress.Security.NonceVerification -- The 64-character token is the credential; the unsubscribe form below carries a nonce.
	if ( ! isset( $_GET['classicpack_subscribe'] ) ) {
		return;
	}
	$action = sanitize_key( wp_unslash( $_GET['classicpack_subscribe'] ) );
	$token  = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification

	if ( $action === 'one-click' ) {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' ) {
			classicpack_subscribers_unsubscribe( $token );
			status_header( 200 );
			exit;
		}
		wp_safe_redirect( classicpack_subscribers_action_url( 'unsubscribe', $token ) );
		exit;
	}

	if ( $action === 'confirm' ) {
		if ( classicpack_subscribers_confirm( $token ) ) {
			classicpack_subscribers_render_public_page( __( 'Subscription confirmed', 'classicpack' ), __( 'Your subscription is confirmed. You will receive an email when a new post is published.', 'classicpack' ) );
		}
		classicpack_subscribers_render_public_page( __( 'Confirmation failed', 'classicpack' ), __( 'That link is invalid or has expired.', 'classicpack' ) );
	}

	if ( $action !== 'unsubscribe' ) {
		return;
	}

	if ( isset( $_POST['classicpack_subscribers_unsub_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['classicpack_subscribers_unsub_nonce'] ) ), 'classicpack_subscribers_unsubscribe' ) ) {
			classicpack_subscribers_render_public_page( __( 'Unsubscribe', 'classicpack' ), __( 'The unsubscribe request could not be verified.', 'classicpack' ) );
		}
		if ( classicpack_subscribers_unsubscribe( $token ) ) {
			classicpack_subscribers_render_public_page( __( 'Unsubscribed', 'classicpack' ), __( 'You have been unsubscribed.', 'classicpack' ) );
		}
		classicpack_subscribers_render_public_page( __( 'Unsubscribe', 'classicpack' ), __( 'That link is invalid or has expired.', 'classicpack' ) );
	}

	if ( ! classicpack_subscribers_get_by_token( $token ) ) {
		classicpack_subscribers_render_public_page( __( 'Unsubscribe', 'classicpack' ), __( 'That link is invalid or has expired.', 'classicpack' ) );
	}

	$form = '<form method="post" action="' . esc_url( classicpack_subscribers_action_url( 'unsubscribe', $token ) ) . '">'
		. wp_nonce_field( 'classicpack_subscribers_unsubscribe', 'classicpack_subscribers_unsub_nonce', true, false )
		. '<p><button type="submit">' . esc_html__( 'Unsubscribe me', 'classicpack' ) . '</button></p></form>';

	classicpack_subscribers_render_public_page( __( 'Unsubscribe', 'classicpack' ), __( 'Are you sure you want to unsubscribe from these emails?', 'classicpack' ), $form );
}

/**
 * Banner after a signup redirect, printed once per page.
 *
 * @return void
 */
function classicpack_subscribers_front_notice() {
	static $printed = false;

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect query arg; it only selects the notice.
	$text = isset( $_GET['classicpack_subscribers_notice'] ) ? classicpack_subscribers_notice_text( sanitize_key( wp_unslash( $_GET['classicpack_subscribers_notice'] ) ) ) : '';
	if ( $printed || $text === '' ) {
		return;
	}
	$printed = true;

	echo '<div class="classicpack-subscribe-front-notice" style="background:#1d4ed8;color:#fff;text-align:center;padding:0.75rem 1rem;">' . esc_html( $text ) . '</div>';
}
