<?php
$cfg          = classicpack_backup_get_options();
$classicpack_backup_msg = [];

if ( isset( $_POST['classicpack_backup_submit'] ) ) {
	check_admin_referer( 'classicpack_backup_options' );
	$export_dir              = isset( $_POST['export_dir'] ) ? sanitize_text_field( wp_unslash( $_POST['export_dir'] ) ) : '';
	$temp['export_dir']      = rtrim( $export_dir, '/' );
	$compression             = isset( $_POST['compression'] ) ? sanitize_key( wp_unslash( $_POST['compression'] ) ) : 'none';
	$temp['compression']     = in_array( $compression, [ 'none', 'gz' ], true ) ? $compression : 'none';
	$temp['gzip_lvl']        = intval( $_POST['gzip_lvl'] ?? 1 );
	$temp['period']          = intval( $_POST['severy'] ?? 1 ) * intval( $_POST['speriod'] ?? 86400 );
	$temp['active']          = ! empty( $_POST['active'] ) ? 1 : 0;
	$temp['rotate']          = intval( $_POST['rotate'] ?? -1 );
	$temp['logs']            = $cfg['logs'] ?? [];
	$temp['smart_email']     = sanitize_email( wp_unslash( $_POST['smart_email'] ?? '' ) );
	$temp['send_attachment'] = ! empty( $_POST['send_attachment'] ) ? 1 : 0;
	$temp['backup_files']    = ! empty( $_POST['backup_files'] ) ? 1 : 0;

	$timenow          = time();
	$schedule_year    = wp_date( 'Y', $timenow );
	$month            = wp_date( 'n', $timenow );
	$day              = wp_date( 'j', $timenow );
	$hours            = intval( $_POST['hours'] ?? 0 );
	$minutes          = intval( $_POST['minutes'] ?? 0 );
	$seconds          = intval( $_POST['seconds'] ?? 0 );
	$temp['schedule'] = mktime( $hours, $minutes, $seconds, $month, $day, $schedule_year );

	update_option( CLASSICPACK_BACKUP_OPTIONS, $temp );

	if ( empty( $_POST['active'] ) ) {
		wp_clear_scheduled_hook( 'classicpack_backup_cron' );
	} else {
		wp_clear_scheduled_hook( 'classicpack_backup_cron' );
		wp_schedule_event( $temp['schedule'], 'classicpack_backup', 'classicpack_backup_cron' );
	}

	$cfg = $temp;
	echo '<div id="message" class="updated fade"><p>' . esc_html__( 'Options saved!', 'classicpack' ) . '</p></div>';
}

if ( ! empty( $cfg['export_dir'] ) ) {
	if ( ! is_dir( $cfg['export_dir'] ) ) {
		if ( wp_mkdir_p( $cfg['export_dir'] ) ) {
			$classicpack_backup_msg[] = sprintf( __( 'Folder <strong>%s</strong> was successfully created!', 'classicpack' ), esc_html( $cfg['export_dir'] ) );
		} else {
			$classicpack_backup_msg[] = sprintf( __( 'Folder <strong>%s</strong> was not created. Check permissions!', 'classicpack' ), esc_html( $cfg['export_dir'] ) );
		}
	} else {
		$classicpack_backup_msg[] = '<small>' . sprintf(
			/* translators: %s: backup folder path */
			__( 'Folder %s is available', 'classicpack' ),
			esc_html( $cfg['export_dir'] )
		) . '</small>';
	}

	if ( is_dir( $cfg['export_dir'] ) ) {
		$condoms = [ '.htaccess', 'index.html' ];
		foreach ( $condoms as $condom ) {
			if ( ! file_exists( $cfg['export_dir'] . '/' . $condom ) ) {
				// phpcs:disable WordPress.WP.AlternativeFunctions -- Backup folder can sit outside the WordPress filesystem API.
				$file = fopen( $cfg['export_dir'] . '/' . $condom, 'w' );
				if ( $file ) {
					$contents = ( $condom === 'index.html' ) ? '' : "Deny from all\n";
					fwrite( $file, $contents );
					fclose( $file );
					$classicpack_backup_msg[] = sprintf( __( 'File <strong>%s</strong> was created', 'classicpack' ), esc_html( $condom ) );
				} else {
					$classicpack_backup_msg[] = sprintf( __( 'File <strong>%s</strong> was not created. Check permissions!', 'classicpack' ), esc_html( $condom ) );
				}
				// phpcs:enable WordPress.WP.AlternativeFunctions
			} else {
				$classicpack_backup_msg[] = sprintf( __( 'File <strong>%s</strong> is available', 'classicpack' ), esc_html( $condom ) );
			}
		}
	}
} else {
	$classicpack_backup_msg[] = __( 'Specify the folder where the backups will be stored', 'classicpack' );
}

$period  = (int) $cfg['period'];
$speriod = 86400;
$severy  = 1;
if ( $period > 0 && $period % 2592000 === 0 ) {
	$speriod = 2592000;
	$severy  = intdiv( $period, 2592000 );
} elseif ( $period > 0 && $period % 604800 === 0 ) {
	$speriod = 604800;
	$severy  = intdiv( $period, 604800 );
} elseif ( $period > 0 && $period % 86400 === 0 ) {
	$speriod = 86400;
	$severy  = intdiv( $period, 86400 );
} elseif ( $period > 0 && $period % 3600 === 0 ) {
	$speriod = 3600;
	$severy  = intdiv( $period, 3600 );
}

classicpack_backup_render_stats_cards( $cfg['export_dir'] ?? '' );
?>

	<div id="poststuff">
		<div class="postbox">
			<h3><?php esc_html_e( 'Welcome', 'classicpack' ); ?></h3>
			<div class="inside">
				<p><?php echo wp_kses( __( '<strong>ClassicPack Backup</strong> creates local database backups and optional file archives. You can create backups of your ClassicPress database. Backups can be restored from Backup Manager or used for easy migration.', 'classicpack' ), [ 'strong' => [] ] ); ?></p>
				<p>
					<a href="https://getbutterfly.com/classicpress-plugins/classicpack/"><?php esc_html_e( 'Plugin Home', 'classicpack' ); ?></a>
					<br><?php
					echo wp_kses(
						sprintf(
							/* translators: %s: plugin website URL */
							__( 'For support, feature requests and bug reporting, visit the <a href="%s" rel="external">official website</a>.', 'classicpack' ),
							esc_url( 'https://getbutterfly.com/classicpress-plugins/classicpack/' )
						),
						[
							'a' => [
								'href' => [],
								'rel'  => [],
							],
						]
					);
					?>
				</p>
			</div>
		</div>
	</div>

	<div class="postbox-container" style="width: 100%">
		<div class="metabox-holder">
			<div class="postbox">
				<h3 class="hndle"><span><?php esc_html_e( 'Diagnostics', 'classicpack' ); ?></span></h3>
				<div class="inside">
					<?php if ( ! empty( $classicpack_backup_msg ) ) { ?>
						<p><?php echo wp_kses_post( implode( '<br>', $classicpack_backup_msg ) ); ?></p>
					<?php } ?>
					<?php
					$scheduledtime = wp_next_scheduled( 'classicpack_backup_cron' );
					?>
					<div class="classicpack-backup-diagnostics-meta">
						<?php
						echo esc_html__( 'Plugin version', 'classicpack' ) . ': <strong>' . esc_html( CLASSICPACK_VERSION ) . '</strong><br>';
						echo esc_html__( 'Next scheduled backup', 'classicpack' ) . ': <strong>';
						echo $scheduledtime !== false ? esc_html( wp_date( 'F j, Y, H:i:s', $scheduledtime ) ) : esc_html__( 'Not scheduled', 'classicpack' );
						echo '</strong><br>';
						echo esc_html__( 'Server time', 'classicpack' ) . ': <strong>' . esc_html( wp_date( 'F j, Y, H:i:s' ) ) . '</strong>';
						if ( ! empty( $cfg['active'] ) && $scheduledtime !== false ) {
							echo '<br>' . esc_html__( 'Scheduling is active.', 'classicpack' );
						}
						?>
					</div>
					<?php classicpack_backup_render_environment_badges(); ?>
				</div>
			</div>
		</div>
	</div>

	<div class="clear"></div>

	<div class="postbox-container" style="width: 100%">
		<div class="metabox-holder">
			<div class="postbox">
				<h3 class="hndle"><span><?php esc_html_e( 'Plugin Settings', 'classicpack' ); ?></span></h3>
				<div class="inside">
					<form method="post" action="">
						<?php wp_nonce_field( 'classicpack_backup_options' ); ?>
						<h4><?php esc_html_e( 'General Settings', 'classicpack' ); ?></h4>
						<p>
							<input type="text" name="export_dir" id="export_dir" value="<?php echo esc_attr( $cfg['export_dir'] ); ?>" class="regular-text">
							<label for="export_dir"><?php esc_html_e( 'Backup directory', 'classicpack' ); ?></label>
							<br><small><?php esc_html_e( 'All your backups will be saved here. Default is', 'classicpack' ); ?> <?php echo esc_html( WP_CONTENT_DIR . '/classicpack-backups' ); ?></small>
						</p>
						<p>
							<input type="email" name="smart_email" id="smart_email" value="<?php echo esc_attr( $cfg['smart_email'] ); ?>" class="regular-text">
							<label for="smart_email"><?php esc_html_e( 'Notification email', 'classicpack' ); ?></label>
							<br><small><?php esc_html_e( 'You will receive notification messages at this address.', 'classicpack' ); ?></small>
						</p>
						<p>
							<select name="compression" id="compression">
								<option value="none" <?php selected( $cfg['compression'], 'none' ); ?>><?php esc_html_e( 'None', 'classicpack' ); ?></option>
								<?php
								if ( function_exists( 'gzopen' ) ) {
									?>
									<option value="gz" <?php selected( $cfg['compression'], 'gz' ); ?>><?php esc_html_e( 'GZIP', 'classicpack' ); ?></option> <?php } ?>
							</select>
							<?php if ( function_exists( 'gzopen' ) ) { ?>
								<select name="gzip_lvl">
									<?php
									for ( $i = 1; $i <= 9; $i++ ) {
										?>
										<option value="<?php echo esc_attr( (string) $i ); ?>" <?php selected( (int) $cfg['gzip_lvl'], $i ); ?>><?php echo esc_html( sprintf( __( 'Use GZIP compression level %d', 'classicpack' ), $i ) ); ?></option>
									<?php } ?>
								</select>
							<?php } ?>
						</p>
						<p>
							<input type="checkbox" name="active" value="1" <?php checked( ! empty( $cfg['active'] ) ); ?>> <?php esc_html_e( 'Activate backup schedule', 'classicpack' ); ?><br>
							<input type="checkbox" name="send_attachment" value="1"<?php checked( ! empty( $cfg['send_attachment'] ) ); ?> /> <?php esc_html_e( 'Send backup as attachment', 'classicpack' ); ?><br>
							<input type="checkbox" name="backup_files" value="1"<?php checked( ! empty( $cfg['backup_files'] ) ); ?> /> <?php esc_html_e( 'Include file backup (wp-content and site root files)', 'classicpack' ); ?>
							<br><small><?php esc_html_e( 'Creates a .tar.gz with wp-content (excluding this backup folder) and root files such as wp-config.php. Needs tar and enough disk space. Copy backups offsite for production sites.', 'classicpack' ); ?></small><br>
							<?php
							list($hours, $minutes, $seconds) = explode( '-', wp_date( 'H-i-s', (int) $cfg['schedule'] ) );
							$times                           = [ 'hours', 'minutes', 'seconds' ];
							$periods                         = [
								3600    => __( 'Hour(s)', 'classicpack' ),
								86400   => __( 'Day(s)', 'classicpack' ),
								604800  => __( 'Week(s)', 'classicpack' ),
								2592000 => __( 'Month(s)', 'classicpack' ),
							];
							?>
							<strong><?php esc_html_e( 'Run every ', 'classicpack' ); ?></strong>
							<select name="severy">
								<?php
								for ( $i = 1; $i <= 12; $i++ ) {
									?>
									<option <?php selected( $severy, $i ); ?>><?php echo esc_html( (string) $i ); ?></option>
								<?php } ?>
							</select>
							<select name="speriod">
								<?php
								foreach ( $periods as $period_val => $display ) {
									?>
									<option value="<?php echo esc_attr( (string) $period_val ); ?>" <?php selected( $period_val, $speriod ); ?>><?php echo esc_html( $display ); ?></option>
								<?php } ?>
							</select>

							<strong><?php esc_html_e( 'When', 'classicpack' ); ?></strong>
							<?php
							foreach ( $times as $time ) {
								$max = $time === 'hours' ? 24 : 60;
								?>
								: <select name="<?php echo esc_attr( $time ); ?>">
									<?php
									for ( $i = 0; $i < $max; $i++ ) {
										?>
										<option <?php selected( (int) ${$time}, $i ); ?>><?php echo esc_html( (string) $i ); ?></option>
									<?php } ?>
								</select>
							<?php } ?>
						</p>
						<p>
							<select name="rotate">
								<?php
								for ( $i = -1; $i <= 90; $i++ ) {
									switch ( $i ) {
										case -1:
											$display = __( 'Do not remove previous backups', 'classicpack' );
											break;
										case 0:
											$display = __( 'Remove all previous backups', 'classicpack' );
											break;
										default:
											$display = __( 'Remove backups older than ', 'classicpack' ) . $i . ( $i > 1 ? __( ' days', 'classicpack' ) : __( ' day', 'classicpack' ) );
											break;
									}
									?>
									<option value="<?php echo esc_attr( (string) $i ); ?>" <?php selected( (int) $cfg['rotate'], $i ); ?>><?php echo esc_html( $display ); ?></option>
								<?php } ?>
							</select>
							<br><small><?php esc_html_e( 'The removal of old backups occurs during new backup generation.', 'classicpack' ); ?></small>
						</p>

						<p>
							<input type="submit" name="classicpack_backup_submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'classicpack' ); ?>">
						</p>
					</form>
				</div>
			</div>
		</div>
	</div>
