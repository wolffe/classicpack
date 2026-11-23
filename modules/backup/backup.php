<?php
/**
 * Backup module for ClassicPack.
 *
 * Scheduled local database backups with optional gzip, rotation and email delivery,
 * optional wp-content file archives, and restore from the Backup Manager.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLASSICPACK_BACKUP_OPTIONS', 'classicpack_backup_options' );
define( 'CLASSICPACK_BACKUP_PATH', __DIR__ );

require_once CLASSICPACK_BACKUP_PATH . '/inc/functions.php';

if ( is_admin() ) {
	require_once CLASSICPACK_BACKUP_PATH . '/inc/admin-ui.php';
	add_action( 'admin_menu', 'classicpack_backup_admin_menu', 12 );
	add_action( 'admin_enqueue_scripts', 'classicpack_backup_admin_enqueue_assets' );
}

add_action( 'classicpack_backup_cron', 'classicpack_backup_run' );
add_filter( 'cron_schedules', 'classicpack_backup_interval' ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Interval comes from the saved schedule, not a literal.

/**
 * Default options.
 *
 * @return array<string,mixed>
 */
function classicpack_backup_default_options() {
	return [
		'export_dir'      => WP_CONTENT_DIR . '/classicpack-backups',
		'compression'     => 'none',
		'gzip_lvl'        => 1,
		'period'          => 86400,
		'schedule'        => time(),
		'active'          => 0,
		'rotate'          => -1,
		'smart_email'     => '',
		'send_attachment' => 0,
		'backup_files'    => 0,
		'logs'            => [],
	];
}

/**
 * Saved options merged over the defaults.
 *
 * @return array<string,mixed>
 */
function classicpack_backup_get_options() {
	$cfg = get_option( CLASSICPACK_BACKUP_OPTIONS );
	if ( ! is_array( $cfg ) ) {
		return classicpack_backup_default_options();
	}
	return array_merge( classicpack_backup_default_options(), $cfg );
}

/**
 * Submenu under ClassicPack.
 *
 * @return void
 */
function classicpack_backup_admin_menu() {
	add_submenu_page(
		classicpack_get_menu_slug(),
		__( 'Backup', 'classicpack' ),
		__( 'Backup', 'classicpack' ),
		'manage_options',
		'classicpack-backup',
		'classicpack_backup_render_page'
	);
}

/**
 * Backup screen with Settings, Backup Manager and Files Manager tabs.
 *
 * @return void
 */
function classicpack_backup_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tabs = [
		'settings' => __( 'Settings', 'classicpack' ),
		'manager'  => __( 'Backup Manager', 'classicpack' ),
		'files'    => __( 'Files Manager', 'classicpack' ),
	];
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab selection only.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'settings';
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'settings';
	}

	require_once CLASSICPACK_BACKUP_PATH . '/inc/restore.php';

	classicpack_backup_render_page_open( __( 'Backup', 'classicpack' ) );
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $slug => $label ) {
		$url = add_query_arg(
			[
				'page' => 'classicpack-backup',
				'tab'  => $slug,
			],
			admin_url( 'admin.php' )
		);
		echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . ( $tab === $slug ? ' nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';

	include CLASSICPACK_BACKUP_PATH . '/views/' . $tab . '.php';

	classicpack_backup_render_page_close();
}

/**
 * Scheduled backup (classicpack_backup_cron hook).
 *
 * @return void
 */
function classicpack_backup_run() {
	wp_raise_memory_limit( 'admin' );
	set_time_limit( 600 );

	if ( defined( 'CLASSICPACK_BACKUP_RETURN' ) ) {
		return;
	}

	$cfg = classicpack_backup_get_options();
	if ( ! $cfg['active'] ) {
		return;
	}
	if ( empty( $cfg['export_dir'] ) ) {
		return;
	}

	define( 'CLASSICPACK_BACKUP_RETURN', true );

	$timenow    = time();
	$mtime      = explode( ' ', microtime() );
	$time_start = $mtime[1] + $mtime[0];
	$key        = substr( md5( md5( DB_NAME . '|' . microtime() ) ), 0, 6 );
	$date       = wp_date( 'm.d.y-H.i.s', $timenow );
	$sql_path   = $cfg['export_dir'] . '/Backup_' . $date . '_' . $key . '.sql';
	$stored     = ( $cfg['compression'] === 'gz' ) ? $sql_path . '.gz' : $sql_path;

	if ( ! isset( $cfg['logs'] ) || ! is_array( $cfg['logs'] ) ) {
		$cfg['logs'] = [];
	}

	$removed = classicpack_backup_rotate( $cfg, $timenow );
	$dump    = classicpack_backup_exec( $sql_path, 'backup' );
	$ok      = ! is_wp_error( $dump ) && is_file( $stored );

	if ( $ok && ! empty( $cfg['send_attachment'] ) && ! empty( $cfg['smart_email'] ) ) {
		$smart_subject = get_bloginfo() . ' new backup available!';
		wp_mail( $cfg['smart_email'], $smart_subject, __( 'A new backup is now available!', 'classicpack' ), '', [ $stored ] );
	}

	$mtime         = explode( ' ', microtime() );
	$time_end      = $mtime[1] + $mtime[0];
	$time_total    = $time_end - $time_start;
	$logged_file   = $ok ? $stored : $sql_path;
	$cfg['logs'][] = [
		'file'    => $logged_file,
		'type'    => 'database',
		'size'    => is_file( $logged_file ) ? filesize( $logged_file ) : 0,
		'started' => $timenow,
		'took'    => $time_total,
		'status'  => $ok ? __( 'Successful', 'classicpack' ) : __( 'Failed', 'classicpack' ),
		'removed' => $removed,
	];

	if ( ! empty( $cfg['backup_files'] ) ) {
		$files_mtime      = explode( ' ', microtime() );
		$files_time_start = $files_mtime[1] + $files_mtime[0];
		$files_path       = $cfg['export_dir'] . '/' . classicpack_backup_files_archive_basename( $date, $key );
		$files_ok         = classicpack_backup_create_content_archive( $files_path, $cfg['export_dir'] );
		$files_mtime      = explode( ' ', microtime() );
		$files_time_end   = $files_mtime[1] + $files_mtime[0];
		$files_time_total = $files_time_end - $files_time_start;
		$cfg['logs'][]    = [
			'file'    => $files_path,
			'type'    => 'files',
			'size'    => ( $files_ok && is_file( $files_path ) ) ? filesize( $files_path ) : 0,
			'started' => $timenow,
			'took'    => $files_time_total,
			'status'  => $files_ok ? __( 'Successful', 'classicpack' ) : __( 'Failed', 'classicpack' ),
			'removed' => 0,
		];
	}

	update_option( CLASSICPACK_BACKUP_OPTIONS, $cfg );
}

/**
 * The classicpack_backup schedule, whose interval is the saved period.
 *
 * @param array<string,array> $schedules Existing schedules.
 * @return array<string,array>
 */
function classicpack_backup_interval( $schedules ) {
	$cfg    = classicpack_backup_get_options();
	$period = ( (int) $cfg['period'] === 0 ) ? 86400 : (int) $cfg['period'];

	$schedules['classicpack_backup'] = [
		'interval' => $period,
		/* translators: %s: interval in minutes */
		'display'  => sprintf( __( 'ClassicPack Backup Interval - %s minutes', 'classicpack' ), (string) ( $period / 60 ) ),
	];

	return $schedules;
}
