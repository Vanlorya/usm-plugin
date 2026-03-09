<?php
/**
 * Plugin Deactivator – clears cron.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USM_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'usm_daily_expiry_check' );
	}
}
