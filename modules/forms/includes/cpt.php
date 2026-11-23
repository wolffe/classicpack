<?php
/**
 * Forms: the classicpack_form post type.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the form post type. Its menu item is added next to Forms Settings in classicpack_forms_admin_menu().
 *
 * @return void
 */
function classicpack_forms_register_cpt() {
	register_post_type(
		CLASSICPACK_FORMS_CPT,
		[
			'labels'              => [
				'name'               => __( 'Forms', 'classicpack' ),
				'singular_name'      => __( 'Form', 'classicpack' ),
				'menu_name'          => __( 'Forms', 'classicpack' ),
				'name_admin_bar'     => __( 'Form', 'classicpack' ),
				'add_new'            => __( 'Add New', 'classicpack' ),
				'add_new_item'       => __( 'Add New Form', 'classicpack' ),
				'edit_item'          => __( 'Edit Form', 'classicpack' ),
				'new_item'           => __( 'New Form', 'classicpack' ),
				'all_items'          => __( 'Forms', 'classicpack' ),
				'search_items'       => __( 'Search Forms', 'classicpack' ),
				'not_found'          => __( 'No forms found', 'classicpack' ),
				'not_found_in_trash' => __( 'No forms found in Trash', 'classicpack' ),
			],
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'supports'            => [ 'title' ],
			'has_archive'         => false,
			'rewrite'             => false,
			'hierarchical'        => false,
			'map_meta_cap'        => true,
		]
	);
}
