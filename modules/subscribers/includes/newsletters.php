<?php
/**
 * Subscribers: newsletter records, queue creation and automatic sending.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsletter row by ID.
 *
 * @param int $newsletter_id Newsletter ID.
 * @return object|null
 */
function classicpack_subscribers_get_newsletter( $newsletter_id ) {
	global $wpdb;

	$table = classicpack_subscribers_newsletters_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $newsletter_id ) );

	return $row ? $row : null;
}

/**
 * Most recent newsletter for a post.
 *
 * @param int $post_id Post ID.
 * @return object|null
 */
function classicpack_subscribers_get_newsletter_by_post( $post_id ) {
	global $wpdb;

	$table = classicpack_subscribers_newsletters_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d ORDER BY id DESC LIMIT 1", $post_id ) );

	return $row ? $row : null;
}

/**
 * Queue counts by status for a newsletter.
 *
 * @param int $newsletter_id Newsletter ID.
 * @return array<string,int> queued, sending, sent, failed, total.
 */
function classicpack_subscribers_queue_counts( $newsletter_id ) {
	global $wpdb;

	$table  = classicpack_subscribers_queue_table();
	$counts = [
		'queued'  => 0,
		'sending' => 0,
		'sent'    => 0,
		'failed'  => 0,
		'total'   => 0,
	];
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS total FROM {$table} WHERE newsletter_id = %d GROUP BY status", $newsletter_id ) );

	foreach ( $rows as $row ) {
		if ( isset( $counts[ $row->status ] ) ) {
			$counts[ $row->status ] = (int) $row->total;
		}
		$counts['total'] += (int) $row->total;
	}

	return $counts;
}

/**
 * Subject line from the settings template.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function classicpack_subscribers_format_subject( $post ) {
	$template = (string) get_option( 'classicpack_subscribers_subject_template', '{site_name}: {post_title}' );
	if ( $template === '' ) {
		$template = '{site_name}: {post_title}';
	}

	return sanitize_text_field(
		strtr(
			$template,
			[
				'{site_name}'  => get_bloginfo( 'name' ),
				'{post_title}' => $post->post_title,
			]
		)
	);
}

/**
 * Create a newsletter for a published post and queue every confirmed subscriber.
 *
 * @param int    $post_id    Post ID.
 * @param string $subject    Optional subject override.
 * @param bool   $send_again Allow a second newsletter for the same post.
 * @return int|WP_Error Newsletter ID or error.
 */
function classicpack_subscribers_queue_newsletter( $post_id, $subject = '', $send_again = false ) {
	global $wpdb;

	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || $post->post_status !== 'publish' || $post->post_type !== 'post' ) {
		return new WP_Error( 'invalid_post', __( 'Publish the post before sending a newsletter.', 'classicpack' ) );
	}
	if ( ! $send_again && classicpack_subscribers_get_newsletter_by_post( $post_id ) ) {
		return new WP_Error( 'already_sent', __( 'A newsletter for this post already exists. Use Send again.', 'classicpack' ) );
	}

	$subscribers_table = classicpack_subscribers_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	$ids = $wpdb->get_col( "SELECT id FROM {$subscribers_table} WHERE status = 'subscribed' ORDER BY id ASC" );
	if ( ! $ids ) {
		return new WP_Error( 'no_subscribers', __( 'There are no confirmed subscribers.', 'classicpack' ) );
	}

	$inserted = $wpdb->insert(
		classicpack_subscribers_newsletters_table(),
		[
			'post_id'    => $post_id,
			'subject'    => $subject !== '' ? $subject : classicpack_subscribers_format_subject( $post ),
			'status'     => 'queued',
			'created_at' => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%s', '%s' ]
	);
	if ( ! $inserted ) {
		return new WP_Error( 'insert_failed', __( 'Could not create the newsletter.', 'classicpack' ) );
	}

	$newsletter_id = (int) $wpdb->insert_id;
	$queue_table   = classicpack_subscribers_queue_table();

	foreach ( array_chunk( array_map( 'intval', $ids ), 200 ) as $chunk ) {
		$placeholders = [];
		$params       = [];
		foreach ( $chunk as $subscriber_id ) {
			$placeholders[] = '( %d, %d )';
			$params[]       = $newsletter_id;
			$params[]       = $subscriber_id;
		}
		$sql = "INSERT INTO {$queue_table} ( newsletter_id, subscriber_id ) VALUES " . implode( ', ', $placeholders );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared just below; the %d placeholders are assembled above, one pair per subscriber.
		$wpdb->query( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- See above.
	}

	return $newsletter_id;
}

/**
 * Recalculate a newsletter's status from its queue.
 *
 * @param int $newsletter_id Newsletter ID.
 * @return void
 */
function classicpack_subscribers_refresh_newsletter_status( $newsletter_id ) {
	global $wpdb;

	$counts = classicpack_subscribers_queue_counts( $newsletter_id );
	$data   = [ 'status' => 'sending' ];

	if ( $counts['queued'] === 0 && $counts['sending'] === 0 ) {
		$data['status']  = $counts['sent'] > 0 ? 'complete' : 'failed';
		$data['sent_at'] = current_time( 'mysql' );
	}

	$wpdb->update( classicpack_subscribers_newsletters_table(), $data, [ 'id' => $newsletter_id ], null, [ '%d' ] );
}

/**
 * Queue a newsletter when a post is first published, if the setting is on.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post.
 * @return void
 */
function classicpack_subscribers_maybe_auto_send( $new_status, $old_status, $post ) {
	if ( (string) get_option( 'classicpack_subscribers_auto_send', '0' ) !== '1' ) {
		return;
	}
	if ( $new_status !== 'publish' || $old_status === 'publish' || $post->post_type !== 'post' ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
		return;
	}
	if ( classicpack_subscribers_get_newsletter_by_post( (int) $post->ID ) ) {
		return;
	}

	classicpack_subscribers_queue_newsletter( (int) $post->ID );
}
