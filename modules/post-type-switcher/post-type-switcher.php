<?php
/**
 * Post type switcher — ClassicPack module (Classic Editor).
 *
 * Adds a post-type dropdown and warning in the publish box on post.php for
 * public post types. Intended for ClassicPress / Classic Editor.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'post_submitbox_misc_actions', 'classicpack_post_type_switcher_render', 20 );
add_action( 'admin_post_classicpack_switch_post_type', 'classicpack_post_type_switcher_handle' );
add_action( 'admin_notices', 'classicpack_post_type_switcher_admin_notice' );

/**
 * Output switcher UI in the publish meta box (existing posts only).
 *
 * @return void
 */
function classicpack_post_type_switcher_render() {
    global $post;

    if ( ! $post instanceof WP_Post ) {
        return;
    }

    $post_id = (int) $post->ID;
    if ( $post_id <= 0 ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || 'post' !== $screen->base || 'add' === $screen->action ) {
        return;
    }

    if ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post_id ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $targets = [];
    foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $name => $pto ) {
        if ( ! $pto instanceof WP_Post_Type || 'attachment' === $name ) {
            continue;
        }
        if ( ! current_user_can( $pto->cap->edit_posts ) ) {
            continue;
        }
        $targets[ $name ] = $pto;
    }
    if ( empty( $targets ) ) {
        return;
    }

    $action = esc_url( admin_url( 'admin-post.php' ) );
    ?>
    <div class="misc-pub-section misc-pub-post-type-switcher">
        <p class="howto" style="margin-top:8px;">
            <?php esc_html_e( 'Switching type can leave taxonomies, meta, or URLs a poor fit for the new type. Review the entry and permalinks after saving.', 'classicpack' ); ?>
        </p>
        <form method="post" action="<?php echo esc_url( $action ); ?>" style="margin-top:6px;">
            <?php wp_nonce_field( 'classicpack_switch_post_' . $post_id ); ?>
            <input type="hidden" name="action" value="classicpack_switch_post_type" />
            <input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
            <label for="classicpack-new-post-type" class="screen-reader-text">
                <?php esc_html_e( 'New post type', 'classicpack' ); ?>
            </label>
            <select name="new_post_type" id="classicpack-new-post-type" required>
                <option value="" disabled><?php esc_html_e( 'Action to take…', 'classicpack' ); ?></option>
                <?php foreach ( $targets as $name => $pto ) : ?>
                    <option value="<?php echo esc_attr( $name ); ?>"<?php selected( $post->post_type, $name ); ?>><?php echo esc_html( $pto->labels->singular_name ); ?></option>
                <?php endforeach; ?>
            </select>
            <?php
            submit_button(
                __( 'Switch type', 'classicpack' ),
                'secondary small',
                'submit',
                false
            );
            ?>
        </form>
    </div>
    <?php
}

/**
 * Process admin-post switch request.
 *
 * @return void
 */
function classicpack_post_type_switcher_handle() {
    if ( ! isset( $_POST['post_id'], $_POST['new_post_type'], $_POST['_wpnonce'] ) ) {
        wp_die( esc_html__( 'Missing data.', 'classicpack' ), '', 400 );
    }

    $post_id = (int) $_POST['post_id'];
    if ( $post_id <= 0 ) {
        wp_die( esc_html__( 'Invalid post.', 'classicpack' ), '', 400 );
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'classicpack_switch_post_' . $post_id ) ) {
        wp_die( esc_html__( 'Invalid nonce.', 'classicpack' ), '', 403 );
    }

    $new_type = sanitize_key( wp_unslash( $_POST['new_post_type'] ) );
    if ( '' === $new_type ) {
        wp_die( esc_html__( 'Choose a post type.', 'classicpack' ), '', 400 );
    }

    $post = get_post( $post_id );
    if ( ! $post instanceof WP_Post ) {
        wp_die( esc_html__( 'Post not found.', 'classicpack' ), '', 404 );
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_die( esc_html__( 'You cannot edit this post.', 'classicpack' ), '', 403 );
    }

    $pto = get_post_type_object( $new_type );
    if ( ! $pto || ! $pto->public || ! current_user_can( $pto->cap->edit_posts ) ) {
        wp_die( esc_html__( 'You cannot use that post type.', 'classicpack' ), '', 403 );
    }

    if ( $post->post_type === $new_type ) {
        wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
        exit;
    }

    $updated = wp_update_post(
        [
            'ID'        => $post_id,
            'post_type' => $new_type,
        ],
        true
    );

    if ( is_wp_error( $updated ) ) {
        wp_die( esc_html( $updated->get_error_message() ), esc_html__( 'Could not switch type', 'classicpack' ), 500 );
    }

    wp_safe_redirect(
        admin_url(
            'post.php?post=' . $post_id . '&action=edit&classicpack_switched=1'
        )
    );
    exit;
}

/**
 * Success notice after redirect.
 *
 * @return void
 */
function classicpack_post_type_switcher_admin_notice() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only query arg after redirect.
    if ( ! isset( $_GET['classicpack_switched'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['classicpack_switched'] ) ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || 'post' !== $screen->base ) {
        return;
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Post type updated.', 'classicpack' ) . '</p></div>';
}
