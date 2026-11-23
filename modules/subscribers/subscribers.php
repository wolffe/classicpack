<?php
/**
 * Subscribers module for ClassicPack.
 *
 * Emails published posts to confirmed subscribers: double opt-in signup with the
 * [classicpack_subscribe] shortcode, sending from the post editor or automatically on
 * publish, queued in small batches through wp_mail().
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLASSICPACK_SUBSCRIBERS_DB_VERSION', '1' );
define( 'CLASSICPACK_SUBSCRIBERS_URL', plugins_url( '/', __FILE__ ) );

require_once __DIR__ . '/includes/subscribers.php';
require_once __DIR__ . '/includes/newsletters.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/queue.php';
require_once __DIR__ . '/includes/public.php';
require_once __DIR__ . '/includes/privacy.php';

// Registered before the schedule call below, which looks the interval up.
add_filter( 'cron_schedules', 'classicpack_subscribers_cron_schedules' ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- One-minute queue runner.

if ( is_admin() ) {
	require_once __DIR__ . '/admin/page.php';
	require_once __DIR__ . '/admin/metabox.php';

	// Create the tables on first use and again whenever the schema version changes.
	if ( (string) get_option( 'classicpack_subscribers_db_version', '' ) !== CLASSICPACK_SUBSCRIBERS_DB_VERSION ) {
		classicpack_subscribers_create_tables();
		update_option( 'classicpack_subscribers_db_version', CLASSICPACK_SUBSCRIBERS_DB_VERSION );
	}

	if ( ! wp_next_scheduled( 'classicpack_subscribers_process_queue' ) ) {
		wp_schedule_event( time(), 'classicpack_minute', 'classicpack_subscribers_process_queue' );
	}

	add_action( 'admin_menu', 'classicpack_subscribers_admin_menu', 12 );
	add_action( 'admin_init', 'classicpack_subscribers_register_settings' );
	add_action( 'admin_enqueue_scripts', 'classicpack_subscribers_admin_enqueue' );
	add_action( 'admin_notices', 'classicpack_subscribers_admin_notices' );
	add_action( 'add_meta_boxes', 'classicpack_subscribers_add_metabox' );
	add_action( 'admin_post_classicpack_subscribers_send', 'classicpack_subscribers_handle_send' );
}

add_action( 'classicpack_subscribers_process_queue', 'classicpack_subscribers_process_queue' );
add_action( 'transition_post_status', 'classicpack_subscribers_maybe_auto_send', 10, 3 );

add_shortcode( 'classicpack_subscribe', 'classicpack_subscribers_shortcode' );
add_action( 'admin_post_nopriv_classicpack_subscribers_subscribe', 'classicpack_subscribers_handle_subscribe' );
add_action( 'admin_post_classicpack_subscribers_subscribe', 'classicpack_subscribers_handle_subscribe' );
add_action( 'template_redirect', 'classicpack_subscribers_handle_public_requests', 0 );
add_filter( 'redirect_canonical', 'classicpack_subscribers_disable_canonical_redirect' );
add_action( 'wp_body_open', 'classicpack_subscribers_front_notice' );
add_action( 'wp_footer', 'classicpack_subscribers_front_notice' );

add_filter( 'wp_privacy_personal_data_exporters', 'classicpack_subscribers_register_exporter' );
add_filter( 'wp_privacy_personal_data_erasers', 'classicpack_subscribers_register_eraser' );
