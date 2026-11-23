<?php
/**
 * Forms: mail dispatch through wp_mail() or the SMTP2Go API.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define(
	'CLASSICPACK_FORMS_MAILERS',
	[
		'default' => 'WordPress default (wp_mail)',
		'smtp2go' => 'SMTP2Go',
	]
);

/**
 * Send through whichever mailer the settings select.
 *
 * @param string[] $recipients Recipient addresses.
 * @param string   $subject    Subject.
 * @param string   $html_body  HTML body.
 * @param string[] $headers    WP-style header strings.
 * @return bool
 */
function classicpack_forms_dispatch_mail( array $recipients, $subject, $html_body, array $headers ) {
	$mailer = (string) get_option( 'classicpack_forms_mailer', 'default' );

	if ( $mailer === 'smtp2go' ) {
		return classicpack_forms_send_smtp2go( $recipients, $subject, $html_body, $headers );
	}

	return (bool) wp_mail( $recipients, $subject, $html_body, $headers );
}

/**
 * Send through the SMTP2Go REST API.
 *
 * @param string[] $recipients Recipient addresses.
 * @param string   $subject    Subject.
 * @param string   $html_body  HTML body.
 * @param string[] $headers    WP-style header strings, e.g. "Reply-To: foo@bar.com".
 * @return bool
 */
function classicpack_forms_send_smtp2go( array $recipients, $subject, $html_body, array $headers ) {
	$api_key = (string) get_option( 'classicpack_forms_smtp2go_key', '' );
	if ( $api_key === '' ) {
		return false;
	}

	$site_name  = (string) get_option( 'blogname', '' );
	$site_email = (string) get_option( 'admin_email', '' );
	$sender     = $site_name !== '' ? $site_name . ' <' . $site_email . '>' : $site_email;

	// Extract Reply-To values from WP-style header strings.
	$reply_to = [];
	foreach ( $headers as $header ) {
		if ( stripos( $header, 'Reply-To:' ) === 0 ) {
			$reply_to[] = trim( substr( $header, 9 ) );
		}
	}

	$data = [
		'api_key'   => $api_key,
		'sender'    => $sender,
		'to'        => $recipients,
		'subject'   => $subject,
		'html_body' => $html_body,
		'text_body' => wp_strip_all_tags( $html_body ),
	];
	if ( $reply_to ) {
		$data['reply_to'] = $reply_to;
	}

	$response = wp_remote_post(
		'https://api.smtp2go.com/v3/email/send',
		[
			'headers'     => [ 'Content-Type' => 'application/json' ],
			'body'        => wp_json_encode( $data ),
			'timeout'     => 15,
			'data_format' => 'body',
		]
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$result = json_decode( wp_remote_retrieve_body( $response ), true );

	return is_array( $result )
		&& isset( $result['data']['succeeded'] )
		&& (int) $result['data']['succeeded'] > 0;
}
