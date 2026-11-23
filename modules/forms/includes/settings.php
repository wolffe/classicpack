<?php
/**
 * Forms: Settings screen (Settings and Email Log tabs) under ClassicPack.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forms and Forms Settings, side by side under ClassicPack.
 *
 * @return void
 */
function classicpack_forms_admin_menu() {
	add_submenu_page(
		classicpack_get_menu_slug(),
		__( 'Forms', 'classicpack' ),
		__( 'Forms', 'classicpack' ),
		'manage_options',
		'edit.php?post_type=' . CLASSICPACK_FORMS_CPT
	);
	add_submenu_page(
		classicpack_get_menu_slug(),
		__( 'Forms Settings', 'classicpack' ),
		__( 'Forms Settings', 'classicpack' ),
		'manage_options',
		'classicpack-forms',
		'classicpack_forms_render_settings_page'
	);
}

/**
 * Settings screen with Settings and Email Log tabs.
 *
 * @return void
 */
function classicpack_forms_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tabs = [
		'settings' => __( 'Settings', 'classicpack' ),
		'log'      => __( 'Email Log', 'classicpack' ),
	];
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab selection only.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'settings';
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'settings';
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Forms Settings', 'classicpack' ); ?></h1>

		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $label ) { ?>
				<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'classicpack-forms', 'tab' => $slug ], admin_url( 'admin.php' ) ) ); ?>" class="nav-tab<?php echo $tab === $slug ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php } ?>
		</nav>

		<?php
		if ( $tab === 'log' ) {
			classicpack_forms_render_log_tab();
			echo '</div>';
			return;
		}

		// Handle test email.
		$test_notice = '';
		if (
			isset( $_POST['classicpack_forms_test_nonce'] )
			&& wp_verify_nonce( sanitize_key( (string) wp_unslash( $_POST['classicpack_forms_test_nonce'] ) ), 'classicpack_forms_send_test' )
		) {
			$test_to = isset( $_POST['classicpack_forms_test_to'] )
				? sanitize_email( (string) wp_unslash( $_POST['classicpack_forms_test_to'] ) )
				: '';

			if ( ! $test_to || ! is_email( $test_to ) ) {
				$test_notice = '<div class="notice notice-error is-dismissible"><p>'
					. esc_html__( 'Please enter a valid email address.', 'classicpack' )
					. '</p></div>';
			} else {
				$site    = (string) get_option( 'blogname', 'ClassicPack' );
				$subject = sprintf(
					/* translators: %s: site name */
					__( '[%s] ClassicPack Forms test email', 'classicpack' ),
					$site
				);
				$body = '<p>' . esc_html(
					sprintf(
						/* translators: %s: mailer label */
						__( 'This is a test email sent via ClassicPack Forms using the "%s" mailer.', 'classicpack' ),
						CLASSICPACK_FORMS_MAILERS[ (string) get_option( 'classicpack_forms_mailer', 'default' ) ] ?? 'default'
					)
				) . '</p>'
					. '<p>' . esc_html__( 'If you received this, your email configuration is working correctly.', 'classicpack' ) . '</p>';

				$ok = classicpack_forms_dispatch_mail(
					[ $test_to ],
					$subject,
					$body,
					[ 'Content-Type: text/html; charset=UTF-8' ]
				);

				classicpack_forms_log_email( 0, __( 'Test email', 'classicpack' ), [ $test_to ], $subject, $body, $ok );

				if ( $ok ) {
					$test_notice = '<div class="notice notice-success is-dismissible"><p>'
						. esc_html(
							sprintf(
								/* translators: %s: recipient address */
								__( 'Test email sent to %s.', 'classicpack' ),
								$test_to
							)
						)
						. '</p></div>';
				} else {
					$test_notice = '<div class="notice notice-error is-dismissible"><p>'
						. esc_html__( 'Test email failed. Check your mailer settings and try again.', 'classicpack' )
						. '</p></div>';
				}
			}
		}

		// Handle save.
		if (
			isset( $_POST['classicpack_forms_settings_nonce'] )
			&& wp_verify_nonce( sanitize_key( (string) wp_unslash( $_POST['classicpack_forms_settings_nonce'] ) ), 'classicpack_forms_save_settings' )
		) {
			$mailer_raw = isset( $_POST['classicpack_forms_mailer'] )
				? sanitize_key( (string) wp_unslash( $_POST['classicpack_forms_mailer'] ) )
				: '';
			$mailer = array_key_exists( $mailer_raw, CLASSICPACK_FORMS_MAILERS ) ? $mailer_raw : 'default';
			update_option( 'classicpack_forms_mailer', $mailer );

			$api_key = isset( $_POST['classicpack_forms_smtp2go_key'] )
				? sanitize_text_field( (string) wp_unslash( $_POST['classicpack_forms_smtp2go_key'] ) )
				: '';
			update_option( 'classicpack_forms_smtp2go_key', $api_key );

			$log_enabled = ! empty( $_POST['classicpack_forms_log_enabled'] ) ? '1' : '';
			update_option( 'classicpack_forms_log_enabled', $log_enabled );

			$log_max = isset( $_POST['classicpack_forms_log_max'] ) ? max( 1, absint( wp_unslash( $_POST['classicpack_forms_log_max'] ) ) ) : 1000;
			update_option( 'classicpack_forms_log_max', $log_max );

			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Settings saved.', 'classicpack' )
				. '</p></div>';
		}

		$mailer      = (string) get_option( 'classicpack_forms_mailer', 'default' );
		$smtp2go_key = (string) get_option( 'classicpack_forms_smtp2go_key', '' );
		$log_enabled = (bool) get_option( 'classicpack_forms_log_enabled', false );
		$log_max     = max( 1, (int) get_option( 'classicpack_forms_log_max', 1000 ) );

		echo wp_kses_post( $test_notice );
		?>

		<form method="post">
			<?php wp_nonce_field( 'classicpack_forms_save_settings', 'classicpack_forms_settings_nonce' ); ?>

			<h2 class="title"><?php esc_html_e( 'Sending method', 'classicpack' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="classicpack_forms_mailer"><?php esc_html_e( 'Mailer', 'classicpack' ); ?></label>
					</th>
					<td>
						<select name="classicpack_forms_mailer" id="classicpack_forms_mailer">
							<?php foreach ( CLASSICPACK_FORMS_MAILERS as $value => $label ) { ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $mailer, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php } ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="classicpack_forms_smtp2go_key"><?php esc_html_e( 'SMTP2Go API key', 'classicpack' ); ?></label>
					</th>
					<td>
						<input type="text" name="classicpack_forms_smtp2go_key" id="classicpack_forms_smtp2go_key" class="regular-text" value="<?php echo esc_attr( $smtp2go_key ); ?>">
						<p class="description"><?php esc_html_e( 'Required when SMTP2Go is selected as the mailer.', 'classicpack' ); ?></p>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Email log', 'classicpack' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable log', 'classicpack' ); ?></th>
					<td>
						<label>
							<input type="checkbox" class="classicpack-ui-toggle" name="classicpack_forms_log_enabled" value="1" <?php checked( $log_enabled ); ?>>
							<?php esc_html_e( 'Store a copy of every sent email in the database', 'classicpack' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="classicpack_forms_log_max"><?php esc_html_e( 'Max log entries', 'classicpack' ); ?></label>
					</th>
					<td>
						<input type="number" name="classicpack_forms_log_max" id="classicpack_forms_log_max" class="small-text" min="1" value="<?php echo esc_attr( (string) $log_max ); ?>">
						<p class="description"><?php esc_html_e( 'The oldest entries are pruned automatically once this limit is reached. Default: 1000.', 'classicpack' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<hr>

		<h2 class="title"><?php esc_html_e( 'Send a test email', 'classicpack' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: currently active mailer label */
				esc_html__( 'Sends a test message using the currently active mailer (%s) to verify your configuration.', 'classicpack' ),
				'<strong>' . esc_html( CLASSICPACK_FORMS_MAILERS[ (string) get_option( 'classicpack_forms_mailer', 'default' ) ] ?? 'default' ) . '</strong>'
			);
			?>
		</p>

		<form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
			<?php wp_nonce_field( 'classicpack_forms_send_test', 'classicpack_forms_test_nonce' ); ?>
			<div>
				<label for="classicpack_forms_test_to" style="display:block;margin-bottom:4px;font-weight:600;"><?php esc_html_e( 'Send to', 'classicpack' ); ?></label>
				<input type="email" name="classicpack_forms_test_to" id="classicpack_forms_test_to" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'admin_email', '' ) ); ?>" required>
			</div>
			<div>
				<?php submit_button( __( 'Send test email', 'classicpack' ), 'secondary', 'classicpack_forms_send_test_submit', false ); ?>
			</div>
		</form>
	</div>
	<?php
}
