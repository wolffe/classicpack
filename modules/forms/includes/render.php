<?php
/**
 * Forms: front-end rendering via the [classicpack_form] shortcode.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [classicpack_form id=N] shortcode.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function classicpack_forms_shortcode( $atts ) {
	$atts    = is_array( $atts ) ? $atts : [];
	$form_id = isset( $atts['id'] ) ? (int) $atts['id'] : 0;
	if ( ! $form_id && isset( $atts[0] ) ) {
		$form_id = (int) $atts[0];
	}
	if ( ! $form_id ) {
		return '';
	}

	$post = get_post( $form_id );
	if ( ! $post || $post->post_type !== CLASSICPACK_FORMS_CPT || $post->post_status !== 'publish' ) {
		return '';
	}

	wp_enqueue_style( 'classicpack-forms-form', CLASSICPACK_FORMS_URL . 'assets/form.css', [], CLASSICPACK_VERSION );

	$config = classicpack_forms_get_config( $form_id );
	$status = classicpack_forms_current_status( $form_id );
	$values = ( $status === 'error' ) ? classicpack_forms_get_stashed_values() : [];

	return classicpack_forms_render_form( $form_id, $config, $status, $values );
}

/**
 * Submission status carried in the redirect query args.
 *
 * @param int $form_id Form post ID.
 * @return string 'success', 'error' or ''.
 */
function classicpack_forms_current_status( $form_id ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect query args after a nonce-checked submit; they only select the notice.
	if ( ! isset( $_GET['classicpack_forms_form'] ) || absint( wp_unslash( $_GET['classicpack_forms_form'] ) ) !== $form_id ) {
		return '';
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect query args after a nonce-checked submit; they only select the notice.
	$status = isset( $_GET['classicpack_forms_status'] ) ? sanitize_key( (string) wp_unslash( $_GET['classicpack_forms_status'] ) ) : '';
	return in_array( $status, [ 'success', 'error' ], true ) ? $status : '';
}

/**
 * Current URL without the submission query args.
 *
 * @return string
 */
function classicpack_forms_current_url() {
	$req = isset( $_SERVER['REQUEST_URI'] )
		? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
		: '/';
	return remove_query_arg( [ 'classicpack_forms_status', 'classicpack_forms_form', 'classicpack_forms_token' ], home_url( $req ) );
}

/**
 * Values stashed by a failed submission, to refill the form.
 *
 * @return array<string,mixed>
 */
function classicpack_forms_get_stashed_values() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect query args after a nonce-checked submit; they only refill the form.
	$token = isset( $_GET['classicpack_forms_token'] ) ? sanitize_key( (string) wp_unslash( $_GET['classicpack_forms_token'] ) ) : '';
	if ( $token === '' ) {
		return [];
	}
	$values = get_transient( 'classicpack_forms_stash_' . $token );
	return is_array( $values ) ? $values : [];
}

/**
 * Form markup.
 *
 * @param int                 $form_id Form post ID.
 * @param array<string,mixed> $config  Form configuration.
 * @param string              $status  Submission status.
 * @param array<string,mixed> $values  Values to refill.
 * @return string
 */
function classicpack_forms_render_form( $form_id, array $config, $status, array $values = [] ) {
	ob_start();
	?>
	<form class="classicpack-forms-form" id="classicpack-forms-form-<?php echo (int) $form_id; ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
		<input type="hidden" name="action" value="classicpack_forms_submit">
		<input type="hidden" name="form_id" value="<?php echo (int) $form_id; ?>">
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( classicpack_forms_current_url() ); ?>">
		<?php wp_nonce_field( 'classicpack_forms_submit_' . $form_id, 'classicpack_forms_nonce' ); ?>

		<div class="classicpack-forms-honeypot" aria-hidden="true">
			<label>Leave this field empty <input type="text" name="classicpack_forms_hp" value="" tabindex="-1" autocomplete="off"></label>
		</div>

		<?php if ( $status === 'success' ) { ?>
			<div class="classicpack-forms-status classicpack-forms-status-success"><?php echo esc_html( $config['success_message'] ); ?></div>
		<?php } elseif ( $status === 'error' ) { ?>
			<div class="classicpack-forms-status classicpack-forms-status-error"><?php echo esc_html( $config['error_message'] ); ?></div>
		<?php } ?>

		<?php foreach ( $config['fields'] as $field ) { ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is escaped inside classicpack_forms_render_field().
			echo classicpack_forms_render_field( $form_id, $field, $values );
			?>
		<?php } ?>

		<?php if ( ! empty( $config['captcha'] ) ) { ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is escaped inside classicpack_forms_render_captcha_field().
			echo classicpack_forms_render_captcha_field( $form_id );
			?>
		<?php } ?>

		<p class="classicpack-forms-actions">
			<button type="submit" class="classicpack-forms-submit"><?php echo esc_html( $config['submit_label'] ); ?></button>
		</p>
	</form>
	<?php
	return (string) ob_get_clean();
}

/**
 * One field.
 *
 * @param int                 $form_id Form post ID.
 * @param array<string,mixed> $field   Field definition.
 * @param array<string,mixed> $values  Values to refill.
 * @return string
 */
function classicpack_forms_render_field( $form_id, array $field, array $values = [] ) {
	$id       = 'classicpack_forms_' . $form_id . '_' . $field['id'];
	$name     = $field['id'];
	$required = ! empty( $field['required'] );
	$req_mark = $required ? ' <span class="classicpack-forms-required" aria-hidden="true">*</span>' : '';
	$width    = ( $field['width'] ?? 'full' ) === 'half' ? 'half' : 'full';

	$type = $field['type'];
	$val  = $values[ $field['id'] ] ?? null;

	ob_start();
	echo '<p class="classicpack-forms-field classicpack-forms-field-' . esc_attr( $type ) . ' classicpack-forms-field-width-' . esc_attr( $width ) . '">';

	switch ( $type ) {
		case 'checkbox':
			echo '<label for="' . esc_attr( $id ) . '" class="classicpack-forms-checkbox-label">';
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . ( $required ? ' required' : '' ) . ( ! empty( $val ) ? ' checked' : '' ) . '>';
			echo '<span>' . esc_html( (string) $field['label'] ) . wp_kses_post( $req_mark ) . '</span>';
			echo '</label>';
			break;

		case 'textarea':
			echo '<label for="' . esc_attr( $id ) . '">' . esc_html( (string) $field['label'] ) . wp_kses_post( $req_mark ) . '</label>';
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="5"' . ( $required ? ' required' : '' ) . '>' . esc_textarea( is_string( $val ) ? $val : '' ) . '</textarea>';
			break;

		case 'select':
			echo '<label for="' . esc_attr( $id ) . '">' . esc_html( (string) $field['label'] ) . wp_kses_post( $req_mark ) . '</label>';
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . '>';
			echo '<option value="">' . esc_html__( '— Select —', 'classicpack' ) . '</option>';
			foreach ( $field['options'] as $opt ) {
				echo '<option value="' . esc_attr( $opt ) . '"' . ( ( is_string( $val ) && $val === $opt ) ? ' selected' : '' ) . '>' . esc_html( $opt ) . '</option>';
			}
			echo '</select>';
			break;

		case 'full_name':
			echo '<span class="classicpack-forms-field-label">' . esc_html( (string) $field['label'] ) . wp_kses_post( $req_mark ) . '</span>';
			echo '<span class="classicpack-forms-subfields">';
			foreach ( [ 'first' => __( 'First', 'classicpack' ), 'last' => __( 'Last', 'classicpack' ) ] as $part => $sublabel ) {
				$sub_id = $id . '_' . $part;
				echo '<span class="classicpack-forms-subfield">';
				echo '<input type="text" id="' . esc_attr( $sub_id ) . '" name="' . esc_attr( $name . '_' . $part ) . '" value="' . esc_attr( is_array( $val ) ? (string) ( $val[ $part ] ?? '' ) : '' ) . '"' . ( $required ? ' required' : '' ) . '>';
				echo '<small><label for="' . esc_attr( $sub_id ) . '">' . esc_html( $sublabel ) . '</label></small>';
				echo '</span>';
			}
			echo '</span>';
			break;

		default:
			$input_type = in_array( $type, [ 'text', 'email', 'tel', 'number', 'url', 'date', 'time' ], true ) ? $type : 'text';
			echo '<label for="' . esc_attr( $id ) . '">' . esc_html( (string) $field['label'] ) . wp_kses_post( $req_mark ) . '</label>';
			echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_string( $val ) ? $val : '' ) . '"' . ( $required ? ' required' : '' ) . '>';
			break;
	}

	if ( ! empty( $field['description'] ) ) {
		echo '<small class="classicpack-forms-field-description">' . esc_html( (string) $field['description'] ) . '</small>';
	}

	echo '</p>';

	return (string) ob_get_clean();
}
