<?php
/**
 * Core Redirects Manager module for ClassicPack.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'classicpress_redirects_register_submenu', 12 );

/**
 * Submenu under ClassicPack.
 *
 * @return void
 */
function classicpress_redirects_register_submenu() {
	if ( ! function_exists( 'classicpack_get_menu_slug' ) ) {
		return;
	}
	add_submenu_page(
		classicpack_get_menu_slug(),
		__( 'Redirects', 'classicpack' ),
		__( 'Redirects', 'classicpack' ),
		'manage_options',
		'classicpress-redirects',
		'classicpress_redirects_render_page'
	);
}

add_action( 'admin_init', 'classicpress_redirects_handle_actions' );

/**
 * Handle delete actions.
 *
 * @return void
 */
function classicpress_redirects_handle_actions() {

	if ( ! isset( $_GET['classicpress_redirects_action'], $_GET['_wpnonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'classicpress_redirects_action' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
	$slug    = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : '';

	if ( 'delete' === $_GET['classicpress_redirects_action'] && $post_id && $slug ) {
		$slugs = get_post_meta( $post_id, '_wp_old_slug' );
		$keep  = array();
		foreach ( $slugs as $s ) {
			if ( $s !== $slug ) {
				$keep[] = $s;
			}
		}

		delete_post_meta( $post_id, '_wp_old_slug' );

		foreach ( $keep as $s ) {
			add_post_meta( $post_id, '_wp_old_slug', $s );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=classicpress-redirects' ) );
		exit;
	}

	if ( 'delete_all' === $_GET['classicpress_redirects_action'] && $post_id ) {
		delete_post_meta( $post_id, '_wp_old_slug' );

		wp_safe_redirect( admin_url( 'admin.php?page=classicpress-redirects' ) );
		exit;
	}
}

/**
 * Render admin page.
 *
 * @return void
 */
function classicpress_redirects_render_page() {
	global $wpdb;

	$rows = $wpdb->get_results(
		"
		SELECT post_id, meta_value AS slug
		FROM {$wpdb->postmeta}
		WHERE meta_key = '_wp_old_slug'
		ORDER BY post_id DESC
		"
	);

	$redirects_admin_base = admin_url( 'admin.php' );

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Redirects', 'classicpack' ) . '</h1>';

	if ( empty( $rows ) ) {
		echo '<p>' . esc_html__( 'No redirects found.', 'classicpack' ) . '</p>';
		echo '</div>';
		return;
	}

	echo '<table class="widefat fixed striped">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__( 'Old URL', 'classicpack' ) . '</th>';
	echo '<th>' . esc_html__( 'Redirects To', 'classicpack' ) . '</th>';
	echo '<th>' . esc_html__( 'Post', 'classicpack' ) . '</th>';
	echo '<th>' . esc_html__( 'Actions', 'classicpack' ) . '</th>';
	echo '</tr></thead>';
	echo '<tbody>';

	foreach ( $rows as $row ) {

		$post = get_post( $row->post_id );

		if ( ! $post ) {
			continue;
		}

		$old_url = home_url( '/' . $row->slug . '/' );
		$new_url = get_permalink( $post );

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'                          => 'classicpress-redirects',
					'classicpress_redirects_action' => 'delete',
					'post_id'                       => $post->ID,
					'slug'                          => $row->slug,
				),
				$redirects_admin_base
			),
			'classicpress_redirects_action'
		);

		$delete_all_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'                          => 'classicpress-redirects',
					'classicpress_redirects_action' => 'delete_all',
					'post_id'                       => $post->ID,
				),
				$redirects_admin_base
			),
			'classicpress_redirects_action'
		);

		echo '<tr>';
		echo '<td><code>' . esc_html( $old_url ) . '</code></td>';
		echo '<td><a href="' . esc_url( $new_url ) . '" target="_blank">' . esc_html( $new_url ) . '</a></td>';
		echo '<td><a href="' . esc_url( get_edit_post_link( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></td>';
		echo '<td>';
		echo '<a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete', 'classicpack' ) . '</a> | ';
		echo '<a href="' . esc_url( $delete_all_url ) . '">' . esc_html__( 'Delete All', 'classicpack' ) . '</a>';
		echo '</td>';
		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';
	echo '</div>';
}
