<?php
/**
 * Forms: form builder meta box and saving.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define(
	'CLASSICPACK_FORMS_FIELD_TYPES',
	[
		'text'      => 'Text',
		'full_name' => 'Full Name',
		'email'     => 'Email',
		'tel'       => 'Phone',
		'number'    => 'Number',
		'url'       => 'URL',
		'date'      => 'Date',
		'time'      => 'Time',
		'textarea'  => 'Textarea',
		'select'    => 'Select',
		'checkbox'  => 'Checkbox',
	]
);

define(
	'CLASSICPACK_FORMS_FIELD_WIDTHS',
	[
		'full' => 'Full width',
		'half' => 'Half width',
	]
);

/**
 * Default form configuration.
 *
 * @return array<string,mixed>
 */
function classicpack_forms_default_config() {
	return [
		'fields'          => [
			[
				'id'          => 'name',
				'label'       => 'Your Name',
				'type'        => 'full_name',
				'required'    => true,
				'options'     => [],
				'width'       => 'full',
				'description' => '',
			],
			[
				'id'          => 'email',
				'label'       => 'Email',
				'type'        => 'email',
				'required'    => true,
				'options'     => [],
				'width'       => 'full',
				'description' => '',
			],
			[
				'id'          => 'message',
				'label'       => 'Message',
				'type'        => 'textarea',
				'required'    => true,
				'options'     => [],
				'width'       => 'full',
				'description' => '',
			],
		],
		'submit_label'    => 'Send',
		'success_message' => 'Thanks! Your message has been sent.',
		'error_message'   => 'Something went wrong. Please check the form and try again.',
		'mail_to'         => '',
		'mail_subject'    => 'New submission on {form_title}',
		'mail_body'       => "A new submission has arrived:\n\n{data}",
		'captcha'         => false,
	];
}

/**
 * Stored configuration merged over the defaults.
 *
 * @param int $form_id Form post ID.
 * @return array<string,mixed>
 */
function classicpack_forms_get_config( $form_id ) {
	$stored = get_post_meta( $form_id, CLASSICPACK_FORMS_META, true );
	if ( ! is_array( $stored ) ) {
		return classicpack_forms_default_config();
	}
	$defaults = classicpack_forms_default_config();
	$merged   = array_replace( $defaults, $stored );
	if ( ! isset( $merged['fields'] ) || ! is_array( $merged['fields'] ) || ! $merged['fields'] ) {
		$merged['fields'] = $defaults['fields'];
	}

	// Migrate forms saved with the old incomplete default subject (missing the token).
	if ( ( $merged['mail_subject'] ?? '' ) === 'New submission on ' ) {
		$merged['mail_subject'] = $defaults['mail_subject'];
	}

	return $merged;
}

/**
 * Form Builder meta box.
 *
 * @return void
 */
function classicpack_forms_register_metabox() {
	add_meta_box(
		'classicpack-forms-builder',
		__( 'Form Builder', 'classicpack' ),
		'classicpack_forms_render_metabox',
		CLASSICPACK_FORMS_CPT,
		'normal',
		'high'
	);
}

/**
 * Shortcode bar under the title.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function classicpack_forms_display_shortcode( $post ) {
	if ( $post->post_type !== CLASSICPACK_FORMS_CPT || ! $post->ID ) {
		return;
	}

	$code = sprintf( '[classicpack_form id=%d]', $post->ID );
	?>
	<div class="classicpack-forms-shortcode-bar">
		<strong><?php esc_html_e( 'Shortcode:', 'classicpack' ); ?></strong>
		<code class="classicpack-forms-shortcode-value"><?php echo esc_html( $code ); ?></code>
		<button type="button" class="button classicpack-forms-shortcode-copy" data-clipboard="<?php echo esc_attr( $code ); ?>"><?php esc_html_e( 'Copy', 'classicpack' ); ?></button>
		<span class="classicpack-forms-shortcode-feedback" aria-live="polite"></span>
	</div>
	<?php
}

/**
 * Form Builder meta box content.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function classicpack_forms_render_metabox( $post ) {
	$config = classicpack_forms_get_config( $post->ID );
	wp_nonce_field( 'classicpack_forms_save_' . $post->ID, 'classicpack_forms_nonce' );
	?>
	<div class="classicpack-forms-builder" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>" data-types="<?php echo esc_attr( wp_json_encode( CLASSICPACK_FORMS_FIELD_TYPES ) ); ?>" data-widths="<?php echo esc_attr( wp_json_encode( CLASSICPACK_FORMS_FIELD_WIDTHS ) ); ?>">

		<input type="hidden" name="classicpack_forms_fields" id="classicpack_forms_fields" value="">

		<h3><?php esc_html_e( 'Fields', 'classicpack' ); ?></h3>
		<ul class="classicpack-forms-builder-fields"></ul>
		<p><button type="button" class="button" id="classicpack-forms-add-field"><?php esc_html_e( 'Add field', 'classicpack' ); ?></button></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="classicpack_forms_submit_label"><?php esc_html_e( 'Submit button label', 'classicpack' ); ?></label></th>
				<td><input type="text" name="classicpack_forms_submit_label" id="classicpack_forms_submit_label" class="regular-text" value="<?php echo esc_attr( $config['submit_label'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="classicpack_forms_success_message"><?php esc_html_e( 'Success message', 'classicpack' ); ?></label></th>
				<td><input type="text" name="classicpack_forms_success_message" id="classicpack_forms_success_message" class="large-text" value="<?php echo esc_attr( $config['success_message'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="classicpack_forms_error_message"><?php esc_html_e( 'Error message', 'classicpack' ); ?></label></th>
				<td><input type="text" name="classicpack_forms_error_message" id="classicpack_forms_error_message" class="large-text" value="<?php echo esc_attr( $config['error_message'] ); ?>"></td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Email notification', 'classicpack' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="classicpack_forms_mail_to"><?php esc_html_e( 'Send to', 'classicpack' ); ?></label></th>
				<td>
					<input type="text" name="classicpack_forms_mail_to" id="classicpack_forms_mail_to" class="large-text" value="<?php echo esc_attr( $config['mail_to'] ); ?>" placeholder="you@example.com, other@example.com">
					<p class="description"><?php esc_html_e( 'Comma- or semicolon-separated. Falls back to the site admin email if blank.', 'classicpack' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="classicpack_forms_mail_subject"><?php esc_html_e( 'Subject', 'classicpack' ); ?></label></th>
				<td><input type="text" name="classicpack_forms_mail_subject" id="classicpack_forms_mail_subject" class="large-text" value="<?php echo esc_attr( $config['mail_subject'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="classicpack_forms_mail_body"><?php esc_html_e( 'Body', 'classicpack' ); ?></label></th>
				<td>
					<textarea name="classicpack_forms_mail_body" id="classicpack_forms_mail_body" rows="6" class="large-text"><?php echo esc_textarea( $config['mail_body'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Placeholders: {data} = all fields, {form_title}, {form_id}, and any field id like {email} or {message}.', 'classicpack' ); ?></p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'CAPTCHA', 'classicpack' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable CAPTCHA', 'classicpack' ); ?></th>
				<td>
					<label>
						<input type="checkbox" class="classicpack-ui-toggle" name="classicpack_forms_captcha" value="1" <?php checked( ! empty( $config['captcha'] ) ); ?>>
						<?php esc_html_e( 'Add a text verification code to this form', 'classicpack' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Visitors must type a 6-character code before they can submit.', 'classicpack' ); ?></p>
				</td>
			</tr>
		</table>
	</div>
	<?php
}

/**
 * Builder assets on the form edit screen only.
 *
 * @param string $hook Current admin page hook.
 * @return void
 */
function classicpack_forms_admin_assets( $hook ) {
	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== CLASSICPACK_FORMS_CPT ) {
		return;
	}

	$deps = [];
	if ( wp_script_is( 'sortablejs', 'registered' ) ) {
		wp_enqueue_script( 'sortablejs' );
		$deps[] = 'sortablejs';
	}

	wp_enqueue_style( 'classicpack-forms-admin', CLASSICPACK_FORMS_URL . 'assets/admin.css', [], CLASSICPACK_VERSION );
	wp_enqueue_script( 'classicpack-forms-admin', CLASSICPACK_FORMS_URL . 'assets/admin.js', $deps, CLASSICPACK_VERSION, true );
}

/**
 * Save the form configuration.
 *
 * @param int $post_id Form post ID.
 * @return void
 */
function classicpack_forms_save_post( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if (
		! isset( $_POST['classicpack_forms_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['classicpack_forms_nonce'] ) ), 'classicpack_forms_save_' . $post_id )
	) {
		return;
	}

	$config = classicpack_forms_default_config();

	if ( isset( $_POST['classicpack_forms_fields'] ) ) {
		$raw     = sanitize_textarea_field( (string) wp_unslash( $_POST['classicpack_forms_fields'] ) );
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$config['fields'] = classicpack_forms_sanitize_fields( $decoded );
		}
	}
	if ( empty( $config['fields'] ) ) {
		$config['fields'] = classicpack_forms_default_config()['fields'];
	}

	if ( isset( $_POST['classicpack_forms_submit_label'] ) ) {
		$config['submit_label'] = sanitize_text_field( (string) wp_unslash( $_POST['classicpack_forms_submit_label'] ) );
	}
	if ( isset( $_POST['classicpack_forms_success_message'] ) ) {
		$config['success_message'] = sanitize_text_field( (string) wp_unslash( $_POST['classicpack_forms_success_message'] ) );
	}
	if ( isset( $_POST['classicpack_forms_error_message'] ) ) {
		$config['error_message'] = sanitize_text_field( (string) wp_unslash( $_POST['classicpack_forms_error_message'] ) );
	}
	if ( isset( $_POST['classicpack_forms_mail_to'] ) ) {
		$config['mail_to'] = classicpack_forms_sanitize_recipient_list( sanitize_text_field( (string) wp_unslash( $_POST['classicpack_forms_mail_to'] ) ) );
	}
	if ( isset( $_POST['classicpack_forms_mail_subject'] ) ) {
		$config['mail_subject'] = sanitize_text_field( (string) wp_unslash( $_POST['classicpack_forms_mail_subject'] ) );
	}
	if ( isset( $_POST['classicpack_forms_mail_body'] ) ) {
		$config['mail_body'] = sanitize_textarea_field( (string) wp_unslash( $_POST['classicpack_forms_mail_body'] ) );
	}

	$config['captcha'] = ! empty( $_POST['classicpack_forms_captcha'] );

	update_post_meta( $post_id, CLASSICPACK_FORMS_META, $config );
}

/**
 * Sanitize the field list posted by the builder.
 *
 * @param array<int,mixed> $raw Decoded JSON from the builder.
 * @return array<int,array<string,mixed>>
 */
function classicpack_forms_sanitize_fields( array $raw ) {
	$clean    = [];
	$seen_ids = [];

	foreach ( $raw as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$label = isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
		if ( $label === '' ) {
			continue;
		}

		$type = isset( $item['type'] ) ? (string) $item['type'] : 'text';
		if ( ! array_key_exists( $type, CLASSICPACK_FORMS_FIELD_TYPES ) ) {
			$type = 'text';
		}

		$id = isset( $item['id'] ) ? sanitize_key( (string) $item['id'] ) : '';
		if ( $id === '' || isset( $seen_ids[ $id ] ) ) {
			$id = classicpack_forms_unique_field_id( $label, $seen_ids );
		}
		$seen_ids[ $id ] = true;

		$options = [];
		if ( $type === 'select' && isset( $item['options'] ) && is_array( $item['options'] ) ) {
			foreach ( $item['options'] as $opt ) {
				$opt = sanitize_text_field( (string) $opt );
				if ( $opt !== '' && ! in_array( $opt, $options, true ) ) {
					$options[] = $opt;
				}
			}
		}

		$width = isset( $item['width'] ) && array_key_exists( (string) $item['width'], CLASSICPACK_FORMS_FIELD_WIDTHS )
			? (string) $item['width']
			: 'full';

		$description = isset( $item['description'] )
			? sanitize_text_field( (string) $item['description'] )
			: '';

		$clean[] = [
			'id'          => $id,
			'label'       => $label,
			'type'        => $type,
			'required'    => ! empty( $item['required'] ),
			'options'     => $options,
			'width'       => $width,
			'description' => $description,
		];
	}

	return $clean;
}

/**
 * Field id derived from the label, unique within the form.
 *
 * @param string              $label    Field label.
 * @param array<string,bool>  $existing Ids already taken.
 * @return string
 */
function classicpack_forms_unique_field_id( $label, array $existing ) {
	$base = sanitize_title( $label );
	$base = $base !== '' ? str_replace( '-', '_', $base ) : 'field';

	$id = $base;
	$i  = 2;
	while ( isset( $existing[ $id ] ) ) {
		$id = $base . '_' . ( $i++ );
	}
	return $id;
}

/**
 * Normalise a comma- or semicolon-separated recipient list.
 *
 * @param string $raw Posted value.
 * @return string
 */
function classicpack_forms_sanitize_recipient_list( $raw ) {
	if ( trim( $raw ) === '' ) {
		return '';
	}

	$emails = [];
	foreach ( preg_split( '/[,;]/', $raw ) ?: [] as $piece ) {
		$email = sanitize_email( trim( $piece ) );
		if ( $email !== '' && is_email( $email ) && ! in_array( $email, $emails, true ) ) {
			$emails[] = $email;
		}
	}
	return implode( ', ', $emails );
}
