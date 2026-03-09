<?php
/**
 * Reports – Báo cáo doanh thu, điểm danh & KPI.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// ── Month filter ────────────────────────────────
$month = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : current_time( 'Y-m' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$month_start = "{$month}-01";
$month_end   = wp_date( 'Y-m-t', strtotime( $month_start ) );

// ── Revenue this month ──────────────────────────
$total_revenue = (float) $wpdb->get_var( $wpdb->prepare(
	"SELECT COALESCE(SUM(amount_paid), 0) FROM {$wpdb->prefix}usm_enrollments WHERE enroll_date BETWEEN %s AND %s",
	$month_start,
	$month_end
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// ── Enrollment counts by status ─────────────────
$status_counts = $wpdb->get_results(
	"SELECT status, COUNT(*) AS cnt FROM {$wpdb->prefix}usm_enrollments GROUP BY status",
	OBJECT_K
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// ── Attendance this month ───────────────────────
$total_sessions_month = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}usm_sessions WHERE session_date BETWEEN %s AND %s",
	$month_start, $month_end
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$total_attendance = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}usm_attendance a
	 JOIN {$wpdb->prefix}usm_sessions s ON a.session_id = s.id
	 WHERE s.session_date BETWEEN %s AND %s AND a.status = 'present'",
	$month_start, $month_end
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$total_absent = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}usm_attendance a
	 JOIN {$wpdb->prefix}usm_sessions s ON a.session_id = s.id
	 WHERE s.session_date BETWEEN %s AND %s AND a.status = 'absent'",
	$month_start, $month_end
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$attendance_total = $total_attendance + $total_absent;
$attendance_rate = $attendance_total > 0 ? round( ( $total_attendance / $attendance_total ) * 100, 1 ) : 0;

// ── Low sessions alert ──────────────────────────
$low_alert = (int) USM_Helpers::get_setting( 'low_session_alert', 2 );
$low_session_list = $wpdb->get_results( $wpdb->prepare(
	"SELECT e.id, e.sessions_remaining, s.full_name AS student_name, p.name AS package_name
	 FROM {$wpdb->prefix}usm_enrollments e
	 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
	 JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
	 WHERE e.status = %s AND e.sessions_remaining > 0 AND e.sessions_remaining <= %d
	 ORDER BY e.sessions_remaining ASC",
	'active',
	$low_alert
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// ── Top coaches by sessions this month ──────────
$top_coaches = $wpdb->get_results( $wpdb->prepare(
	"SELECT co.full_name, COUNT(*) AS session_count
	 FROM {$wpdb->prefix}usm_sessions s
	 JOIN {$wpdb->prefix}usm_courses c ON s.course_id = c.id
	 JOIN {$wpdb->prefix}usm_coaches co ON COALESCE(s.coach_id, c.coach_id) = co.id
	 WHERE s.session_date BETWEEN %s AND %s
	 GROUP BY co.id
	 ORDER BY session_count DESC
	 LIMIT 5",
	$month_start, $month_end
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// ── 6-month revenue trend ───────────────────────
$chart_months  = array();
$chart_revenue = array();
for ( $i = 5; $i >= 0; $i-- ) {
	$m_start = wp_date( 'Y-m-01', strtotime( "-{$i} months" ) );
	$m_end   = wp_date( 'Y-m-t', strtotime( $m_start ) );
	$chart_months[] = wp_date( 'm/Y', strtotime( $m_start ) );
	$chart_revenue[] = (float) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(SUM(amount_paid), 0) FROM {$wpdb->prefix}usm_enrollments WHERE enroll_date BETWEEN %s AND %s",
		$m_start, $m_end
	) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// CSV export is handled in class-usm-admin.php via admin_init hook.
?>

<div class="wrap usm-wrap">
	<div class="usm-header">
		<h1><?php esc_html_e( '📈 Báo cáo & Thống kê', 'usm' ); ?></h1>
		<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-reports&month=' . $month . '&export=csv' ), 'usm_export_csv' ) ); ?>" class="button">
			<?php esc_html_e( '📥 Xuất CSV', 'usm' ); ?>
		</a>
	</div>

	<!-- Month quick filters -->
	<div style="margin-bottom:16px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
		<form method="get" style="display:flex; align-items:center; gap:8px;">
			<input type="hidden" name="page" value="usm-reports">
			<input type="month" name="month" value="<?php echo esc_attr( $month ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'Xem', 'usm' ); ?></button>
		</form>
		<?php
		$prev_month = wp_date( 'Y-m', strtotime( $month_start . ' -1 month' ) );
		$next_month = wp_date( 'Y-m', strtotime( $month_start . ' +1 month' ) );
		$this_month = current_time( 'Y-m' );
		?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-reports&month=' . $prev_month ) ); ?>" class="button">← Tháng trước</a>
		<?php if ( $month !== $this_month ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-reports&month=' . $this_month ) ); ?>" class="button button-primary">Tháng này</a>
		<?php endif; ?>
		<?php if ( $month < $this_month ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-reports&month=' . $next_month ) ); ?>" class="button">Tháng sau →</a>
		<?php endif; ?>
	</div>

	<!-- KPI Cards -->
	<div class="usm-stats">
		<div class="usm-stat-card green">
			<h3><?php echo esc_html( sprintf( 'Doanh thu %s', wp_date( 'm/Y', strtotime( $month_start ) ) ) ); ?></h3>
			<div class="usm-stat-value"><?php echo esc_html( USM_Helpers::format_vnd( $total_revenue ) ); ?></div>
		</div>

		<div class="usm-stat-card blue">
			<h3><?php esc_html_e( 'Buổi dạy tháng này', 'usm' ); ?></h3>
			<div class="usm-stat-value"><?php echo esc_html( $total_sessions_month ); ?></div>
		</div>

		<div class="usm-stat-card <?php echo $attendance_rate >= 80 ? 'green' : ( $attendance_rate >= 60 ? 'orange' : 'red' ); ?>">
			<h3><?php esc_html_e( 'Tỷ lệ điểm danh', 'usm' ); ?></h3>
			<div class="usm-stat-value"><?php echo esc_html( $attendance_rate ); ?>%</div>
		</div>

		<div class="usm-stat-card blue">
			<h3><?php esc_html_e( 'Đang học', 'usm' ); ?></h3>
			<div class="usm-stat-value"><?php echo esc_html( $status_counts['active']->cnt ?? 0 ); ?></div>
		</div>

		<div class="usm-stat-card orange">
			<h3><?php esc_html_e( 'Tạm dừng', 'usm' ); ?></h3>
			<div class="usm-stat-value"><?php echo esc_html( $status_counts['paused']->cnt ?? 0 ); ?></div>
		</div>

		<div class="usm-stat-card red">
			<h3><?php esc_html_e( 'Hết hạn', 'usm' ); ?></h3>
			<div class="usm-stat-value"><?php echo esc_html( $status_counts['expired']->cnt ?? 0 ); ?></div>
		</div>
	</div>

	<!-- Charts Row -->
	<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap:20px; margin:24px 0;">
		<!-- Revenue Trend -->
		<div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
			<h3 style="margin-top:0; color:#1d2327;"><?php esc_html_e( '💰 Doanh thu 6 tháng', 'usm' ); ?></h3>
			<canvas id="usm-report-revenue" height="200"></canvas>
		</div>

		<!-- Enrollment Status Pie -->
		<div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
			<h3 style="margin-top:0; color:#1d2327;"><?php esc_html_e( '📊 Tỷ lệ trạng thái đăng ký', 'usm' ); ?></h3>
			<canvas id="usm-report-status" height="200"></canvas>
		</div>
	</div>

	<!-- Top Coaches -->
	<?php if ( ! empty( $top_coaches ) ) : ?>
		<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap:20px; margin-bottom:24px;">
			<div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
				<h3 style="margin-top:0;"><?php esc_html_e( '🏆 Top HLV tháng này (theo buổi dạy)', 'usm' ); ?></h3>
				<table class="usm-table">
					<thead><tr>
						<th><?php esc_html_e( 'HLV', 'usm' ); ?></th>
						<th><?php esc_html_e( 'Số buổi', 'usm' ); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ( $top_coaches as $tc ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $tc->full_name ); ?></strong></td>
								<td><?php echo esc_html( $tc->session_count ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Low Sessions Alert -->
			<?php if ( ! empty( $low_session_list ) ) : ?>
				<div style="background:#fff; border:1px solid #d63638; border-radius:8px; padding:20px;">
					<h3 style="margin-top:0; color:#d63638;"><?php esc_html_e( '⚠️ Sắp hết buổi', 'usm' ); ?></h3>
					<table class="usm-table">
						<thead><tr>
							<th><?php esc_html_e( 'Học viên', 'usm' ); ?></th>
							<th><?php esc_html_e( 'Gói học', 'usm' ); ?></th>
							<th><?php esc_html_e( 'Còn lại', 'usm' ); ?></th>
						</tr></thead>
						<tbody>
							<?php foreach ( $low_session_list as $row ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $row->student_name ); ?></strong></td>
									<td><?php echo esc_html( $row->package_name ); ?></td>
									<td style="color:#d63638; font-weight:600;"><?php echo esc_html( $row->sessions_remaining ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php
	// Enqueue report charts JS and pass data.
	wp_enqueue_script( 'usm-reports-charts', USM_PLUGIN_URL . 'assets/js/usm-reports-charts.js', array( 'chartjs' ), USM_VERSION, true );
	wp_localize_script( 'usm-reports-charts', 'usmReportData', array(
		'months'       => $chart_months,
		'revenue'      => $chart_revenue,
		'statusLabels' => array( 'Đang học', 'Tạm dừng', 'Hoàn thành', 'Hết hạn' ),
		'statusData'   => array(
			(int) ( $status_counts['active']->cnt ?? 0 ),
			(int) ( $status_counts['paused']->cnt ?? 0 ),
			(int) ( $status_counts['completed']->cnt ?? 0 ),
			(int) ( $status_counts['expired']->cnt ?? 0 ),
		),
	) );
	?>
</div>
