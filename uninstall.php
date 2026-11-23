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

	$options = [
		'classicpack_modules',
		'classicpack_user_manager_options',
		'classicpack_admin_login_bootstrapped',
		'classicpack_admin_login_page',
		'classicpack_admin_login_redirect',
		'classicpack_admin_login_redirect_field',
		'useronline_most',
		'classicpress_auto_save_images_options',
		'easy_author_avatar_image_option',
		'classicpack_lightweight_comments_notify_emails',
		'classicpack_forms_db_version',
		'classicpack_forms_mailer',
		'classicpack_forms_smtp2go_key',
		'classicpack_forms_log_enabled',
		'classicpack_forms_log_max',
		'classicpack_backup_options',
		'classicpack_subscribers_db_version',
		'classicpack_subscribers_auto_send',
		'classicpack_subscribers_from_name',
		'classicpack_subscribers_from_email',
		'classicpack_subscribers_subject_template',
		'classicpack_subscribers_batch_size',
	];

	foreach ( $options as $option_name ) {
		delete_option( $option_name );
	}

	delete_site_option( 'classicpack_admin_login_page' );

	wp_clear_scheduled_hook( 'classicpack_backup_cron' );
	wp_clear_scheduled_hook( 'classicpack_subscribers_process_queue' );

	// Forms: form posts, their meta, and the short-lived stash/CAPTCHA transients.
	$form_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'classicpack_form'" );
	foreach ( $form_ids as $form_id ) {
		wp_delete_post( (int) $form_id, true );
	}
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_classicpack\_forms\_%' OR option_name LIKE '\_transient\_timeout\_classicpack\_forms\_%'" );

	delete_metadata( 'user', 0, 'classicpack_last_login', '', true );
	delete_metadata( 'user', 0, 'easy-author-avatar-profile-image', '', true );
	delete_post_meta_by_key( 'has_user_restriction' );
	delete_post_meta_by_key( '_classicpress_skip_remote_images' );

	$tables = [
		$wpdb->prefix . 'useronline',
		$wpdb->prefix . 'classicpack_comments',
		$wpdb->prefix . 'classicpack_forms_log',
		$wpdb->prefix . 'classicpack_subscribers',
		$wpdb->prefix . 'classicpack_subscriber_newsletters',
		$wpdb->prefix . 'classicpack_subscriber_queue',
	];

	foreach ( $tables as $table_name ) {
		$safe_table = esc_sql( $table_name );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall; table name from $wpdb->prefix . 'useronline' escaped with esc_sql().
		$wpdb->query( "DROP TABLE IF EXISTS `{$safe_table}`" );
	}
}

if ( is_multisite() ) {
	global $wpdb;
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs} WHERE archived = '0' AND spam = '0' AND deleted = '0'" );
	foreach ( $blog_ids as $site_blog_id ) {
		switch_to_blog( (int) $site_blog_id );
		classicpack_uninstall_single_site();
		restore_current_blog();
	}
} else {
	classicpack_uninstall_single_site();
}
