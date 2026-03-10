<?php
/**
 * Admin menu registration, enqueue, and page dispatch.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USM_Admin {

	/**
	 * Constructor – register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'usm_daily_expiry_check', array( $this, 'run_expiry_check' ) );
		add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
		add_action( 'wp_ajax_usm_toggle_focus', array( $this, 'ajax_toggle_focus' ) );

		// Keep USM menu expanded on hidden pages.
		add_action( 'admin_head', array( $this, 'fix_hidden_page_menu' ) );
		add_action( 'admin_head', array( $this, 'focus_mode_css' ) );
	}

	/**
	 * Register admin menu and submenu pages.
	 */
	public function register_menus() {
		add_menu_page(
			__( 'USM', 'usm' ),
			__( '🏋️ USM', 'usm' ),
			'manage_usm_students',
			'usm-dashboard',
			array( $this, 'render_page' ),
			'dashicons-groups',
			30
		);

		$submenus = array(
			// ── Tổng quan ──
			array( 'usm-dashboard',    __( '📊 Tổng quan', 'usm' ),        'manage_usm_students' ),
			// ── Người & Đăng ký ──
			array( 'usm-students',     __( '🧑‍🎓 Học viên', 'usm' ),     'manage_usm_students' ),
			array( 'usm-enrollments',  __( '📝 Đăng ký học', 'usm' ),      'manage_usm_enrollments' ),
			// ── Vận hành hàng ngày ──
			array( 'usm-attendance',   __( '📋 Điểm danh', 'usm' ),        'manage_usm_attendance' ),
			array( 'usm-schedule',     __( '📅 Lịch dạy', 'usm' ),         'manage_usm_classes' ),
			// ── Nhân sự & Tài chính ──
			array( 'usm-coaches',      __( '🏃 Huấn luyện viên', 'usm' ),  'manage_usm_students' ),
			array( 'usm-coach-salary', __( '💰 Lương HLV', 'usm' ),        'view_usm_reports' ),
			array( 'usm-reports',      __( '📈 Báo cáo', 'usm' ),          'view_usm_reports' ),
			// ── Hệ thống ──
			array( 'usm-courses',      __( '🛠️ Thiết lập', 'usm' ),      'manage_usm_students' ),
			array( 'usm-users',        __( '👤 Tài khoản', 'usm' ),        'manage_usm_users' ),
			array( 'usm-parent-dashboard', __( '👨‍👧 Phụ huynh', 'usm' ), 'read' ),
			array( 'usm-settings',     __( '⚙️ Cài đặt', 'usm' ),        'manage_options' ),
		);

		foreach ( $submenus as $sub ) {
			add_submenu_page(
				'usm-dashboard',
				$sub[1],
				$sub[1],
				$sub[2],
				$sub[0],
				array( $this, 'render_page' )
			);
		}

		// ── Hidden pages (accessible via URL, not in sidebar) ──
		$hidden_pages = array(
			array( 'usm-packages',   __( 'Gói học', 'usm' ),     'manage_usm_packages' ),
			array( 'usm-facilities', __( 'Cơ sở', 'usm' ),       'manage_usm_students' ),
			array( 'usm-checkin',    __( 'Check-in', 'usm' ),     'manage_usm_attendance' ),
		);

		foreach ( $hidden_pages as $hp ) {
			add_submenu_page(
				null, // null parent = hidden from menu.
				$hp[1],
				$hp[1],
				$hp[2],
				$hp[0],
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * Force USM sidebar menu to stay expanded on hidden pages
	 * and inject menu separator CSS for all USM pages.
	 */
	public function fix_hidden_page_menu() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// ── Menu separators (always on USM pages) ──
		if ( strpos( $page, 'usm' ) === 0 ) {
			?>
			<style>
			/* Menu group separators */
			.toplevel_page_usm-dashboard .wp-submenu a[href*="page=usm-attendance"],
			.toplevel_page_usm-dashboard .wp-submenu a[href*="page=usm-coaches"],
			.toplevel_page_usm-dashboard .wp-submenu a[href*="page=usm-courses"] {
				border-top: 1px solid rgba(240,246,252,0.12) !important;
				margin-top: 2px !important;
				padding-top: 7px !important;
			}
			</style>
			<?php
		}

		// ── Hidden page menu expansion (only for null-parent pages) ──
		$map = array(
			'usm-packages'   => 'usm-courses',
			'usm-facilities' => 'usm-courses',
			'usm-checkin'    => 'usm-attendance',
		);

		if ( ! isset( $map[ $page ] ) ) {
			return;
		}

		$highlight_slug = $map[ $page ];
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function(){
			// Find USM top-level menu and expand it.
			var topMenu = document.querySelector('#adminmenu .toplevel_page_usm-dashboard');
			if (topMenu) {
				topMenu.className = topMenu.className
					.replace('wp-not-current-submenu', 'wp-has-current-submenu wp-menu-open')
					.replace(/\bmenu-top\b/, 'menu-top current');

				// Highlight the correct submenu item.
				var subLinks = topMenu.querySelectorAll('.wp-submenu a');
				subLinks.forEach(function(a) {
					if (a.href.indexOf('page=<?php echo esc_js( $highlight_slug ); ?>') !== -1) {
						a.parentElement.className += ' current';
						a.className += ' current';
						a.setAttribute('aria-current', 'page');
					}
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Render the requested admin page.
	 */
	public function render_page() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'usm-dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$page_map = array(
			'usm-dashboard'    => 'dashboard.php',
			'usm-students'     => 'students.php',
			'usm-facilities'   => 'facilities.php',
			'usm-coaches'      => 'coaches.php',
			'usm-courses'      => 'courses.php',
			'usm-packages'     => 'packages.php',
			'usm-enrollments'  => 'enrollments.php',
			'usm-schedule'     => 'schedule.php',
			'usm-attendance'   => 'attendance.php',
			'usm-coach-salary' => 'coach-salary.php',
			'usm-reports'      => 'reports.php',
			'usm-users'        => 'users.php',
			'usm-parent-dashboard' => 'parent-dashboard.php',
			'usm-checkin'          => 'mobile-checkin.php',
			'usm-settings'         => 'settings.php',
		);

		$file = $page_map[ $page ] ?? 'dashboard.php';
		$path = USM_PLUGIN_DIR . 'admin/pages/' . $file;

		if ( file_exists( $path ) ) {
			include $path;
		} else {
			echo '<div class="wrap"><h1>USM</h1><p>' . esc_html__( 'Trang đang được phát triển.', 'usm' ) . '</p></div>';
		}
	}

	/**
	 * Enqueue admin CSS and JS only on USM pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( strpos( $page, 'usm' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'usm-admin',
			USM_PLUGIN_URL . 'assets/css/usm-admin.css',
			array(),
			USM_VERSION
		);

		wp_enqueue_script(
			'usm-admin',
			USM_PLUGIN_URL . 'assets/js/usm-admin.js',
			array(),
			USM_VERSION,
			true
		);

		wp_localize_script( 'usm-admin', 'usmData', array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'focusNonce' => wp_create_nonce( 'usm_toggle_focus' ),
			'focusMode'  => get_user_meta( get_current_user_id(), 'usm_focus_mode', true ) === '1' ? '1' : '0',
		) );

		// Chart.js for dashboard and reports (local vendor).
		if ( in_array( $page, array( 'usm-dashboard', 'usm-reports' ), true ) ) {
			wp_enqueue_script( 'chartjs', USM_PLUGIN_URL . 'assets/vendor/chart.umd.min.js', array(), '4.4.7', true );
		}

		// QR code generator for schedule (local vendor).
		if ( 'usm-schedule' === $page ) {
			wp_enqueue_script( 'qrcode-gen', USM_PLUGIN_URL . 'assets/vendor/qrcode.min.js', array(), '1.4.4', true );
			wp_enqueue_script( 'usm-schedule-qr', USM_PLUGIN_URL . 'assets/js/usm-schedule-qr.js', array( 'qrcode-gen' ), USM_VERSION, true );
		}
	}

	/**
	 * WP Cron: bulk-expire stale active enrollments.
	 */
	public function run_expiry_check() {
		global $wpdb;
		$table = $wpdb->prefix . 'usm_enrollments';

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, updated_at = %s WHERE status = %s AND end_date IS NOT NULL AND end_date < %s",
				'expired',
				current_time( 'mysql' ),
				'active',
				current_time( 'Y-m-d' )
			)
		);
	}

	/**
	 * Handle CSV export early (before output) via admin_init.
	 */
	public function handle_csv_export() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['export'], $_GET['_wpnonce'] ) || 'csv' !== $_GET['export'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Students CSV.
		if ( 'usm-students' === $page ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_export_students_csv' ) ) {
				return;
			}
			if ( ! current_user_can( 'manage_usm_students' ) ) {
				return;
			}
			global $wpdb;
			$items = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}usm_students WHERE is_active = 1 ORDER BY full_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=usm-students-' . current_time( 'Y-m-d' ) . '.csv' );
			$csv = fopen( 'php://output', 'w' );
			fwrite( $csv, "\xEF\xBB\xBF" );
			fputcsv( $csv, array( 'ID', 'Họ và tên', 'Ngày sinh', 'Số điện thoại', 'Email', 'Ghi chú', 'Ngày tạo' ) );
			foreach ( $items as $row ) {
				fputcsv( $csv, array( $row->id, $row->full_name, $row->date_of_birth, $row->phone, $row->email, $row->notes, $row->created_at ) );
			}
			fclose( $csv );
			exit;
		}

		// Coaches CSV.
		if ( 'usm-coaches' === $page ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_export_coaches_csv' ) ) {
				return;
			}
			global $wpdb;
			$items = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}usm_coaches WHERE is_active = 1 ORDER BY full_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=usm-coaches-' . current_time( 'Y-m-d' ) . '.csv' );
			$csv = fopen( 'php://output', 'w' );
			fwrite( $csv, "\xEF\xBB\xBF" );
			fputcsv( $csv, array( 'ID', 'Họ và tên', 'SĐT', 'Email', 'Chuyên môn', 'Lương/buổi', 'Ngày tạo' ) );
			foreach ( $items as $row ) {
				fputcsv( $csv, array( $row->id, $row->full_name, $row->phone, $row->email, $row->specialty, $row->hourly_rate, $row->created_at ) );
			}
			fclose( $csv );
			exit;
		}

		// Enrollments CSV.
		if ( 'usm-enrollments' === $page ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_export_enrollments_csv' ) ) {
				return;
			}
			global $wpdb;
			$items = $wpdb->get_results(
				"SELECT e.*, s.full_name AS student_name, p.name AS package_name
				 FROM {$wpdb->prefix}usm_enrollments e
				 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
				 JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
				 ORDER BY e.enroll_date DESC"
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=usm-enrollments-' . current_time( 'Y-m-d' ) . '.csv' );
			$csv = fopen( 'php://output', 'w' );
			fwrite( $csv, "\xEF\xBB\xBF" );
			fputcsv( $csv, array( 'ID', 'Học viên', 'Gói học', 'Trạng thái', 'Buổi còn lại', 'Ngày ĐK', 'Hết hạn', 'Đã TT', 'TT' ) );
			foreach ( $items as $row ) {
				fputcsv( $csv, array( $row->id, $row->student_name, $row->package_name, $row->status, $row->sessions_remaining, $row->enroll_date, $row->expiry_date, $row->amount_paid, $row->payment_status ) );
			}
			fclose( $csv );
			exit;
		}

		// Revenue CSV (existing).
		if ( 'usm-reports' !== $page ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_export_csv' ) ) {
			return;
		}
		if ( ! current_user_can( 'view_usm_reports' ) ) {
			return;
		}

		global $wpdb;

		$month       = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : current_time( 'Y-m' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$month_start = "{$month}-01";
		$month_end   = wp_date( 'Y-m-t', strtotime( $month_start ) );

		$revenue_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT r.*, s.full_name AS student_name, p.name AS package_name
			 FROM {$wpdb->prefix}usm_revenue r
			 JOIN {$wpdb->prefix}usm_enrollments e ON r.enrollment_id = e.id
			 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
			 JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
			 WHERE r.collected_at BETWEEN %s AND %s
			 ORDER BY r.collected_at DESC",
			$month_start . ' 00:00:00',
			$month_end . ' 23:59:59'
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=usm-revenue-' . $month . '.csv' );
		$csv = fopen( 'php://output', 'w' );
		fwrite( $csv, "\xEF\xBB\xBF" ); // BOM for Excel UTF-8.
		fputcsv( $csv, array( 'ID', 'Học viên', 'Gói học', 'Số tiền', 'Loại', 'Ngày thu', 'Ghi chú' ) );
		foreach ( $revenue_rows as $r ) {
			fputcsv( $csv, array( $r->id, $r->student_name, $r->package_name, $r->amount, $r->payment_type, $r->collected_at, $r->notes ) );
		}
		fclose( $csv );
		exit;
	}

	/**
	 * AJAX: Toggle focus mode per user.
	 */
	public function ajax_toggle_focus() {
		check_ajax_referer( 'usm_toggle_focus', 'nonce' );

		$user_id = get_current_user_id();
		$current = get_user_meta( $user_id, 'usm_focus_mode', true );
		$new_val = '1' === $current ? '0' : '1';
		update_user_meta( $user_id, 'usm_focus_mode', $new_val );

		wp_send_json_success( array( 'focus' => $new_val ) );
	}

	/**
	 * Inject CSS to hide non-USM menus when focus mode is ON.
	 */
	public function focus_mode_css() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( strpos( $page, 'usm' ) === false ) {
			return;
		}

		$focus = get_user_meta( get_current_user_id(), 'usm_focus_mode', true );
		if ( '1' !== $focus ) {
			return;
		}

		echo '<style id="usm-focus-mode">
			/* Focus Mode: hide non-USM sidebar items */
			#adminmenu li.menu-top:not(.toplevel_page_usm-dashboard):not(.menu-top-last) { display: none !important; }
			#adminmenu li.wp-menu-separator { display: none !important; }
			/* Keep collapse button */
			#collapse-menu { display: block !important; }
			/* Slim admin bar */
			#wp-admin-bar-wp-logo,
			#wp-admin-bar-comments,
			#wp-admin-bar-new-content,
			#wp-admin-bar-updates,
			#wp-admin-bar-edit { display: none !important; }
			/* USM branding in admin bar */
			#wpadminbar .ab-top-menu > li.menupop:first-child > a.ab-item::after {
				content: " | USM Focus";
				color: #72aee6;
				font-weight: 600;
			}
		</style>';
	}
}
