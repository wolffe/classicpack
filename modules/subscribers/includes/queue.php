<?php
/**
 * Subscribers: the cron queue runner.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-minute schedule for the queue runner.
 *
 * @param array<string,array> $schedules Existing schedules.
 * @return array<string,array>
 */
function classicpack_subscribers_cron_schedules( $schedules ) {
	$schedules['classicpack_minute'] = [
		'interval' => MINUTE_IN_SECONDS,
		'display'  => __( 'Every Minute (ClassicPack)', 'classicpack' ),
	];
	return $schedules;
}

/**
 * Send one batch of queued emails. Failed sends are retried up to three times.
 *
 * @return void
 */
function classicpack_subscribers_process_queue() {
	global $wpdb;

	if ( get_transient( 'classicpack_subscribers_queue_lock' ) ) {
		return;
	}
	set_transient( 'classicpack_subscribers_queue_lock', 1, 2 * MINUTE_IN_SECONDS );

	$queue       = classicpack_subscribers_queue_table();
	$subscribers = classicpack_subscribers_table();
	$newsletters = classicpack_subscribers_newsletters_table();
	$batch       = max( 1, min( 100, (int) get_option( 'classicpack_subscribers_batch_size', 20 ) ) );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$queue} WHERE status = 'queued' ORDER BY id ASC LIMIT %d", $batch ) ) );
	if ( ! $ids ) {
		delete_transient( 'classicpack_subscribers_queue_lock' );
		return;
	}

	// One %d per queued id, so both statements go through prepare().
	$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix; $in holds %d placeholders only.
	$wpdb->query( $wpdb->prepare( "UPDATE {$queue} SET status = 'sending' WHERE id IN ({$in}) AND status = 'queued'", $ids ) );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT q.id, q.newsletter_id, q.attempts, s.email, s.token, s.status AS subscriber_status, n.post_id, n.subject FROM {$queue} q INNER JOIN {$subscribers} s ON s.id = q.subscriber_id INNER JOIN {$newsletters} n ON n.id = q.newsletter_id WHERE q.id IN ({$in}) AND q.status = 'sending'", $ids ) );
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$touched = [];
	foreach ( $rows as $row ) {
		$touched[ (int) $row->newsletter_id ] = true;
		$attempts                             = (int) $row->attempts + 1;
		$post                                 = get_post( (int) $row->post_id );
		$data                                 = [
			'status'   => 'failed',
			'attempts' => $attempts,
		];

		if ( $row->subscriber_status === 'subscribed' && $post instanceof WP_Post ) {
			$sent = classicpack_subscribers_send_mail(
				$row->email,
				(string) $row->subject,
				classicpack_subscribers_newsletter_html( $post, $row->token ),
				[
					'List-Unsubscribe: <' . esc_url_raw( classicpack_subscribers_action_url( 'one-click', $row->token ) ) . '>',
					'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
				]
			);
			if ( $sent ) {
				$data['status']  = 'sent';
				$data['sent_at'] = current_time( 'mysql' );
			} elseif ( $attempts < 3 ) {
				$data['status'] = 'queued';
			}
		}

		$wpdb->update( $queue, $data, [ 'id' => (int) $row->id ], null, [ '%d' ] );
	}

	foreach ( array_keys( $touched ) as $newsletter_id ) {
		classicpack_subscribers_refresh_newsletter_status( $newsletter_id );
	}

	delete_transient( 'classicpack_subscribers_queue_lock' );
}
