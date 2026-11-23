<?php
/**
 * Subscribers: the post editor box and the manual send handler.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Side box on posts.
 *
 * @return void
 */
function classicpack_subscribers_add_metabox() {
	add_meta_box( 'classicpack_subscribers', __( 'Subscribers', 'classicpack' ), 'classicpack_subscribers_render_metabox', 'post', 'side' );
}

/**
 * Box content: subject, excerpt preview, subscriber count and the send button.
 *
 * @param WP_Post $post Post.
 * @return void
 */
function classicpack_subscribers_render_metabox( $post ) {
	$existing = classicpack_subscribers_get_newsletter_by_post( (int) $post->ID );
	$count    = classicpack_subscribers_count( 'subscribed' );

	echo '<p><label for="classicpack-subscribers-subject">' . esc_html__( 'Subject', 'classicpack' ) . '</label></p>';
	echo '<p><input type="text" id="classicpack-subscribers-subject" class="widefat" value="' . esc_attr( classicpack_subscribers_format_subject( $post ) ) . '" /></p>';
	echo '<p class="description">' . esc_html__( 'Newsletter text', 'classicpack' ) . '</p>';
	echo '<p class="classicpack-subscribers-excerpt">' . esc_html( classicpack_subscribers_post_excerpt( (int) $post->ID ) ) . '</p>';
	/* translators: %d: subscriber count */
	echo '<p>' . esc_html( sprintf( _n( '%d confirmed subscriber', '%d confirmed subscribers', $count, 'classicpack' ), $count ) ) . '</p>';

	if ( $post->post_status !== 'publish' ) {
		echo '<p class="description">' . esc_html__( 'Publish the post before sending.', 'classicpack' ) . '</p>';
		return;
	}

	echo '<p><button type="button" class="button button-primary" id="classicpack-subscribers-send"'
		. ' data-action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"'
		. ' data-post-id="' . esc_attr( (string) $post->ID ) . '"'
		. ' data-nonce="' . esc_attr( wp_create_nonce( 'classicpack_subscribers_send' ) ) . '"'
		. ' data-send-again="' . ( $existing ? '1' : '0' ) . '">'
		. esc_html( $existing ? __( 'Send again', 'classicpack' ) : __( 'Send to subscribers', 'classicpack' ) )
		. '</button></p>';

	if ( $existing ) {
		$url = add_query_arg(
			[
				'page' => 'classicpack-subscribers',
				'tab'  => 'newsletters',
				'id'   => (int) $existing->id,
			],
			admin_url( 'admin.php' )
		);
		echo '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'View last newsletter', 'classicpack' ) . '</a></p>';
	}
}

/**
 * Manual send from the post editor (admin-post.php).
 *
 * @return void
 */
function classicpack_subscribers_handle_send() {
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified just below.

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You cannot send a newsletter for this post.', 'classicpack' ) );
	}
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'classicpack_subscribers_send' ) ) {
		wp_die( esc_html__( 'The send request could not be verified.', 'classicpack' ) );
	}

	$subject    = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
	$send_again = isset( $_POST['send_again'] ) && (string) wp_unslash( $_POST['send_again'] ) === '1';
	$result     = classicpack_subscribers_queue_newsletter( $post_id, $subject, $send_again );
	$notice     = is_wp_error( $result ) ? $result->get_error_code() : 'queued';

	wp_safe_redirect( add_query_arg( 'classicpack_subscribers_notice', $notice, get_edit_post_link( $post_id, 'raw' ) ) );
	exit;
}
