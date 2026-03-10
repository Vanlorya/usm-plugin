<?php
/**
 * Uninstall – Cleans up all USM data on plugin deletion.
 *
 * Runs only when the plugin is deleted via WordPress admin.
 *
 * @package USM
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// ── Drop all 13 custom tables (reverse FK order) ──
$tables = array(
	'usm_parent_students',
	'usm_revenue',
	'usm_attendance',
	'usm_pause_logs',
	'usm_enrollments',
	'usm_sessions',
	'usm_parents',
	'usm_students',
	'usm_packages',
	'usm_courses',
	'usm_coaches',
	'usm_facilities',
	'usm_sports',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
}

// ── Remove custom roles ──
remove_role( 'usm_admin' );
remove_role( 'usm_coach' );
remove_role( 'usm_parent' );

// ── Remove capabilities from administrator ──
$admin = get_role( 'administrator' );
if ( $admin ) {
	$caps = array(
		'manage_usm_students',
		'manage_usm_packages',
		'manage_usm_enrollments',
		'manage_usm_classes',
		'manage_usm_attendance',
		'manage_usm_users',
		'view_usm_reports',
	);
	foreach ( $caps as $cap ) {
		$admin->remove_cap( $cap );
	}
}

// ── Remove plugin options ──
delete_option( 'usm_db_version' );
delete_option( 'usm_settings' );

// ── Remove user meta ──
delete_metadata( 'user', 0, 'usm_focus_mode', '', true );

// ── Clear scheduled cron events ──
wp_clear_scheduled_hook( 'usm_daily_expiry_check' );
