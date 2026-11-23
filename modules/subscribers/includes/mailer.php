<?php
/**
 * Subscribers: HTML email through wp_mail() and the two email templates.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * From address from the settings, falling back to the admin email.
 *
 * @return string
 */
function classicpack_subscribers_from_email() {
	$email = sanitize_email( (string) get_option( 'classicpack_subscribers_from_email', '' ) );
	return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
}

/**
 * From name from the settings, falling back to the site name.
 *
 * @return string
 */
function classicpack_subscribers_from_name() {
	$name = sanitize_text_field( (string) get_option( 'classicpack_subscribers_from_name', '' ) );
	return $name !== '' ? $name : (string) get_bloginfo( 'name' );
}

/**
 * Send one HTML email with the module's From name and address.
 *
 * @param string   $to      Recipient.
 * @param string   $subject Subject.
 * @param string   $html    HTML body.
 * @param string[] $headers Extra headers.
 * @return bool
 */
function classicpack_subscribers_send_mail( $to, $subject, $html, array $headers = [] ) {
	$headers = array_merge( [ 'Content-Type: text/html; charset=UTF-8' ], $headers );

	add_filter( 'wp_mail_from', 'classicpack_subscribers_from_email' );
	add_filter( 'wp_mail_from_name', 'classicpack_subscribers_from_name' );

	$sent = wp_mail( $to, $subject, $html, $headers );

	remove_filter( 'wp_mail_from', 'classicpack_subscribers_from_email' );
	remove_filter( 'wp_mail_from_name', 'classicpack_subscribers_from_name' );

	return (bool) $sent;
}

/**
 * Plain-text excerpt for a post.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function classicpack_subscribers_post_excerpt( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	if ( $post->post_excerpt !== '' ) {
		return wp_strip_all_tags( $post->post_excerpt );
	}
	return wp_trim_words( wp_strip_all_tags( $post->post_content ), 55, '…' );
}

/**
 * Send the double opt-in email.
 *
 * @param object $subscriber Subscriber row.
 * @return bool
 */
function classicpack_subscribers_send_confirmation( $subscriber ) {
	$site = get_bloginfo( 'name' );
	$html = '<!DOCTYPE html><html><body style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;line-height:1.6;color:#111;padding:24px;">'
		/* translators: %s: site name */
		. '<p>' . sprintf( esc_html__( 'Confirm your subscription to %s.', 'classicpack' ), esc_html( $site ) ) . '</p>'
		. '<p><a href="' . esc_url( classicpack_subscribers_action_url( 'confirm', $subscriber->token ) ) . '" style="display:inline-block;background:#1d4ed8;color:#fff;text-decoration:none;padding:12px 20px;">'
		. esc_html__( 'Confirm your subscription', 'classicpack' ) . '</a></p>'
		. '<p style="color:#666;font-size:13px;">' . esc_html__( 'If you did not request this, you can ignore this email.', 'classicpack' ) . '</p>'
		. '</body></html>';

	return classicpack_subscribers_send_mail(
		$subscriber->email,
		/* translators: %s: site name */
		sprintf( __( 'Confirm your subscription to %s', 'classicpack' ), $site ),
		$html
	);
}

/**
 * Newsletter HTML for a post. The token builds the unsubscribe link ('preview' for the admin preview).
 *
 * @param WP_Post $post  Post.
 * @param string  $token Subscriber token.
 * @return string
 */
function classicpack_subscribers_newsletter_html( $post, $token ) {
	$site    = get_bloginfo( 'name' );
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	$logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	$brand   = $logo
		? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $site ) . '" style="max-width:180px;height:auto;margin-bottom:24px;" />'
		: '<p style="font-size:18px;font-weight:bold;margin:0 0 24px;">' . esc_html( $site ) . '</p>';

	return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f4f5;">'
		. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f5;"><tr><td align="center" style="padding:32px 16px;">'
		. '<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background:#ffffff;max-width:600px;width:100%;"><tr><td style="padding:40px 40px 24px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;color:#111;">'
		. $brand
		. '<h1 style="font-size:28px;line-height:1.25;margin:0 0 8px;">' . esc_html( get_the_title( $post ) ) . '</h1>'
		. '<p style="color:#6b7280;font-size:14px;margin:0 0 24px;">' . esc_html( get_the_date( '', $post ) ) . '</p>'
		. '<p style="font-size:16px;line-height:1.7;margin:0 0 28px;">' . esc_html( classicpack_subscribers_post_excerpt( (int) $post->ID ) ) . '</p>'
		. '<p style="margin:0 0 40px;"><a href="' . esc_url( get_permalink( $post ) ) . '" style="display:inline-block;background:#1d4ed8;color:#ffffff;text-decoration:none;padding:12px 22px;font-weight:600;">' . esc_html__( 'Read the article', 'classicpack' ) . '</a></p>'
		. '<hr style="border:0;border-top:1px solid #e5e7eb;margin:0 0 20px;" />'
		/* translators: %s: site name */
		. '<p style="color:#6b7280;font-size:13px;margin:0 0 8px;">' . sprintf( esc_html__( "You're receiving this because you subscribed to %s.", 'classicpack' ), esc_html( $site ) ) . '</p>'
		. '<p style="margin:0;"><a href="' . esc_url( classicpack_subscribers_action_url( 'unsubscribe', $token ) ) . '" style="color:#6b7280;font-size:13px;">' . esc_html__( 'Unsubscribe', 'classicpack' ) . '</a></p>'
		. '</td></tr></table></td></tr></table></body></html>';
}
