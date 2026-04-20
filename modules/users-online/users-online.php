<?php
/**
 * Users Online module for ClassicPack.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create DB table on plugin activation.
 *
 * @return void
 */
function classicpress_useronline_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table           = $wpdb->prefix . 'useronline';
	$charset_collate = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE $table (
		timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		user_type varchar(20) NOT NULL default 'guest',
		user_id bigint NOT NULL default 0,
		user_name varchar(250) NOT NULL default '',
		user_ip varchar(39) NOT NULL default '',
		user_agent text NOT NULL,
		page_title text NOT NULL,
		page_url varchar(255) NOT NULL default '',
		referral varchar(255) NOT NULL default '',
		UNIQUE KEY useronline_id (timestamp, user_type, user_ip)
	) $charset_collate"
	);
}

/**
 * Register hooks when the module is enabled (idempotent).
 *
 * @return void
 */
function classicpress_useronline_bootstrap() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	add_action( 'init', 'classicpress_useronline_init', 9 );
}

/**
 * Module bootstrap on init.
 *
 * @return void
 */
function classicpress_useronline_init() {
	global $wpdb;
	$wpdb->tables[]   = 'useronline';
	$wpdb->useronline = $wpdb->prefix . 'useronline';

	require_once __DIR__ . '/core.php';

	$most = get_option(
		'useronline_most',
		array(
			'count' => 1,
			'date'  => time(),
		)
	);

	classicpress_useronline_setup( $most );

	if ( is_admin() ) {
		add_action( 'admin_menu', 'classicpress_useronline_register_submenu', 12 );
		add_action( 'wp_dashboard_setup', 'classicpress_useronline_register_dashboard_widget' );
	}
}

/**
 * Submenu under ClassicPack.
 *
 * @return void
 */
function classicpress_useronline_register_submenu() {
	if ( ! function_exists( 'classicpack_get_menu_slug' ) ) {
		return;
	}
	add_submenu_page(
		classicpack_get_menu_slug(),
		__( 'Users Online', 'classicpack' ),
		__( 'Users Online', 'classicpack' ),
		'manage_options',
		'classicpress-usersonline',
		'classicpress_useronline_render_admin_screen'
	);
}

/**
 * Dashboard widget.
 *
 * @return void
 */
function classicpress_useronline_register_dashboard_widget() {
	if ( ! current_user_can( 'list_users' ) ) {
		return;
	}
	wp_add_dashboard_widget(
		'useronline_dashboard_widget',
		__( 'ClassicPack Users Online', 'classicpack' ),
		'classicpress_useronline_dashboard_widget_content'
	);
}

/**
 * @return void
 */
function classicpress_useronline_dashboard_widget_content() {
	echo classicpress_useronline_render_page();
}

/**
 * Admin screen: module info.
 *
 * @return void
 */
function classicpress_useronline_render_admin_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Users Online', 'classicpack' ) . '</h1>';
	echo classicpress_useronline_render_page();
	echo '</div>';
}
