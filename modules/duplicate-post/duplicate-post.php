<?php
/**
 * Duplicate post — ClassicPack module.
 *
 * Row action on public post type list tables; creates a draft with copied
 * content, meta, taxonomies, and featured image.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', 'classicpack_duplicate_post_register_row_actions', 20 );
add_action( 'admin_post_classicpack_duplicate_post', 'classicpack_duplicate_post_handle' );
add_action( 'admin_notices', 'classicpack_duplicate_post_admin_notice' );

/**
 * Register row-action filter for each public post type.
 *
 * @return void
 */
function classicpack_duplicate_post_register_row_actions() {
    foreach ( get_post_types( [ 'public' => true ], 'names' ) as $post_type ) {
        add_filter( "{$post_type}_row_actions", 'classicpack_duplicate_post_row_actions', 10, 2 );
    }
}

/**
 * Add Duplicate link when the module is active (caller is the enabled module file).
 *
 * @param array    $actions Row actions.
 * @param WP_Post $post    Post object.
 * @return array
 */
function classicpack_duplicate_post_row_actions( $actions, $post ) {
    if ( ! $post instanceof WP_Post ) {
        return $actions;
    }

    $post_type = $post->post_type;
    if ( ! in_array( $post_type, get_post_types( [ 'public' => true ], 'names' ), true ) ) {
        return $actions;
    }

    if ( ! current_user_can( 'edit_post', $post->ID ) ) {
        return $actions;
    }

    $pto = get_post_type_object( $post_type );
    if ( ! $pto || ! current_user_can( $pto->cap->edit_posts ) ) {
        return $actions;
    }

    $url = wp_nonce_url(
        add_query_arg(
            [
                'action' => 'classicpack_duplicate_post',
                'post'   => $post->ID,
            ],
            admin_url( 'admin-post.php' )
        ),
        'classicpack_duplicate_post_' . $post->ID
    );

    $actions['classicpack_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'classicpack' ) . '</a>';

    return $actions;
}

/**
 * Meta keys not copied to the duplicate.
 *
 * @return string[]
 */
function classicpack_duplicate_post_skipped_meta_keys() {
    return [
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        '_wp_old_date',
        '_sticky_post',
    ];
}

/**
 * Clone post into a new draft and redirect to the editor.
 *
 * @return void
 */
function classicpack_duplicate_post_handle() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
    if ( ! isset( $_GET['post'] ) ) {
        wp_die( esc_html__( 'Missing post.', 'classicpack' ), '', 400 );
    }

    $post_id = (int) $_GET['post'];
    if ( $post_id <= 0 ) {
        wp_die( esc_html__( 'Invalid post.', 'classicpack' ), '', 400 );
    }

    if ( ! isset( $_GET['_wpnonce'] ) ) {
        wp_die( esc_html__( 'Invalid link.', 'classicpack' ), '', 403 );
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'classicpack_duplicate_post_' . $post_id ) ) {
        wp_die( esc_html__( 'Invalid nonce.', 'classicpack' ), '', 403 );
    }

    $post = get_post( $post_id );
    if ( ! $post instanceof WP_Post ) {
        wp_die( esc_html__( 'Post not found.', 'classicpack' ), '', 404 );
    }

    if ( ! in_array( $post->post_type, get_post_types( [ 'public' => true ], 'names' ), true ) ) {
        wp_die( esc_html__( 'This post type cannot be duplicated here.', 'classicpack' ), '', 403 );
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_die( esc_html__( 'You cannot edit this post.', 'classicpack' ), '', 403 );
    }

    $pto = get_post_type_object( $post->post_type );
    if ( ! $pto || ! current_user_can( $pto->cap->edit_posts ) ) {
        wp_die( esc_html__( 'You cannot duplicate this post.', 'classicpack' ), '', 403 );
    }

    $new_title = $post->post_title . __( ' (Copy)', 'classicpack' );

    $new_id = wp_insert_post(
        [
            'post_title'     => $new_title,
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_status'    => 'draft',
            'post_type'      => $post->post_type,
            'post_author'    => $post->post_author,
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'post_password'  => $post->post_password,
            'post_parent'    => $post->post_parent,
            'menu_order'     => $post->menu_order,
            'post_name'      => '',
        ],
        true
    );

    if ( is_wp_error( $new_id ) ) {
        wp_die( esc_html( $new_id->get_error_message() ), esc_html__( 'Could not duplicate', 'classicpack' ), 500 );
    }

    $new_id = (int) $new_id;
    if ( $new_id <= 0 ) {
        wp_die( esc_html__( 'Could not create duplicate.', 'classicpack' ), '', 500 );
    }

    classicpack_duplicate_post_copy_meta( $post_id, $new_id );
    classicpack_duplicate_post_copy_terms( $post, $new_id );

    wp_safe_redirect(
        admin_url( 'post.php?post=' . $new_id . '&action=edit&classicpack_duplicated=1' )
    );
    exit;
}

/**
 * Copy post meta (skipping internal keys).
 *
 * @param int $source_id Source post ID.
 * @param int $target_id New post ID.
 * @return void
 */
function classicpack_duplicate_post_copy_meta( $source_id, $target_id ) {
    $source_id = (int) $source_id;
    $target_id = (int) $target_id;
    if ( $source_id <= 0 || $target_id <= 0 ) {
        return;
    }

    $skip = array_flip( classicpack_duplicate_post_skipped_meta_keys() );
    $all  = get_post_meta( $source_id, '', false );
    if ( ! is_array( $all ) ) {
        return;
    }

    foreach ( $all as $meta_key => $values ) {
        if ( isset( $skip[ $meta_key ] ) ) {
            continue;
        }
        if ( ! is_array( $values ) ) {
            continue;
        }
        foreach ( $values as $meta_value ) {
            add_post_meta( $target_id, $meta_key, maybe_unserialize( $meta_value ) );
        }
    }
}

/**
 * Copy taxonomy terms to the new post.
 *
 * @param WP_Post $post    Original post.
 * @param int     $new_id New post ID.
 * @return void
 */
function classicpack_duplicate_post_copy_terms( $post, $new_id ) {
    if ( ! $post instanceof WP_Post ) {
        return;
    }

    $new_id = (int) $new_id;
    if ( $new_id <= 0 ) {
        return;
    }

    foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $taxonomy ) {
        $terms = wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }
        wp_set_object_terms( $new_id, array_map( 'intval', $terms ), $taxonomy );
    }
}

/**
 * Admin notice after duplicate redirect.
 *
 * @return void
 */
function classicpack_duplicate_post_admin_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only query arg after redirect.
    if ( ! isset( $_GET['classicpack_duplicated'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['classicpack_duplicated'] ) ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || 'post' !== $screen->base ) {
        return;
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Draft copy created.', 'classicpack' ) . '</p></div>';
}
