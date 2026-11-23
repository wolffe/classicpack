<?php
/**
 * Subscribers: personal data exporter and eraser (Tools → Export/Erase Personal Data).
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the exporter.
 *
 * @param array<string,array> $exporters Existing exporters.
 * @return array<string,array>
 */
function classicpack_subscribers_register_exporter( $exporters ) {
	$exporters['classicpack-subscribers'] = [
		'exporter_friendly_name' => __( 'ClassicPack Subscribers', 'classicpack' ),
		'callback'               => 'classicpack_subscribers_privacy_exporter',
	];
	return $exporters;
}

/**
 * Register the eraser.
 *
 * @param array<string,array> $erasers Existing erasers.
 * @return array<string,array>
 */
function classicpack_subscribers_register_eraser( $erasers ) {
	$erasers['classicpack-subscribers'] = [
		'eraser_friendly_name' => __( 'ClassicPack Subscribers', 'classicpack' ),
		'callback'             => 'classicpack_subscribers_privacy_eraser',
	];
	return $erasers;
}

/**
 * Export the subscriber record for an email address.
 *
 * @param string $email_address Email address.
 * @return array{data: array, done: bool}
 */
function classicpack_subscribers_privacy_exporter( $email_address ) {
	$subscriber = classicpack_subscribers_get_by_email( $email_address );
	if ( ! $subscriber ) {
		return [
			'data' => [],
			'done' => true,
		];
	}

	return [
		'data' => [
			[
				'group_id'    => 'classicpack-subscribers',
				'group_label' => __( 'ClassicPack Subscribers', 'classicpack' ),
				'item_id'     => 'classicpack-subscriber-' . (int) $subscriber->id,
				'data'        => [
					[
						'name'  => __( 'Email', 'classicpack' ),
						'value' => $subscriber->email,
					],
					[
						'name'  => __( 'Status', 'classicpack' ),
						'value' => $subscriber->status,
					],
					[
						'name'  => __( 'Created', 'classicpack' ),
						'value' => $subscriber->created_at,
					],
					[
						'name'  => __( 'Confirmed', 'classicpack' ),
						'value' => (string) $subscriber->confirmed_at,
					],
					[
						'name'  => __( 'Unsubscribed', 'classicpack' ),
						'value' => (string) $subscriber->unsubscribed_at,
					],
				],
			],
		],
		'done' => true,
	];
}

/**
 * Erase the subscriber record and its queue rows for an email address.
 *
 * @param string $email_address Email address.
 * @return array{items_removed: bool, items_retained: bool, messages: array, done: bool}
 */
function classicpack_subscribers_privacy_eraser( $email_address ) {
	global $wpdb;

	$subscriber = classicpack_subscribers_get_by_email( $email_address );
	if ( $subscriber ) {
		$wpdb->delete( classicpack_subscribers_queue_table(), [ 'subscriber_id' => (int) $subscriber->id ], [ '%d' ] );
		$wpdb->delete( classicpack_subscribers_table(), [ 'id' => (int) $subscriber->id ], [ '%d' ] );
	}

	return [
		'items_removed'  => (bool) $subscriber,
		'items_retained' => false,
		'messages'       => [],
		'done'           => true,
	];
}
