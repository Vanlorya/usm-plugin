<?php
/**
 * Parent Dashboard – Enhanced view for parents.
 *
 * @package USM
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$current_user_id = get_current_user_id();

// Get parent record.
$parent = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$wpdb->prefix}usm_parents WHERE wp_user_id = %d",
	$current_user_id
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

if ( ! $parent ) {
	echo '<div class="wrap usm-wrap"><h1>' . esc_html__( 'Trang phụ huynh', 'usm' ) . '</h1>';
	echo '<div class="usm-form"><p>' . esc_html__( 'Tài khoản của bạn chưa được liên kết với hồ sơ phụ huynh nào. Vui lòng liên hệ quản trị viên.', 'usm' ) . '</p></div></div>';
	return;
}

// Get linked students.
$students = $wpdb->get_results( $wpdb->prepare(
	"SELECT s.*
	 FROM {$wpdb->prefix}usm_students s
	 JOIN {$wpdb->prefix}usm_parent_students ps ON s.id = ps.student_id
	 WHERE ps.parent_id = %d
	 ORDER BY s.full_name",
	$parent->id
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$center_name  = USM_Helpers::get_setting( 'center_name', '' );
$center_phone = USM_Helpers::get_setting( 'center_phone', '' );
$low_alert    = (int) USM_Helpers::get_setting( 'low_session_alert', 2 );
$today        = current_time( 'Y-m-d' );
$week_start   = wp_date( 'Y-m-d', strtotime( 'monday this week' ) );
$week_end     = wp_date( 'Y-m-d', strtotime( 'sunday this week' ) );

// VietQR bank settings.
$bank_bin     = USM_Helpers::get_setting( 'bank_bin', '' );
$bank_account = USM_Helpers::get_setting( 'bank_account', '' );
$bank_name    = USM_Helpers::get_setting( 'bank_name', '' );
$bank_holder  = USM_Helpers::get_setting( 'bank_holder', '' );
$has_bank     = ! empty( $bank_bin ) && ! empty( $bank_account );
?>

<style>
.usm-parent-card { background:#fff; border:1px solid #ddd; border-radius:8px; padding:20px; margin-bottom:16px; }
.usm-parent-card h2 { margin-top:0; font-size:18px; border-bottom:2px solid #2271b1; padding-bottom:8px; }
.usm-parent-section { margin-bottom:20px; }
.usm-parent-section h3 { font-size:14px; color:#1d2327; margin-bottom:10px; }
.usm-enroll-card { background:#f9f9f9; border-left:3px solid #2271b1; padding:14px 16px; margin-bottom:10px; border-radius:0 6px 6px 0; }
.usm-enroll-card.warning { border-left-color:#d63638; }
.usm-enroll-card .enroll-title { font-weight:600; font-size:14px; margin-bottom:6px; }
.usm-progress-bar { background:#e0e0e0; border-radius:10px; height:18px; position:relative; overflow:hidden; margin:8px 0; }
.usm-progress-fill { height:100%; border-radius:10px; transition:width .3s; display:flex; align-items:center; justify-content:center; font-size:11px; color:#fff; font-weight:600; min-width:30px; }
.usm-progress-fill.green { background:linear-gradient(90deg, #00a32a, #00c853); }
.usm-progress-fill.orange { background:linear-gradient(90deg, #dba617, #f0c33c); }
.usm-progress-fill.red { background:linear-gradient(90deg, #d63638, #e03c3c); }
.usm-schedule-row { display:flex; align-items:center; padding:8px 12px; margin-bottom:4px; border-radius:6px; font-size:13px; gap:10px; }
.usm-schedule-row.is-today { background:#e7f5ff; border-left:3px solid #2271b1; font-weight:600; }
.usm-schedule-row:not(.is-today) { background:#f6f7f7; }
.usm-stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:10px; margin-bottom:16px; }
.usm-stat-box { background:#f6f7f7; border-radius:8px; padding:12px; text-align:center; }
.usm-stat-box .stat-value { font-size:22px; font-weight:700; color:#1d2327; }
.usm-stat-box .stat-label { font-size:11px; color:#646970; text-transform:uppercase; letter-spacing:.5px; }
.usm-att-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:4px; vertical-align:middle; }
.usm-att-dot.present { background:#00a32a; }
.usm-att-dot.absent { background:#d63638; }
.usm-att-dot.excused { background:#dba617; }
.usm-qr-toggle { background:#2271b1; color:#fff; border:none; padding:6px 14px; border-radius:4px; cursor:pointer; font-size:13px; margin-top:8px; }
.usm-qr-toggle:hover { background:#135e96; }
.usm-qr-panel { display:none; margin-top:10px; padding:16px; background:#f0f6fc; border-radius:8px; text-align:center; animation:qrSlide .3s ease; }
.usm-qr-panel.show { display:block; }
@keyframes qrSlide { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
@media (max-width:600px) {
	.usm-stats-grid { grid-template-columns:1fr 1fr; }
	.usm-schedule-row { flex-direction:column; align-items:flex-start; gap:2px; }
}
</style>

<div class="wrap usm-wrap">
	<?php if ( $center_name ) : ?>
		<p style="color:#646970; margin-bottom:4px; font-size:13px;">
			🏢 <?php echo esc_html( $center_name ); ?>
			<?php if ( $center_phone ) : ?>
				— 📞 <?php echo esc_html( $center_phone ); ?>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<div class="usm-header">
		<h1><?php esc_html_e( '👨‍👧 Trang Phụ huynh', 'usm' ); ?></h1>
	</div>
	<p style="font-size:15px;"><?php echo esc_html( sprintf( 'Xin chào, %s!', $parent->full_name ) ); ?></p>

	<?php if ( empty( $students ) ) : ?>
		<div class="usm-parent-card">
			<p><?php esc_html_e( 'Chưa có học viên nào được liên kết với tài khoản của bạn.', 'usm' ); ?></p>
		</div>
	<?php else : ?>
		<?php foreach ( $students as $student ) : ?>
			<?php
			// Get enrollments.
			$enrollments = $wpdb->get_results( $wpdb->prepare(
				"SELECT e.*, p.name AS package_name, p.price AS package_price, c.name AS course_name
				 FROM {$wpdb->prefix}usm_enrollments e
				 JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
				 JOIN {$wpdb->prefix}usm_courses c ON p.course_id = c.id
				 WHERE e.student_id = %d
				 ORDER BY e.status ASC, e.enroll_date DESC",
				$student->id
			) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			// Get this week's schedule.
			$week_schedule = $wpdb->get_results( $wpdb->prepare(
				"SELECT sess.session_date, sess.start_time, sess.end_time,
				        c.name AS course_name, co.full_name AS coach_name, f.name AS facility_name
				 FROM {$wpdb->prefix}usm_sessions sess
				 JOIN {$wpdb->prefix}usm_courses c ON sess.course_id = c.id
				 LEFT JOIN {$wpdb->prefix}usm_coaches co ON c.coach_id = co.id
				 LEFT JOIN {$wpdb->prefix}usm_facilities f ON c.facility_id = f.id
				 WHERE sess.course_id IN (
				   SELECT p.course_id FROM {$wpdb->prefix}usm_packages p
				   JOIN {$wpdb->prefix}usm_enrollments e ON e.package_id = p.id
				   WHERE e.student_id = %d AND e.status = 'active'
				 )
				 AND sess.session_date BETWEEN %s AND %s
				 ORDER BY sess.session_date ASC, sess.start_time ASC",
				$student->id, $week_start, $week_end
			) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			// Get attendance history (last 30 days).
			$attendance = $wpdb->get_results( $wpdb->prepare(
				"SELECT a.status, a.notes AS att_note, sess.session_date, sess.notes AS session_note, c.name AS course_name
				 FROM {$wpdb->prefix}usm_attendance a
				 JOIN {$wpdb->prefix}usm_sessions sess ON a.session_id = sess.id
				 JOIN {$wpdb->prefix}usm_courses c ON sess.course_id = c.id
				 WHERE a.student_id = %d AND sess.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
				 ORDER BY sess.session_date DESC",
				$student->id
			) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			// Stats.
			$total_present = 0;
			$total_absent  = 0;
			foreach ( $attendance as $att ) {
				if ( 'present' === $att->status ) { $total_present++; }
				if ( 'absent' === $att->status ) { $total_absent++; }
			}
			$has_warning = false;
			foreach ( $enrollments as $e ) {
				if ( 'active' === $e->status && (int) $e->sessions_remaining <= $low_alert && (int) $e->sessions_remaining > 0 ) {
					$has_warning = true;
					break;
				}
			}

			$day_names = array(
				'Mon' => 'T2', 'Tue' => 'T3', 'Wed' => 'T4',
				'Thu' => 'T5', 'Fri' => 'T6', 'Sat' => 'T7', 'Sun' => 'CN',
			);
			?>

			<div class="usm-parent-card" style="border-left:4px solid <?php echo $has_warning ? '#d63638' : '#2271b1'; ?>;">
				<h2>🧒 <?php echo esc_html( $student->full_name ); ?>
					<?php if ( $has_warning ) : ?>
						<span style="color:#d63638; font-size:13px; font-weight:normal;">⚠️ <?php esc_html_e( 'Sắp hết buổi!', 'usm' ); ?></span>
					<?php endif; ?>
				</h2>

				<!-- Stats overview -->
				<div class="usm-stats-grid">
					<div class="usm-stat-box">
						<div class="stat-value"><?php echo count( $enrollments ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Gói đăng ký', 'usm' ); ?></div>
					</div>
					<div class="usm-stat-box">
						<div class="stat-value"><?php echo count( $week_schedule ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Buổi tuần này', 'usm' ); ?></div>
					</div>
					<div class="usm-stat-box">
						<div class="stat-value" style="color:#00a32a;"><?php echo $total_present; ?></div>
						<div class="stat-label"><?php esc_html_e( 'Có mặt (30 ngày)', 'usm' ); ?></div>
					</div>
					<div class="usm-stat-box">
						<div class="stat-value" style="color:#d63638;"><?php echo $total_absent; ?></div>
						<div class="stat-label"><?php esc_html_e( 'Vắng (30 ngày)', 'usm' ); ?></div>
					</div>
				</div>

				<!-- Enrollments with progress bars -->
				<?php if ( ! empty( $enrollments ) ) : ?>
				<div class="usm-parent-section">
					<h3>📦 <?php esc_html_e( 'Gói học đăng ký', 'usm' ); ?></h3>
					<?php foreach ( $enrollments as $enroll ) :
						$is_low    = 'active' === $enroll->status && (int) $enroll->sessions_remaining <= $low_alert && (int) $enroll->sessions_remaining > 0;
						$total     = max( 1, (int) $enroll->sessions_total );
						$remaining = (int) $enroll->sessions_remaining;
						$used      = $total - $remaining;
						$pct       = min( 100, round( ( $used / $total ) * 100 ) );
						$bar_class = $pct >= 80 ? 'red' : ( $pct >= 60 ? 'orange' : 'green' );
					?>
					<div class="usm-enroll-card <?php echo $is_low ? 'warning' : ''; ?>">
						<div class="enroll-title"><?php echo esc_html( $enroll->course_name ); ?> — <?php echo esc_html( $enroll->package_name ); ?></div>

						<?php if ( $total > 0 ) : ?>
						<div class="usm-progress-bar">
							<div class="usm-progress-fill <?php echo esc_attr( $bar_class ); ?>" style="width:<?php echo max( 15, $pct ); ?>%;">
								<?php echo esc_html( $used . '/' . $total ); ?>
							</div>
						</div>
						<div style="display:flex; justify-content:space-between; font-size:12px; color:#666;">
							<span><?php echo esc_html( sprintf( 'Đã học: %d buổi', $used ) ); ?></span>
							<span style="font-weight:600; <?php echo $is_low ? 'color:#d63638;' : ''; ?>">
								<?php echo esc_html( sprintf( 'Còn lại: %d buổi', $remaining ) ); ?>
								<?php if ( $is_low ) : ?> ⚠️<?php endif; ?>
							</span>
						</div>
						<?php endif; ?>

						<div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; font-size:13px;">
							<span><?php esc_html_e( 'Trạng thái:', 'usm' ); ?> <?php echo wp_kses_post( USM_Helpers::status_badge( $enroll->status, USM_Helpers::enrollment_status_label( $enroll->status ) ) ); ?></span>
							<span><?php esc_html_e( 'Thanh toán:', 'usm' ); ?> <?php echo wp_kses_post( USM_Helpers::status_badge( $enroll->payment_status, USM_Helpers::payment_status_label( $enroll->payment_status ) ) ); ?></span>
							<?php if ( $enroll->amount_paid > 0 ) : ?>
								<span><?php esc_html_e( 'Đã đóng:', 'usm' ); ?> <strong><?php echo esc_html( USM_Helpers::format_vnd( $enroll->amount_paid ) ); ?></strong></span>
							<?php endif; ?>
							<?php if ( $enroll->package_price > $enroll->amount_paid ) : ?>
								<span style="color:#d63638;"><?php esc_html_e( 'Còn nợ:', 'usm' ); ?> <strong><?php echo esc_html( USM_Helpers::format_vnd( $enroll->package_price - $enroll->amount_paid ) ); ?></strong></span>
							<?php endif; ?>
						</div>

						<?php
						// VietQR payment QR — show for unpaid/partial enrollments.
						$amount_owed = (float) $enroll->package_price - (float) $enroll->amount_paid;
						if ( $has_bank && $amount_owed > 0 && 'paid' !== $enroll->payment_status ) :
							$qr_desc = 'USM ' . $enroll->id;
							$qr_url  = sprintf(
								'https://img.vietqr.io/image/%s-%s-compact.png?amount=%d&addInfo=%s&accountName=%s',
								rawurlencode( $bank_bin ),
								rawurlencode( $bank_account ),
								(int) $amount_owed,
								rawurlencode( $qr_desc ),
								rawurlencode( $bank_holder )
							);
						?>
						<button class="usm-qr-toggle" onclick="this.nextElementSibling.classList.toggle('show')">
							💳 <?php esc_html_e( 'Thanh toán QR', 'usm' ); ?>
						</button>
						<div class="usm-qr-panel">
							<img src="<?php echo esc_url( $qr_url ); ?>" alt="VietQR" style="max-width:280px; border-radius:8px;"><br>
							<div style="margin-top:10px; font-size:13px; text-align:left; display:inline-block;">
								<strong><?php esc_html_e( 'Thông tin chuyển khoản:', 'usm' ); ?></strong><br>
								🏦 <?php echo esc_html( $bank_name ); ?><br>
								📋 STK: <strong><?php echo esc_html( $bank_account ); ?></strong><br>
								👤 <?php echo esc_html( $bank_holder ); ?><br>
								💰 Số tiền: <strong style="color:#d63638;"><?php echo esc_html( USM_Helpers::format_vnd( $amount_owed ) ); ?></strong><br>
								📝 Nội dung CK: <strong><?php echo esc_html( $qr_desc ); ?></strong>
							</div>
							<p style="font-size:11px; color:#888; margin-top:8px;"><?php esc_html_e( 'Quét mã QR bằng app ngân hàng. Sau khi CK, trung tâm sẽ xác nhận.', 'usm' ); ?></p>
						</div>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<!-- Weekly Schedule -->
				<?php if ( ! empty( $week_schedule ) ) : ?>
				<div class="usm-parent-section">
					<h3>📅 <?php esc_html_e( 'Lịch học tuần này', 'usm' ); ?>
						<span style="font-weight:normal; font-size:12px; color:#888;">
							(<?php echo esc_html( wp_date( 'd/m', strtotime( $week_start ) ) . ' – ' . wp_date( 'd/m', strtotime( $week_end ) ) ); ?>)
						</span>
					</h3>
					<?php foreach ( $week_schedule as $sess ) :
						$is_today  = $sess->session_date === $today;
						$day_key   = wp_date( 'D', strtotime( $sess->session_date ) );
						$day_label = $day_names[ $day_key ] ?? $day_key;
					?>
					<div class="usm-schedule-row <?php echo $is_today ? 'is-today' : ''; ?>">
						<span style="min-width:70px;">
							<strong><?php echo esc_html( $day_label ); ?></strong>
							<?php echo esc_html( wp_date( 'd/m', strtotime( $sess->session_date ) ) ); ?>
							<?php if ( $is_today ) : ?><span style="color:#2271b1;">📍</span><?php endif; ?>
						</span>
						<span style="min-width:90px;">
							⏰ <?php echo esc_html( substr( $sess->start_time, 0, 5 ) . ' – ' . substr( $sess->end_time, 0, 5 ) ); ?>
						</span>
						<span><?php echo esc_html( $sess->course_name ); ?></span>
						<?php if ( $sess->coach_name ) : ?>
							<span style="color:#666;">👤 <?php echo esc_html( $sess->coach_name ); ?></span>
						<?php endif; ?>
						<?php if ( $sess->facility_name ) : ?>
							<span style="color:#666;">📍 <?php echo esc_html( $sess->facility_name ); ?></span>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<!-- Attendance History -->
				<?php if ( ! empty( $attendance ) ) : ?>
				<div class="usm-parent-section">
					<h3>📋 <?php esc_html_e( 'Lịch sử điểm danh (30 ngày)', 'usm' ); ?></h3>
					<table class="usm-table" style="font-size:13px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Ngày', 'usm' ); ?></th>
								<th><?php esc_html_e( 'Khóa học', 'usm' ); ?></th>
								<th><?php esc_html_e( 'Trạng thái', 'usm' ); ?></th>
								<th><?php esc_html_e( '📝 Ghi chú HLV', 'usm' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $attendance as $att ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( 'd/m/Y (D)', strtotime( $att->session_date ) ) ); ?></td>
								<td><?php echo esc_html( $att->course_name ); ?></td>
								<td>
									<?php
									$att_labels = array(
										'present' => '<span class="usm-att-dot present"></span>Có mặt',
										'absent'  => '<span class="usm-att-dot absent"></span>Vắng',
										'excused' => '<span class="usm-att-dot excused"></span>Xin phép',
									);
									echo wp_kses_post( $att_labels[ $att->status ] ?? esc_html( $att->status ) );
									?>
								</td>
								<td>
									<?php if ( ! empty( $att->att_note ) ) : ?>
										<span style="font-size:12px;">📝 <?php echo esc_html( $att->att_note ); ?></span>
									<?php elseif ( ! empty( $att->session_note ) ) : ?>
										<span style="font-size:12px; color:#888;"><?php echo esc_html( $att->session_note ); ?></span>
									<?php else : ?>
										<span style="color:#ccc;">—</span>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
