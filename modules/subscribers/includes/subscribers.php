<?php
/**
 * Subscribers: tables, subscriber records, tokens and public URLs.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscribers table name.
 *
 * @return string
 */
function classicpack_subscribers_table() {
	global $wpdb;
	return $wpdb->prefix . 'classicpack_subscribers';
}

/**
 * Newsletters table name.
 *
 * @return string
 */
function classicpack_subscribers_newsletters_table() {
	global $wpdb;
	return $wpdb->prefix . 'classicpack_subscriber_newsletters';
}

/**
 * Queue table name.
 *
 * @return string
 */
function classicpack_subscribers_queue_table() {
	global $wpdb;
	return $wpdb->prefix . 'classicpack_subscriber_queue';
}

/**
 * Create or upgrade the three tables. Safe to call repeatedly; uses dbDelta.
 *
 * @return void
 */
function classicpack_subscribers_create_tables() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$subscribers     = classicpack_subscribers_table();
	$newsletters     = classicpack_subscribers_newsletters_table();
	$queue           = classicpack_subscribers_queue_table();

	dbDelta(
		"CREATE TABLE {$subscribers} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			token varchar(64) NOT NULL,
			confirmed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			unsubscribed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			UNIQUE KEY token (token),
			KEY status (status)
		) {$charset_collate};"
	);

	dbDelta(
		"CREATE TABLE {$newsletters} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			subject varchar(255) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			created_at datetime NOT NULL,
			sent_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY status (status)
		) {$charset_collate};"
	);

	dbDelta(
		"CREATE TABLE {$queue} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			newsletter_id bigint(20) unsigned NOT NULL,
			subscriber_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			sent_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY newsletter_subscriber (newsletter_id,subscriber_id),
			KEY status (status),
			KEY newsletter_id (newsletter_id)
		) {$charset_collate};"
	);
}

/**
 * Lower-cased, sanitized email.
 *
 * @param string $email Raw email.
 * @return string
 */
function classicpack_subscribers_normalize_email( $email ) {
	return strtolower( sanitize_email( (string) $email ) );
}

/**
 * Subscriber row by email.
 *
 * @param string $email Email address.
 * @return object|null
 */
function classicpack_subscribers_get_by_email( $email ) {
	global $wpdb;

	$table = classicpack_subscribers_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", classicpack_subscribers_normalize_email( $email ) ) );

	return $row ? $row : null;
}

/**
 * Subscriber row by 64-character hex token.
 *
 * @param string $token Token.
 * @return object|null
 */
function classicpack_subscribers_get_by_token( $token ) {
	global $wpdb;

	$token = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $token ) );
	if ( strlen( $token ) !== 64 ) {
		return null;
	}

	$table = classicpack_subscribers_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s LIMIT 1", $token ) );

	return $row ? $row : null;
}

/**
 * Count subscribers, optionally by status.
 *
 * @param string $status pending, subscribed, unsubscribed or '' for all.
 * @return int
 */
function classicpack_subscribers_count( $status = '' ) {
	global $wpdb;

	$table = classicpack_subscribers_table();
	if ( $status === '' ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
}

/**
 * Insert or refresh a pending subscriber and return the row.
 *
 * Already subscribed addresses are returned unchanged. Unsubscribed or pending
 * addresses get a new token so they can confirm again.
 *
 * @param string $email Email address.
 * @return object|null
 */
function classicpack_subscribers_create_pending( $email ) {
	global $wpdb;

	$email = classicpack_subscribers_normalize_email( $email );
	if ( ! is_email( $email ) ) {
		return null;
	}

	$existing = classicpack_subscribers_get_by_email( $email );
	if ( $existing && $existing->status === 'subscribed' ) {
		return $existing;
	}

	$token = bin2hex( random_bytes( 32 ) );
	$table = classicpack_subscribers_table();

	if ( $existing ) {
		$wpdb->update(
			$table,
			[
				'status'          => 'pending',
				'token'           => $token,
				'confirmed_at'    => null,
				'unsubscribed_at' => null,
			],
			[ 'id' => (int) $existing->id ],
			[ '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);
	} else {
		$wpdb->insert(
			$table,
			[
				'email'      => $email,
				'status'     => 'pending',
				'token'      => $token,
				'created_at' => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s' ]
		);
	}

	return classicpack_subscribers_get_by_email( $email );
}

/**
 * Confirm a pending (or returning) subscriber.
 *
 * @param string $token Token.
 * @return object|null
 */
function classicpack_subscribers_confirm( $token ) {
	global $wpdb;

	$subscriber = classicpack_subscribers_get_by_token( $token );
	if ( ! $subscriber ) {
		return null;
	}
	if ( $subscriber->status === 'subscribed' ) {
		return $subscriber;
	}

	$wpdb->update(
		classicpack_subscribers_table(),
		[
			'status'          => 'subscribed',
			'confirmed_at'    => current_time( 'mysql' ),
			'unsubscribed_at' => null,
		],
		[ 'id' => (int) $subscriber->id ],
		[ '%s', '%s', '%s' ],
		[ '%d' ]
	);

	return classicpack_subscribers_get_by_email( $subscriber->email );
}

/**
 * Unsubscribe by token.
 *
 * @param string $token Token.
 * @return object|null
 */
function classicpack_subscribers_unsubscribe( $token ) {
	global $wpdb;

	$subscriber = classicpack_subscribers_get_by_token( $token );
	if ( ! $subscriber ) {
		return null;
	}
	if ( $subscriber->status === 'unsubscribed' ) {
		return $subscriber;
	}

	$wpdb->update(
		classicpack_subscribers_table(),
		[
			'status'          => 'unsubscribed',
			'unsubscribed_at' => current_time( 'mysql' ),
		],
		[ 'id' => (int) $subscriber->id ],
		[ '%s', '%s' ],
		[ '%d' ]
	);

	return classicpack_subscribers_get_by_email( $subscriber->email );
}

/**
 * Public URL for confirm, unsubscribe or one-click, as query args on the home URL.
 *
 * @param string $action confirm, unsubscribe or one-click.
 * @param string $token  Token.
 * @return string
 */
function classicpack_subscribers_action_url( $action, $token ) {
	return add_query_arg(
		[
			'classicpack_subscribe' => sanitize_key( $action ),
			'token'                 => $token,
		],
		home_url( '/' )
	);
}

/**
 * Translated label for a subscriber or newsletter status.
 *
 * @param string $status Status key.
 * @return string
 */
function classicpack_subscribers_status_label( $status ) {
	$labels = [
		'pending'      => __( 'Pending', 'classicpack' ),
		'subscribed'   => __( 'Subscribed', 'classicpack' ),
		'unsubscribed' => __( 'Unsubscribed', 'classicpack' ),
		'queued'       => __( 'Queued', 'classicpack' ),
		'sending'      => __( 'Sending', 'classicpack' ),
		'complete'     => __( 'Complete', 'classicpack' ),
		'sent'         => __( 'Sent', 'classicpack' ),
		'failed'       => __( 'Failed', 'classicpack' ),
	];
	return $labels[ $status ] ?? $status;
}
