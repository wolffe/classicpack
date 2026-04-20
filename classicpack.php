<?php
/**
 * Plugin Name: ClassicPack
 * Description: A modular toolkit for ClassicPress and WordPress. Enable modules from the ClassicPack admin screen.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires CP: 2.5
 * Requires PHP: 8.5
 * Author: Ciprian Popescu
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: classicpack
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLASSICPACK_VERSION', '0.1.0' );
define( 'CLASSICPACK_FILE', __FILE__ );
define( 'CLASSICPACK_PATH', dirname( __FILE__ ) );

require_once CLASSICPACK_PATH . '/includes/classicpack-modules.php';

register_activation_hook(
	CLASSICPACK_FILE,
	function () {
		if ( ! function_exists( 'classicpress_useronline_activate' ) ) {
			require_once CLASSICPACK_PATH . '/modules/users-online/users-online.php';
		}
		classicpress_useronline_activate();
	}
);

classicpack_modules_init();
