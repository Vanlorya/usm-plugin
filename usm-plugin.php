<?php
/**
 * Plugin Name: Universal Sports Manager
 * Plugin URI:  https://github.com/Vanlorya/usm-plugin
 * Description: Hệ thống quản lý trung tâm thể thao toàn diện — học viên, HLV, khóa học, điểm danh, lịch dạy, báo cáo.
 * Version:     1.3.0
 * Author:      USM Team
 * Author URI:  https://github.com/Vanlorya
 * Text Domain: usm
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package USM
 */

/*
 * Copyright (C) 2026 USM Team
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'USM_VERSION', '1.3.0' );
define( 'USM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'USM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'USM_PLUGIN_FILE', __FILE__ );
define( 'USM_DB_VERSION', '1.0' );

/**
 * Include required files.
 */
require_once USM_PLUGIN_DIR . 'includes/class-usm-activator.php';
require_once USM_PLUGIN_DIR . 'includes/class-usm-deactivator.php';
require_once USM_PLUGIN_DIR . 'includes/class-usm-helpers.php';
require_once USM_PLUGIN_DIR . 'includes/class-usm-notifications.php';

/**
 * GitHub auto-update via Plugin Update Checker.
 */
require_once USM_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$usm_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/Vanlorya/usm-plugin/',
	__FILE__,
	'usm-plugin'
);
$usm_update_checker->setBranch( 'main' );
$usm_update_checker->getVcsApi()->enableReleaseAssets();

/**
 * Plugin activation.
 */
register_activation_hook( USM_PLUGIN_FILE, array( 'USM_Activator', 'activate' ) );

/**
 * Plugin deactivation.
 */
register_deactivation_hook( USM_PLUGIN_FILE, array( 'USM_Deactivator', 'deactivate' ) );

/**
 * Load admin functionality after plugins are loaded.
 */
function usm_init() {
	// UI Polish loads globally (handles login page + admin).
	require_once USM_PLUGIN_DIR . 'includes/class-usm-ui-polish.php';
	new USM_UI_Polish();

	if ( is_admin() ) {
		require_once USM_PLUGIN_DIR . 'admin/class-usm-admin.php';
		new USM_Admin();

		// DB version migration check.
		$installed_ver = get_option( 'usm_db_version', '0' );
		if ( version_compare( $installed_ver, USM_DB_VERSION, '<' ) ) {
			USM_Activator::activate();
		}
	}
}
add_action( 'plugins_loaded', 'usm_init' );

/**
 * Register notification cron hooks.
 */
add_action( 'usm_daily_notifications', array( 'USM_Notifications', 'cron_remind_tomorrow' ) );
add_action( 'usm_daily_notifications', array( 'USM_Notifications', 'cron_check_expiry' ) );
