<?php
/**
 * Dashboard – Overview stats + Quick Actions + Focus Mode toggle.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$total_students     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}usm_students WHERE is_active = 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$total_coaches      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}usm_coaches WHERE is_active = 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$total_enrollments  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}usm_enrollments WHERE status = %s", 'active' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$total_courses      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}usm_courses WHERE is_active = 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$low_alert = (int) USM_Helpers::get_setting( 'low_session_alert', 2 );
$low_sessions = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}usm_enrollments WHERE status = %s AND sessions_remaining <= %d AND sessions_remaining > 0",
	'active',
	$low_alert
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$unpaid = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}usm_enrollments WHERE payment_status = %s",
	'unpaid'
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// ── New stats ──────────────────────────────────
$today = current_time( 'Y-m-d' );

// Today's sessions.
$today_sessions = $wpdb->get_results( $wpdb->prepare(
	"SELECT s.*, c.name AS course_name
	 FROM {$wpdb->prefix}usm_sessions s
	 LEFT JOIN {$wpdb->prefix}usm_courses c ON s.course_id = c.id
	 WHERE s.session_date = %s ORDER BY s.start_time",
	$today
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Total revenue (actual collected from usm_revenue).
$total_revenue = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}usm_revenue" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Revenue this month.
$month_start   = wp_date( 'Y-m-01' );
$month_revenue = (float) $wpdb->get_var( $wpdb->prepare(
	"SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}usm_revenue WHERE collected_at >= %s",
	$month_start . ' 00:00:00'
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Attendance rate (30 days).
$att_total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}usm_attendance WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$att_present = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}usm_attendance WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status = %s", 'present' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$att_rate    = $att_total > 0 ? round( ( $att_present / $att_total ) * 100 ) : 0;

// Alerts: low sessions list.
$low_session_list = $wpdb->get_results( $wpdb->prepare(
	"SELECT e.id, e.sessions_remaining, s.full_name AS student_name, p.name AS package_name
	 FROM {$wpdb->prefix}usm_enrollments e
	 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
	 JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
	 WHERE e.status = %s AND e.sessions_remaining <= %d AND e.sessions_remaining > 0
	 ORDER BY e.sessions_remaining ASC LIMIT 10",
	'active', $low_alert
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Alerts: unpaid list.
$unpaid_list = $wpdb->get_results( $wpdb->prepare(
	"SELECT e.id, s.full_name AS student_name, p.name AS package_name, p.price AS pkg_price, e.amount_paid
	 FROM {$wpdb->prefix}usm_enrollments e
	 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
	 JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
	 WHERE e.payment_status IN ('unpaid', 'partial') AND e.status = 'active'
	 ORDER BY (p.price - e.amount_paid) DESC LIMIT 10"
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

// Recent activity (last 5 enrollments).
$recent_enrollments = $wpdb->get_results(
	"SELECT e.enroll_date, e.status, s.full_name AS student_name, p.name AS package_name
	 FROM {$wpdb->prefix}usm_enrollments e
	 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
	 JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
	 ORDER BY e.created_at DESC
	 LIMIT 5"
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Focus mode state.
$focus_mode = get_user_meta( get_current_user_id(), 'usm_focus_mode', true ) === '1';
?>

<div class="wrap usm-wrap">
	<div class="usm-header">
		<h1><?php esc_html_e( '🏋️ USM – Tổng Quan', 'usm' ); ?></h1>
		<button id="usm-focus-toggle" class="button <?php echo $focus_mode ? 'button-primary' : ''; ?>" title="<?php esc_attr_e( 'Thu gọn menu WordPress, chỉ hiện USM', 'usm' ); ?>">
			<?php echo $focus_mode ? '🎯 Focus Mode: BẬT' : '🎯 Focus Mode: TẮT'; ?>
		</button>
	</div>

	<!-- Stats Row 1: Core -->
	<div class="usm-stats">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-students' ) ); ?>" class="usm-stat-card blue usm-stat-link">
			<h3><?php esc_html_e( '🧑‍🎓 Học viên', 'usm' ); ?></h3>
			<div class="usm-stat-value"><?php echo esc_html( $total_students ); ?></div>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments' ) ); ?>" class="usm-stat-card blue usm-stat-link">
			<h3><?php esc_html_e( '📝 Đang học', 'usm' ); ?></h3>
			<div class="usm-stat-value"><?php echo esc_html( $total_enrollments ); ?></div>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule&view=month' ) ); ?>" class="usm-stat-card green usm-stat-link">
			<h3><?php esc_html_e( '📅 Hôm nay', 'usm' ); ?></h3>
			<div class="usm-stat-value"><?php echo esc_html( count( $today_sessions ) ); ?> <small style="font-size:14px;">buổi</small></div>
		</a>
		<div class="usm-stat-card" style="border-left:4px solid #00a32a; cursor:default;">
			<h3><?php esc_html_e( '💰 Doanh thu tháng', 'usm' ); ?></h3>
			<div class="usm-stat-value" style="font-size:18px;"><?php echo esc_html( USM_Helpers::format_vnd( $month_revenue ) ); ?></div>
		</div>
		<div class="usm-stat-card" style="border-left:4px solid <?php echo $att_rate >= 80 ? '#00a32a' : ( $att_rate >= 60 ? '#dba617' : '#d63638' ); ?>; cursor:default;">
			<h3><?php esc_html_e( '📊 Tỷ lệ đi học', 'usm' ); ?></h3>
			<div class="usm-stat-value"><?php echo esc_html( $att_rate ); ?>%</div>
		</div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments&pay=unpaid' ) ); ?>" class="usm-stat-card <?php echo $unpaid > 0 ? 'red' : 'green'; ?> usm-stat-link">
			<h3><?php esc_html_e( '❌ Chưa đóng phí', 'usm' ); ?></h3>
			<div class="usm-stat-value"><?php echo esc_html( $unpaid ); ?></div>
		</a>
	</div>

	<!-- Quick Actions + Recent + Today -->
	<div class="usm-dashboard-grid">
		<div class="usm-quick-actions">
			<h2><?php esc_html_e( '⚡ Thao tác nhanh', 'usm' ); ?></h2>
			<div class="usm-action-buttons">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-students&action=add' ) ); ?>" class="button button-primary">
					<?php esc_html_e( '+ Thêm học viên', 'usm' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments&action=add' ) ); ?>" class="button button-primary">
					<?php esc_html_e( '+ Đăng ký học', 'usm' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-attendance' ) ); ?>" class="button">
					<?php esc_html_e( '📋 Điểm danh', 'usm' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule' ) ); ?>" class="button">
					<?php esc_html_e( '📅 Lịch dạy', 'usm' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-reports' ) ); ?>" class="button">
					<?php esc_html_e( '📊 Báo cáo', 'usm' ); ?>
				</a>
			</div>

			<!-- Today's sessions mini -->
			<?php if ( ! empty( $today_sessions ) ) : ?>
			<h3 style="margin-top:16px;"><?php esc_html_e( '📅 Lịch hôm nay', 'usm' ); ?></h3>
			<div style="font-size:13px;">
				<?php foreach ( $today_sessions as $ts ) : ?>
				<div style="padding:4px 0; border-bottom:1px solid #f0f0f1;">
					<strong><?php echo esc_html( substr( $ts->start_time, 0, 5 ) ); ?></strong>
					<?php echo esc_html( $ts->course_name ); ?>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>

		<!-- Recent Activity -->
		<?php if ( ! empty( $recent_enrollments ) ) : ?>
			<div class="usm-recent-activity">
				<h2><?php esc_html_e( '📝 Hoạt động gần đây', 'usm' ); ?></h2>
				<ul class="usm-activity-list">
					<?php foreach ( $recent_enrollments as $re ) : ?>
						<li>
							<span class="usm-activity-date"><?php echo esc_html( wp_date( 'd/m', strtotime( $re->enroll_date ) ) ); ?></span>
							<strong><?php echo esc_html( $re->student_name ); ?></strong>
							<?php esc_html_e( 'đăng ký', 'usm' ); ?>
							<em><?php echo esc_html( $re->package_name ); ?></em>
							<?php echo wp_kses_post( USM_Helpers::status_badge( $re->status, USM_Helpers::enrollment_status_label( $re->status ) ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>

	<!-- Alerts Section -->
	<?php if ( ! empty( $low_session_list ) || ! empty( $unpaid_list ) ) : ?>
	<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap:16px; margin-top:16px;">
		<?php if ( ! empty( $low_session_list ) ) : ?>
		<div style="background:#fff; border:1px solid #e0e0e0; border-left:4px solid #dba617; border-radius:8px; padding:16px;">
			<h3 style="margin:0 0 10px; font-size:14px; color:#1d2327;">⚠️ <?php esc_html_e( 'Sắp hết buổi', 'usm' ); ?></h3>
			<?php foreach ( $low_session_list as $ls ) : ?>
			<div style="padding:4px 0; border-bottom:1px solid #f5f5f5; font-size:13px; display:flex; justify-content:space-between; align-items:center;">
				<span><strong><?php echo esc_html( $ls->student_name ); ?></strong> — <?php echo esc_html( $ls->package_name ); ?></span>
				<span style="color:#d63638; font-weight:600; white-space:nowrap;">
					<?php echo esc_html( $ls->sessions_remaining ); ?> buổi
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments&action=add&renew_student=' . $ls->id ) ); ?>" style="font-size:11px; margin-left:4px;">🔄</a>
				</span>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $unpaid_list ) ) : ?>
		<div style="background:#fff; border:1px solid #e0e0e0; border-left:4px solid #d63638; border-radius:8px; padding:16px;">
			<h3 style="margin:0 0 10px; font-size:14px; color:#1d2327;">💸 <?php esc_html_e( 'Chờ thanh toán', 'usm' ); ?></h3>
			<?php foreach ( $unpaid_list as $up ) : ?>
			<div style="padding:4px 0; border-bottom:1px solid #f5f5f5; font-size:13px; display:flex; justify-content:space-between; align-items:center;">
				<span><strong><?php echo esc_html( $up->student_name ); ?></strong> — <?php echo esc_html( $up->package_name ); ?></span>
				<span style="color:#d63638; font-weight:600; white-space:nowrap;">
					<?php echo esc_html( USM_Helpers::format_vnd( (float) $up->pkg_price - (float) $up->amount_paid ) ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments&action=payment&id=' . $up->id ) ); ?>" style="font-size:11px; margin-left:4px;">💰</a>
				</span>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- Charts Section -->
	<?php
	// Revenue data (last 6 months) — from actual payments.
	$chart_months  = array();
	$chart_revenue = array();
	$chart_students = array();

	for ( $i = 5; $i >= 0; $i-- ) {
		$m_start = wp_date( 'Y-m-01', strtotime( "-{$i} months" ) );
		$m_end   = wp_date( 'Y-m-t', strtotime( $m_start ) );
		$m_label = wp_date( 'm/Y', strtotime( $m_start ) );

		$chart_months[] = $m_label;

		$rev = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}usm_revenue WHERE collected_at BETWEEN %s AND %s",
			$m_start . ' 00:00:00',
			$m_end . ' 23:59:59'
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$chart_revenue[] = $rev;

		$new_s = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}usm_students WHERE created_at BETWEEN %s AND %s",
			$m_start . ' 00:00:00',
			$m_end . ' 23:59:59'
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$chart_students[] = $new_s;
	}
	?>

	<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap:20px; margin-top:20px;">
		<div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
			<h3 style="margin-top:0; color:#1d2327;">
				<?php esc_html_e( '💰 Doanh thu 6 tháng', 'usm' ); ?>
				<span style="font-size:12px; font-weight:normal; color:#666; float:right;">
					<?php esc_html_e( 'Tổng:', 'usm' ); ?> <strong style="color:#00a32a;"><?php echo esc_html( USM_Helpers::format_vnd( $total_revenue ) ); ?></strong>
				</span>
			</h3>
			<canvas id="usm-revenue-chart" height="200"></canvas>
		</div>
		<div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
			<h3 style="margin-top:0; color:#1d2327;"><?php esc_html_e( '🧑‍🎓 Học viên mới / tháng', 'usm' ); ?></h3>
			<canvas id="usm-students-chart" height="200"></canvas>
		</div>
	</div>

	<?php
	// Enqueue chart JS and pass data.
	wp_enqueue_script( 'usm-dashboard-charts', USM_PLUGIN_URL . 'assets/js/usm-dashboard-charts.js', array( 'chartjs' ), USM_VERSION, true );
	wp_localize_script( 'usm-dashboard-charts', 'usmChartData', array(
		'months'   => $chart_months,
		'revenue'  => $chart_revenue,
		'students' => $chart_students,
	) );
	?>
</div>
