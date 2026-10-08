<?php
/**
 * Lightweight Comments module for ClassicPack.
 *
 * Shortcode-based threaded feedback/comments, independent of native WP comments.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option name storing the notification recipient addresses.
 */
const CLASSICPACK_LIGHTWEIGHT_COMMENTS_NOTIFY_OPTION = 'classicpack_lightweight_comments_notify_emails';

add_action( 'init', 'classicpress_lightweight_comments_shortcode_init' );
add_action( 'wp_enqueue_scripts', 'classicpress_lightweight_comments_enqueue_assets' );
add_action( 'wp_ajax_classicpress_lightweight_comments_submit', 'classicpress_lightweight_comments_submit' );
add_action( 'wp_ajax_nopriv_classicpress_lightweight_comments_submit', 'classicpress_lightweight_comments_submit' );
add_action( 'admin_menu', 'classicpress_lightweight_comments_register_submenu', 12 );
add_action( 'admin_post_classicpress_lightweight_comments_delete', 'classicpress_lightweight_comments_admin_delete' );
add_action( 'admin_post_classicpress_lightweight_comments_save_settings', 'classicpress_lightweight_comments_save_settings' );

/**
 * Create database table.
 *
 * @return void
 */
function classicpress_lightweight_comments_activate() {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'classicpack_comments';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table_name} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		post_id BIGINT UNSIGNED NOT NULL,
		parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		author_name VARCHAR(100) NOT NULL,
		author_email VARCHAR(190) NOT NULL,
		comment_content LONGTEXT NOT NULL,
		user_ip VARCHAR(100) NOT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY post_id (post_id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( $sql );
}

/**
 * Register shortcode.
 *
 * @return void
 */
function classicpress_lightweight_comments_shortcode_init() {
	add_shortcode( 'lightweight_comments', 'classicpress_lightweight_comments_shortcode' );
}

/**
 * Enqueue assets.
 *
 * @return void
 */
function classicpress_lightweight_comments_enqueue_assets() {
	if ( ! is_singular() ) {
		return;
	}

	global $post;

	if ( ! isset( $post->post_content ) || ! has_shortcode( $post->post_content, 'lightweight_comments' ) ) {
		return;
	}

	wp_enqueue_style(
		'classicpack-lightweight-comments',
		plugins_url( 'css/lightweight-comments.css', __FILE__ ),
		[],
		CLASSICPACK_VERSION
	);

	wp_enqueue_script(
		'classicpack-lightweight-comments',
		plugins_url( 'js/lightweight-comments.js', __FILE__ ),
		[],
		CLASSICPACK_VERSION,
		true
	);

	wp_localize_script(
		'classicpack-lightweight-comments',
		'classicpackLightweightComments',
		[
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'postId'  => get_the_ID(),
			'nonce'   => wp_create_nonce( 'classicpack_lightweight_comments_nonce' ),
		]
	);
}

/**
 * Render comments shortcode.
 *
 * @return string
 */
function classicpress_lightweight_comments_shortcode() {
	ob_start();

	?>

	<div class="lwc-comments-wrap">

		<div class="lwc-comments-header">
			<h3 class="lwc-comments-title">
				<?php esc_html_e( 'Your Feedback', 'classicpack' ); ?>
			</h3>

			<button type="button" id="lwc-new-comment" class="lwc-new-comment-button">
				<?php esc_html_e( 'New Comment', 'classicpack' ); ?>
			</button>
		</div>

		<div id="lwc-comments-list">
			<?php echo wp_kses_post( classicpress_lightweight_comments_render_comments( get_the_ID() ) ); ?>
		</div>

		<form id="lwc-comments-form" class="lwc-comments-form">

			<input
				type="text"
				name="lwc_website"
				class="lwc-hidden-field"
				autocomplete="off"
				tabindex="-1"
			>

			<input type="hidden" name="parent_id" id="lwc-parent-id" value="0">

			<div id="lwc-replying-to" class="lwc-replying-to" hidden>
				<span class="lwc-replying-to-text"></span>
				<button type="button" id="lwc-cancel-reply" class="lwc-cancel-reply"><?php esc_html_e( 'Cancel', 'classicpack' ); ?></button>
			</div>

			<p>
				<input
					type="text"
					name="author_name"
					placeholder="<?php esc_attr_e( 'Name', 'classicpack' ); ?>"
					autocomplete="name"
					required
				>
			</p>

			<p>
				<input
					type="email"
					name="author_email"
					placeholder="<?php esc_attr_e( 'Email', 'classicpack' ); ?>"
					autocomplete="email"
					required
				>
				<span class="lwc-field-note"><?php esc_html_e( 'Your email will not be displayed publicly.', 'classicpack' ); ?></span>
			</p>

			<p>
				<textarea
					name="comment_content"
					placeholder="<?php esc_attr_e( 'Write your feedback...', 'classicpack' ); ?>"
					required
				></textarea>
			</p>

			<p>
				<button type="submit" id="lwc-submit-button">
					<?php esc_html_e( 'Post Feedback', 'classicpack' ); ?>
				</button>
			</p>

		</form>

	</div>

	<?php

	return (string) ob_get_clean();
}

/**
 * Render comments.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function classicpress_lightweight_comments_render_comments( $post_id ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'classicpack_comments';

	$comments = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE post_id = %d ORDER BY created_at ASC",
			$post_id
		)
	);

	if ( empty( $comments ) ) {
		return '<p>' . esc_html__( 'No feedback yet.', 'classicpack' ) . '</p>';
	}

	$tree = [];

	foreach ( $comments as $comment ) {
		$tree[ (int) $comment->parent_id ][] = $comment;
	}

	return classicpress_lightweight_comments_build_tree( $tree, 0 );
}

/**
 * Build comments tree.
 *
 * @param array $tree      Tree.
 * @param int   $parent_id Parent ID.
 * @return string
 */
function classicpress_lightweight_comments_build_tree( $tree, $parent_id = 0 ) {
	if ( empty( $tree[ $parent_id ] ) ) {
		return '';
	}

	$output = '<div class="lwc-comment-group">';

	foreach ( $tree[ $parent_id ] as $comment ) {

		$output .= '<div class="lwc-comment">';

		$output .= '<div class="lwc-comment-author">';
		$output .= esc_html( $comment->author_name );
		$output .= '</div>';

		$output .= '<div class="lwc-comment-date">';
		$output .= esc_html(
			wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $comment->created_at )
			)
		);
		$output .= '</div>';

		$output .= '<div class="lwc-comment-content">';
		$output .= wpautop( esc_html( $comment->comment_content ) );
		$output .= '</div>';

		$output .= '<button class="lwc-reply-button" data-comment-id="' . esc_attr( (string) $comment->id ) . '" data-author="' . esc_attr( $comment->author_name ) . '">' . esc_html__( 'Reply', 'classicpack' ) . '</button>';

		$output .= classicpress_lightweight_comments_build_tree( $tree, (int) $comment->id );

		$output .= '</div>';
	}

	$output .= '</div>';

	return $output;
}

/**
 * Submit comment.
 *
 * @return void
 */
function classicpress_lightweight_comments_submit() {
	check_ajax_referer( 'classicpack_lightweight_comments_nonce', 'nonce' );

	// Honeypot: real visitors never fill this hidden field.
	if ( ! empty( $_POST['lwc_website'] ) ) {
		wp_send_json_error();
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

	$parent_id = isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0;

	$author_name = isset( $_POST['author_name'] )
		? sanitize_text_field( wp_unslash( $_POST['author_name'] ) )
		: '';

	$author_email = isset( $_POST['author_email'] )
		? sanitize_email( wp_unslash( $_POST['author_email'] ) )
		: '';

	$comment_content = isset( $_POST['comment_content'] )
		? sanitize_textarea_field( wp_unslash( $_POST['comment_content'] ) )
		: '';

	if (
		empty( $post_id ) ||
		empty( $author_name ) ||
		empty( $author_email ) ||
		empty( $comment_content )
	) {
		wp_send_json_error();
	}

	global $wpdb;

	$table_name = $wpdb->prefix . 'classicpack_comments';

	$wpdb->insert(
		$table_name,
		[
			'post_id'         => $post_id,
			'parent_id'       => $parent_id,
			'author_name'     => $author_name,
			'author_email'    => $author_email,
			'comment_content' => $comment_content,
			'user_ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'created_at'      => current_time( 'mysql' ),
		],
		[ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
	);

	classicpress_lightweight_comments_send_notifications( $post_id, $parent_id, $author_name, $author_email, $comment_content );

	wp_send_json_success(
		[
			'html' => classicpress_lightweight_comments_render_comments( $post_id ),
		]
	);
}

/**
 * Send email notifications when feedback is posted.
 *
 * Notifies the configured recipient(s) of every submission, and notifies the
 * parent comment's author when their comment receives a reply.
 *
 * @param int    $post_id         Post ID.
 * @param int    $parent_id       Parent comment ID (0 for top-level).
 * @param string $author_name     Author name.
 * @param string $author_email    Author email.
 * @param string $comment_content Comment content.
 * @return void
 */
function classicpress_lightweight_comments_send_notifications( $post_id, $parent_id, $author_name, $author_email, $comment_content ) {
	$post_title = get_the_title( $post_id );
	$post_link  = get_permalink( $post_id );
	$admin_link = admin_url( 'admin.php?page=classicpack-lightweight-comments' );

	$notify_emails = classicpress_lightweight_comments_get_notify_emails();

	if ( ! empty( $notify_emails ) ) {
		$notify_subject = sprintf(
			/* translators: %s: post title */
			__( 'New feedback on "%s"', 'classicpack' ),
			$post_title
		);

		$notify_body = implode(
			"\r\n",
			[
				sprintf( 'Author: %s (%s)', $author_name, $author_email ),
				sprintf( 'Post: %s', $post_link ? $post_link : $post_title ),
				'',
				$comment_content,
				'',
				sprintf( 'Manage feedback: %s', $admin_link ),
			]
		);

		wp_mail( $notify_emails, $notify_subject, $notify_body );
	}

	// Notify the parent comment author of a reply.
	if ( $parent_id > 0 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'classicpack_comments';

		$parent = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT author_name, author_email FROM {$table_name} WHERE id = %d",
				$parent_id
			)
		);

		// Skip self-replies and missing/invalid parent emails.
		if (
			$parent
			&& ! empty( $parent->author_email )
			&& is_email( $parent->author_email )
			&& strtolower( $parent->author_email ) !== strtolower( $author_email )
		) {
			$reply_subject = sprintf(
				/* translators: %s: post title */
				__( 'You have a new reply on "%s"', 'classicpack' ),
				$post_title
			);

			$reply_body = implode(
				"\r\n",
				[
					sprintf( 'Hi %s,', $parent->author_name ),
					'',
					sprintf( '%s replied to your comment:', $author_name ),
					'',
					$comment_content,
					'',
					$post_link ? sprintf( 'View the conversation: %s', $post_link ) : '',
				]
			);

			wp_mail( $parent->author_email, $reply_subject, $reply_body );
		}
	}
}

/**
 * Get the list of valid notification recipient email addresses.
 *
 * @return string[]
 */
function classicpress_lightweight_comments_get_notify_emails() {
	$raw = (string) get_option( CLASSICPACK_LIGHTWEIGHT_COMMENTS_NOTIFY_OPTION, '' );

	if ( '' === trim( $raw ) ) {
		return [];
	}

	return classicpress_lightweight_comments_parse_emails( $raw );
}

/**
 * Parse a comma/newline separated string into valid, unique email addresses.
 *
 * @param string $raw Raw textarea value.
 * @return string[]
 */
function classicpress_lightweight_comments_parse_emails( $raw ) {
	$parts  = preg_split( '/[\r\n,]+/', $raw );
	$emails = [];

	foreach ( (array) $parts as $part ) {
		$email = sanitize_email( trim( $part ) );

		if ( $email && is_email( $email ) ) {
			$emails[] = $email;
		}
	}

	return array_values( array_unique( $emails ) );
}

/**
 * Submenu under ClassicPack.
 *
 * @return void
 */
function classicpress_lightweight_comments_register_submenu() {
	if ( ! function_exists( 'classicpack_get_menu_slug' ) ) {
		return;
	}
	add_submenu_page(
		classicpack_get_menu_slug(),
		__( 'Lightweight Comments', 'classicpack' ),
		__( 'Lightweight Comments', 'classicpack' ),
		'manage_options',
		'classicpack-lightweight-comments',
		'classicpress_lightweight_comments_admin_page'
	);
}

/**
 * Handle deletion of a feedback entry from the admin screen.
 *
 * @return void
 */
function classicpress_lightweight_comments_admin_delete() {
	$id = isset( $_GET['feedback_id'] ) ? absint( $_GET['feedback_id'] ) : 0;

	if ( empty( $id ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'classicpack' ) );
	}

	check_admin_referer( 'classicpack_lightweight_comments_delete_' . $id );

	global $wpdb;

	$table_name = $wpdb->prefix . 'classicpack_comments';

	$wpdb->delete( $table_name, [ 'id' => $id ], [ '%d' ] );

	wp_safe_redirect(
		add_query_arg(
			[ 'page' => 'classicpack-lightweight-comments', 'deleted' => '1' ],
			admin_url( 'admin.php' )
		)
	);
	exit;
}

/**
 * Save the notification recipient addresses from the admin screen.
 *
 * @return void
 */
function classicpress_lightweight_comments_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'classicpack' ) );
	}

	check_admin_referer( 'classicpack_lightweight_comments_save_settings' );

	$raw = isset( $_POST['lwc_notify_emails'] )
		? sanitize_textarea_field( wp_unslash( $_POST['lwc_notify_emails'] ) )
		: '';

	$emails = classicpress_lightweight_comments_parse_emails( $raw );

	update_option( CLASSICPACK_LIGHTWEIGHT_COMMENTS_NOTIFY_OPTION, implode( "\n", $emails ) );

	wp_safe_redirect(
		add_query_arg(
			[ 'page' => 'classicpack-lightweight-comments', 'updated' => '1' ],
			admin_url( 'admin.php' )
		)
	);
	exit;
}

/**
 * Render the admin page listing all feedback.
 *
 * @return void
 */
function classicpress_lightweight_comments_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;

	$table_name = $wpdb->prefix . 'classicpack_comments';

	$feedback = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY created_at DESC" );

	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Lightweight Comments', 'classicpack' ); ?></h1>

		<?php if ( isset( $_GET['deleted'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Feedback deleted.', 'classicpack' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Notification settings saved.', 'classicpack' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="lwc-feedback-usage card" style="max-width:600px;padding:12px 20px;margin:20px 0;">
			<h2><?php esc_html_e( 'How to use', 'classicpack' ); ?></h2>

			<p>
				<?php
				printf(
					/* translators: %s: shortcode tag */
					esc_html__( 'Add %s to any post or page to show the feedback form and thread on the front end.', 'classicpack' ),
					'<code>[lightweight_comments]</code>'
				);
				?>
			</p>

			<p class="description">
				<?php esc_html_e( 'Typical use: a password-protected page (e.g. a client proposal, draft, or private preview) where you want visitor feedback without opening up native WordPress comments or exposing the discussion to the public web.', 'classicpack' ); ?>
			</p>
		</div>

		<div class="lwc-feedback-settings card" style="max-width:600px;padding:12px 20px;margin:20px 0;">
			<h2><?php esc_html_e( 'Notification settings', 'classicpack' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="classicpress_lightweight_comments_save_settings">
				<?php wp_nonce_field( 'classicpack_lightweight_comments_save_settings' ); ?>

				<p>
					<label for="lwc_notify_emails">
						<strong><?php esc_html_e( 'Notification email(s)', 'classicpack' ); ?></strong>
					</label>
				</p>

				<p>
					<textarea
						id="lwc_notify_emails"
						name="lwc_notify_emails"
						rows="4"
						class="large-text code"
						placeholder="name@example.com"
					><?php echo esc_textarea( (string) get_option( CLASSICPACK_LIGHTWEIGHT_COMMENTS_NOTIFY_OPTION, '' ) ); ?></textarea>
				</p>

				<p class="description">
					<?php esc_html_e( 'Email address that will be notified of new feedback. Add multiple addresses separated by a comma or a new line. Leave empty to disable admin notifications (reply notifications to commenters still apply).', 'classicpack' ); ?>
				</p>

				<?php submit_button( __( 'Save Settings', 'classicpack' ) ); ?>
			</form>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Author', 'classicpack' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Feedback', 'classicpack' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Post', 'classicpack' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date', 'classicpack' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'classicpack' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $feedback ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No feedback yet.', 'classicpack' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $feedback as $item ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $item->author_name ); ?></strong><br>
								<a href="<?php echo esc_url( 'mailto:' . $item->author_email ); ?>"><?php echo esc_html( $item->author_email ); ?></a>
							</td>
							<td>
								<?php echo esc_html( $item->comment_content ); ?>
								<?php if ( (int) $item->parent_id > 0 ) : ?>
									<br><em>(<?php esc_html_e( 'reply', 'classicpack' ); ?>)</em>
								<?php endif; ?>
							</td>
							<td>
								<?php
								$title = get_the_title( (int) $item->post_id );
								$link  = get_permalink( (int) $item->post_id );

								if ( $link ) {
									echo '<a href="' . esc_url( $link ) . '">' . esc_html( $title ? $title : '#' . $item->post_id ) . '</a>';
								} else {
									echo esc_html( $title ? $title : '#' . $item->post_id );
								}
								?>
							</td>
							<td>
								<?php
								echo esc_html(
									wp_date(
										get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
										strtotime( $item->created_at )
									)
								);
								?>
							</td>
							<td>
								<?php
								$delete_url = wp_nonce_url(
									add_query_arg(
										[
											'action'      => 'classicpress_lightweight_comments_delete',
											'feedback_id' => $item->id,
										],
										admin_url( 'admin-post.php' )
									),
									'classicpack_lightweight_comments_delete_' . $item->id
								);
								?>
								<a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this feedback entry?', 'classicpack' ) ); ?>');"><?php esc_html_e( 'Delete', 'classicpack' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
