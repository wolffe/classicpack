<?php
/**
 * Runs when ClassicPack is deleted from the Plugins screen.
 *
 * Removes all options and tables used by ClassicPack and every bundled module.
 * Does not check which modules were enabled — the full list below is always removed.
 *
 * @package ClassicPack
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete stored data for the current site (all modules, unconditional).
 *
 * @return void
 */
function classicpack_uninstall_single_site() {
	global $wpdb;

	$options = array(
		'classicpack_modules',
		'classicpack_user_manager_options',
		'useronline_most',
		'classicpress_auto_save_images_options',
	);

	foreach ( $options as $option_name ) {
		delete_option( $option_name );
	}

	delete_metadata( 'user', 0, 'classicpack_last_login', '', true );
	delete_metadata( 'post', 0, 'has_user_restriction', '', true );

	$tables = array(
		$wpdb->prefix . 'useronline',
	);

	foreach ( $tables as $table_name ) {
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $table_name );
	}
}

if ( is_multisite() ) {
	global $wpdb;
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs} WHERE archived = '0' AND spam = '0' AND deleted = '0'" );
	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( (int) $blog_id );
		classicpack_uninstall_single_site();
		restore_current_blog();
	}
} else {
	classicpack_uninstall_single_site();
}
