<?php
/**
 * User content overview — shows counts and admin links for content owned by
 * a user (all post types, media, and optional extra rows).
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'show_user_profile', 'classicpack_user_content_show_user_profile' );
add_action( 'edit_user_profile', 'classicpack_user_content_show_user_profile' );

/**
 * Renders the “User Content Overview” block on the user profile screen.
 *
 * @param WP_User $user User being edited.
 * @return void
 *
 * Hooks:
 * - `classicpack_user_content_overview_rows` — add rows with keys `label`, `count`, optional `url`, `description`.
 * - `classicpack_user_content_overview_after` — output after the table (e.g. orphaned meta notes).
 */
function classicpack_user_content_show_user_profile( $user ) {
    if ( ! $user instanceof WP_User ) {
        return;
    }
    if ( ! current_user_can( 'edit_users' ) ) {
        return;
    }

    global $wpdb;

    $user_id = (int) $user->ID;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only, single user; counts by type.
    $by_type = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_type, COUNT(ID) AS post_count
            FROM {$wpdb->posts}
            WHERE post_author = %d
            GROUP BY post_type
            ORDER BY post_type ASC",
            $user_id
        ),
        OBJECT_K
    );

    $by_type     = is_array( $by_type ) ? $by_type : [];
    $total_shown = 0;

    ?>
    <h2 id="classicpack-user-content-overview"><?php esc_html_e( 'User Content Overview', 'classicpack' ); ?></h2>
    <p class="description" id="classicpack-user-content-desc">
        <?php
        esc_html_e(
            'When reassigning or deleting a user, you may be asked to attribute their content. This list shows what exists in the database for this account: all post types they author, media they uploaded, and other counts below. A user may have no posts or pages but still own attachments, custom types, order relationships, or revisions.',
            'classicpack'
        );
        ?>
    </p>
    <table class="widefat striped" style="max-width: 700px" aria-describedby="classicpack-user-content-desc">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Type', 'classicpack' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Count', 'classicpack' ); ?></th>
                <th scope="col"><?php esc_html_e( 'View', 'classicpack' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php
        foreach ( $by_type as $row ) {
            if ( ! is_object( $row ) || ! isset( $row->post_type, $row->post_count ) ) {
                continue;
            }
            $post_type = (string) $row->post_type;
            $count     = (int) $row->post_count;
            if ( $count <= 0 || 'attachment' === $post_type ) {
                continue;
            }
            $total_shown += $count;

            $pto   = get_post_type_object( $post_type );
            $label = $pto && ! empty( $pto->labels->name ) ? $pto->labels->name : $post_type;
            $url   = classicpack_user_content_get_list_url( $post_type, $user_id );
            ?>
            <tr>
                <td><?php echo esc_html( $label ); ?></td>
                <td><?php echo esc_html( (string) $count ); ?></td>
                <td>
            <?php if ( $url ) : ?>
                    <a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'View', 'classicpack' ); ?></a>
            <?php else : ?>
                    &mdash;
            <?php endif; ?>
                </td>
            </tr>
            <?php
        }

        if ( isset( $by_type['attachment'] ) && (int) $by_type['attachment']->post_count > 0 ) {
            $att          = (int) $by_type['attachment']->post_count;
            $total_shown += $att;
            $media_url    = admin_url( 'upload.php' );
            $media_url    = add_query_arg( 'author', $user_id, $media_url );
            ?>
            <tr>
                <td><?php esc_html_e( 'Media (attachments)', 'classicpack' ); ?></td>
                <td><?php echo esc_html( (string) $att ); ?></td>
                <td>
                    <a href="<?php echo esc_url( $media_url ); ?>"><?php esc_html_e( 'View', 'classicpack' ); ?></a>
                </td>
            </tr>
            <?php
        }

        if ( function_exists( 'wc_get_orders' ) && function_exists( 'post_type_exists' ) && post_type_exists( 'shop_order' ) ) {
            $wc_orders = wc_get_orders(
                [
                    'limit'    => -1,
                    'customer' => (int) $user_id,
                    'return'   => 'ids',
                    'status'   => 'any',
                    'orderby'  => 'date',
                    'order'    => 'DESC',
                ]
            );
            $wc_count  = is_array( $wc_orders ) ? count( $wc_orders ) : 0;
            if ( $wc_count > 0 ) {
                $total_shown += $wc_count;
                $wc_url       = classicpack_user_content_get_wc_orders_list_url( $user_id );
                $wc_label     = __( 'WooCommerce orders (as customer)', 'classicpack' );
                $wc_legend    = __( 'WooCommerce links orders to a customer; that can differ from the “post author” line for orders. The same order may be reflected in both rows if the author and customer are the same.', 'classicpack' );
                ?>
            <tr>
                <td>
                    <?php echo esc_html( $wc_label ); ?>
                    <br /><span class="description"><?php echo esc_html( $wc_legend ); ?></span>
                </td>
                <td><?php echo esc_html( (string) $wc_count ); ?></td>
                <td>
                <?php if ( $wc_url ) : ?>
                    <a href="<?php echo esc_url( $wc_url ); ?>"><?php esc_html_e( 'View', 'classicpack' ); ?></a>
                <?php else : ?>
                    &mdash;
                <?php endif; ?>
                </td>
            </tr>
                <?php
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $comment_count = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d", $user_id )
        );
    if ( $comment_count > 0 ) {
        $total_shown += $comment_count;
        if ( $user->user_email !== '' && $user->user_email !== null ) {
            $comments_url = add_query_arg( 's', $user->user_email, admin_url( 'edit-comments.php' ) );
        } else {
            $comments_url = admin_url( 'edit-comments.php' );
        }
        ?>
            <tr>
                <td><?php esc_html_e( 'Comments (linked to this user account)', 'classicpack' ); ?></td>
                <td><?php echo esc_html( (string) $comment_count ); ?></td>
                <td>
                    <a href="<?php echo esc_url( $comments_url ); ?>"><?php esc_html_e( 'View', 'classicpack' ); ?></a>
                </td>
            </tr>
            <?php
    }

        $extra_rows = apply_filters( 'classicpack_user_content_overview_rows', [], $user_id );
    if ( is_array( $extra_rows ) && $extra_rows !== [] ) {
        foreach ( $extra_rows as $er ) {
            if ( ! is_array( $er ) || ! isset( $er['label'], $er['count'] ) ) {
                continue;
            }
            $c = (int) $er['count'];
            if ( $c <= 0 ) {
                continue;
            }
            $total_shown += $c;
            $label        = (string) $er['label'];
            $desc         = isset( $er['description'] ) ? (string) $er['description'] : '';
            $ext_url      = ! empty( $er['url'] ) ? (string) $er['url'] : '';
            ?>
            <tr>
                <td>
                <?php echo esc_html( $label ); ?>
                <?php if ( $desc !== '' ) : ?>
                        <br /><span class="description"><?php echo esc_html( $desc ); ?></span>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html( (string) $c ); ?></td>
                <td>
                <?php if ( $ext_url !== '' ) : ?>
                        <a href="<?php echo esc_url( $ext_url ); ?>"><?php esc_html_e( 'View', 'classicpack' ); ?></a>
                    <?php else : ?>
                        &mdash;
                    <?php endif; ?>
                </td>
            </tr>
                <?php
        }
    }

    if ( 0 === $total_shown ) {
        ?>
            <tr>
                <td colspan="3"><?php esc_html_e( 'No content found for this user in the rows above.', 'classicpack' ); ?></td>
            </tr>
            <?php
    }
    ?>
        </tbody>
    </table>
    <?php
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $raw_total = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_author = %d", $user_id )
    );
    ?>
    <p>
        <strong><?php esc_html_e( 'Total rows in the posts table for this user as author:', 'classicpack' ); ?></strong>
        <?php echo esc_html( (string) $raw_total ); ?>
    </p>
    <?php
    do_action( 'classicpack_user_content_overview_after', $user_id, $user );
}

/**
 * List URL for a post type (or media) in admin.
 *
 * @param string $post_type Post type name.
 * @param int    $user_id   User ID.
 * @return string
 */
function classicpack_user_content_get_list_url( $post_type, $user_id ) {
    $post_type = (string) $post_type;
    $user_id   = (int) $user_id;
    if ( $user_id <= 0 ) {
        return '';
    }
    if ( 'attachment' === $post_type ) {
        return add_query_arg( 'author', $user_id, admin_url( 'upload.php' ) );
    }
    $url = add_query_arg(
        [
            'post_type' => $post_type,
            'author'    => $user_id,
        ],
        admin_url( 'edit.php' )
    );
    /**
     * Filter the “View” URL for a post type in User Content overview.
     *
     * @param string $url       Default list URL.
     * @param string $post_type Post type slug.
     * @param int    $user_id   User ID.
     */
    $url = apply_filters( 'classicpack_user_content_list_url', $url, $post_type, $user_id );
    return is_string( $url ) ? $url : '';
}

/**
 * Best-effort admin URL to the WooCommerce orders list filtered to this customer.
 *
 * @param int $user_id User ID.
 * @return string
 */
function classicpack_user_content_get_wc_orders_list_url( $user_id ) {
    $user_id = (int) $user_id;
    if ( $user_id <= 0 || ! class_exists( '\WooCommerce' ) ) {
        return '';
    }
    if ( is_callable( [ 'Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ] )
        && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
        $url = add_query_arg(
            [
                'page'     => 'wc-orders',
                'customer' => $user_id,
            ],
            admin_url( 'admin.php' )
        );
        /**
         * Filter the Woo orders “View” URL (HPOS).
         *
         * @param string $url     Default list URL.
         * @param int    $user_id User ID.
         */
        $filtered = apply_filters( 'classicpack_user_content_wc_orders_list_url', $url, $user_id );
        return is_string( $filtered ) ? $filtered : $url;
    }
    $url      = add_query_arg(
        [
            'post_type'      => 'shop_order',
            '_customer_user' => $user_id,
        ],
        admin_url( 'edit.php' )
    );
    $filtered = apply_filters( 'classicpack_user_content_wc_orders_list_url', $url, $user_id );
    return is_string( $filtered ) ? $filtered : $url;
}
