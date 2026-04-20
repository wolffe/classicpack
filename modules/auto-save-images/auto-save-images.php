<?php
/**
 * Auto Save Images module for ClassicPack (procedural).
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_insert_post_data', 'classicpress_auto_save_images_post_save', 10, 2 );
add_action( 'admin_menu', 'classicpress_auto_save_images_register_submenu', 12 );
add_filter( 'intermediate_image_sizes_advanced', 'classicpress_auto_save_images_remove_tmb' );
add_action( 'submitpost_box', 'classicpress_auto_save_images_submit_box' );
add_action( 'submitpage_box', 'classicpress_auto_save_images_submit_box' );
add_action( 'init', 'classicpress_auto_save_images_register_post_meta' );
add_action( 'enqueue_block_editor_assets', 'classicpress_auto_save_images_enqueue_block_editor' );

/**
 * REST auth for post meta.
 *
 * @return bool
 */
function classicpress_auto_save_images_meta_auth() {
	return current_user_can( 'edit_posts' );
}

/**
 * Register post meta for the block editor skip toggle.
 *
 * @return void
 */
function classicpress_auto_save_images_register_post_meta() {
	register_post_meta(
		'',
		'_classicpress_skip_remote_images',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'auth_callback' => 'classicpress_auto_save_images_meta_auth',
		)
	);
}

/**
 * Submenu under ClassicPack.
 *
 * @return void
 */
function classicpress_auto_save_images_register_submenu() {
	if ( ! function_exists( 'classicpack_get_menu_slug' ) ) {
		return;
	}
	add_submenu_page(
		classicpack_get_menu_slug(),
		__( 'Auto Save Images', 'classicpack' ),
		__( 'Auto Save Images', 'classicpack' ),
		'manage_options',
		'classicpress-auto-save-images',
		'classicpress_auto_save_images_options_form'
	);
}

/**
 * Enqueue block editor script when per-post toggle is enabled in settings.
 *
 * @return void
 */
function classicpress_auto_save_images_enqueue_block_editor() {
	$options = get_option( 'classicpress_auto_save_images_options' );
	if ( ! is_array( $options ) || empty( $options['switch'] ) || 'yes' !== $options['switch'] ) {
		return;
	}
	wp_enqueue_script(
		'classicpress-auto-save-images-block-editor',
		plugins_url( 'block-editor-toggle.js', __FILE__ ),
		array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' ),
		CLASSICPACK_VERSION,
		true
	);
}

/**
 * Replace remote images in post content on save.
 *
 * @param array $data    Post data.
 * @param array $postarr Raw post array.
 * @return array
 */
function classicpress_auto_save_images_post_save( $data, $postarr ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return $data;
	}
	if ( isset( $data['post_status'] ) && 'auto-draft' === $data['post_status'] ) {
		return $data;
	}
	if ( isset( $postarr['post_type'] ) && 'revision' === $postarr['post_type'] ) {
		return $data;
	}

	$post_id = ! empty( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- wp_insert_post_data runs after core has verified the save request for this post.
	$skip = ( isset( $_POST['classicpress_skip_remote_save'] ) && '1' === wp_unslash( $_POST['classicpress_skip_remote_save'] ) )
		|| ( $post_id && get_post_meta( $post_id, '_classicpress_skip_remote_images', true ) === 'yes' );

	if ( $skip ) {
		return $data;
	}

	set_time_limit( 240 );
	$content = $data['post_content'];
	$preg    = preg_match_all( '/<img[^>]+src="([^"]+)"/i', stripslashes( $content ), $matches );
	if ( $preg && $post_id ) {
		$i = 1;
		foreach ( $matches[1] as $image_url ) {
			if ( empty( $image_url ) ) {
				continue;
			}
			if ( strpos( $image_url, get_bloginfo( 'url' ) ) === false ) {
				$res = classicpress_auto_save_images_save_remote( $image_url, $post_id, $i );
				if ( ! empty( $res['url'] ) ) {
					$content = str_replace( $image_url, $res['url'], $content );
				}
			}
			++$i;
		}
	}
	$data['post_content'] = $content;
	return $data;
}

/**
 * Download remote image and attach to post.
 *
 * @param string $image_url URL.
 * @param int    $post_id   Post ID.
 * @param int    $i         Image index in content.
 * @return array{url: string}
 */
function classicpress_auto_save_images_save_remote( $image_url, $post_id, $i ) {
	$options = get_option( 'classicpress_auto_save_images_options' );
	if ( ! is_array( $options ) ) {
		$options = array();
	}

	if ( ! wp_http_validate_url( $image_url ) ) {
		return array( 'url' => $image_url );
	}

	$response = wp_remote_get( $image_url, array( 'timeout' => 30 ) );
	if ( is_wp_error( $response ) ) {
		return array( 'url' => $image_url );
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== (int) $status_code ) {
		return array( 'url' => $image_url );
	}

	$content_type = wp_remote_retrieve_header( $response, 'content-type' );
	if ( strpos( $content_type, 'image/' ) === false ) {
		return array( 'url' => $image_url );
	}

	$file = wp_remote_retrieve_body( $response );
	if ( empty( $file ) ) {
		return array( 'url' => $image_url );
	}

	$raw_name = urldecode( basename( (string) wp_parse_url( $image_url, PHP_URL_PATH ) ) );
	$filename = sanitize_file_name( $raw_name );

	if ( empty( pathinfo( $filename, PATHINFO_FILENAME ) ) ) {
		$ext      = pathinfo( $raw_name, PATHINFO_EXTENSION );
		$filename = md5( $image_url ) . ( $ext ? '.' . sanitize_file_name( $ext ) : '' );
	}

	$res = wp_upload_bits( $filename, null, $file );
	if ( ! empty( $res['error'] ) ) {
		return array( 'url' => $image_url );
	}

	$file        = $res['file'];
	$dirs        = wp_upload_dir();
	$filetype    = wp_check_filetype( $file );
	$attachment  = array(
		'guid'           => $dirs['baseurl'] . '/' . _wp_relative_upload_path( $file ),
		'post_mime_type' => $filetype['type'],
		'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file ) ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);
	$attach_id   = wp_insert_attachment( $attachment, $file, $post_id );
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
	wp_update_attachment_metadata( $attach_id, $attach_data );
	if ( ! empty( $options['post-tmb'] ) && 'yes' === $options['post-tmb'] && 1 === $i ) {
		set_post_thumbnail( $post_id, $attach_id );
	}
	return $res;
}

/**
 * Settings screen.
 *
 * @return void
 */
function classicpress_auto_save_images_options_form() {
	if ( isset( $_POST['submit'] ) ) {
		check_admin_referer( 'classicpress_auto_save_images_options', 'classicpress_auto_save_images_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'classicpack' ) );
		}

		$data = array(
			'tmb'      => isset( $_POST['tmb'] ) ? sanitize_text_field( wp_unslash( $_POST['tmb'] ) ) : '',
			'switch'   => isset( $_POST['switch'] ) ? sanitize_text_field( wp_unslash( $_POST['switch'] ) ) : '',
			'post-tmb' => isset( $_POST['post-tmb'] ) ? sanitize_text_field( wp_unslash( $_POST['post-tmb'] ) ) : '',
		);
		update_option( 'classicpress_auto_save_images_options', $data );
	}

	$options = get_option( 'classicpress_auto_save_images_options' );
	if ( ! is_array( $options ) ) {
		$options = array(
			'tmb'      => '',
			'switch'   => '',
			'post-tmb' => '',
		);
	}
	require __DIR__ . '/options-form.php';
}

/**
 * Strip intermediate sizes when option enabled.
 *
 * @param array $sizes Sizes.
 * @return array
 */
function classicpress_auto_save_images_remove_tmb( $sizes ) {
	$options = get_option( 'classicpress_auto_save_images_options' );
	if ( is_array( $options ) && ! empty( $options['tmb'] ) && 'yes' === $options['tmb'] ) {
		$sizes = array();
	}
	return $sizes;
}

/**
 * Classic editor checkbox to skip downloads for one post.
 *
 * @return void
 */
function classicpress_auto_save_images_submit_box() {
	$options = get_option( 'classicpress_auto_save_images_options' );
	if ( is_array( $options ) && ! empty( $options['switch'] ) && 'yes' === $options['switch'] ) {
		echo '<span style="padding-bottom:5px;display:inline-block;"><input type="checkbox" name="classicpress_skip_remote_save" value="1"/> ';
		esc_html_e( 'Skip remote image download for this post.', 'classicpack' );
		echo '</span>';
	}
}
