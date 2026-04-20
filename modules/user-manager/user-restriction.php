<?php
/**
 * Restrict posts/pages to logged-in users — meta box and front-end filtering.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post meta flag (1 = members only). Kept for compatibility with existing content.
 */
define( 'CLASSICPACK_USER_RESTRICTION_META_KEY', 'has_user_restriction' );

add_filter( 'get_pages', 'classicpack_user_restriction_filter_pages', 1 );
add_filter( 'the_posts', 'classicpack_user_restriction_filter_posts', 10, 2 );

add_action( 'save_post', 'classicpack_user_restriction_save_meta', 10, 2 );

add_action( 'add_meta_boxes', 'classicpack_user_restriction_add_meta_box' );

add_filter( 'the_content', 'classicpack_user_restriction_the_content', 10 );

add_action( 'manage_posts_custom_column', 'classicpack_user_restriction_column_post', 10, 2 );
add_action( 'manage_pages_custom_column', 'classicpack_user_restriction_column_post', 10, 2 );
add_filter( 'manage_edit-post_columns', 'classicpack_user_restriction_columns_post' );
add_filter( 'manage_edit-page_columns', 'classicpack_user_restriction_columns_post' );

/**
 * Front-end: replace content for guests when restriction is enabled.
 *
 * @param string $content Post content.
 * @return string
 */
function classicpack_user_restriction_the_content( $content ) {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return $content;
	}

	if ( (int) get_post_meta( $post_id, CLASSICPACK_USER_RESTRICTION_META_KEY, true ) !== 1 ) {
		return $content;
	}

	if ( is_user_logged_in() ) {
		return $content;
	}

	$login = wp_login_url( get_permalink( $post_id ) );

	return '<p>' . esc_html__( 'This content is restricted to logged-in users.', 'classicpack' ) . '</p>'
		. '<p><a href="' . esc_url( $login ) . '">' . esc_html__( 'Log in to view this content.', 'classicpack' ) . '</a></p>';
}

/**
 * Hide restricted items from listings for guests (pages list).
 *
 * @param WP_Post[] $posts Posts.
 * @return WP_Post[]
 */
function classicpack_user_restriction_filter_pages( $posts ) {
	if ( is_admin() || is_user_logged_in() ) {
		return $posts;
	}

	$filtered = array();
	foreach ( $posts as $post ) {
		if ( ! (int) get_post_meta( $post->ID, CLASSICPACK_USER_RESTRICTION_META_KEY, true ) ) {
			$filtered[] = $post;
		}
	}
	return $filtered;
}

/**
 * Hide restricted items from post queries for guests.
 *
 * @param WP_Post[]   $posts Posts.
 * @param WP_Query|null $query Query.
 * @return WP_Post[]
 */
function classicpack_user_restriction_filter_posts( $posts, $query = null ) {
	if ( is_admin() || is_user_logged_in() ) {
		return $posts;
	}

	if ( $query instanceof WP_Query && $query->is_singular() ) {
		return $posts;
	}

	$filtered = array();
	foreach ( $posts as $post ) {
		if ( ! (int) get_post_meta( $post->ID, CLASSICPACK_USER_RESTRICTION_META_KEY, true ) ) {
			$filtered[] = $post;
		}
	}
	return $filtered;
}

/**
 * @return void
 */
function classicpack_user_restriction_add_meta_box() {
	add_meta_box(
		'classicpack-user-restriction',
		__( 'User restriction', 'classicpack' ),
		'classicpack_user_restriction_render_meta_box',
		array( 'post', 'page' ),
		'side',
		'low'
	);
}

/**
 * @param WP_Post $post Post.
 * @return void
 */
function classicpack_user_restriction_render_meta_box( $post ) {
	wp_nonce_field( 'classicpack_user_restriction_save', 'classicpack_user_restriction_nonce' );

	$restricted = (int) get_post_meta( $post->ID, CLASSICPACK_USER_RESTRICTION_META_KEY, true );
	?>
    <p>
        <label>
            <input type="checkbox" name="classicpack_has_user_restriction" value="1" <?php checked( $restricted, 1 ); ?> />
            <?php esc_html_e( 'Restrict to logged-in users', 'classicpack' ); ?>
        </label>
    </p>
	<?php
}

/**
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 * @return void
 */
function classicpack_user_restriction_save_meta( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['classicpack_user_restriction_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['classicpack_user_restriction_nonce'] ) ), 'classicpack_user_restriction_save' ) ) {
		return;
	}

	if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$value = isset( $_POST['classicpack_has_user_restriction'] ) ? 1 : 0;
	update_post_meta( $post_id, CLASSICPACK_USER_RESTRICTION_META_KEY, $value );
}

/**
 * @param string $column Column name.
 * @param int    $post_id Post ID.
 * @return void
 */
function classicpack_user_restriction_column_post( $column, $post_id ) {
	if ( 'classicpack_user_restriction' !== $column ) {
		return;
	}

	if ( (int) get_post_meta( $post_id, CLASSICPACK_USER_RESTRICTION_META_KEY, true ) === 1 ) {
		echo '<span class="dashicons dashicons-lock" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Restricted', 'classicpack' ) . '</span>';
	}
}

/**
 * @param string[] $columns Columns.
 * @return string[]
 */
function classicpack_user_restriction_columns_post( $columns ) {
	$columns['classicpack_user_restriction'] = __( 'Restricted', 'classicpack' );
	return $columns;
}
