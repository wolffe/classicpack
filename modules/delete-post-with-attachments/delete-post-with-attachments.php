<?php
/**
 * Delete post with attachments — ClassicPack module (core Media Library only).
 *
 * On permanent post delete, removes child attachments when they are not used
 * as featured image or in content elsewhere. Derived in part from Alsvin’s
 * “Delete Post with Attachments” (GPL-2.0+).
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'before_delete_post', 'classicpack_delete_post_attachments_on_before_delete' );

/**
 * Delete attachments that belong only to this post, or re-parent if used elsewhere.
 *
 * @param int $post_id Post ID being deleted.
 * @return void
 */
function classicpack_delete_post_attachments_on_before_delete( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return;
	}

	$attachments = get_attached_media( '', $post_id );
	if ( empty( $attachments ) ) {
		return;
	}

	foreach ( $attachments as $attachment ) {
		if ( ! $attachment instanceof WP_Post || (int) $attachment->post_parent !== $post_id ) {
			continue;
		}

		$attachment_id = (int) $attachment->ID;

		$thumb_query = new WP_Query(
			array(
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Narrow query; runs on intentional post delete only.
				'meta_key'       => '_thumbnail_id',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => $attachment_id,
				'post_type'      => 'any',
				'fields'         => 'ids',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);

		$attachment_urls = array( wp_get_attachment_url( $attachment_id ) );
		$meta            = wp_get_attachment_metadata( $attachment_id );

		if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			$upload_dir = wp_upload_dir();
			foreach ( $meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) && ! empty( $meta['file'] ) ) {
					$attachment_urls[] = trailingslashit( $upload_dir['baseurl'] ) . dirname( $meta['file'] ) . '/' . $size['file'];
				}
			}
		}

		$content_ids = array();
		foreach ( array_filter( $attachment_urls ) as $url ) {
			$q = new WP_Query(
				array(
					's'              => esc_url_raw( $url ),
					'post_type'      => 'any',
					'fields'         => 'ids',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
				)
			);
			$content_ids = array_merge( $content_ids, $q->posts );
		}

		$usage = array(
			'thumbnail' => array_unique( array_map( 'intval', $thumb_query->posts ) ),
			'content'   => array_unique( array_map( 'intval', $content_ids ) ),
		);

		$used_elsewhere = array_diff(
			array_merge( $usage['content'], $usage['thumbnail'] ),
			array( $post_id )
		);

		if ( ! empty( $used_elsewhere ) ) {
			wp_update_post(
				array(
					'ID'          => $attachment_id,
					'post_parent' => (int) reset( $used_elsewhere ),
				)
			);
			continue;
		}

		wp_delete_attachment( $attachment_id, true );
	}
}
