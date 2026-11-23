<?php
/**
 * Forms: optional email log (classicpack_forms_log table).
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Log table name.
 *
 * @return string
 */
function classicpack_forms_log_table() {
	global $wpdb;
	return $wpdb->prefix . 'classicpack_forms_log';
}

/**
 * Create (or upgrade) the log table. Safe to call repeatedly; uses dbDelta.
 *
 * @return void
 */
function classicpack_forms_create_log_table() {
	global $wpdb;

	$table           = classicpack_forms_log_table();
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		form_id bigint(20) unsigned NOT NULL DEFAULT 0,
		form_title varchar(255) NOT NULL DEFAULT '',
		recipients text NOT NULL,
		subject varchar(500) NOT NULL DEFAULT '',
		body longtext NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'sent',
		sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY form_id (form_id),
		KEY sent_at (sent_at)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/**
 * Append one entry (no-op when logging is disabled), then prune.
 *
 * @param int      $form_id    Form post ID (0 for test emails).
 * @param string   $form_title Form title.
 * @param string[] $recipients Recipient addresses.
 * @param string   $subject    Subject.
 * @param string   $body       HTML body.
 * @param bool     $ok         Whether sending succeeded.
 * @return void
 */
function classicpack_forms_log_email( $form_id, $form_title, array $recipients, $subject, $body, $ok ) {
	if ( ! get_option( 'classicpack_forms_log_enabled' ) ) {
		return;
	}

	global $wpdb;

	$wpdb->insert(
		classicpack_forms_log_table(),
		[
			'form_id'    => $form_id,
			'form_title' => $form_title,
			'recipients' => implode( ', ', $recipients ),
			'subject'    => $subject,
			'body'       => $body,
			'status'     => $ok ? 'sent' : 'failed',
			'sent_at'    => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
	);

	$table = esc_sql( classicpack_forms_log_table() );
	$max   = max( 1, (int) get_option( 'classicpack_forms_log_max', 1000 ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, escaped with esc_sql().
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );

	if ( $count <= $max ) {
		return;
	}

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM `{$table}` ORDER BY sent_at ASC, id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, escaped with esc_sql().
			$count - $max
		)
	);
}

/**
 * Email Log tab on the Forms Settings screen.
 *
 * @return void
 */
function classicpack_forms_render_log_tab() {
	global $wpdb;

	// Handle "Clear log" action.
	if (
		isset( $_POST['classicpack_forms_clear_log'], $_POST['classicpack_forms_log_nonce'] )
		&& wp_verify_nonce( sanitize_key( (string) wp_unslash( $_POST['classicpack_forms_log_nonce'] ) ), 'classicpack_forms_clear_log' )
	) {
		$table = esc_sql( classicpack_forms_log_table() );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, escaped with esc_sql().
		$wpdb->query( "TRUNCATE TABLE `{$table}`" );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Email log cleared.', 'classicpack' ) . '</p></div>';
	}

	$table    = esc_sql( classicpack_forms_log_table() );
	$per_page = 50;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin pagination.
	$page   = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
	$offset = ( $page - 1 ) * $per_page;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, escaped with esc_sql().
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM `{$table}` ORDER BY sent_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, escaped with esc_sql().
			$per_page,
			$offset
		)
	);

	if ( $total === 0 ) {
		echo '<p>' . esc_html__( 'No emails logged yet.', 'classicpack' ) . '</p>';
		return;
	}
	?>
	<form method="post" style="margin-bottom:1em;">
		<?php wp_nonce_field( 'classicpack_forms_clear_log', 'classicpack_forms_log_nonce' ); ?>
		<input type="submit" name="classicpack_forms_clear_log" class="button button-secondary" value="<?php esc_attr_e( 'Clear log', 'classicpack' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete all log entries? This cannot be undone.', 'classicpack' ); ?>')">
		<span style="margin-left:1em;color:#666;">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: current entry count, 2: configured maximum */
					__( '%1$d entries (max %2$d)', 'classicpack' ),
					$total,
					max( 1, (int) get_option( 'classicpack_forms_log_max', 1000 ) )
				)
			);
			?>
		</span>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'classicpack' ); ?></th>
				<th><?php esc_html_e( 'Form', 'classicpack' ); ?></th>
				<th><?php esc_html_e( 'Subject', 'classicpack' ); ?></th>
				<th><?php esc_html_e( 'Recipients', 'classicpack' ); ?></th>
				<th><?php esc_html_e( 'Status', 'classicpack' ); ?></th>
				<th><?php esc_html_e( 'Body', 'classicpack' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) { ?>
				<tr>
					<td><?php echo esc_html( $row->sent_at ); ?></td>
					<td>
						<?php
						$edit_link = $row->form_id ? get_edit_post_link( (int) $row->form_id ) : '';
						if ( $edit_link ) {
							echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $row->form_title ) . '</a>';
						} else {
							echo esc_html( $row->form_title );
						}
						?>
					</td>
					<td><?php echo esc_html( $row->subject ); ?></td>
					<td><?php echo esc_html( $row->recipients ); ?></td>
					<td>
						<?php if ( $row->status === 'sent' ) { ?>
							<span style="color:#2e7d32;">&#10003; <?php esc_html_e( 'Sent', 'classicpack' ); ?></span>
						<?php } else { ?>
							<span style="color:#c62828;">&#10007; <?php esc_html_e( 'Failed', 'classicpack' ); ?></span>
						<?php } ?>
					</td>
					<td>
						<?php if ( $row->body !== '' ) { ?>
							<button type="button" class="button button-small classicpack-forms-view-body" data-body="<?php echo esc_attr( $row->body ); ?>" data-subject="<?php echo esc_attr( $row->subject ); ?>">
								<?php esc_html_e( 'View', 'classicpack' ); ?>
							</button>
						<?php } ?>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>

	<?php if ( $total > $per_page ) { ?>
		<div class="tablenav bottom" style="margin-top:.5em;">
			<div class="tablenav-pages">
				<?php
				$pagination = paginate_links(
					[
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $page,
						'total'   => (int) ceil( $total / $per_page ),
					]
				);
				if ( is_string( $pagination ) ) {
					echo wp_kses_post( $pagination );
				}
				?>
			</div>
		</div>
	<?php } ?>

	<dialog id="classicpack-forms-log-dialog" style="padding:0;border:1px solid #c3c4c7;border-radius:4px;box-shadow:0 4px 24px rgba(0,0,0,.18);max-width:90vw;width:680px;">
		<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #c3c4c7;gap:16px;">
			<strong id="classicpack-forms-log-dialog-subject" style="font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></strong>
			<button type="button" id="classicpack-forms-log-dialog-close" style="background:none;border:none;cursor:pointer;font-size:20px;line-height:1;padding:0;color:#666;" aria-label="<?php esc_attr_e( 'Close', 'classicpack' ); ?>">&#x2715;</button>
		</div>
		<iframe id="classicpack-forms-log-dialog-iframe" srcdoc="" sandbox="allow-same-origin" style="display:block;width:100%;height:420px;border:none;"></iframe>
	</dialog>

	<script>
	(function () {
		var dialog  = document.getElementById('classicpack-forms-log-dialog');
		var iframe  = document.getElementById('classicpack-forms-log-dialog-iframe');
		var subject = document.getElementById('classicpack-forms-log-dialog-subject');

		document.querySelectorAll('.classicpack-forms-view-body').forEach(function (btn) {
			btn.addEventListener('click', function () {
				subject.textContent = btn.dataset.subject || '';
				iframe.srcdoc = btn.dataset.body || '';
				dialog.showModal();
			});
		});

		document.getElementById('classicpack-forms-log-dialog-close').addEventListener('click', function () {
			dialog.close();
		});

		// Close on backdrop click.
		dialog.addEventListener('click', function (e) {
			var rect = dialog.getBoundingClientRect();
			if (e.clientX < rect.left || e.clientX > rect.right ||
				e.clientY < rect.top  || e.clientY > rect.bottom) {
				dialog.close();
			}
		});
	}());
	</script>
	<?php
}
