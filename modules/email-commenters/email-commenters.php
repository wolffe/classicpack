<?php
/**
 * Email Commenters module for ClassicPack.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'classicpress_email_commenters_register_submenu', 12 );

/**
 * Submenu under ClassicPack.
 *
 * @return void
 */
function classicpress_email_commenters_register_submenu() {
	if ( ! function_exists( 'classicpack_get_menu_slug' ) ) {
		return;
	}
	add_submenu_page(
		classicpack_get_menu_slug(),
		__( 'Email Commenters', 'classicpack' ),
		__( 'Email Commenters', 'classicpack' ),
		'manage_options',
		'classicpress-email-commenters',
		'classicpress_email_commenters_render_admin_page'
	);
}

/**
 * Render admin UI.
 *
 * @return void
 */
function classicpress_email_commenters_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['classicpress_email_commenters_send'] ) && check_admin_referer( 'classicpress_email_commenters_action', 'classicpress_email_commenters_nonce' ) ) {
		$post_id = isset( $_POST['classicpress_email_commenters_post_id'] ) ? (int) $_POST['classicpress_email_commenters_post_id'] : 0;
		$subject = isset( $_POST['classicpress_email_commenters_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['classicpress_email_commenters_subject'] ) ) : '';
		$message = isset( $_POST['classicpress_email_commenters_message'] ) ? wp_kses_post( wp_unslash( $_POST['classicpress_email_commenters_message'] ) ) : '';

		global $wpdb;
		$emails = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT comment_author_email
		 FROM $wpdb->comments
		 WHERE comment_post_ID = %d
		   AND comment_author_email != ''
		   AND comment_approved = '1'",
				$post_id
			)
		);
		if ( ! empty( $emails ) ) {
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			foreach ( $emails as $email ) {
				wp_mail( $email, $subject, $message, $headers );
			}
		}

		echo '<div class="updated"><p>' . esc_html__( 'Emails sent successfully to all commenters.', 'classicpack' ) . '</p></div>';
	}

	if ( isset( $_POST['classicpress_email_commenters_preview'] ) && check_admin_referer( 'classicpress_email_commenters_action', 'classicpress_email_commenters_nonce' ) ) {
		$subject = isset( $_POST['classicpress_email_commenters_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['classicpress_email_commenters_subject'] ) ) : '';
		$message = isset( $_POST['classicpress_email_commenters_message'] ) ? wp_kses_post( wp_unslash( $_POST['classicpress_email_commenters_message'] ) ) : '';

		$admin_email = get_option( 'admin_email' );
		wp_mail( $admin_email, $subject . ' [PREVIEW]', $message, array( 'Content-Type: text/html; charset=UTF-8' ) );

		echo '<div class="updated"><p>' . esc_html(
			sprintf(
				/* translators: %s: admin email */
				__( 'Preview sent to %s.', 'classicpack' ),
				$admin_email
			)
		) . '</p></div>';
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Email Commenters', 'classicpack' ); ?></h1>
		<form method="post">
			<?php wp_nonce_field( 'classicpress_email_commenters_action', 'classicpress_email_commenters_nonce' ); ?>

			<p><label for="classicpress_email_commenters_post_id"><?php esc_html_e( 'Select Post:', 'classicpack' ); ?></label><br>
				<?php
				global $wpdb;
				$posts = get_posts(
					array(
						'numberposts' => -1,
						'post_status' => 'publish',
						'post_type'   => array( 'post', 'page' ),
						'orderby'     => 'title',
						'order'       => 'ASC',
					)
				);
				echo '<select name="classicpress_email_commenters_post_id">';
				foreach ( $posts as $post ) {
					$count = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(DISTINCT comment_author_email)
			 FROM $wpdb->comments
			 WHERE comment_post_ID = %d
			   AND comment_author_email != ''
			   AND comment_approved = '1'",
							$post->ID
						)
					);
					printf(
						'<option value="%d">%s (%d commenters)</option>',
						(int) $post->ID,
						esc_html( $post->post_title ),
						(int) $count
					);
				}
				echo '</select>';
				?>
			</p>

			<p><label for="classicpress_email_commenters_subject"><?php esc_html_e( 'Subject:', 'classicpack' ); ?></label><br>
				<input type="text" id="classicpress_email_commenters_subject" name="classicpress_email_commenters_subject" value="" size="60">
			</p>

			<p><label for="classicpress_email_commenters_message"><?php esc_html_e( 'Message (HTML allowed):', 'classicpack' ); ?></label><br>
				<textarea id="classicpress_email_commenters_message" name="classicpress_email_commenters_message" rows="12" cols="80"></textarea>
			</p>

			<p>
				<input type="submit" name="classicpress_email_commenters_preview" class="button" value="<?php esc_attr_e( 'Send Preview', 'classicpack' ); ?>">
				<input type="submit" name="classicpress_email_commenters_send" class="button-primary" value="<?php esc_attr_e( 'Send to Commenters', 'classicpack' ); ?>">
			</p>
		</form>
	</div>
	<?php
}
