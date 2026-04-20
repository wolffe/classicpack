<?php
/**
 * User Manager module for ClassicPack — optional Users list columns (registration, last login)
 * and members-only content helpers.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/user-restriction.php';

/**
 * Option name for User Manager sub-settings (Users list columns).
 */
function classicpack_user_manager_get_options_name() {
	return 'classicpack_user_manager_options';
}

/**
 * Defaults merged with stored options.
 *
 * @return array{last_login_enabled: int, registration_column_enabled: int}
 */
function classicpack_user_manager_get_options() {
	$defaults = array(
		'last_login_enabled'           => 1,
		'registration_column_enabled'  => 1,
	);
	$stored = get_option( classicpack_user_manager_get_options_name(), array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	return array_merge( $defaults, $stored );
}

/**
 * @param array<string, mixed> $value Raw option value.
 * @return array{last_login_enabled: int, registration_column_enabled: int}
 */
function classicpack_user_manager_sanitize_options( $value ) {
	if ( ! is_array( $value ) ) {
		$value = array();
	}
	return array(
		'last_login_enabled'          => ! empty( $value['last_login_enabled'] ) ? 1 : 0,
		'registration_column_enabled' => ! empty( $value['registration_column_enabled'] ) ? 1 : 0,
	);
}

/**
 * Whether last-login tracking and Users-screen column are enabled.
 *
 * @return bool
 */
function classicpack_user_manager_is_last_login_enabled() {
	$o = classicpack_user_manager_get_options();
	return ! empty( $o['last_login_enabled'] );
}

/**
 * Whether the Registration date column on the Users screen is enabled.
 *
 * @return bool
 */
function classicpack_user_manager_is_registration_column_enabled() {
	$o = classicpack_user_manager_get_options();
	return ! empty( $o['registration_column_enabled'] );
}

/**
 * Register User Manager settings (ClassicPack → User Manager).
 *
 * @return void
 */
function classicpack_user_manager_register_settings() {
	register_setting(
		'classicpack_user_manager',
		classicpack_user_manager_get_options_name(),
		array(
			'type'              => 'array',
			'sanitize_callback' => 'classicpack_user_manager_sanitize_options',
			'default'           => array(),
		)
	);
}

add_action( 'admin_init', 'classicpack_user_manager_register_settings' );

/**
 * Last login as Unix timestamp (classicpack_last_login).
 *
 * @param int $user_id User ID.
 * @return int
 */
function classicpack_user_manager_get_last_login_ts( $user_id ) {
	return (int) get_user_meta( (int) $user_id, 'classicpack_last_login', true );
}

/**
 * Formatted last login for display.
 *
 * @param int $user_id User ID.
 * @return string
 */
function classicpack_user_manager_format_last_login( $user_id ) {
	$ts = classicpack_user_manager_get_last_login_ts( $user_id );
	if ( $ts <= 0 ) {
		return __( 'Never', 'classicpack' );
	}
	$date_format = __( 'j M Y, H:i', 'classicpack' );
	return wp_date( $date_format, $ts );
}

/**
 * Store last login time on successful login.
 *
 * @param string  $user_login Username.
 * @param WP_User $user       User object.
 * @return void
 */
function classicpack_user_manager_on_login( $user_login, $user ) {
	if ( ! classicpack_user_manager_is_last_login_enabled() ) {
		return;
	}
	if ( ! is_object( $user ) || ! isset( $user->ID ) ) {
		return;
	}
	update_user_meta( (int) $user->ID, 'classicpack_last_login', time() );
}

add_action( 'wp_login', 'classicpack_user_manager_on_login', 10, 2 );

/**
 * @param string[] $columns Column headers.
 * @return string[]
 */
function classicpack_user_manager_users_columns( $columns ) {
	if ( classicpack_user_manager_is_registration_column_enabled() ) {
		$columns['classicpack_registration'] = __( 'Registration Date', 'classicpack' );
	}
	if ( classicpack_user_manager_is_last_login_enabled() ) {
		$columns['classicpack_last_login'] = __( 'Last Login', 'classicpack' );
	}
	unset( $columns['posts'] );
	return $columns;
}

add_filter( 'manage_users_columns', 'classicpack_user_manager_users_columns' );

/**
 * @param string $output      Cell output.
 * @param string $column_name Column ID.
 * @param int    $user_id     User ID.
 * @return string
 */
function classicpack_user_manager_users_custom_column( $output, $column_name, $user_id ) {
	$date_format = __( 'j M Y, H:i', 'classicpack' );

	switch ( $column_name ) {
		case 'classicpack_registration':
			if ( ! classicpack_user_manager_is_registration_column_enabled() ) {
				return $output;
			}
			$user = get_userdata( $user_id );
			if ( $user && ! empty( $user->user_registered ) ) {
				return esc_html( wp_date( $date_format, strtotime( $user->user_registered ) ) );
			}
			return esc_html( '—' );

		case 'classicpack_last_login':
			return esc_html( classicpack_user_manager_format_last_login( $user_id ) );

		default:
			return $output;
	}
}

add_filter( 'manage_users_custom_column', 'classicpack_user_manager_users_custom_column', 10, 3 );

/**
 * @param string[] $columns Sortable columns.
 * @return string[]
 */
function classicpack_user_manager_sortable_users_columns( $columns ) {
	if ( classicpack_user_manager_is_last_login_enabled() ) {
		$columns['classicpack_last_login'] = 'classicpack_last_login';
	}
	if ( classicpack_user_manager_is_registration_column_enabled() ) {
		$columns['classicpack_registration'] = 'registered';
	}
	return $columns;
}

add_filter( 'manage_users_sortable_columns', 'classicpack_user_manager_sortable_users_columns' );

/**
 * @param WP_User_Query $query Query.
 * @return void
 */
function classicpack_user_manager_pre_get_users( $query ) {
	if ( ! is_admin() || ! $query instanceof WP_User_Query ) {
		return;
	}

	if ( ! classicpack_user_manager_is_last_login_enabled() ) {
		return;
	}

	if ( empty( $query->query_vars['orderby'] ) || 'classicpack_last_login' !== $query->query_vars['orderby'] ) {
		return;
	}

	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for ordering users by last-login meta.
	$query->query_vars['meta_key'] = 'classicpack_last_login';
	$query->query_vars['orderby']  = 'meta_value_num';
	$query->query_vars['meta_type'] = 'NUMERIC';
}

add_action( 'pre_get_users', 'classicpack_user_manager_pre_get_users' );

/**
 * Info screen under ClassicPack.
 *
 * @return void
 */
function classicpack_user_manager_register_submenu() {
	if ( ! function_exists( 'classicpack_get_menu_slug' ) ) {
		return;
	}
	add_submenu_page(
		classicpack_get_menu_slug(),
		__( 'User Manager', 'classicpack' ),
		__( 'User Manager', 'classicpack' ),
		'list_users',
		'classicpack-user-manager',
		'classicpack_user_manager_render_screen'
	);
}

add_action( 'admin_menu', 'classicpack_user_manager_register_submenu', 12 );

/**
 * @return void
 */
function classicpack_user_manager_render_screen() {
	if ( ! current_user_can( 'list_users' ) ) {
		return;
	}

	$restricted_q = new WP_Query(
		array(
			'post_type'              => array( 'post', 'page' ),
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only screen; small result set.
			'meta_query'             => array(
				array(
					'key'     => CLASSICPACK_USER_RESTRICTION_META_KEY,
					'value'   => 1,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	$restricted = $restricted_q->posts;

	$show_last = classicpack_user_manager_is_last_login_enabled();
	if ( $show_last ) {
		$users = get_users(
			array(
				'number' => -1,
				'fields' => 'all',
			)
		);
		usort(
			$users,
			function ( $a, $b ) {
				$ta = classicpack_user_manager_get_last_login_ts( $a->ID );
				$tb = classicpack_user_manager_get_last_login_ts( $b->ID );
				return $tb <=> $ta;
			}
		);
	} else {
		$users = get_users(
			array(
				'number'  => -1,
				'fields'  => 'all',
				'orderby' => 'registered',
				'order'   => 'DESC',
			)
		);
	}
	$opt_name   = classicpack_user_manager_get_options_name();
	$opts       = classicpack_user_manager_get_options();

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'User Manager', 'classicpack' ) . '</h1>';

	if ( current_user_can( 'manage_options' ) ) {
		echo '<form method="post" action="options.php" class="classicpack-user-manager-settings" style="margin:1em 0 1.25em;">';
		settings_fields( 'classicpack_user_manager' );
		echo '<fieldset class="classicpack-user-manager-settings__fieldset">';
		echo '<p><label for="classicpack-registration-column-enabled">';
		echo '<input type="checkbox" id="classicpack-registration-column-enabled" name="' . esc_attr( $opt_name ) . '[registration_column_enabled]" value="1" ' . checked( ! empty( $opts['registration_column_enabled'] ), true, false ) . ' /> ';
		echo esc_html__( 'Show registration date on the Users screen', 'classicpack' );
		echo '</label></p>';
		echo '<p class="description">' . esc_html__( 'Turn off if your theme or another plugin already lists registration date, to avoid duplicate columns.', 'classicpack' ) . '</p>';
		echo '<p><label for="classicpack-last-login-enabled">';
		echo '<input type="checkbox" id="classicpack-last-login-enabled" name="' . esc_attr( $opt_name ) . '[last_login_enabled]" value="1" ' . checked( ! empty( $opts['last_login_enabled'] ), true, false ) . ' /> ';
		echo esc_html__( 'Track last login and show it on the Users screen', 'classicpack' );
		echo '</label></p>';
		echo '<p class="description">' . esc_html__( 'Turn off if your theme or another plugin already shows last login, to avoid duplicate columns.', 'classicpack' ) . '</p>';
		submit_button( __( 'Save settings', 'classicpack' ), 'secondary', 'submit', false );
		echo '</fieldset>';
		echo '</form>';
	}

	$show_reg = classicpack_user_manager_is_registration_column_enabled();
	if ( $show_reg && $show_last ) {
		echo '<p class="description">' . esc_html__( 'Registration date and last login also appear on the main Users screen when those options are enabled above. Use the editor sidebar on each post or page to restrict content to logged-in visitors.', 'classicpack' ) . '</p>';
	} elseif ( $show_reg ) {
		echo '<p class="description">' . esc_html__( 'Registration date also appears on the main Users screen when that option is enabled above. Use the editor sidebar on each post or page to restrict content to logged-in visitors.', 'classicpack' ) . '</p>';
	} elseif ( $show_last ) {
		echo '<p class="description">' . esc_html__( 'Last login also appears on the main Users screen when that option is enabled above. Use the editor sidebar on each post or page to restrict content to logged-in visitors.', 'classicpack' ) . '</p>';
	} else {
		echo '<p class="description">' . esc_html__( 'Use the editor sidebar on each post or page to restrict content to logged-in visitors.', 'classicpack' ) . '</p>';
	}

	echo '<h2>' . esc_html__( 'Restricted posts & pages', 'classicpack' ) . '</h2>';

	if ( empty( $restricted ) ) {
		echo '<p>' . esc_html__( 'No posts or pages are currently restricted.', 'classicpack' ) . '</p>';
	} else {
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Title', 'classicpack' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'classicpack' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'classicpack' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'classicpack' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $restricted as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$edit_link = get_edit_post_link( $post->ID );
			$type_obj  = get_post_type_object( $post->post_type );
			$type_lbl  = $type_obj ? $type_obj->labels->singular_name : $post->post_type;
			$status    = get_post_status_object( get_post_status( $post ) );
			$status_lbl = $status ? $status->label : get_post_status( $post );
			$date_fmt = __( 'j M Y, H:i', 'classicpack' );
			$date_out = ! empty( $post->post_date ) ? wp_date( $date_fmt, strtotime( $post->post_date ) ) : '—';

			echo '<tr>';
			echo '<td>';
			$title = get_the_title( $post );
			if ( $title === '' ) {
				$title = __( '(No title)', 'classicpack' );
			}
			if ( $edit_link ) {
				echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>';
			} else {
				echo esc_html( $title );
			}
			echo '</td>';
			echo '<td>' . esc_html( $type_lbl ) . '</td>';
			echo '<td>' . esc_html( $status_lbl ) . '</td>';
			echo '<td>' . esc_html( $date_out ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	if ( $show_last ) {
		echo '<h2>' . esc_html__( 'Users by last login', 'classicpack' ) . '</h2>';
	} else {
		echo '<h2>' . esc_html__( 'Users', 'classicpack' ) . '</h2>';
	}

	if ( empty( $users ) ) {
		echo '<p>' . esc_html__( 'No users found.', 'classicpack' ) . '</p>';
	} else {
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'User', 'classicpack' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'classicpack' ) . '</th>';
		if ( $show_last ) {
			echo '<th>' . esc_html__( 'Last login', 'classicpack' ) . '</th>';
		}
		echo '<th>' . esc_html__( 'Registered', 'classicpack' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $users as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}
			$profile = get_edit_user_link( $user->ID );
			echo '<tr>';
			echo '<td>';
			if ( $profile ) {
				echo '<a href="' . esc_url( $profile ) . '">' . esc_html( $user->display_name ) . '</a>';
			} else {
				echo esc_html( $user->display_name );
			}
			echo '</td>';
			echo '<td>' . esc_html( $user->user_email ) . '</td>';
			if ( $show_last ) {
				echo '<td>' . esc_html( classicpack_user_manager_format_last_login( $user->ID ) ) . '</td>';
			}
			$reg = $user->user_registered ? wp_date( __( 'j M Y, H:i', 'classicpack' ), strtotime( $user->user_registered ) ) : '—';
			echo '<td>' . esc_html( $reg ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	echo '</div>';
}
