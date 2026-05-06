<?php
/**
 * Module registry, admin menu, settings, and loader (procedural).
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option name storing enabled module slugs (array).
 */
function classicpack_get_modules_option_name() {
	return 'classicpack_modules';
}

/**
 * Parent admin menu slug for ClassicPack and submenus.
 *
 * @return string
 */
function classicpack_get_menu_slug() {
	return 'classicpack';
}

/**
 * Registered modules (slug => meta).
 *
 * @return array<string, array{label: string, description: string, file: string}>
 */
function classicpack_get_module_registry() {
	$base = CLASSICPACK_PATH . '/modules/';

	return array(
		'core-redirects-manager' => array(
			'label'       => __( 'Core Redirects Manager', 'classicpack' ),
			'description' => __( 'Lists automatic redirects that ClassicPress and WordPress store when you change a post URL. Remove ones you do not need to tidy SEO and old links.', 'classicpack' ),
			'file'        => $base . 'core-redirects-manager/core-redirects-manager.php',
		),
		'email-commenters'       => array(
			'label'       => __( 'Email Commenters', 'classicpack' ),
			'description' => __( 'Send one email to everyone who commented on a chosen post or page. Handy for updates, thank-yous, or follow-ups.', 'classicpack' ),
			'file'        => $base . 'email-commenters/email-commenters.php',
		),
		'auto-save-images'       => array(
			'label'       => __( 'Auto Save Images', 'classicpack' ),
			'description' => __( 'Saves images from external URLs into your Media Library when you publish. Keeps pages fast and avoids broken images if remote sites go away.', 'classicpack' ),
			'file'        => $base . 'auto-save-images/auto-save-images.php',
		),
		'delete-post-with-attachments' => array(
			'label'       => __( 'Delete Post with Attachments', 'classicpack' ),
			'description' => __( 'When you permanently delete a post or page, removes Media Library files that were uploaded to that post, if they are not used elsewhere (featured image or content).', 'classicpack' ),
			'file'        => $base . 'delete-post-with-attachments/delete-post-with-attachments.php',
		),
		'users-online'           => array(
			'label'       => __( 'Users Online', 'classicpack' ),
			'description' => __( 'Tracks who is browsing your site right now and adds a dashboard summary. Useful for spotting live traffic at a glance.', 'classicpack' ),
			'file'        => $base . 'users-online/users-online.php',
		),
		'user-manager'           => array(
			'label'       => __( 'User Manager', 'classicpack' ),
			'description' => __( 'Optional registration and last-login columns on the Users screen (turn each off if your theme already shows them). You can also hide posts or pages from guests until they sign in.', 'classicpack' ),
			'file'        => $base . 'user-manager/user-manager.php',
		),
		'user-content'           => array(
			'label'       => __( 'User Content Overview', 'classicpack' ),
			'description' => __( 'On a user’s profile, lists what they “own” in the database: counts by post type (including revisions and other CPTs), media, and optional links for WooCommerce customer orders. Helps when reassigning or deleting accounts.', 'classicpack' ),
			'file'        => $base . 'user-content/user-content.php',
		),
		'post-type-switcher'     => array(
			'label'       => __( 'Post type switcher', 'classicpack' ),
			'description' => __( 'On the Classic post editor, switch the item to another public post type. Shows a short warning before you confirm.', 'classicpack' ),
			'file'        => $base . 'post-type-switcher/post-type-switcher.php',
		),
		'duplicate-post'         => array(
			'label'       => __( 'Duplicate post', 'classicpack' ),
			'description' => __( 'Adds a “Duplicate” row action on list screens for public post types. Creates a draft copy with content, meta, terms, and featured image.', 'classicpack' ),
			'file'        => $base . 'duplicate-post/duplicate-post.php',
		),
		'user-avatar'            => array(
			'label'       => __( 'User Avatar', 'classicpack' ),
			'description' => __( 'Let users pick a profile picture from the Media Library on their profile; uses it instead of Gravatar where avatars are shown.', 'classicpack' ),
			'file'        => $base . 'user-avatar/user-avatar.php',
		),
	);
}

/**
 * Per-module admin link: Settings (options-style screens) vs Details (other screens or in-page anchor).
 *
 * @return array<string, array{mode: string, page?: string, anchored?: bool, cap: string}>
 */
function classicpack_get_module_admin_action_config() {
	return array(
		'core-redirects-manager'       => array(
			'mode' => 'details',
			'page' => 'classicpress-redirects',
			'cap'  => 'manage_options',
		),
		'email-commenters'             => array(
			'mode' => 'details',
			'page' => 'classicpress-email-commenters',
			'cap'  => 'manage_options',
		),
		'auto-save-images'             => array(
			'mode' => 'settings',
			'page' => 'classicpress-auto-save-images',
			'cap'  => 'manage_options',
		),
		'users-online'                 => array(
			'mode' => 'details',
			'page' => 'classicpress-usersonline',
			'cap'  => 'manage_options',
		),
		'user-manager'                 => array(
			'mode' => 'settings',
			'page' => 'classicpack-user-manager',
			'cap'  => 'list_users',
		),
		'delete-post-with-attachments' => array(
			'mode'     => 'details',
			'anchored' => true,
			'cap'      => 'manage_options',
		),
		'user-avatar'                  => array(
			'mode'     => 'details',
			'anchored' => true,
			'cap'      => 'manage_options',
		),
	);
}

/**
 * Sanitize saved module list.
 *
 * @param mixed $value Raw option value.
 * @return string[]
 */
function classicpack_sanitize_modules( $value ) {
	$allowed = array_keys( classicpack_get_module_registry() );
	if ( ! is_array( $value ) ) {
		return array();
	}
	$clean = array();
	foreach ( $value as $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( in_array( $slug, $allowed, true ) ) {
			$clean[] = $slug;
		}
	}
	return array_values( array_unique( $clean ) );
}

/**
 * Enabled module slugs.
 *
 * @return string[]
 */
function classicpack_get_enabled_modules() {
	$mods = get_option( classicpack_get_modules_option_name(), array() );
	if ( ! is_array( $mods ) ) {
		return array();
	}
	return classicpack_sanitize_modules( $mods );
}

/**
 * Add Plugins list link to the ClassicPack modules screen.
 *
 * @param string[] $links Existing action links.
 * @return string[]
 */
function classicpack_plugin_action_links( $links ) {
	$url          = esc_url( admin_url( 'admin.php?page=' . classicpack_get_menu_slug() ) );
	$modules_link = '<a href="' . $url . '">' . esc_html__( 'Modules', 'classicpack' ) . '</a>';
	return array_merge( $links, array( $modules_link ) );
}

/**
 * Bootstrap hooks.
 *
 * @return void
 */
function classicpack_modules_init() {
	add_filter( 'plugin_action_links_' . plugin_basename( CLASSICPACK_FILE ), 'classicpack_plugin_action_links' );
	add_action( 'admin_menu', 'classicpack_register_admin_menu', 5 );
	add_action( 'admin_init', 'classicpack_register_settings' );
	add_action( 'admin_enqueue_scripts', 'classicpack_enqueue_modules_screen_assets' );
	add_action( 'plugins_loaded', 'classicpack_load_enabled_modules', 20 );
}

/**
 * Styles for the main ClassicPack screen.
 *
 * @param string $hook_suffix Current admin page hook.
 * @return void
 */
function classicpack_enqueue_modules_screen_assets( $hook_suffix ) {
	if ( 'toplevel_page_classicpack' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'classicpack-modules',
		plugins_url( 'assets/css/classicpack-modules.css', CLASSICPACK_FILE ),
		array(),
		CLASSICPACK_VERSION
	);
}

/**
 * Top-level ClassicPack menu; enabled modules add their own submenus later.
 *
 * @return void
 */
function classicpack_register_admin_menu() {
	$slug = classicpack_get_menu_slug();

	add_menu_page(
		__( 'ClassicPack', 'classicpack' ),
		__( 'ClassicPack', 'classicpack' ),
		'manage_options',
		$slug,
		'classicpack_render_modules_page',
		'dashicons-admin-plugins',
		3
	);

	add_submenu_page(
		$slug,
		__( 'ClassicPack', 'classicpack' ),
		__( 'ClassicPack', 'classicpack' ),
		'manage_options',
		$slug,
		'classicpack_render_modules_page'
	);
}

/**
 * Register plugin settings for the main ClassicPack screen.
 *
 * @return void
 */
function classicpack_register_settings() {
	register_setting(
		'classicpack_settings',
		classicpack_get_modules_option_name(),
		array(
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'classicpack_sanitize_modules',
		)
	);
}

/**
 * ClassicPack (enable/disable modules) screen.
 *
 * @return void
 */
function classicpack_render_modules_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$registry  = classicpack_get_module_registry();
	$groups    = array(
		array(
			'id'    => 'performance-seo',
			'title' => __( 'Performance & SEO', 'classicpack' ),
			'slugs' => array( 'core-redirects-manager' ),
		),
		array(
			'id'    => 'media',
			'title' => __( 'Media', 'classicpack' ),
			'slugs' => array( 'auto-save-images', 'delete-post-with-attachments' ),
		),
		array(
			'id'    => 'content',
			'title' => __( 'Content', 'classicpack' ),
			'slugs' => array( 'post-type-switcher', 'duplicate-post' ),
		),
		array(
			'id'    => 'users',
			'title' => __( 'Users', 'classicpack' ),
			'slugs' => array( 'email-commenters', 'users-online', 'user-manager', 'user-content', 'user-avatar' ),
		),
	);
	$enabled   = array_fill_keys( classicpack_get_enabled_modules(), true );
	$opt       = classicpack_get_modules_option_name();
	$screen_id = 'classicpack-modules';
	?>
	<div class="wrap classicpack-modules-wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Enable the features you need. All modules are off until you turn them on here.', 'classicpack' ); ?>
		</p>

		<form action="options.php" method="post" id="<?php echo esc_attr( $screen_id ); ?>">
			<?php settings_fields( 'classicpack_settings' ); ?>

			<?php foreach ( $groups as $group ) : ?>
				<section
					class="classicpack-module-category"
					aria-labelledby="classicpack-cat-<?php echo esc_attr( $group['id'] ); ?>"
				>
					<h2 class="classicpack-module-category__heading" id="classicpack-cat-<?php echo esc_attr( $group['id'] ); ?>">
						<?php echo esc_html( $group['title'] ); ?>
					</h2>
					<ul class="classicpack-module-list" role="list">
						<?php foreach ( $group['slugs'] as $slug ) : ?>
							<?php
							if ( empty( $registry[ $slug ] ) ) {
								continue;
							}
							$meta           = $registry[ $slug ];
							$on             = ! empty( $enabled[ $slug ] );
							$action_cfgs    = classicpack_get_module_admin_action_config();
							$action_cfg     = isset( $action_cfgs[ $slug ] ) ? $action_cfgs[ $slug ] : null;
							$action_url     = '';
							if ( $action_cfg ) {
								$c = $action_cfg;
								if ( ! empty( $c['anchored'] ) ) {
									$action_url = admin_url( 'admin.php?page=classicpack' ) . '#classicpack-module-anchor-' . sanitize_key( $slug );
								} elseif ( ! empty( $c['page'] ) ) {
									$action_url = admin_url( 'admin.php?page=' . sanitize_key( $c['page'] ) );
								}
							}
							$action_cap_ok  = $action_cfg && current_user_can( $action_cfg['cap'] );
							$action_active  = $action_cfg && $on && $action_cap_ok && $action_url !== '';
							$is_settings    = $action_cfg && isset( $action_cfg['mode'] ) && 'settings' === $action_cfg['mode'];
							$action_label   = $is_settings ? __( 'Settings', 'classicpack' ) : __( 'Details', 'classicpack' );
							?>
							<li
								class="classicpack-module-row<?php echo $on ? ' is-active' : ''; ?>"
								id="classicpack-module-anchor-<?php echo esc_attr( $slug ); ?>"
							>
								<input
									type="checkbox"
									class="classicpack-ui-toggle"
									id="classicpack-module-<?php echo esc_attr( $slug ); ?>"
									name="<?php echo esc_attr( $opt ); ?>[]"
									value="<?php echo esc_attr( $slug ); ?>"
									<?php checked( $on ); ?>
								/>
								<div class="classicpack-module-row__main">
									<label class="screen-reader-text" for="classicpack-module-<?php echo esc_attr( $slug ); ?>">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: module name */
												__( 'Enable module: %s', 'classicpack' ),
												$meta['label']
											)
										);
										?>
									</label>
									<h3 class="classicpack-module-row__title"><?php echo esc_html( $meta['label'] ); ?></h3>
									<p class="classicpack-module-row__desc"><?php echo esc_html( $meta['description'] ); ?></p>
								</div>
								<?php if ( $action_cfg ) : ?>
									<div class="classicpack-module-row__actions">
										<?php if ( $action_active ) : ?>
											<a class="button button-small" href="<?php echo esc_url( $action_url ); ?>">
												<?php echo esc_html( $action_label ); ?>
											</a>
										<?php else : ?>
											<?php
											$inactive_title = ! $on
												? __( 'Enable this module first to open its screen.', 'classicpack' )
												: ( ! $action_cap_ok
													? __( 'You do not have access to this screen.', 'classicpack' )
													: __( 'Unavailable.', 'classicpack' ) );
											?>
											<button
												type="button"
												class="button button-small classicpack-module-row__action--inactive"
												disabled
												title="<?php echo esc_attr( $inactive_title ); ?>"
											><?php echo esc_html( $action_label ); ?></button>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>

			<div class="classicpack-save-sticky">
				<?php submit_button( __( 'Save modules', 'classicpack' ), 'primary large', 'submit', false ); ?>
			</div>
		</form>
		<script>
		(function () {
			var form = document.getElementById( <?php echo wp_json_encode( $screen_id ); ?> );
			if ( ! form ) {
				return;
			}
			form.querySelectorAll( '.classicpack-module-row input.classicpack-ui-toggle' ).forEach( function ( el ) {
				el.addEventListener( 'change', function () {
					var row = el.closest( '.classicpack-module-row' );
					if ( row ) {
						row.classList.toggle( 'is-active', el.checked );
					}
				} );
			} );
		} )();
		</script>
	</div>
	<?php
}

/**
 * Require bootstrap files for enabled modules.
 *
 * @return void
 */
function classicpack_load_enabled_modules() {
	foreach ( classicpack_get_enabled_modules() as $slug ) {
		$registry = classicpack_get_module_registry();
		if ( empty( $registry[ $slug ]['file'] ) ) {
			continue;
		}
		$file = $registry[ $slug ]['file'];
		if ( is_readable( $file ) ) {
			require_once $file;
			if ( 'users-online' === $slug && function_exists( 'classicpress_useronline_bootstrap' ) ) {
				classicpress_useronline_bootstrap();
			}
		}
	}
}
