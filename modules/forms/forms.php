<?php
/**
 * Forms module for ClassicPack.
 *
 * Contact forms built in the admin, placed with [classicpack_form id=N] and delivered
 * by email (wp_mail or SMTP2Go), with a honeypot, optional CAPTCHA and an optional log.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLASSICPACK_FORMS_DB_VERSION', '1' );
define( 'CLASSICPACK_FORMS_CPT', 'classicpack_form' );
define( 'CLASSICPACK_FORMS_META', '_classicpack_forms_config' );
define( 'CLASSICPACK_FORMS_URL', plugins_url( '/', __FILE__ ) );

require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/submit.php';
require_once __DIR__ . '/includes/captcha.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/log.php';
require_once __DIR__ . '/includes/settings.php';

// Modules load during init, so the post type is registered directly rather than on the init hook.
classicpack_forms_register_cpt();

// Create the log table on first use and again whenever the schema version changes.
if ( is_admin() && (string) get_option( 'classicpack_forms_db_version', '' ) !== CLASSICPACK_FORMS_DB_VERSION ) {
	classicpack_forms_create_log_table();
	update_option( 'classicpack_forms_db_version', CLASSICPACK_FORMS_DB_VERSION );
}

add_action( 'admin_menu', 'classicpack_forms_admin_menu', 12 );
add_action( 'add_meta_boxes', 'classicpack_forms_register_metabox' );
add_action( 'edit_form_after_title', 'classicpack_forms_display_shortcode' );
add_action( 'save_post_' . CLASSICPACK_FORMS_CPT, 'classicpack_forms_save_post' );
add_action( 'admin_enqueue_scripts', 'classicpack_forms_admin_assets' );

add_shortcode( 'classicpack_form', 'classicpack_forms_shortcode' );

add_action( 'admin_post_classicpack_forms_submit', 'classicpack_forms_handle_submit' );
add_action( 'admin_post_nopriv_classicpack_forms_submit', 'classicpack_forms_handle_submit' );
