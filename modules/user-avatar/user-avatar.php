<?php
/**
 * User Avatar module for ClassicPack.
 *
 * Profile picture from the Media Library; replaces Gravatar via get_avatar when set.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_enqueue_scripts', 'classicpack_author_avatar_image_admin_enqueue_scripts' );
add_action( 'show_user_profile', 'classicpack_author_avatar_image_profile_fields' );
add_action( 'edit_user_profile', 'classicpack_author_avatar_image_profile_fields' );
add_action( 'personal_options_update', 'classicpack_author_avatar_image_save_profile_attachment' );
add_action( 'edit_user_profile_update', 'classicpack_author_avatar_image_save_profile_attachment' );
add_filter( 'get_avatar', 'classicpack_author_avatar_image_filter_avatar', 10, 5 );

/**
 * Enqueue scripts and styles on profile / user-edit screens only.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function classicpack_author_avatar_image_admin_enqueue_scripts( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'profile.php', 'user-edit.php' ), true ) ) {
		return;
	}

	wp_enqueue_style(
		'classicpack-author-avatar-image',
		plugins_url( 'css/user-avatar.css', __FILE__ ),
		array(),
		CLASSICPACK_VERSION
	);

	wp_enqueue_media();

	wp_enqueue_script(
		'classicpack-author-avatar-image',
		plugins_url( 'js/user-avatar.js', __FILE__ ),
		array( 'jquery' ),
		CLASSICPACK_VERSION,
		true
	);

	wp_localize_script(
		'classicpack-author-avatar-image',
		'classicpackAuthorAvatarImage',
		array(
			'mediaTitle'       => __( 'Choose image — profile picture', 'classicpack' ),
			'mediaButtonTitle' => __( 'Select', 'classicpack' ),
			'deleteConfirm'    => __( 'Remove this profile picture?', 'classicpack' ),
			'uploadButtonText' => __( 'Upload New Profile Picture', 'classicpack' ),
			'changeButtonText' => __( 'Change Profile Picture', 'classicpack' ),
		)
	);
}

/**
 * Output profile picture field and hidden attachment id input.
 *
 * @param WP_User $user User being edited.
 * @return void
 */
function classicpack_author_avatar_image_profile_fields( $user ) {
	if ( ! $user instanceof WP_User ) {
		return;
	}

	$avatar_meta = get_user_meta( $user->ID, 'easy-author-avatar-profile-image', true );
	$avatar_url  = $avatar_meta ? wp_get_attachment_image_url( (int) $avatar_meta ) : '';
	$hide_class  = ! $avatar_url ? ' easy-author-avatar-image-hide' : '';
	$value       = $avatar_meta ? (string) (int) $avatar_meta : '';
	?>
	<div class="easy-author-avatar-image-upload-wrap">
		<input type="hidden" id="easy-author-avatar-image-id" class="easy-author-avatar-image-input" name="easy-author-avatar-image-id" value="<?php echo esc_attr( $value ); ?>">
		<h3><?php esc_html_e( 'Profile picture', 'classicpack' ); ?></h3>
		<table class="author-avatar-image-form-table">
			<tbody>
				<tr class="easy-author-avatar-image-user-profile-picture">
					<th><?php esc_html_e( 'Profile Picture', 'classicpack' ); ?></th>
					<td>
						<img class="avatar avatar-96 photo easy-author-avatar-img<?php echo esc_attr( $hide_class ); ?>" id="easy-author-avatar-image-custom" src="<?php echo $avatar_url ? esc_url( $avatar_url ) : ''; ?>" width="96" height="96" alt="" />
						<div class="easy-author-avatar-image-upload-action">
							<button type="button" class="button easy-author-avatar-image-upload" id="easy-author-avatar-image-upload">
								<?php echo $avatar_url ? esc_html__( 'Change Profile Picture', 'classicpack' ) : esc_html__( 'Upload New Profile Picture', 'classicpack' ); ?>
							</button>
							<button type="button" id="easy-author-avatar-image-delete-btn" class="button easy-author-avatar-image-remove<?php echo esc_attr( $hide_class ); ?>">
								<?php esc_html_e( 'Delete profile picture', 'classicpack' ); ?>
							</button>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Save attachment id user meta after profile / user save.
 *
 * @param int $user_id Saved user ID.
 * @return void
 */
function classicpack_author_avatar_image_save_profile_attachment( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	check_admin_referer( 'update-user_' . $user_id );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core profile form nonce verified via check_admin_referer above.
	if ( ! isset( $_POST['easy-author-avatar-image-id'] ) ) {
		return;
	}

	$raw = sanitize_text_field( wp_unslash( $_POST['easy-author-avatar-image-id'] ) );
	$attachment_id = '' === trim( $raw ) ? 0 : absint( $raw );

	if ( $attachment_id <= 0 ) {
		delete_user_meta( $user_id, 'easy-author-avatar-profile-image' );
		return;
	}

	if ( false === wp_get_attachment_url( $attachment_id ) ) {
		return;
	}

	update_user_meta( $user_id, 'easy-author-avatar-profile-image', $attachment_id );
}

/**
 * Build avatar markup from profile attachment when present.
 *
 * @param string $avatar      Avatar HTML from core.
 * @param mixed  $id_or_email User id, email, WP_User, or object with user_id.
 * @param mixed  $size        Requested avatar size (int or array with width/height).
 * @param string $default Default avatar URL or type (unused).
 * @param string $alt     Alt text from core.
 * @return string
 */
function classicpack_author_avatar_image_filter_avatar( $avatar, $id_or_email, $size, $default, $alt ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature matches get_avatar filter.
	$user = classicpack_author_avatar_image_resolve_user( $id_or_email );
	if ( ! $user instanceof WP_User ) {
		return $avatar;
	}

	$attachment_id = get_user_meta( $user->ID, 'easy-author-avatar-profile-image', true );
	if ( ! $attachment_id ) {
		return $avatar;
	}

	$avatar_url = wp_get_attachment_image_url( (int) $attachment_id );
	if ( ! $avatar_url ) {
		return $avatar;
	}

	$dim = classicpack_author_avatar_image_avatar_dimensions( $size );

	$alt_text = is_string( $alt ) && '' !== trim( $alt ) ? $alt : $user->display_name;
	$alt_text = apply_filters( 'classicpack_author_avatar_image_alt', $alt_text, $user->ID, $user );

	return sprintf(
		'<img alt="%s" src="%s" class="avatar avatar-%d photo" height="%d" width="%d" loading="lazy" decoding="async" />',
		esc_attr( $alt_text ),
		esc_url( $avatar_url ),
		$dim['w'],
		$dim['h'],
		$dim['w'],
		$dim['h']
	);
}

/**
 * Width/height from get_avatar size (number or width/height array).
 *
 * @param mixed $size Core size argument.
 * @return array{w:int,h:int}
 */
function classicpack_author_avatar_image_avatar_dimensions( $size ) {
	if ( is_array( $size ) ) {
		$w = isset( $size['width'] ) ? (int) $size['width'] : ( isset( $size['size'] ) ? (int) $size['size'] : 96 );
		$h = isset( $size['height'] ) ? (int) $size['height'] : $w;
		return array(
			'w' => max( 1, $w ),
			'h' => max( 1, $h ),
		);
	}
	if ( is_numeric( $size ) ) {
		$n = max( 1, (int) $size );
		return array(
			'w' => $n,
			'h' => $n,
		);
	}
	return array(
		'w' => 96,
		'h' => 96,
	);
}

/**
 * Resolve WP_User from get_avatar identifier.
 *
 * @param mixed $id_or_email Numeric user id, email string, WP_User, or object with user_id.
 * @return WP_User|false
 */
function classicpack_author_avatar_image_resolve_user( $id_or_email ) {
	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', (int) $id_or_email );
		return $user instanceof WP_User ? $user : false;
	}

	if ( $id_or_email instanceof WP_User ) {
		return $id_or_email->exists() ? $id_or_email : false;
	}

	if ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) && (int) $id_or_email->user_id > 0 ) {
		$user = get_user_by( 'id', (int) $id_or_email->user_id );
		return $user instanceof WP_User ? $user : false;
	}

	if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
		return $user instanceof WP_User ? $user : false;
	}

	return false;
}
