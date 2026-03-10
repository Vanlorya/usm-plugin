<?php
/**
 * Plugin Activator – creates tables, roles, and cron.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USM_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		self::migrate_sport_to_name();
		self::create_roles();
		self::schedule_cron();
		update_option( 'usm_db_version', USM_DB_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * Migrate sport_id FK to sport_name text field.
	 */
	private static function migrate_sport_to_name() {
		global $wpdb;
		$courses_table = $wpdb->prefix . 'usm_courses';

		// Check if old sport_id column still exists.
		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$courses_table} LIKE 'sport_id'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		if ( empty( $col ) ) {
			return; // Already migrated.
		}

		// Add sport_name column if not exists.
		$has_name = $wpdb->get_results( "SHOW COLUMNS FROM {$courses_table} LIKE 'sport_name'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		if ( empty( $has_name ) ) {
			$wpdb->query( "ALTER TABLE {$courses_table} ADD COLUMN sport_name VARCHAR(100) NULL AFTER id" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		}

		// Copy sport names from sports table.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
			"UPDATE {$courses_table} c
			 JOIN {$wpdb->prefix}usm_sports s ON c.sport_id = s.id
			 SET c.sport_name = s.name"
		);

		// Drop old sport_id column and its index.
		$wpdb->query( "ALTER TABLE {$courses_table} DROP KEY sport_id" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$wpdb->query( "ALTER TABLE {$courses_table} DROP COLUMN sport_id" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
	}

	/**
	 * Create all 13 custom tables in FK-safe order.
	 */
	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. Sports.
		dbDelta( "CREATE TABLE {$prefix}usm_sports (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			description TEXT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) {$charset_collate};" );

		// 2. Facilities.
		dbDelta( "CREATE TABLE {$prefix}usm_facilities (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			address VARCHAR(500) NULL,
			capacity INT UNSIGNED NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) {$charset_collate};" );

		// 3. Coaches.
		dbDelta( "CREATE TABLE {$prefix}usm_coaches (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			wp_user_id BIGINT UNSIGNED NULL DEFAULT 0,
			full_name VARCHAR(255) NOT NULL,
			phone VARCHAR(20) NOT NULL,
			email VARCHAR(255) NULL,
			specialization VARCHAR(255) NULL,
			certifications TEXT NULL,
			hourly_rate DECIMAL(12,2) NULL,
			commission_pct DECIMAL(5,2) NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY wp_user_id (wp_user_id)
		) {$charset_collate};" );

		// 4. Courses.
		dbDelta( "CREATE TABLE {$prefix}usm_courses (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sport_name VARCHAR(100) NULL,
			facility_id BIGINT UNSIGNED NULL,
			coach_id BIGINT UNSIGNED NULL,
			name VARCHAR(255) NOT NULL,
			description TEXT NULL,
			max_students INT UNSIGNED NULL,
			start_date DATE NULL,
			end_date DATE NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY facility_id (facility_id),
			KEY coach_id (coach_id)
		) {$charset_collate};" );

		// 5. Packages.
		dbDelta( "CREATE TABLE {$prefix}usm_packages (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(255) NOT NULL,
			type VARCHAR(10) NOT NULL DEFAULT 'session',
			sessions INT UNSIGNED NULL,
			duration_days INT UNSIGNED NULL,
			price DECIMAL(12,2) NOT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY course_id (course_id)
		) {$charset_collate};" );

		// 6. Students.
		dbDelta( "CREATE TABLE {$prefix}usm_students (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			wp_user_id BIGINT UNSIGNED NULL,
			full_name VARCHAR(255) NOT NULL,
			date_of_birth DATE NULL,
			phone VARCHAR(20) NULL,
			email VARCHAR(255) NULL,
			notes TEXT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY wp_user_id (wp_user_id)
		) {$charset_collate};" );

		// 7. Parents.
		dbDelta( "CREATE TABLE {$prefix}usm_parents (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			wp_user_id BIGINT UNSIGNED NOT NULL,
			full_name VARCHAR(255) NOT NULL,
			phone VARCHAR(20) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY wp_user_id (wp_user_id)
		) {$charset_collate};" );

		// 8. Sessions (scheduled class slots).
		dbDelta( "CREATE TABLE {$prefix}usm_sessions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT UNSIGNED NOT NULL,
			coach_id BIGINT UNSIGNED NULL,
			facility_id BIGINT UNSIGNED NULL,
			session_date DATE NOT NULL,
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY course_id (course_id),
			KEY coach_id (coach_id),
			KEY facility_id (facility_id),
			KEY session_date (session_date)
		) {$charset_collate};" );

		// 9. Enrollments.
		dbDelta( "CREATE TABLE {$prefix}usm_enrollments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			student_id BIGINT UNSIGNED NOT NULL,
			package_id BIGINT UNSIGNED NOT NULL,
			enroll_date DATE NOT NULL,
			start_date DATE NOT NULL,
			end_date DATE NULL,
			sessions_total INT UNSIGNED NOT NULL,
			sessions_remaining INT UNSIGNED NOT NULL,
			amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
			payment_status VARCHAR(10) NOT NULL DEFAULT 'unpaid',
			status VARCHAR(10) NOT NULL DEFAULT 'active',
			pause_used TINYINT(1) NOT NULL DEFAULT 0,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY student_id (student_id),
			KEY package_id (package_id),
			KEY status (status)
		) {$charset_collate};" );

		// 10. Pause logs.
		dbDelta( "CREATE TABLE {$prefix}usm_pause_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			enrollment_id BIGINT UNSIGNED NOT NULL,
			pause_date DATE NOT NULL,
			resume_date DATE NULL,
			days_paused INT UNSIGNED NULL,
			reason TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY enrollment_id (enrollment_id)
		) {$charset_collate};" );

		// 11. Attendance.
		dbDelta( "CREATE TABLE {$prefix}usm_attendance (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id BIGINT UNSIGNED NOT NULL,
			enrollment_id BIGINT UNSIGNED NOT NULL,
			student_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(10) NOT NULL,
			checked_by BIGINT UNSIGNED NOT NULL,
			checked_at DATETIME NOT NULL,
			notes TEXT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY session_enrollment (session_id, enrollment_id),
			KEY session_id (session_id),
			KEY enrollment_id (enrollment_id),
			KEY student_id (student_id)
		) {$charset_collate};" );

		// 12. Revenue.
		dbDelta( "CREATE TABLE {$prefix}usm_revenue (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			enrollment_id BIGINT UNSIGNED NOT NULL,
			amount DECIMAL(12,2) NOT NULL,
			payment_type VARCHAR(10) NOT NULL,
			collected_by BIGINT UNSIGNED NOT NULL,
			collected_at DATETIME NOT NULL,
			notes TEXT NULL,
			PRIMARY KEY (id),
			KEY enrollment_id (enrollment_id)
		) {$charset_collate};" );

		// 13. Parent-Student link (many-to-many).
		dbDelta( "CREATE TABLE {$prefix}usm_parent_students (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			parent_id BIGINT UNSIGNED NOT NULL,
			student_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY parent_student (parent_id, student_id),
			KEY parent_id (parent_id),
			KEY student_id (student_id)
		) {$charset_collate};" );
	}

	/**
	 * Register custom roles and capabilities.
	 */
	private static function create_roles() {
		// USM Admin.
		add_role( 'usm_admin', __( 'USM Admin', 'usm' ), array(
			'read'                    => true,
			'manage_usm_students'     => true,
			'manage_usm_packages'     => true,
			'manage_usm_enrollments'  => true,
			'manage_usm_classes'      => true,
			'manage_usm_attendance'   => true,
			'manage_usm_users'        => true,
			'view_usm_reports'        => true,
		) );

		// USM Coach.
		add_role( 'usm_coach', __( 'USM Coach', 'usm' ), array(
			'read'                    => true,
			'view_assigned_classes'   => true,
			'manage_usm_attendance'   => true,
		) );

		// USM Parent.
		add_role( 'usm_parent', __( 'USM Parent', 'usm' ), array(
			'read'                     => true,
			'view_own_children'        => true,
			'view_attendance_history'  => true,
			'view_remaining_sessions'  => true,
		) );

		// Grant USM capabilities to existing administrator role.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_usm_students' );
			$admin->add_cap( 'manage_usm_packages' );
			$admin->add_cap( 'manage_usm_enrollments' );
			$admin->add_cap( 'manage_usm_classes' );
			$admin->add_cap( 'manage_usm_attendance' );
			$admin->add_cap( 'manage_usm_users' );
			$admin->add_cap( 'view_usm_reports' );
		}
	}

	/**
	 * Schedule daily expiry check cron.
	 */
	private static function schedule_cron() {
		if ( ! wp_next_scheduled( 'usm_daily_expiry_check' ) ) {
			wp_schedule_event( time(), 'daily', 'usm_daily_expiry_check' );
		}
		// Notification cron – runs daily at 7:00 AM local.
		if ( ! wp_next_scheduled( 'usm_daily_notifications' ) ) {
			$tomorrow_7am = strtotime( 'tomorrow 07:00:00', current_time( 'timestamp' ) );
			wp_schedule_event( $tomorrow_7am, 'daily', 'usm_daily_notifications' );
		}
	}
}
