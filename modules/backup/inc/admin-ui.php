<?php
/**
 * ClassicPack Backup: admin screen chrome, stats cards and environment badges.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function classicpack_backup_admin_enqueue_assets( $hook_suffix ) {
	if ( 'classicpack_page_classicpack-backup' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'classicpack-backup-admin',
		plugins_url( 'assets/admin.css', CLASSICPACK_BACKUP_PATH . '/backup.php' ),
		[],
		CLASSICPACK_VERSION
	);
}

function classicpack_backup_disk_stats( $export_dir = '' ) {
	$stats = [
		'count'    => 0,
		'bytes'    => 0,
		'db'       => 0,
		'files'    => 0,
		'readable' => false,
	];

	if ( $export_dir === '' ) {
		$export_dir = classicpack_backup_get_options()['export_dir'] ?? '';
	}

	$export_dir = rtrim( (string) $export_dir, '/\\' );
	if ( $export_dir === '' || ! is_dir( $export_dir ) || ! is_readable( $export_dir ) ) {
		return $stats;
	}

	$stats['readable'] = true;
	$handle            = opendir( $export_dir );
	if ( $handle === false ) {
		return $stats;
	}

	$file = readdir( $handle );
	while ( false !== $file ) {
		if ( classicpack_backup_is_rotatable_backup_file( $file ) ) {
			$path = $export_dir . '/' . $file;
			if ( is_file( $path ) ) {
				$size = filesize( $path );
				if ( $size !== false ) {
					++$stats['count'];
					$stats['bytes'] += $size;
					if ( preg_match( '/\.tar\.gz$/i', $file ) ) {
						++$stats['files'];
					} else {
						++$stats['db'];
					}
				}
			}
		}
		$file = readdir( $handle );
	}
	closedir( $handle );

	return $stats;
}

function classicpack_backup_parse_ini_bytes( $value ) {
	if ( $value === false || $value === '' ) {
		return 0;
	}
	$value = trim( (string) $value );
	if ( $value === '-1' ) {
		return -1;
	}
	$unit  = strtolower( substr( $value, -1 ) );
	$bytes = (int) $value;
	if ( $unit === 'g' ) {
		return $bytes * 1024 * 1024 * 1024;
	}
	if ( $unit === 'm' ) {
		return $bytes * 1024 * 1024;
	}
	if ( $unit === 'k' ) {
		return $bytes * 1024;
	}
	return $bytes;
}

function classicpack_backup_memory_limit_state( $ini_value ) {
	$bytes = classicpack_backup_parse_ini_bytes( $ini_value );
	if ( $bytes === -1 ) {
		return 'ok';
	}
	if ( $bytes >= 256 * 1024 * 1024 ) {
		return 'ok';
	}
	if ( $bytes >= 128 * 1024 * 1024 ) {
		return 'warn';
	}
	return 'fail';
}

function classicpack_backup_max_execution_state( $ini_value ) {
	if ( $ini_value === false || $ini_value === '' ) {
		return 'neutral';
	}
	$seconds = (int) $ini_value;
	if ( $seconds === 0 ) {
		return 'ok';
	}
	if ( $seconds >= 300 ) {
		return 'ok';
	}
	if ( $seconds >= 60 ) {
		return 'warn';
	}
	return 'fail';
}

function classicpack_backup_mysqli_available() {
	global $wpdb;

	return isset( $wpdb->dbh ) && $wpdb->dbh instanceof mysqli;
}

function classicpack_backup_requirement_checks() {
	$cfg             = classicpack_backup_get_options();
	$export_dir      = rtrim( (string) ( $cfg['export_dir'] ?? '' ), '/\\' );
	$php_ok          = version_compare( PHP_VERSION, '8.0', '>=' );
	$gzip            = function_exists( 'gzopen' );
	$tar             = classicpack_backup_tar_available();
	$exec_ok         = function_exists( 'exec' );
	$files_ok        = $tar && $exec_ok;
	$export_writable = ( $export_dir !== '' && is_dir( $export_dir ) && is_writable( $export_dir ) );
	$export_exists   = ( $export_dir !== '' && is_dir( $export_dir ) );
	$scheduled       = wp_next_scheduled( 'classicpack_backup_cron' ) !== false;
	$restore_ok      = classicpack_backup_mysqli_available() || classicpack_backup_mysql_cli_available();
	$memory_ini      = ini_get( 'memory_limit' );
	$max_exec_ini    = ini_get( 'max_execution_time' );
	$memory_state    = classicpack_backup_memory_limit_state( $memory_ini );
	$max_exec_state  = classicpack_backup_max_execution_state( $max_exec_ini );

	$checks = [
		[
			'label'  => __( 'PHP 8.0+', 'classicpack' ),
			'detail' => $php_ok
				? sprintf( __( 'Running PHP %s.', 'classicpack' ), PHP_VERSION )
				: sprintf( __( 'PHP %s is installed; 8.0 or newer is required.', 'classicpack' ), PHP_VERSION ),
			'state'  => $php_ok ? 'ok' : 'fail',
		],
		[
			'label'  => __( 'Backup folder', 'classicpack' ),
			'detail' => $export_writable
				? ( $export_dir !== '' ? $export_dir : __( 'Writable.', 'classicpack' ) )
				: ( $export_exists
					? __( 'Folder exists but is not writable.', 'classicpack' )
					: __( 'Set a writable backup directory in settings.', 'classicpack' ) ),
			'state'  => $export_writable ? 'ok' : ( $export_exists ? 'warn' : 'fail' ),
		],
		[
			'label'  => __( 'GZIP', 'classicpack' ),
			'detail' => $gzip
				? __( 'Compressed .sql.gz backups are supported.', 'classicpack' )
				: __( 'Optional; uncompressed .sql dumps only.', 'classicpack' ),
			'state'  => $gzip ? 'ok' : 'warn',
		],
		[
			'label'  => __( 'File archives', 'classicpack' ),
			'detail' => $files_ok
				? __( 'tar and exec() are available.', 'classicpack' )
				: __( 'Optional; required for file backups and restore.', 'classicpack' ),
			'state'  => $files_ok ? 'ok' : 'warn',
		],
		[
			'label'  => __( 'Database restore', 'classicpack' ),
			'detail' => $restore_ok
				? ( classicpack_backup_mysql_cli_available()
					? __( 'mysql CLI or mysqli can import dumps.', 'classicpack' )
					: __( 'mysqli multi-query can import dumps.', 'classicpack' ) )
				: __( 'Needs mysqli or mysql CLI for restore.', 'classicpack' ),
			'state'  => $restore_ok ? 'ok' : 'fail',
		],
		[
			'label'  => __( 'memory_limit', 'classicpack' ),
			'detail' => $memory_ini !== false && $memory_ini !== ''
				? (string) $memory_ini . ( $memory_state === 'ok' ? '' : __( ' — 256M+ recommended for large backups.', 'classicpack' ) )
				: __( 'Unknown', 'classicpack' ),
			'state'  => $memory_state,
		],
		[
			'label'  => __( 'max_execution_time', 'classicpack' ),
			'detail' => $max_exec_ini !== false && $max_exec_ini !== ''
				? ( (int) $max_exec_ini === 0
					? __( 'Unlimited (0).', 'classicpack' )
					: (string) $max_exec_ini . 's' . ( $max_exec_state === 'ok' ? '' : __( ' — 300s+ recommended for large backups.', 'classicpack' ) ) )
				: __( 'Unknown', 'classicpack' ),
			'state'  => $max_exec_state,
		],
		[
			'label'  => __( 'Scheduled backups', 'classicpack' ),
			'detail' => empty( $cfg['active'] )
				? __( 'Schedule is off in settings.', 'classicpack' )
				: ( $scheduled
					? __( 'Cron event is registered.', 'classicpack' )
					: __( 'Schedule is on but no cron event found.', 'classicpack' ) ),
			'state'  => empty( $cfg['active'] ) ? 'neutral' : ( $scheduled ? 'ok' : 'warn' ),
		],
	];

	if ( ! empty( $cfg['backup_files'] ) && ! $files_ok ) {
		$checks[] = [
			'label'  => __( 'Scheduled file backup', 'classicpack' ),
			'detail' => __( 'Enabled in settings but tar/exec() is missing.', 'classicpack' ),
			'state'  => 'fail',
		];
	}

	if ( ! empty( $cfg['compression'] ) && $cfg['compression'] === 'gz' && ! $gzip ) {
		$checks[] = [
			'label'  => __( 'GZIP compression', 'classicpack' ),
			'detail' => __( 'Selected in settings but gzopen is not available.', 'classicpack' ),
			'state'  => 'fail',
		];
	}

	return $checks;
}

function classicpack_backup_environment_checks() {
	$checks = [
		[
			'label'  => 'DB_CHARSET',
			'detail' => defined( 'DB_CHARSET' ) ? DB_CHARSET : __( 'Not defined', 'classicpack' ),
			'state'  => defined( 'DB_CHARSET' ) ? 'ok' : 'warn',
		],
		[
			'label'  => 'ABSPATH',
			'detail' => defined( 'ABSPATH' ) ? ABSPATH : __( 'Not defined', 'classicpack' ),
			'state'  => defined( 'ABSPATH' ) ? 'ok' : 'fail',
		],
		[
			'label'  => 'WP_CONTENT_DIR',
			'detail' => defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : __( 'Not defined', 'classicpack' ),
			'state'  => ( defined( 'WP_CONTENT_DIR' ) && is_dir( WP_CONTENT_DIR ) ) ? 'ok' : 'fail',
		],
	];

	if ( defined( 'CP_VERSION' ) ) {
		$checks[] = [
			'label'  => __( 'ClassicPress', 'classicpack' ),
			'detail' => CP_VERSION,
			'state'  => 'ok',
		];
	}

	return $checks;
}

function classicpack_backup_render_badge_list( $checks, $heading, $wrap_class ) {
	if ( $checks === [] ) {
		return;
	}
	?>
	<div class="<?php echo esc_attr( $wrap_class ); ?>">
		<h4 class="classicpack-backup-subheading"><?php echo esc_html( $heading ); ?></h4>
		<ul class="classicpack-backup-badges">
			<?php
			foreach ( $checks as $check ) {
				$state = $check['state'];
				?>
				<li class="classicpack-backup-badge classicpack-backup-badge--<?php echo esc_attr( $state ); ?>">
					<span class="classicpack-backup-badge__label"><?php echo esc_html( $check['label'] ); ?></span>
					<span class="classicpack-backup-badge__detail"><?php echo esc_html( $check['detail'] ); ?></span>
				</li>
			<?php } ?>
		</ul>
	</div>
	<?php
}

function classicpack_backup_render_requirement_badges() {
	classicpack_backup_render_badge_list(
		classicpack_backup_requirement_checks(),
		__( 'Requirements', 'classicpack' ),
		'classicpack-backup-requirements-wrap'
	);
}

function classicpack_backup_render_page_open( $title ) {
	echo '<div class="wrap classicpack-backup-wrap">';
	echo '<header class="classicpack-backup-header">';
	echo '<h1 class="classicpack-backup-title"><span class="dashicons dashicons-cloud" aria-hidden="true"></span>';
	echo esc_html( $title );
	echo '</h1>';
	echo '</header>';
}

function classicpack_backup_render_page_close() {
	echo '</div>';
}

function classicpack_backup_render_stats_cards( $export_dir = '' ) {
	$stats = classicpack_backup_disk_stats( $export_dir );
	?>
	<div class="classicpack-backup-stats" role="group" aria-label="<?php esc_attr_e( 'Backup storage summary', 'classicpack' ); ?>">
		<div class="classicpack-backup-stat-card">
			<span class="classicpack-backup-stat-value"><?php echo esc_html( (string) $stats['count'] ); ?></span>
			<span class="classicpack-backup-stat-label"><?php esc_html_e( 'Backups on disk', 'classicpack' ); ?></span>
		</div>
		<div class="classicpack-backup-stat-card">
			<span class="classicpack-backup-stat-value"><?php echo esc_html( size_format( $stats['bytes'], 2 ) ); ?></span>
			<span class="classicpack-backup-stat-label"><?php esc_html_e( 'Total size on disk', 'classicpack' ); ?></span>
		</div>
		<div class="classicpack-backup-stat-card">
			<span class="classicpack-backup-stat-value"><?php echo esc_html( (string) $stats['db'] ); ?></span>
			<span class="classicpack-backup-stat-label"><?php esc_html_e( 'Database dumps', 'classicpack' ); ?></span>
		</div>
		<div class="classicpack-backup-stat-card">
			<span class="classicpack-backup-stat-value"><?php echo esc_html( (string) $stats['files'] ); ?></span>
			<span class="classicpack-backup-stat-label"><?php esc_html_e( 'File archives', 'classicpack' ); ?></span>
		</div>
	</div>
	<?php
	if ( $export_dir !== '' && ! $stats['readable'] ) {
		echo '<p class="classicpack-backup-note">' . esc_html__( 'Backup folder is missing or not readable. Stats count only matching backup files on disk.', 'classicpack' ) . '</p>';
	}
	classicpack_backup_render_requirement_badges();
}

function classicpack_backup_render_environment_badges() {
	classicpack_backup_render_badge_list(
		classicpack_backup_environment_checks(),
		__( 'Environment', 'classicpack' ),
		'classicpack-backup-badges-wrap'
	);
}
