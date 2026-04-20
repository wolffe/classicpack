<?php
/**
 * Bulk Post Type Editor — ClassicPack module.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screen slug (submenu under ClassicPack).
 *
 * @return string
 */
function classicpack_bpte_get_page_slug() {
	return 'classicpack-bpte';
}

/**
 * Register under ClassicPack when loaded as a module.
 *
 * @return void
 */
function classicpack_bpte_register_submenu() {
	if ( ! function_exists( 'classicpack_get_menu_slug' ) ) {
		return;
	}
	add_submenu_page(
		classicpack_get_menu_slug(),
		__( 'Bulk Post Type Editor', 'classicpack' ),
		__( 'Post Type Editor', 'classicpack' ),
		'manage_options',
		classicpack_bpte_get_page_slug(),
		'classicpack_bpte_render_admin_page'
	);
}

add_action( 'admin_menu', 'classicpack_bpte_register_submenu', 12 );

/**
 * Setup WordPress CodeMirror for content editing.
 *
 * @param string $hook_suffix Current admin page hook.
 * @return void
 */
function classicpack_bpte_setup_codemirror( $_hook_suffix ) {
	if ( ! isset( $_GET['page'] ) || (string) wp_unslash( $_GET['page'] ) !== classicpack_bpte_get_page_slug() ) {
		return;
	}
	// Simple WordPress CodeMirror setup - minimal configuration
    wp_enqueue_code_editor(
        [
            'type'       => 'text/html',
            'codemirror' => [
                'lineNumbers' => true,
                'mode'        => 'htmlmixed',
            ],
        ]
    );

    // Register Google Fonts
    wp_register_style(
        'classicpack-bpte-google-fonts',
        'https://fonts.googleapis.com/css2?family=Geist+Mono:wght@100..900&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap',
        [],
        null
    );

    // Register our custom editor script
    wp_register_script(
        'classicpack-bpte-editor-js',
        plugin_dir_url( __FILE__ ) . 'bpte-editor.js',
        [ 'wp-theme-plugin-editor', 'jquery' ],
        '1.0.0',
        true
    );

}
add_action( 'admin_enqueue_scripts', 'classicpack_bpte_setup_codemirror' );

function classicpack_bpte_render_admin_page() {
    $action  = $_GET['action'] ?? 'list';
    $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;

    if ( $action === 'edit' && $post_id ) {
        classicpack_bpte_render_edit_form( $post_id );
    } else {
        classicpack_bpte_render_list_table();
    }
}

/**
 * List posts with pagination, search, and taxonomy filter
 */
function classicpack_bpte_render_list_table() {
	$public_post_types = get_post_types( [ 'public' => true ], 'objects' );
	// Use classicpack_bpte_post_type (not post_type): WordPress admin.php sets $typenow from $_REQUEST['post_type'],
	// which breaks get_plugin_page_hook() for ClassicPack submenu pages.
	$selected_type = '';
	if ( isset( $_GET['classicpack_bpte_post_type'] ) ) {
		$selected_type = sanitize_key( wp_unslash( $_GET['classicpack_bpte_post_type'] ) );
	} elseif ( isset( $_GET['post_type'] ) ) {
		$selected_type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
	}
	if ( $selected_type === '' || ! isset( $public_post_types[ $selected_type ] ) ) {
		$selected_type = (string) key( $public_post_types );
	}

    $paged    = max( 1, intval( $_GET['paged'] ?? 1 ) );
    $per_page = 20;
    $search   = sanitize_text_field( $_GET['s'] ?? '' );

    // Build query args
    $args = [
        'post_type'      => $selected_type,
        'posts_per_page' => $per_page,
        'paged'          => $paged,
    ];
    if ( $search ) {
        $args['s'] = $search;
    }

    // Taxonomy filter
    $tax_filter = $_GET['tax_filter'] ?? '';
    $term_id    = intval( $_GET['term_id'] ?? 0 );
    if ( $tax_filter && $term_id ) {
        $args['tax_query'] = [
            [
                'taxonomy' => $tax_filter,
                'field'    => 'term_id',
                'terms'    => $term_id,
            ],
        ];
    }

    $query = new WP_Query( $args );

    // Enqueue fonts and scripts for the main page
    wp_enqueue_style( 'classicpack-bpte-google-fonts' );
    wp_enqueue_style( 'classicpack-bpte-admin-css', plugin_dir_url( __FILE__ ) . 'bpte-admin.css', [ 'classicpack-bpte-google-fonts' ], '1.0.0' );
    wp_enqueue_script( 'classicpack-bpte-editor-js' );

    echo '<div class="wrap">';
    echo '<h1>' . __( 'Bulk Post Type Editor', 'classicpack' ) . '</h1>';

    // Post type + filters form
    echo '<form method="get">';
	echo '<input type="hidden" name="page" value="' . esc_attr( classicpack_bpte_get_page_slug() ) . '" />';

    // Post type selector
    echo '<label>' . __( 'Post type:', 'classicpack' ) . ' ';
	echo '<select id="classicpack-bpte-post-type-selector" name="classicpack_bpte_post_type" onchange="this.form.submit()">';
    foreach ( $public_post_types as $type => $obj ) {
        $sel = ( $selected_type === $type ) ? 'selected' : '';
        echo '<option value="' . esc_attr( $type ) . '" ' . $sel . '>' . esc_html( $obj->labels->name ) . '</option>';
    }
    echo '</select></label> ';

    // Taxonomy filter
    $taxonomies = get_object_taxonomies( $selected_type, 'objects' );
    if ( $taxonomies ) {
        echo '<label>' . __( 'Filter:', 'classicpack' ) . ' ';
		echo '<select id="classicpack-bpte-tax-filter-selector" name="tax_filter" onchange="this.form.submit()">';
        echo '<option value="">' . __( 'All taxonomies', 'classicpack' ) . '</option>';
        foreach ( $taxonomies as $tax ) {
            $sel = ( $tax_filter === $tax->name ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $tax->name ) . '" ' . $sel . '>' . esc_html( $tax->labels->name ) . '</option>';
        }
        echo '</select></label> ';

        if ( $tax_filter ) {
            wp_dropdown_categories(
                [
                    'taxonomy'        => $tax_filter,
                    'name'            => 'term_id',
                    'selected'        => $term_id,
                    'show_option_all' => __( 'All terms', 'classicpack' ),
                    'hide_empty'      => false,
                    'value_field'     => 'term_id',
                    'id'              => 'classicpack-bpte-term-selector',
                ]
            );
        }
    }

    // Search box
    echo ' <input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . __( 'Search...', 'classicpack' ) . '">';
    echo ' <input type="submit" class="button" value="' . __( 'Filter', 'classicpack' ) . '">';
    echo '</form>';

    echo '<table class="widefat striped">';
    echo '<thead><tr><th>' . __( 'Image', 'classicpack' ) . '</th><th>ID</th><th>Title</th><th>Author</th><th>Date</th><th>Actions</th></tr></thead><tbody>';

    foreach ( $query->posts as $post ) {
		$edit_url      = admin_url( 'admin.php?page=' . classicpack_bpte_get_page_slug() . '&action=edit&post_id=' . $post->ID . '&classicpack_bpte_post_type=' . rawurlencode( $selected_type ) );
        $view_url      = get_permalink( $post );
        $thumbnail_id  = get_post_thumbnail_id( $post->ID );
        $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, [ 48, 48 ] ) : '';
        $thumbnail_alt = $thumbnail_id ? get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) : '';

        echo '<tr>';
        echo '<td class="classicpack-bpte-featured-image-cell">';
        if ( $thumbnail_url ) {
            echo '<img src="' . esc_url( $thumbnail_url ) . '" alt="' . esc_attr( $thumbnail_alt ) . '" class="classicpack-bpte-table-featured-image" width="48" height="48" />';
        } else {
            echo '<div class="classicpack-bpte-no-image">' . __( 'No Image', 'classicpack' ) . '</div>';
        }
        echo '</td>';
        echo '<td>' . $post->ID . '</td>';
        echo '<td>' . esc_html( $post->post_title ) . '</td>';
        $author_name  = get_the_author_meta( 'display_name', $post->post_author );
        $author_data  = get_userdata( $post->post_author );
        $author_roles = $author_data ? $author_data->roles : [];
        $author_role  = $author_roles ? ucfirst( $author_roles[0] ) : 'No Role';
        echo '<td>' . esc_html( $author_name ) . '<br><small>(' . esc_html( $author_role ) . ' - ID: ' . $post->post_author . ')</small></td>';
        echo '<td>' . esc_html( $post->post_date ) . '</td>';
        echo '<td><a href="' . esc_url( $edit_url ) . '">' . __( 'Edit', 'classicpack' ) . '</a> | <a href="' . esc_url( $view_url ) . '" target="_blank">' . __( 'View', 'classicpack' ) . '</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    // Pagination
    $total_pages = $query->max_num_pages;
	if ( $total_pages > 1 ) {
		$base_url = remove_query_arg( array( 'paged', 'post_type' ) );
		echo '<div class="tablenav"><div class="tablenav-pages">';
        echo paginate_links(
            [
                'base'      => add_query_arg( 'paged', '%#%', $base_url ),
                'format'    => '',
                'prev_text' => __( '&laquo;', 'classicpack' ),
                'next_text' => __( '&raquo;', 'classicpack' ),
                'total'     => $total_pages,
                'current'   => $paged,
            ]
        );
        echo '</div></div>';
    }

    echo '</div>';
}

/**
 * Render edit form for a single post
 */
function classicpack_bpte_render_edit_form( int $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post ) {
        echo '<div class="error"><p>' . __( 'Invalid post.', 'classicpack' ) . '</p></div>';
        return;
    }

    if ( isset( $_POST['classicpack_bpte_nonce'] ) && wp_verify_nonce( $_POST['classicpack_bpte_nonce'], 'classicpack_bpte_save_' . $post_id ) ) {
        classicpack_bpte_save_post( $post_id, $_POST['classicpack_bpte'] ?? [] );
        $post = get_post( $post_id ); // reload
        echo '<div class="updated"><p>' . __( 'Post updated.', 'classicpack' ) . '</p></div>';
    }

    // Enqueue scripts and styles for the editor
    wp_enqueue_style( 'classicpack-bpte-google-fonts' );
    wp_enqueue_style( 'classicpack-bpte-admin-css', plugin_dir_url( __FILE__ ) . 'bpte-admin.css', [ 'classicpack-bpte-google-fonts' ], '1.0.0' );

    // Enqueue media scripts for featured image functionality
    wp_enqueue_media();

    wp_enqueue_script( 'classicpack-bpte-editor-js' );

    echo '<div class="wrap">
        <h1>' . sprintf( __( 'Edit %s', 'classicpack' ), esc_html( $post->post_title ) ) . '</h1>';

        // Show notice if post type was just changed
    if ( isset( $_GET['post_type_changed'] ) && $_GET['post_type_changed'] === '1' ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . __( 'Post Type Updated', 'classicpack' ) . '</strong></p>';
        echo '<p>' . __( 'The post type has been successfully changed. Please review the taxonomies and meta fields below.', 'classicpack' ) . '</p>';
        echo '</div>';
    }

        // Action links
        $view_url = get_permalink( $post->ID );
        $edit_url = get_edit_post_link( $post->ID );
		$main_url = admin_url( 'admin.php?page=' . classicpack_bpte_get_page_slug() . '&classicpack_bpte_post_type=' . rawurlencode( $post->post_type ) );

        echo '<div class="classicpack-bpte-action-links">
            <a href="' . esc_url( $main_url ) . '" class="button button-secondary">' . __( '← Back to Main Page', 'classicpack' ) . '</a>
            <a href="' . esc_url( $view_url ) . '" target="_blank" class="button button-secondary">' . __( 'View Post', 'classicpack' ) . '</a>
            <a href="' . esc_url( $edit_url ) . '" class="button button-secondary">' . __( 'Edit with Default Editor', 'classicpack' ) . '</a>
        </div>

        <form method="post">';
            wp_nonce_field( 'classicpack_bpte_save_' . $post_id, 'classicpack_bpte_nonce' );
            echo '<input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '">

            <div class="classicpack-bpte-edit-container">
                <div class="classicpack-bpte-main-content">
                    <h2 class="classicpack-bpte-section-header">' . __( 'Content', 'classicpack' ) . '</h2>

                    <div class="classicpack-bpte-section-content">
                        <p>
                            <label for="classicpack-bpte-title">' . __( 'Title', 'classicpack' ) . '</label>
                            <br>
                            <input type="text" id="classicpack-bpte-title" name="classicpack_bpte[post_title]" value="' . esc_attr( $post->post_title ) . '" class="large-text hero-text">
                        </p>

                        <p>
                            <label for="classicpack-bpte-content">' . __( 'Content', 'classicpack' ) . '</label>
                            <br>
                            <textarea id="classicpack-bpte-content" name="classicpack_bpte[post_content]" rows="10" cols="80" class="large-text">' . esc_textarea( $post->post_content ) . '</textarea>
                        </p>

                        <p>
                            <label for="classicpack-bpte-author">' . __( 'Author', 'classicpack' ) . '</label>
                            <br>
                            <select id="classicpack-bpte-author" name="classicpack_bpte[post_author]">';

                                // Get all users
                                $users = get_users( [ 'orderby' => 'display_name' ] );
    foreach ( $users as $user ) {
        $roles        = $user->roles;
        $primary_role = $roles ? ucfirst( $roles[0] ) : 'No Role';
        $selected     = ( $user->ID == $post->post_author ) ? 'selected' : '';
        echo '<option value="' . esc_attr( $user->ID ) . '" ' . $selected . '>';
        echo esc_html( $user->display_name ) . ' (' . esc_html( $primary_role ) . ' - ID: ' . $user->ID . ')';
        echo '</option>';
    }

                            echo '</select>
                        </p>

                        <p>
                            <label for="classicpack-bpte-excerpt">' . __( 'Excerpt', 'classicpack' ) . '</label>
                            <br>
                            <textarea id="classicpack-bpte-excerpt" name="classicpack_bpte[post_excerpt]" rows="3" cols="80" class="large-text">' . esc_textarea( $post->post_excerpt ) . '</textarea>
                        </p>';

                        // Post Type (moved to end of Content section)
                        $public_post_types = get_post_types( [ 'public' => true ], 'objects' );

                        echo '<p>
                            <label for="classicpack-bpte-post-type">' . __( 'Post Type', 'classicpack' ) . '</label>
                            <br>
                            <select id="classicpack-bpte-post-type" name="classicpack_bpte[post_type]">';
    foreach ( $public_post_types as $pt_slug => $pt_obj ) {
        $selected = ( $post->post_type === $pt_slug ) ? 'selected' : '';
        echo '<option value="' . esc_attr( $pt_slug ) . '" ' . $selected . '>' . esc_html( $pt_obj->labels->singular_name ) . ' (' . esc_html( $pt_slug ) . ')</option>';
    }
                            echo '</select>
                        </p>
						<p class="classicpack-bpte-description classicpack-bpte-warning">' . __( 'Changing the post type may cause data loss, including taxonomies, meta fields, and functionality from plugins or themes. Make sure to backup your data first.', 'classicpack' ) . '</p>
                    </div>
                </div>';

    // Right column - Featured Image
    echo '<div><div class="classicpack-bpte-featured-image-section">
        <h2 class="classicpack-bpte-section-header">' . __( 'Featured Image', 'classicpack' ) . '</h2>

        <div class="classicpack-bpte-section-content">
            <div class="classicpack-bpte-featured-image-container">';

                // Get current featured image
                $thumbnail_id  = get_post_thumbnail_id( $post->ID );
                $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';
                $thumbnail_alt = $thumbnail_id ? get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) : '';

    if ( $thumbnail_url ) {
        echo '<div class="classicpack-bpte-current-featured-image">
                        <img src="' . esc_url( $thumbnail_url ) . '" alt="' . esc_attr( $thumbnail_alt ) . '" class="classicpack-bpte-featured-image-preview">
                        <button type="button" class="button classicpack-bpte-remove-featured-image" data-post-id="' . $post->ID . '">' . __( 'Remove', 'classicpack' ) . '</button>
                    </div>';
    }

                echo '<div class="classicpack-bpte-featured-image-upload">
                    <input type="hidden" name="classicpack_bpte[featured_image]" id="classicpack-bpte-featured-image" value="' . esc_attr( $thumbnail_id ) . '">
                    <button type="button" class="button classicpack-bpte-upload-featured-image" id="classicpack-bpte-upload-btn">' . ( $thumbnail_id ? __( 'Change Image', 'classicpack' ) : __( 'Set Featured Image', 'classicpack' ) ) . '</button>
                    <p class="classicpack-bpte-description">' . __( 'Upload or select an image from the media library.', 'classicpack' ) . '</p>
                </div>
            </div>

            <div class="classicpack-bpte-attachments-section">
                <h3>' . __( 'Attachments', 'classicpack' ) . '</h3>
                <p class="classicpack-bpte-description">' . __( 'All media items attached to this post (including the featured image if it is uploaded to this post).', 'classicpack' ) . '</p>
                <div class="classicpack-bpte-attachments-list-wrap">
                    ' . classicpack_bpte_render_attachments_list( $post->ID ) . '
                </div>
            </div>
        </div>
    </div>

    <hr class="classicpack-bpte-section-separator">

    <div class="classicpack-bpte-taxonomies-section">
        <h2 class="classicpack-bpte-section-header">' . __( 'Taxonomies', 'classicpack' ) . '</h2>
        <div class="classicpack-bpte-section-content">';

            $taxonomies = get_object_taxonomies( $post->post_type, 'objects' );

    if ( ! empty( $taxonomies ) ) {
        foreach ( $taxonomies as $tax ) {
            echo '<p><label>' . esc_html( $tax->labels->singular_name ) . '</label></p>';
            $terms     = wp_get_post_terms( $post->ID, $tax->name, [ 'fields' => 'ids' ] );
            $all_terms = get_terms(
                [
                    'taxonomy'   => $tax->name,
                    'hide_empty' => false,
                ]
            );

            if ( ! empty( $all_terms ) ) {
                echo '<select name="classicpack_bpte[tax][' . esc_attr( $tax->name ) . '][]" multiple size="8">';
                foreach ( $all_terms as $term ) {
                    $selected = in_array( $term->term_id, $terms, true ) ? 'selected' : '';
                    echo '<option value="' . esc_attr( $term->term_id ) . '" ' . $selected . '>' . esc_html( $term->name ) . '</option>';
                }
                echo '</select>';
                echo '<p class="classicpack-bpte-description">' . __( 'Hold ', 'classicpack' ) . '<kbd>Ctrl</kbd>' . __( ' (Windows) or ', 'classicpack' ) . '<kbd>Cmd</kbd>' . __( ' (Mac) to select multiple.', 'classicpack' ) . '</p>';
            } else {
                echo '<p class="classicpack-bpte-description">' . __( 'No terms available for this taxonomy.', 'classicpack' ) . '</p>';
            }
        }
    } else {
        echo '<p>' . __( 'Taxonomies', 'classicpack' ) . '</p>';
        echo '<p class="classicpack-bpte-description">' . __( 'This post type has no taxonomies.', 'classicpack' ) . '</p>';
    }

        echo '</div>
    </div>
    </div>';

    echo '</div>'; // End grid container

    // Bottom section - Meta Fields
    echo '<div class="classicpack-bpte-meta-section">';
    echo '<h2 class="classicpack-bpte-section-header">' . __( 'Post meta', 'classicpack' ) . '</h2>';
    echo '<div class="classicpack-bpte-section-content">';
    echo '<p class="classicpack-bpte-description">' . __( 'All custom fields stored for this post (including private keys).', 'classicpack' ) . '</p>';
    echo '<p class="classicpack-bpte-description classicpack-bpte-warning">' . __( 'Serialized meta must stay exactly right: one wrong character can corrupt plugin or theme settings. Do not paste arbitrary text into these fields.', 'classicpack' ) . '</p>';
    echo '<table class="classicpack-bpte-form-table"><tbody>';

    $meta = get_post_meta( $post->ID );
    if ( empty( $meta ) ) {
        echo '<tr><td colspan="2"><p class="classicpack-bpte-description">' . esc_html__( 'No post meta.', 'classicpack' ) . '</p></td></tr>';
    } else {
        foreach ( $meta as $key => $vals ) {
            foreach ( $vals as $idx => $val ) {
                $field_id = 'classicpack-bpte-meta-' . md5( $key . '|' . (string) $idx );
                echo '<tr>';
                echo '<th scope="row"><label for="' . esc_attr( $field_id ) . '">' . esc_html( $key ) . '</label></th>';
                echo '<td><textarea id="' . esc_attr( $field_id ) . '" name="classicpack_bpte[meta][' . esc_attr( $key ) . '][]" rows="4" cols="60" class="large-text code">' . esc_textarea( $val ) . '</textarea></td>';
                echo '</tr>';
            }
        }
    }

    echo '</tbody></table>';

    echo '<p><input type="submit" class="button button-primary" value="' . __( 'Save Changes', 'classicpack' ) . '"></p>';
    echo '</div>';
    echo '</div>';

    echo '</form>';

    echo '</div>';
}

/**
 * Read-only list of attachments for the edit screen.
 *
 * @param int $post_id Post ID.
 * @return string HTML.
 */
function classicpack_bpte_render_attachments_list( $post_id ) {
    $attachments = get_posts(
        [
            'post_type'              => 'attachment',
            'posts_per_page'         => -1,
            'post_status'            => 'inherit',
            'post_parent'            => (int) $post_id,
            'orderby'                => 'date',
            'order'                  => 'ASC',
            'update_post_meta_cache' => false,
        ]
    );
    if ( empty( $attachments ) ) {
        return '<p class="classicpack-bpte-description">' . esc_html__( 'No attachments for this post.', 'classicpack' ) . '</p>';
    }

    $featured_id = (int) get_post_thumbnail_id( $post_id );
    $out         = '<table class="widefat striped classicpack-bpte-attachments-table"><thead><tr>';
    $out        .= '<th>' . esc_html__( 'Preview', 'classicpack' ) . '</th>';
    $out        .= '<th>' . esc_html__( 'ID', 'classicpack' ) . '</th>';
    $out        .= '<th>' . esc_html__( 'Title', 'classicpack' ) . '</th>';
    $out        .= '<th>' . esc_html__( 'File type', 'classicpack' ) . '</th>';
    $out        .= '<th>' . esc_html__( 'Actions', 'classicpack' ) . '</th>';
    $out        .= '</tr></thead><tbody>';

    foreach ( $attachments as $attachment ) {
        if ( ! $attachment instanceof WP_Post ) {
            continue;
        }
        $is_featured = $featured_id && (int) $attachment->ID === $featured_id;
        $edit_link   = get_edit_post_link( $attachment->ID );
        $file_url    = wp_get_attachment_url( $attachment->ID );
        $thumb       = wp_attachment_is_image( $attachment->ID )
            ? wp_get_attachment_image( $attachment->ID, [ 60, 60 ], true, [ 'class' => 'classicpack-bpte-attachment-thumb' ] )
            : '';

        $out .= '<tr>';
        $out .= '<td class="classicpack-bpte-attachment-preview">' . ( $thumb ? $thumb : '—' ) . '</td>';
        $out .= '<td>' . (int) $attachment->ID . ( $is_featured ? ' <span class="classicpack-bpte-badge-featured">' . esc_html__( 'Featured', 'classicpack' ) . '</span>' : '' ) . '</td>';
        $out .= '<td>' . esc_html( $attachment->post_title ?: __( '(no title)', 'classicpack' ) ) . '</td>';
        $out .= '<td><code>' . esc_html( $attachment->post_mime_type ) . '</code></td>';
        $out .= '<td>';
        if ( $edit_link ) {
            $out .= '<a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit media', 'classicpack' ) . '</a>';
        }
        if ( $file_url ) {
            $out .= ( $edit_link ? ' | ' : '' ) . '<a href="' . esc_url( $file_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View file', 'classicpack' ) . '</a>';
        }
        $out .= '</td>';
        $out .= '</tr>';
    }

    $out .= '</tbody></table>';

    return $out;
}

/**
 * Save post edits
 */
function classicpack_bpte_save_post( int $post_id, array $data ) {
    // Handle post type change (with caution)
    $current_post  = get_post( $post_id );
    $new_post_type = sanitize_text_field( $data['post_type'] ?? $current_post->post_type );

    // Only change post type if it's different and valid
    if ( $new_post_type !== $current_post->post_type && post_type_exists( $new_post_type ) ) {
        // Verify user has permission to edit this post type
        $post_type_object = get_post_type_object( $new_post_type );
        if ( $post_type_object && current_user_can( $post_type_object->cap->edit_post, $post_id ) ) {
            // Change post type
            $result = set_post_type( $post_id, $new_post_type );

			if ( $result ) {
				// Redirect back to the edit page to refresh with new post type
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'                       => classicpack_bpte_get_page_slug(),
							'action'                     => 'edit',
							'post_id'                    => $post_id,
							'post_type_changed'          => '1',
							'classicpack_bpte_post_type' => $new_post_type,
						),
						admin_url( 'admin.php' )
					)
				);
                exit;
            }
        }
    }

    $postarr = [
        'ID'           => $post_id,
        'post_title'   => sanitize_text_field( $data['post_title'] ?? '' ),
        'post_content' => wp_kses_post( $data['post_content'] ?? '' ),
        'post_excerpt' => sanitize_textarea_field( $data['post_excerpt'] ?? '' ),
        'post_author'  => intval( $data['post_author'] ?? get_current_user_id() ),
    ];
    wp_update_post( $postarr );

    // Taxonomies
    if ( ! empty( $data['tax'] ) ) {
        foreach ( $data['tax'] as $tax => $terms ) {
            $terms = array_map( 'intval', (array) $terms );
            wp_set_post_terms( $post_id, $terms, $tax );
        }
    }

    // Featured Image
    $featured_image = intval( $data['featured_image'] ?? 0 );
    if ( $featured_image > 0 ) {
        set_post_thumbnail( $post_id, $featured_image );
    } elseif ( isset( $data['featured_image'] ) && empty( $data['featured_image'] ) ) {
        delete_post_thumbnail( $post_id );
    }

    // Meta (preserve raw strings; many keys store serialized or structured data).
    if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
        foreach ( $data['meta'] as $key => $vals ) {
            $key = wp_unslash( (string) $key );
            if ( $key === '' ) {
                continue;
            }
            delete_post_meta( $post_id, $key );
            foreach ( (array) $vals as $val ) {
                $val = wp_unslash( $val );
                update_post_meta( $post_id, $key, $val );
            }
        }
    }
}
