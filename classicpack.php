<?php
/**
 * Plugin Name: ClassicPack
 * Plugin URI: https://getbutterfly.com/classicpress-plugins/classicpack/
 * Description: A modular toolkit for ClassicPress and WordPress. Enable modules from the ClassicPack admin screen.
 * Author: Ciprian Popescu
 * Author URI: https://getbutterfly.com/
 * Version: 0.2.0
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Requires CP: 2.5
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: classicpack
 *
 * ClassicPack
 * Copyright (C) 2026 Ciprian Popescu (getbutterfly@gmail.com)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLASSICPACK_VERSION', '0.2.0' );
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
