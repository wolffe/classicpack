<?php
/**
 * Admin Login — ClassicPack module.
 *
 * Custom login URL and redirects for wp-login.php and /wp-admin/.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current request is being masked as a non-login page.
 *
 * @var bool
 */
$GLOBALS['classicpack_admin_login_wp_login_php'] = false;

/**
 * Plugin basename for ClassicPack (network activation checks).
 *
 * @return string
 */
function classicpack_admin_login_plugin_basename() {
	return plugin_basename( CLASSICPACK_FILE );
}

/**
 * Whether ClassicPack is active network-wide.
 *
 * @return bool
 */
function classicpack_admin_login_is_network_active() {
	if ( ! is_multisite() ) {
		return false;
	}
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . '/wp-admin/includes/plugin.php';
	}
	return is_plugin_active_for_network( classicpack_admin_login_plugin_basename() );
}

/**
 * @return bool
 */
function classicpack_admin_login_use_trailing_slashes() {
	return '/' === substr( (string) get_option( 'permalink_structure' ), -1, 1 );
}

/**
 * @param string $text Path or URL fragment.
 * @return string
 */
function classicpack_admin_login_user_trailingslashit( $text ) {
	return classicpack_admin_login_use_trailing_slashes() ? trailingslashit( $text ) : untrailingslashit( $text );
}

/**
 * @return void
 */
function classicpack_admin_login_wp_template_loader() {
	global $pagenow;

	$pagenow = 'index.php';

	if ( ! defined( 'WP_USE_THEMES' ) ) {
		define( 'WP_USE_THEMES', true );
	}

	wp();

	if ( isset( $_SERVER['REQUEST_URI'] ) && $_SERVER['REQUEST_URI'] === classicpack_admin_login_user_trailingslashit( str_repeat( '-/', 10 ) ) ) {
		$_SERVER['REQUEST_URI'] = classicpack_admin_login_user_trailingslashit( '/wp-login-php/' );
	}

	require_once ABSPATH . WPINC . '/template-loader.php';

	die;
}

/**
 * Login slug from options.
 *
 * @return string
 */
function classicpack_admin_login_new_login_slug() {
	$slug = get_option( 'classicpack_admin_login_page' );
	if ( $slug ) {
		return $slug;
	}
	if ( is_multisite() && classicpack_admin_login_is_network_active() ) {
		$slug = get_site_option( 'classicpack_admin_login_page', 'login' );
		if ( $slug ) {
			return $slug;
		}
	}
	return 'login';
}

/**
 * @param string|null $scheme URL scheme.
 * @return string
 */
function classicpack_admin_login_new_login_url( $scheme = null ) {
	if ( get_option( 'permalink_structure' ) ) {
		return classicpack_admin_login_user_trailingslashit( home_url( '/', $scheme ) . classicpack_admin_login_new_login_slug() );
	}
	return home_url( '/', $scheme ) . '?' . classicpack_admin_login_new_login_slug();
}

/**
 * First enable: one-time redirect flag (same as standalone plugin activation).
 *
 * @return void
 */
function classicpack_admin_login_maybe_bootstrap() {
	if ( get_option( 'classicpack_admin_login_bootstrapped' ) ) {
		return;
	}
	add_option( 'classicpack_admin_login_redirect', '1' );
	update_option( 'classicpack_admin_login_bootstrapped', '1' );
}

/**
 * @return void
 */
function classicpack_admin_login_wpmu_options() {
	echo '<h3>' . esc_html__( 'Admin Login', 'classicpack' ) . '</h3>';
	echo '<p>' . esc_html__( 'This option allows you to set a networkwide default, which can be overridden by individual sites. Go to each site’s permalink settings to change the URL.', 'classicpack' ) . '</p>';
	echo '<table class="form-table">';
	echo '<tr valign="top">';
	echo '<th scope="row">' . esc_html__( 'Networkwide default', 'classicpack' ) . '</th>';
	echo '<td><input id="classicpack-admin-login-page-input" type="text" name="classicpack_admin_login_page" value="' . esc_attr( get_site_option( 'classicpack_admin_login_page', 'login' ) ) . '"></td>';
	echo '</tr>';
	echo '</table>';
}

/**
 * @return void
 */
function classicpack_admin_login_update_wpmu_options() {
	if ( empty( $_POST['classicpack_admin_login_page'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Network settings form.
	$login_page = sanitize_title_with_dashes( wp_unslash( $_POST['classicpack_admin_login_page'] ) );
	if (
		$login_page &&
		strpos( $login_page, 'wp-login' ) === false &&
		! in_array( $login_page, classicpack_admin_login_forbidden_slugs(), true )
	) {
		update_site_option( 'classicpack_admin_login_page', $login_page );
	}
}

/**
 * @return void
 */
function classicpack_admin_login_admin_init() {
	global $pagenow;

	add_settings_section(
		'classicpack-admin-login-section',
		__( 'Admin Login', 'classicpack' ),
		'classicpack_admin_login_section_desc',
		'permalink'
	);

	add_settings_field(
		'classicpack-admin-login-page',
		'<label for="classicpack-admin-login-page-input">' . esc_html__( 'Login URL', 'classicpack' ) . '</label>',
		'classicpack_admin_login_page_input',
		'permalink',
		'classicpack-admin-login-section'
	);

	add_settings_field(
		'classicpack_admin_login_redirect_field',
		__( 'Redirect URL', 'classicpack' ),
		'classicpack_admin_login_redirect_field',
		'permalink',
		'classicpack-admin-login-section'
	);

	register_setting( 'permalink', 'classicpack_admin_login_page_input' );
	register_setting( 'permalink', 'classicpack_admin_login_redirect_field' );

	if ( current_user_can( 'manage_options' ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-permalink' ) ) {
		if ( isset( $_POST['permalink_structure'] ) && isset( $_POST['classicpack_admin_login_redirect_field'] ) ) {
			$short_domain = sanitize_title_with_dashes( wp_unslash( $_POST['classicpack_admin_login_redirect_field'] ) );
			update_option( 'classicpack_admin_login_redirect_field', $short_domain );
		}

		if ( isset( $_POST['permalink_structure'] ) && isset( $_POST['classicpack_admin_login_page'] ) ) {
			$login_page = sanitize_title_with_dashes( wp_unslash( $_POST['classicpack_admin_login_page'] ) );
			if (
				$login_page &&
				strpos( $login_page, 'wp-login' ) === false &&
				! in_array( $login_page, classicpack_admin_login_forbidden_slugs(), true )
			) {
				if ( is_multisite() && $login_page === get_site_option( 'classicpack_admin_login_page', 'login' ) ) {
					delete_option( 'classicpack_admin_login_page' );
				} else {
					update_option( 'classicpack_admin_login_page', $login_page );
				}
			}
		}

		if ( get_option( 'classicpack_admin_login_redirect' ) ) {
			delete_option( 'classicpack_admin_login_redirect' );

			if ( is_multisite() && is_super_admin() && classicpack_admin_login_is_network_active() ) {
				$redirect = network_admin_url( 'settings.php#classicpack-admin-login-page-input' );
			} else {
				$redirect = admin_url( 'options-permalink.php#classicpack-admin-login-page-input' );
			}

			wp_safe_redirect( $redirect );
			die;
		}
	}
}

/**
 * @return void
 */
function classicpack_admin_login_section_desc() {
	if ( is_multisite() && is_super_admin() && classicpack_admin_login_is_network_active() ) {
		printf(
			/* translators: %s: Network Settings link */
			'<p>' . esc_html__( 'To set a networkwide default, go to %s.', 'classicpack' ) . '</p>',
			'<a href="' . esc_url( network_admin_url( 'settings.php#classicpack-admin-login-page-input' ) ) . '">' . esc_html__( 'Network Settings', 'classicpack' ) . '</a>'
		);
	}
}

/**
 * @return void
 */
function classicpack_admin_login_redirect_field() {
	$value = get_option( 'classicpack_admin_login_redirect_field' );
	echo '<code>' . esc_url( trailingslashit( home_url() ) ) . '</code> <input type="text" value="' . esc_attr( $value ) . '" name="classicpack_admin_login_redirect_field" id="classicpack-admin-login-redirect-field" class="regular-text" /> <code>/</code>';
	echo '<p class="description"><strong>' . esc_html__( 'If you leave the above field empty the plugin will add a redirect to the website homepage.', 'classicpack' ) . '</strong></p>';
}

/**
 * @return void
 */
function classicpack_admin_login_page_input() {
	if ( get_option( 'permalink_structure' ) ) {
		echo '<code>' . esc_url( trailingslashit( home_url() ) ) . '</code> <input id="classicpack-admin-login-page-input" type="text" name="classicpack_admin_login_page" value="' . esc_attr( classicpack_admin_login_new_login_slug() ) . '">';
		if ( classicpack_admin_login_use_trailing_slashes() ) {
			echo ' <code>/</code>';
		}
	} else {
		echo '<code>' . esc_url( trailingslashit( home_url() ) ) . '?</code> <input id="classicpack-admin-login-page-input" type="text" name="classicpack_admin_login_page" value="' . esc_attr( classicpack_admin_login_new_login_slug() ) . '">';
	}
}

/**
 * @return void
 */
function classicpack_admin_login_admin_notices() {
	global $pagenow;

	if ( ! is_network_admin() && 'options-permalink.php' === $pagenow && isset( $_GET['settings-updated'] ) ) {
		$url = classicpack_admin_login_new_login_url();
		printf(
			/* translators: %s: login page link */
			'<div class="updated"><p>' . esc_html__( 'Your login page is now here: %s. Bookmark this page!', 'classicpack' ) . '</p></div>',
			'<strong><a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a></strong>'
		);
	}
}

/**
 * @return void
 */
function classicpack_admin_login_plugins_loaded() {
	global $pagenow;

	if ( ! is_multisite()
		&& ( strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-signup' ) !== false
			|| strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-activate' ) !== false ) ) {

		wp_die( esc_html__( 'This feature is not enabled.', 'classicpack' ) );
	}

	$request = parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );

	if ( ( strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-login.php' ) !== false
		|| ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-login', 'relative' ) ) )
		&& ! is_admin() ) {

		$GLOBALS['classicpack_admin_login_wp_login_php'] = true;

		$_SERVER['REQUEST_URI'] = classicpack_admin_login_user_trailingslashit( '/' . str_repeat( '-/', 10 ) );

		$pagenow = 'index.php';

	} elseif ( ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === home_url( classicpack_admin_login_new_login_slug(), 'relative' ) )
		|| ( ! get_option( 'permalink_structure' )
			&& isset( $_GET[ classicpack_admin_login_new_login_slug() ] )
			&& empty( $_GET[ classicpack_admin_login_new_login_slug() ] ) ) ) {

		$pagenow = 'wp-login.php';

	} elseif ( ( strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-register.php' ) !== false
			|| ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-register', 'relative' ) ) )
		&& ! is_admin() ) {

		$GLOBALS['classicpack_admin_login_wp_login_php'] = true;

		$_SERVER['REQUEST_URI'] = classicpack_admin_login_user_trailingslashit( '/' . str_repeat( '-/', 10 ) );

		$pagenow = 'index.php';
	}
}

/**
 * @return void
 */
function classicpack_admin_login_wp_loaded() {
	global $pagenow;

	if ( is_admin() && ! is_user_logged_in() && ! defined( 'DOING_AJAX' ) ) {
		if ( 'false' === get_option( 'classicpack_admin_login_redirect_field' ) ) {
			wp_safe_redirect( '/' );
		} else {
			wp_safe_redirect( '/' . get_option( 'classicpack_admin_login_redirect_field' ) );
		}
		die();
	}

	$request = parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );

	if (
		'wp-login.php' === $pagenow &&
		isset( $request['path'] ) &&
		$request['path'] !== classicpack_admin_login_user_trailingslashit( $request['path'] ) &&
		get_option( 'permalink_structure' )
	) {
		wp_safe_redirect( classicpack_admin_login_user_trailingslashit( classicpack_admin_login_new_login_url() ) . ( ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . wp_unslash( $_SERVER['QUERY_STRING'] ) : '' ) );
		die;
	} elseif ( ! empty( $GLOBALS['classicpack_admin_login_wp_login_php'] ) ) {
		if (
			( $referer = wp_get_referer() ) &&
			strpos( $referer, 'wp-activate.php' ) !== false &&
			( $referer = parse_url( $referer ) ) &&
			! empty( $referer['query'] )
		) {
			parse_str( $referer['query'], $referer );

			if (
				! empty( $referer['key'] ) &&
				( $result = wpmu_activate_signup( $referer['key'] ) ) &&
				is_wp_error( $result ) && (
					$result->get_error_code() === 'already_active' ||
					$result->get_error_code() === 'blog_taken'
				)
			) {
				wp_safe_redirect( classicpack_admin_login_new_login_url() . ( ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . wp_unslash( $_SERVER['QUERY_STRING'] ) : '' ) );
				die;
			}
		}

		classicpack_admin_login_wp_template_loader();
	} elseif ( 'wp-login.php' === $pagenow ) {
		require_once ABSPATH . 'wp-login.php';
		die;
	}
}

/**
 * @param string $url     Site URL.
 * @param string $path    Path.
 * @param string|null $scheme Scheme.
 * @param int|null $blog_id Blog ID.
 * @return string
 */
function classicpack_admin_login_site_url( $url, $path, $scheme, $blog_id ) {
	unset( $path, $blog_id );
	return classicpack_admin_login_filter_wp_login_php( $url, $scheme );
}

/**
 * @param string $url    Network site URL.
 * @param string $path   Path.
 * @param string|null $scheme Scheme.
 * @return string
 */
function classicpack_admin_login_network_site_url( $url, $path, $scheme ) {
	unset( $path );
	return classicpack_admin_login_filter_wp_login_php( $url, $scheme );
}

/**
 * @param string $location Redirect location.
 * @param int    $status   Status code.
 * @return string
 */
function classicpack_admin_login_wp_redirect( $location, $status ) {
	unset( $status );
	return classicpack_admin_login_filter_wp_login_php( $location );
}

/**
 * @param string      $url    URL to filter.
 * @param string|null $scheme Scheme.
 * @return string
 */
function classicpack_admin_login_filter_wp_login_php( $url, $scheme = null ) {
	$current_url = isset( $_SERVER['PHP_SELF'] ) ? sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) : '';
	if ( is_int( strpos( $url, 'wp-login.php' ) ) || is_int( strpos( $url, 'wp-login' ) ) ) {
		if ( is_ssl() ) {
			$scheme = 'https';
		}
		$args = explode( '?', $url );
		if ( isset( $args[1] ) ) {
			wp_parse_str( $args[1], $args );
			$url = add_query_arg( $args, classicpack_admin_login_new_login_url( $scheme ) );
		} else {
			$url = classicpack_admin_login_new_login_url( $scheme );
		}
	}

	if ( ! is_int( strpos( $current_url, 'wp-admin' ) ) ) {
		return $url;
	}

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return $url;
	}

	if ( ! function_exists( 'is_user_logged_in' ) ) {
		return $url;
	}

	if ( ! is_user_logged_in() ) {
		$redirect_url = get_option( 'classicpack_admin_login_redirect_field' );
		if ( is_null( $redirect_url ) ) {
			$redirect_url = '';
		}
		return '/' . $redirect_url;
	}

	return $url;
}

/**
 * @param string $value Welcome email body.
 * @return string
 */
function classicpack_admin_login_welcome_email( $value ) {
	return str_replace( 'wp-login.php', trailingslashit( get_site_option( 'classicpack_admin_login_page', 'login' ) ), $value );
}

/**
 * @return string[]
 */
function classicpack_admin_login_forbidden_slugs() {
	$wp = new WP();
	return array_merge( $wp->public_query_vars, $wp->private_query_vars );
}

/**
 * @return void
 */
function classicpack_admin_login_init() {
	classicpack_admin_login_maybe_bootstrap();

	add_action( 'admin_init', 'classicpack_admin_login_admin_init' );
	add_action( 'admin_notices', 'classicpack_admin_login_admin_notices' );
	add_action( 'network_admin_notices', 'classicpack_admin_login_admin_notices' );

	if ( is_multisite() && classicpack_admin_login_is_network_active() ) {
		add_action( 'wpmu_options', 'classicpack_admin_login_wpmu_options' );
		add_action( 'update_wpmu_options', 'classicpack_admin_login_update_wpmu_options' );
	}

	add_action( 'wp_loaded', 'classicpack_admin_login_wp_loaded' );

	add_filter( 'site_url', 'classicpack_admin_login_site_url', 10, 4 );
	add_filter( 'network_site_url', 'classicpack_admin_login_network_site_url', 10, 3 );
	add_filter( 'wp_redirect', 'classicpack_admin_login_wp_redirect', 10, 2 );
	add_filter( 'site_option_welcome_email', 'classicpack_admin_login_welcome_email' );

	remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

	if ( did_action( 'plugins_loaded' ) ) {
		classicpack_admin_login_plugins_loaded();
	} else {
		add_action( 'plugins_loaded', 'classicpack_admin_login_plugins_loaded', 1 );
	}
}

classicpack_admin_login_init();
