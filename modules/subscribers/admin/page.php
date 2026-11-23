<?php
/**
 * Subscribers: admin screen (Subscribers, Newsletters and Settings tabs) and settings.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submenu under ClassicPack.
 *
 * @return void
 */
function classicpack_subscribers_admin_menu() {
	add_submenu_page(
		classicpack_get_menu_slug(),
		__( 'Subscribers', 'classicpack' ),
		__( 'Subscribers', 'classicpack' ),
		'manage_options',
		'classicpack-subscribers',
		'classicpack_subscribers_render_page'
	);
}

/**
 * Settings saved through options.php.
 *
 * @return void
 */
function classicpack_subscribers_register_settings() {
	register_setting(
		'classicpack_subscribers',
		'classicpack_subscribers_auto_send',
		[
			'type'              => 'string',
			'sanitize_callback' => static function ( $value ) {
				return $value ? '1' : '0';
			},
			'default'           => '0',
		]
	);
	register_setting(
		'classicpack_subscribers',
		'classicpack_subscribers_from_name',
		[
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		]
	);
	register_setting(
		'classicpack_subscribers',
		'classicpack_subscribers_from_email',
		[
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_email',
			'default'           => '',
		]
	);
	register_setting(
		'classicpack_subscribers',
		'classicpack_subscribers_subject_template',
		[
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '{site_name}: {post_title}',
		]
	);
	register_setting(
		'classicpack_subscribers',
		'classicpack_subscribers_batch_size',
		[
			'type'              => 'integer',
			'sanitize_callback' => static function ( $value ) {
				return max( 1, min( 100, absint( $value ) ) );
			},
			'default'           => 20,
		]
	);
}

/**
 * Admin assets: the screen stylesheet, plus the send button script on the post editor.
 *
 * @param string $hook Current admin page hook.
 * @return void
 */
function classicpack_subscribers_admin_enqueue( $hook ) {
	$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$editing = in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && $screen && $screen->post_type === 'post';

	if ( $hook !== 'classicpack_page_classicpack-subscribers' && ! $editing ) {
		return;
	}

	wp_enqueue_style( 'classicpack-subscribers-admin', CLASSICPACK_SUBSCRIBERS_URL . 'assets/admin.css', [], CLASSICPACK_VERSION );
	if ( $editing ) {
		wp_enqueue_script( 'classicpack-subscribers-admin', CLASSICPACK_SUBSCRIBERS_URL . 'assets/admin.js', [], CLASSICPACK_VERSION, true );
	}
}

/**
 * Notices after a manual send from the post editor.
 *
 * @return void
 */
function classicpack_subscribers_admin_notices() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect query arg; it only selects the notice.
	$notice = isset( $_GET['classicpack_subscribers_notice'] ) ? sanitize_key( wp_unslash( $_GET['classicpack_subscribers_notice'] ) ) : '';
	$map    = [
		'queued'         => [ 'success', __( 'Newsletter queued. Emails will send in the background.', 'classicpack' ) ],
		'no_subscribers' => [ 'error', __( 'There are no confirmed subscribers.', 'classicpack' ) ],
		'already_sent'   => [ 'warning', __( 'A newsletter for this post already exists. Use Send again.', 'classicpack' ) ],
		'invalid_post'   => [ 'error', __( 'Publish the post before sending a newsletter.', 'classicpack' ) ],
		'insert_failed'  => [ 'error', __( 'Could not create the newsletter.', 'classicpack' ) ],
	];

	if ( ! isset( $map[ $notice ] ) || ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	echo '<div class="notice notice-' . esc_attr( $map[ $notice ][0] ) . ' is-dismissible"><p>' . esc_html( $map[ $notice ][1] ) . '</p></div>';
}

/**
 * The Subscribers screen.
 *
 * @return void
 */
function classicpack_subscribers_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tabs = [
		'subscribers' => __( 'Subscribers', 'classicpack' ),
		'newsletters' => __( 'Newsletters', 'classicpack' ),
		'settings'    => __( 'Settings', 'classicpack' ),
	];
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab selection and read-only filters.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'subscribers';
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'subscribers';
	}
	$base = add_query_arg( 'page', 'classicpack-subscribers', admin_url( 'admin.php' ) );
	?>
	<div class="wrap classicpack-subscribers-wrap">
		<h1><?php esc_html_e( 'Subscribers', 'classicpack' ); ?></h1>

		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $label ) { ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base ) ); ?>" class="nav-tab<?php echo $tab === $slug ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php } ?>
		</nav>

		<?php
		if ( $tab === 'settings' ) {
			classicpack_subscribers_render_settings_tab();
		} elseif ( $tab === 'newsletters' ) {
			classicpack_subscribers_render_newsletters_tab( add_query_arg( 'tab', 'newsletters', $base ) );
		} else {
			classicpack_subscribers_render_subscribers_tab( add_query_arg( 'tab', 'subscribers', $base ) );
		}
		?>
	</div>
	<?php
}

/**
 * Subscribers tab: counts, filters and the list.
 *
 * @param string $base_url Tab URL.
 * @return void
 */
function classicpack_subscribers_render_subscribers_tab( $base_url ) {
	global $wpdb;

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
	$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$statuses = [ 'pending', 'subscribed', 'unsubscribed' ];
	$table    = classicpack_subscribers_table();
	$per_page = 20;
	$where    = 'WHERE 1=1';
	$params   = [];

	if ( in_array( $status, $statuses, true ) ) {
		$where   .= ' AND status = %s';
		$params[] = $status;
	}
	if ( $search !== '' ) {
		$where   .= ' AND email LIKE %s';
		$params[] = '%' . $wpdb->esc_like( $search ) . '%';
	}

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name from $wpdb->prefix; the WHERE clause only holds placeholders.
	$total = $params
		? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $params ) )
		: (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	$params[] = $per_page;
	$params[] = ( $page - 1 ) * $per_page;
	$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $params ) );
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	?>
	<div class="classicpack-subscribers-stats">
		<?php foreach ( $statuses as $key ) { ?>
			<div class="classicpack-subscribers-stat"><strong><?php echo esc_html( (string) classicpack_subscribers_count( $key ) ); ?></strong><span><?php echo esc_html( classicpack_subscribers_status_label( $key ) ); ?></span></div>
		<?php } ?>
	</div>

	<form method="get" class="classicpack-subscribers-filters">
		<input type="hidden" name="page" value="classicpack-subscribers" />
		<input type="hidden" name="tab" value="subscribers" />
		<select name="status">
			<option value=""><?php esc_html_e( 'All statuses', 'classicpack' ); ?></option>
			<?php foreach ( $statuses as $key ) { ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( classicpack_subscribers_status_label( $key ) ); ?></option>
			<?php } ?>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search email', 'classicpack' ); ?>" />
		<?php submit_button( __( 'Filter', 'classicpack' ), 'secondary', '', false ); ?>
	</form>

	<p><?php esc_html_e( 'Add the [classicpack_subscribe] shortcode to a page to collect subscribers.', 'classicpack' ); ?></p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Email', 'classicpack' ); ?></th>
				<th><?php esc_html_e( 'Status', 'classicpack' ); ?></th>
				<th><?php esc_html_e( 'Confirmed', 'classicpack' ); ?></th>
				<th><?php esc_html_e( 'Created', 'classicpack' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $rows ) { ?>
				<tr><td colspan="4"><?php esc_html_e( 'No subscribers found.', 'classicpack' ); ?></td></tr>
			<?php } ?>
			<?php foreach ( $rows as $row ) { ?>
				<tr>
					<td><?php echo esc_html( $row->email ); ?></td>
					<td><?php echo esc_html( classicpack_subscribers_status_label( (string) $row->status ) ); ?></td>
					<td><?php echo esc_html( $row->confirmed_at ? $row->confirmed_at : '—' ); ?></td>
					<td><?php echo esc_html( $row->created_at ); ?></td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php
	classicpack_subscribers_render_pagination(
		$total,
		$per_page,
		$page,
		add_query_arg(
			[
				'status' => $status,
				's'      => $search,
			],
			$base_url
		)
	);
}

/**
 * Newsletters tab: list, or one newsletter when ?id= is set.
 *
 * @param string $base_url Tab URL.
 * @return void
 */
function classicpack_subscribers_render_newsletters_tab( $base_url ) {
	global $wpdb;

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only selection.
	$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( $id ) {
		$newsletter = classicpack_subscribers_get_newsletter( $id );
		if ( ! $newsletter ) {
			echo '<p>' . esc_html__( 'Newsletter not found.', 'classicpack' ) . '</p>';
			return;
		}
		$post   = get_post( (int) $newsletter->post_id );
		$counts = classicpack_subscribers_queue_counts( $id );
		?>
		<h2><?php echo esc_html( $post ? get_the_title( $post ) : '#' . (int) $newsletter->post_id ); ?></h2>
		<p><a href="<?php echo esc_url( $base_url ); ?>">&larr; <?php esc_html_e( 'All newsletters', 'classicpack' ); ?></a></p>
		<table class="widefat striped" style="max-width:32rem;">
			<tr><th><?php esc_html_e( 'Subject', 'classicpack' ); ?></th><td><?php echo esc_html( $newsletter->subject ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Status', 'classicpack' ); ?></th><td><?php echo esc_html( classicpack_subscribers_status_label( (string) $newsletter->status ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Recipients', 'classicpack' ); ?></th><td><?php echo esc_html( (string) $counts['total'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Sent', 'classicpack' ); ?></th><td><?php echo esc_html( (string) $counts['sent'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Queued', 'classicpack' ); ?></th><td><?php echo esc_html( (string) $counts['queued'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Failed', 'classicpack' ); ?></th><td><?php echo esc_html( (string) $counts['failed'] ); ?></td></tr>
		</table>
		<?php if ( $post instanceof WP_Post ) { ?>
			<h2><?php esc_html_e( 'Preview email', 'classicpack' ); ?></h2>
			<iframe class="classicpack-subscribers-preview" srcdoc="<?php echo esc_attr( classicpack_subscribers_newsletter_html( $post, 'preview' ) ); ?>"></iframe>
			<?php
		}
		return;
	}

	$table    = classicpack_subscribers_newsletters_table();
	$per_page = 20;
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page ) );
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	?>
	<p><?php esc_html_e( 'Newsletters are sent from the Subscribers box on the post editor, or automatically on publish when that setting is on.', 'classicpack' ); ?></p>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Post', 'classicpack' ); ?></th>
				<th><?php esc_html_e( 'Subject', 'classicpack' ); ?></th>
				<th><?php esc_html_e( 'Sent', 'classicpack' ); ?></th>
				<th><?php esc_html_e( 'Status', 'classicpack' ); ?></th>
				<th><?php esc_html_e( 'Created', 'classicpack' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $rows ) { ?>
				<tr><td colspan="5"><?php esc_html_e( 'No newsletters yet.', 'classicpack' ); ?></td></tr>
			<?php } ?>
			<?php foreach ( $rows as $row ) { ?>
				<?php
				$post   = get_post( (int) $row->post_id );
				$counts = classicpack_subscribers_queue_counts( (int) $row->id );
				?>
				<tr>
					<td><a href="<?php echo esc_url( add_query_arg( 'id', (int) $row->id, $base_url ) ); ?>"><?php echo esc_html( $post ? get_the_title( $post ) : '#' . (int) $row->post_id ); ?></a></td>
					<td><?php echo esc_html( $row->subject ); ?></td>
					<td><?php echo esc_html( $counts['sent'] . ' / ' . $counts['total'] ); ?></td>
					<td><?php echo esc_html( classicpack_subscribers_status_label( (string) $row->status ) ); ?></td>
					<td><?php echo esc_html( $row->created_at ); ?></td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php
	classicpack_subscribers_render_pagination( $total, $per_page, $page, $base_url );
}

/**
 * Settings tab.
 *
 * @return void
 */
function classicpack_subscribers_render_settings_tab() {
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'classicpack_subscribers' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Automatically email new posts', 'classicpack' ); ?></th>
				<td>
					<input type="hidden" name="classicpack_subscribers_auto_send" value="0" />
					<label><input type="checkbox" class="classicpack-ui-toggle" name="classicpack_subscribers_auto_send" value="1" <?php checked( get_option( 'classicpack_subscribers_auto_send' ), '1' ); ?> /> <?php esc_html_e( 'Send a newsletter when a post is first published', 'classicpack' ); ?></label>
					<p class="description"><?php esc_html_e( 'Leave this off to send only from the post editor.', 'classicpack' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="classicpack_subscribers_from_name"><?php esc_html_e( 'From name', 'classicpack' ); ?></label></th>
				<td><input type="text" class="regular-text" id="classicpack_subscribers_from_name" name="classicpack_subscribers_from_name" value="<?php echo esc_attr( classicpack_subscribers_from_name() ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="classicpack_subscribers_from_email"><?php esc_html_e( 'From email', 'classicpack' ); ?></label></th>
				<td><input type="email" class="regular-text" id="classicpack_subscribers_from_email" name="classicpack_subscribers_from_email" value="<?php echo esc_attr( classicpack_subscribers_from_email() ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="classicpack_subscribers_subject_template"><?php esc_html_e( 'Subject', 'classicpack' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="classicpack_subscribers_subject_template" name="classicpack_subscribers_subject_template" value="<?php echo esc_attr( (string) get_option( 'classicpack_subscribers_subject_template', '{site_name}: {post_title}' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Placeholders: {site_name}, {post_title}', 'classicpack' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="classicpack_subscribers_batch_size"><?php esc_html_e( 'Emails per minute', 'classicpack' ); ?></label></th>
				<td>
					<input type="number" min="1" max="100" id="classicpack_subscribers_batch_size" name="classicpack_subscribers_batch_size" value="<?php echo esc_attr( (string) (int) get_option( 'classicpack_subscribers_batch_size', 20 ) ); ?>" />
					<p class="description"><?php esc_html_e( 'The queue runs every minute through WP-Cron. On a low-traffic site, point a server cron job at wp-cron.php.', 'classicpack' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
	<p class="description"><?php esc_html_e( 'Emails are sent with wp_mail(). Configure SMTP separately if you need it.', 'classicpack' ); ?></p>
	<?php
}

/**
 * Simple numbered pagination.
 *
 * @param int    $total    Row count.
 * @param int    $per_page Rows per page.
 * @param int    $page     Current page.
 * @param string $base_url URL to add paged= to.
 * @return void
 */
function classicpack_subscribers_render_pagination( $total, $per_page, $page, $base_url ) {
	$pages = (int) ceil( $total / $per_page );
	if ( $pages < 2 ) {
		return;
	}
	echo '<p class="classicpack-subscribers-pagination">';
	for ( $i = 1; $i <= $pages; $i++ ) {
		if ( $i === $page ) {
			echo '<strong>' . esc_html( (string) $i ) . '</strong> ';
		} else {
			echo '<a href="' . esc_url( add_query_arg( 'paged', $i, $base_url ) ) . '">' . esc_html( (string) $i ) . '</a> ';
		}
	}
	echo '</p>';
}
