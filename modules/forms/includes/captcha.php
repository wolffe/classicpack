<?php
/**
 * Forms: text CAPTCHA backed by short-lived transients (no PHP sessions).
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * New challenge: a 6-character code stored for ten minutes.
 *
 * @return array{token: string, code: string}
 */
function classicpack_forms_generate_captcha() {
	$code  = substr( str_shuffle( 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789' ), 0, 6 );
	$token = wp_generate_uuid4();
	set_transient( 'classicpack_forms_cap_' . $token, $code, 10 * MINUTE_IN_SECONDS );
	return [
		'token' => $token,
		'code'  => $code,
	];
}

/**
 * Check the answer and consume the challenge (one-time use).
 *
 * @param string $token Challenge token.
 * @param string $input Visitor's answer.
 * @return bool
 */
function classicpack_forms_verify_captcha( $token, $input ) {
	if ( $token === '' || $input === '' ) {
		return false;
	}

	$key    = 'classicpack_forms_cap_' . $token;
	$stored = get_transient( $key );
	delete_transient( $key );

	if ( ! is_string( $stored ) || $stored === '' ) {
		return false;
	}

	return strtoupper( trim( $input ) ) === $stored;
}

/**
 * CAPTCHA field markup (a fresh challenge on every render).
 *
 * @param int $form_id Form post ID.
 * @return string
 */
function classicpack_forms_render_captcha_field( $form_id ) {
	$captcha  = classicpack_forms_generate_captcha();
	$input_id = 'classicpack_forms_' . $form_id . '_captcha';

	ob_start();
	echo '<p class="classicpack-forms-field classicpack-forms-field-captcha classicpack-forms-field-width-full">';
	echo '<label for="' . esc_attr( $input_id ) . '">';
	echo esc_html__( 'Verification code', 'classicpack' );
	echo ' <span class="classicpack-forms-required" aria-hidden="true">*</span>';
	echo '</label>';
	echo '<span class="classicpack-forms-captcha-code" aria-hidden="true">' . esc_html( $captcha['code'] ) . '</span>';
	echo '<input type="text" id="' . esc_attr( $input_id ) . '" name="classicpack_forms_captcha" autocomplete="off" maxlength="6" required>';
	echo '<input type="hidden" name="classicpack_forms_captcha_token" value="' . esc_attr( $captcha['token'] ) . '">';
	echo '<small class="classicpack-forms-field-description">' . esc_html__( 'Type the characters shown above (not case-sensitive).', 'classicpack' ) . '</small>';
	echo '</p>';

	return (string) ob_get_clean();
}
