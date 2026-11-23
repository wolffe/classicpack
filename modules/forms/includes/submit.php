<?php
/**
 * Forms: submission handling (admin-post.php).
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validate a submission, send the notification and redirect back.
 *
 * @return void
 */
function classicpack_forms_handle_submit() {
	// phpcs:disable WordPress.Security.NonceVerification -- Form id selects the nonce action; both values are sanitized before the nonce check below.
	$form_id  = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
	$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( (string) wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );
	// phpcs:enable WordPress.Security.NonceVerification

	if ( ! $form_id ) {
		classicpack_forms_redirect( $redirect, 'error', 0 );
	}

	if (
		! isset( $_POST['classicpack_forms_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['classicpack_forms_nonce'] ) ), 'classicpack_forms_submit_' . $form_id )
	) {
		classicpack_forms_redirect( $redirect, 'error', $form_id );
	}

	if ( ! empty( $_POST['classicpack_forms_hp'] ) ) {
		// Honeypot tripped. Pretend success so bots don't learn from the response.
		classicpack_forms_redirect( $redirect, 'success', $form_id );
	}

	$post = get_post( $form_id );
	if ( ! $post || $post->post_type !== CLASSICPACK_FORMS_CPT || $post->post_status !== 'publish' ) {
		classicpack_forms_redirect( $redirect, 'error', $form_id );
	}

	$config = classicpack_forms_get_config( $form_id );
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitized in classicpack_forms_collect_values().
	$values = classicpack_forms_collect_values( $config['fields'], wp_unslash( $_POST ) );

	// Verify CAPTCHA before checking required fields so the stashed values
	// are already collected and can pre-fill the form on re-render.
	if ( ! empty( $config['captcha'] ) ) {
		$cap_token = isset( $_POST['classicpack_forms_captcha_token'] )
			? sanitize_key( (string) wp_unslash( $_POST['classicpack_forms_captcha_token'] ) )
			: '';
		$cap_input = isset( $_POST['classicpack_forms_captcha'] )
			? sanitize_text_field( (string) wp_unslash( $_POST['classicpack_forms_captcha'] ) )
			: '';
		if ( ! classicpack_forms_verify_captcha( $cap_token, $cap_input ) ) {
			$token = classicpack_forms_stash_values( $values );
			classicpack_forms_redirect( $redirect, 'error', $form_id, $token );
		}
	}

	if ( classicpack_forms_missing_required( $config['fields'], $values ) ) {
		$token = classicpack_forms_stash_values( $values );
		classicpack_forms_redirect( $redirect, 'error', $form_id, $token );
	}

	$ok = classicpack_forms_send_mail( $form_id, $config, $values );
	classicpack_forms_redirect( $redirect, $ok ? 'success' : 'error', $form_id );
}

/**
 * Sanitized values keyed by field id.
 *
 * @param array<int,array<string,mixed>> $fields Field definitions.
 * @param array<string,mixed>            $post   Unslashed POST data.
 * @return array<string,mixed>
 */
function classicpack_forms_collect_values( array $fields, array $post ) {
	$values = [];
	foreach ( $fields as $field ) {
		$name = (string) $field['id'];

		if ( $field['type'] === 'full_name' ) {
			$values[ $name ] = [
				'first' => sanitize_text_field( (string) wp_unslash( $post[ $name . '_first' ] ?? '' ) ),
				'last'  => sanitize_text_field( (string) wp_unslash( $post[ $name . '_last' ] ?? '' ) ),
			];
			continue;
		}

		$raw = $post[ $name ] ?? '';
		$raw = is_array( $raw ) ? '' : wp_unslash( (string) $raw );

		switch ( $field['type'] ) {
			case 'textarea':
				$values[ $name ] = sanitize_textarea_field( (string) $raw );
				break;
			case 'email':
				$values[ $name ] = sanitize_email( (string) $raw );
				break;
			case 'url':
				$values[ $name ] = esc_url_raw( (string) $raw );
				break;
			case 'number':
				$replaced        = preg_replace( '/[^\d.\-+e]/i', '', (string) $raw );
				$values[ $name ] = is_string( $replaced ) ? $replaced : '';
				break;
			case 'checkbox':
				$values[ $name ] = ! empty( $raw );
				break;
			case 'select':
				$values[ $name ] = in_array( (string) $raw, $field['options'], true ) ? (string) $raw : '';
				break;
			default:
				$values[ $name ] = sanitize_text_field( (string) $raw );
				break;
		}
	}
	return $values;
}

/**
 * Whether any required field is empty.
 *
 * @param array<int,array<string,mixed>> $fields Field definitions.
 * @param array<string,mixed>            $values Collected values.
 * @return bool
 */
function classicpack_forms_missing_required( array $fields, array $values ) {
	foreach ( $fields as $field ) {
		if ( empty( $field['required'] ) ) {
			continue;
		}
		$val = $values[ $field['id'] ] ?? null;

		switch ( $field['type'] ) {
			case 'checkbox':
				$missing = ! $val;
				break;
			case 'full_name':
				$missing = ! is_array( $val ) || empty( $val['first'] ) || empty( $val['last'] );
				break;
			default:
				$missing = ! is_string( $val ) || $val === '';
				break;
		}

		if ( $missing ) {
			return true;
		}
	}
	return false;
}

/**
 * Send the notification email and log it.
 *
 * @param int                 $form_id Form post ID.
 * @param array<string,mixed> $config  Form configuration.
 * @param array<string,mixed> $values  Collected values.
 * @return bool
 */
function classicpack_forms_send_mail( $form_id, array $config, array $values ) {
	$recipients = classicpack_forms_resolve_recipients( (string) ( $config['mail_to'] ?? '' ) );
	if ( ! $recipients ) {
		return false;
	}

	$form_title = (string) get_the_title( $form_id );

	$tokens = [
		'{form_id}'    => (string) $form_id,
		'{form_title}' => $form_title,
		'{data}'       => classicpack_forms_format_data( $config['fields'], $values ),
	];
	foreach ( $config['fields'] as $field ) {
		$val = $values[ $field['id'] ] ?? '';

		if ( $field['type'] === 'full_name' && is_array( $val ) ) {
			$tokens[ '{' . $field['id'] . '_first}' ] = (string) ( $val['first'] ?? '' );
			$tokens[ '{' . $field['id'] . '_last}' ]  = (string) ( $val['last'] ?? '' );
			$tokens[ '{' . $field['id'] . '}' ]       = trim( ( $val['first'] ?? '' ) . ' ' . ( $val['last'] ?? '' ) );
			continue;
		}

		if ( $field['type'] === 'checkbox' ) {
			$val = $val ? __( 'Yes', 'classicpack' ) : __( 'No', 'classicpack' );
		}
		$tokens[ '{' . $field['id'] . '}' ] = (string) $val;
	}

	$subject = strtr( (string) $config['mail_subject'], $tokens );
	$body    = wpautop( strtr( (string) $config['mail_body'], $tokens ) );

	$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
	$reply   = classicpack_forms_first_email_value( $config['fields'], $values );
	if ( $reply !== null ) {
		$headers[] = 'Reply-To: ' . $reply;
	}

	$ok = classicpack_forms_dispatch_mail( $recipients, $subject, $body, $headers );

	classicpack_forms_log_email( $form_id, $form_title, $recipients, $subject, $body, $ok );

	return $ok;
}

/**
 * Recipient list, falling back to the site admin email.
 *
 * @param string $raw Saved "Send to" value.
 * @return string[]
 */
function classicpack_forms_resolve_recipients( $raw ) {
	$out = [];
	foreach ( preg_split( '/[,;]/', $raw ) ?: [] as $piece ) {
		$email = sanitize_email( trim( $piece ) );
		if ( $email !== '' && is_email( $email ) && ! in_array( $email, $out, true ) ) {
			$out[] = $email;
		}
	}
	if ( ! $out ) {
		$admin = (string) get_option( 'admin_email' );
		if ( $admin !== '' && is_email( $admin ) ) {
			$out[] = $admin;
		}
	}
	return $out;
}

/**
 * All field values as HTML paragraphs for the {data} token.
 *
 * @param array<int,array<string,mixed>> $fields Field definitions.
 * @param array<string,mixed>            $values Collected values.
 * @return string
 */
function classicpack_forms_format_data( array $fields, array $values ) {
	$lines = [];
	foreach ( $fields as $field ) {
		$val = $values[ $field['id'] ] ?? '';

		if ( $field['type'] === 'checkbox' ) {
			$display = $val ? __( 'Yes', 'classicpack' ) : __( 'No', 'classicpack' );
		} elseif ( $field['type'] === 'full_name' && is_array( $val ) ) {
			$display = trim( ( $val['first'] ?? '' ) . ' ' . ( $val['last'] ?? '' ) );
		} else {
			$display = (string) $val;
		}

		$lines[] = '<p><strong>' . esc_html( (string) $field['label'] ) . ':</strong> '
			. nl2br( esc_html( $display ) ) . '</p>';
	}
	return implode( "\n", $lines );
}

/**
 * First valid email the visitor typed, used as Reply-To.
 *
 * @param array<int,array<string,mixed>> $fields Field definitions.
 * @param array<string,mixed>            $values Collected values.
 * @return string|null
 */
function classicpack_forms_first_email_value( array $fields, array $values ) {
	foreach ( $fields as $field ) {
		if ( $field['type'] !== 'email' ) {
			continue;
		}
		$val = $values[ $field['id'] ] ?? '';
		if ( is_string( $val ) && $val !== '' && is_email( $val ) ) {
			return $val;
		}
	}
	return null;
}

/**
 * Keep the typed values for five minutes so the form can be refilled.
 *
 * @param array<string,mixed> $values Collected values.
 * @return string Token.
 */
function classicpack_forms_stash_values( array $values ) {
	$token = wp_generate_uuid4();
	set_transient( 'classicpack_forms_stash_' . $token, $values, 5 * MINUTE_IN_SECONDS );
	return $token;
}

/**
 * Redirect back to the page with the status in the query string.
 *
 * @param string $redirect Page URL.
 * @param string $status   'success' or 'error'.
 * @param int    $form_id  Form post ID.
 * @param string $token    Stash token.
 * @return void
 */
function classicpack_forms_redirect( $redirect, $status, $form_id, $token = '' ) {
	$args = [
		'classicpack_forms_status' => $status,
		'classicpack_forms_form'   => $form_id,
	];
	if ( $token !== '' ) {
		$args['classicpack_forms_token'] = $token;
	}
	$url = add_query_arg( $args, $redirect );

	if ( $form_id ) {
		$url .= '#classicpack-forms-form-' . $form_id;
	}

	wp_safe_redirect( $url );
	exit;
}
