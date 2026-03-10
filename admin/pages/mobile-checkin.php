<?php
/**
 * Mobile Check-in — Giao diện điểm danh cho HLV trên điện thoại.
 *
 * Touch-friendly, large buttons, minimal UI.
 * Reuses the same attendance submission logic.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$today = current_time( 'Y-m-d' );

// Today's sessions.
$sessions = $wpdb->get_results( $wpdb->prepare(
	"SELECT s.*, c.name AS course_name, co.full_name AS coach_name, f.name AS facility_name
	 FROM {$wpdb->prefix}usm_sessions s
	 LEFT JOIN {$wpdb->prefix}usm_courses c ON s.course_id = c.id
	 LEFT JOIN {$wpdb->prefix}usm_coaches co ON s.coach_id = co.id
	 LEFT JOIN {$wpdb->prefix}usm_facilities f ON s.facility_id = f.id
	 WHERE s.session_date = %s
	 ORDER BY s.start_time ASC",
	$today
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Selected session.
$selected_session_id = absint( $_GET['sid'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$sel_session = null;
$students    = array();
$already_marked = array();

if ( $selected_session_id ) {
	$sel_session = $wpdb->get_row( $wpdb->prepare(
		"SELECT s.*, c.name AS course_name
		 FROM {$wpdb->prefix}usm_sessions s
		 LEFT JOIN {$wpdb->prefix}usm_courses c ON s.course_id = c.id
		 WHERE s.id = %d",
		$selected_session_id
	) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	if ( $sel_session ) {
		// Get enrolled students for this session's course.
		$students = $wpdb->get_results( $wpdb->prepare(
			"SELECT e.id AS enrollment_id, e.sessions_remaining, s.full_name
			 FROM {$wpdb->prefix}usm_enrollments e
			 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
			 WHERE e.status = 'active'
			   AND e.package_id IN (SELECT id FROM {$wpdb->prefix}usm_packages WHERE course_id = %d)
			 ORDER BY s.full_name",
			$sel_session->course_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		// Check who's already marked.
		$already_marked = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.enrollment_id, a.status, a.notes
			 FROM {$wpdb->prefix}usm_attendance a
			 WHERE a.session_id = %d",
			$selected_session_id
		), OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
?>
<div class="wrap usm-wrap">
<style>
.mc-container { max-width: 480px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, sans-serif; }
.mc-header { background: linear-gradient(135deg, #2271b1, #135e96); color: #fff; padding: 16px; border-radius: 12px; margin-bottom: 16px; text-align: center; }
.mc-header h1 { font-size: 20px; margin: 0; }
.mc-header .mc-date { font-size: 13px; opacity: 0.8; margin-top: 4px; }
.mc-session-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 14px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: all 0.2s; text-decoration: none; color: inherit; }
.mc-session-card:hover, .mc-session-card:focus { border-color: #2271b1; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.mc-session-card.active { border-color: #2271b1; border-width: 2px; background: #f0f6fc; }
.mc-session-time { font-size: 22px; font-weight: 700; color: #2271b1; min-width: 60px; }
.mc-session-info { flex: 1; margin-left: 12px; }
.mc-session-info .name { font-weight: 600; font-size: 15px; }
.mc-session-info .meta { font-size: 12px; color: #666; }
.mc-session-badge { font-size: 11px; padding: 3px 10px; border-radius: 12px; font-weight: 600; white-space: nowrap; }
.mc-badge-todo { background: #fff3cd; color: #856404; }
.mc-badge-done { background: #d4edda; color: #00a32a; }

/* Student cards */
.mc-student { background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 12px; margin-bottom: 8px; }
.mc-student-name { font-weight: 600; font-size: 15px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
.mc-student-remaining { font-size: 11px; color: #666; background: #f0f0f1; padding: 2px 8px; border-radius: 8px; }
.mc-student-remaining.low { color: #d63638; background: #fce4ec; }
.mc-btns { display: flex; gap: 6px; }
.mc-btn { flex: 1; padding: 10px 0; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; font-weight: 600; text-align: center; cursor: pointer; background: #fff; transition: all 0.15s; -webkit-tap-highlight-color: transparent; }
.mc-btn:active { transform: scale(0.95); }
.mc-btn input { display: none; }
.mc-btn.selected-present { border-color: #00a32a; background: #d4edda; color: #00a32a; }
.mc-btn.selected-absent  { border-color: #d63638; background: #fce4ec; color: #d63638; }
.mc-btn.selected-excused { border-color: #dba617; background: #fff3cd; color: #856404; }
.mc-btn-submit { width: 100%; padding: 14px; font-size: 16px; font-weight: 700; border: none; border-radius: 10px; background: #2271b1; color: #fff; cursor: pointer; margin-top: 12px; -webkit-tap-highlight-color: transparent; }
.mc-btn-submit:active { background: #135e96; }
.mc-back { display: inline-block; padding: 8px 16px; background: #f0f0f1; border-radius: 8px; text-decoration: none; color: #1d2327; font-size: 14px; margin-bottom: 12px; }
.mc-done-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; padding: 4px 10px; border-radius: 8px; font-weight: 600; }
.mc-empty { text-align: center; padding: 30px 16px; color: #666; font-size: 14px; }
.mc-empty .emoji { font-size: 40px; display: block; margin-bottom: 8px; }
@media (max-width: 600px) {
	.mc-container { max-width: 100%; }
}
</style>

<div class="mc-container">
	<div class="mc-header">
		<h1>📋 Điểm danh</h1>
		<div class="mc-date"><?php echo esc_html( wp_date( 'l, d/m/Y' ) ); ?></div>
	</div>

	<?php if ( ! $selected_session_id ) : ?>
		<!-- Session list -->
		<?php if ( empty( $sessions ) ) : ?>
			<div class="mc-empty">
				<span class="emoji">📅</span>
				<?php esc_html_e( 'Hôm nay không có buổi học nào.', 'usm' ); ?>
			</div>
		<?php else : ?>
			<p style="font-weight:600; font-size:14px; margin-bottom:8px;"><?php echo esc_html( sprintf( '%d buổi hôm nay', count( $sessions ) ) ); ?></p>
			<?php foreach ( $sessions as $sess ) :
				// Check if already fully marked.
				$marked_count = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}usm_attendance WHERE session_id = %d",
					$sess->id
				) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$is_done = $marked_count > 0;
			?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-checkin&sid=' . $sess->id ) ); ?>" class="mc-session-card">
				<div class="mc-session-time"><?php echo esc_html( substr( $sess->start_time, 0, 5 ) ); ?></div>
				<div class="mc-session-info">
					<div class="name"><?php echo esc_html( $sess->course_name ); ?></div>
					<div class="meta">
						<?php if ( $sess->coach_name ) echo esc_html( $sess->coach_name ) . ' · '; ?>
						<?php if ( $sess->facility_name ) echo esc_html( $sess->facility_name ); ?>
					</div>
				</div>
				<span class="mc-session-badge <?php echo $is_done ? 'mc-badge-done' : 'mc-badge-todo'; ?>">
					<?php echo $is_done ? '✅ Đã chấm' : '⏳ Chờ'; ?>
				</span>
			</a>
			<?php endforeach; ?>
		<?php endif; ?>

	<?php else : ?>
		<!-- Attendance marking for selected session -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-checkin' ) ); ?>" class="mc-back">← Quay lại</a>

		<?php if ( ! $sel_session ) : ?>
			<div class="mc-empty">
				<span class="emoji">❌</span>
				<?php esc_html_e( 'Không tìm thấy buổi học.', 'usm' ); ?>
			</div>
		<?php elseif ( empty( $students ) ) : ?>
			<div class="mc-empty">
				<span class="emoji">👥</span>
				<?php esc_html_e( 'Chưa có học viên đăng ký khóa này.', 'usm' ); ?>
			</div>
		<?php else : ?>
			<div style="background:#fff; border-radius:10px; padding:12px; margin-bottom:12px; border:1px solid #e0e0e0;">
				<div style="font-size:18px; font-weight:700;">
					<?php echo esc_html( substr( $sel_session->start_time, 0, 5 ) ); ?> — <?php echo esc_html( $sel_session->course_name ); ?>
				</div>
				<div style="font-size:12px; color:#666; margin-top:2px;">
					<?php echo esc_html( count( $students ) ); ?> học viên
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=usm-attendance' ) ); ?>">
				<?php wp_nonce_field( 'usm_submit_attendance', 'usm_attendance_nonce' ); ?>
				<input type="hidden" name="session_id" value="<?php echo esc_attr( $selected_session_id ); ?>">

				<?php foreach ( $students as $stu ) :
					$marked     = isset( $already_marked[ $stu->enrollment_id ] );
					$marked_st  = $marked ? $already_marked[ $stu->enrollment_id ]->status : '';
					$marked_note = $marked ? $already_marked[ $stu->enrollment_id ]->notes : '';
					$low_alert  = (int) USM_Helpers::get_setting( 'low_session_alert', 2 );
					$is_low     = (int) $stu->sessions_remaining <= $low_alert && (int) $stu->sessions_remaining > 0;
				?>
				<div class="mc-student">
					<div class="mc-student-name">
						<span><?php echo esc_html( $stu->full_name ); ?></span>
						<span class="mc-student-remaining <?php echo $is_low ? 'low' : ''; ?>">
							<?php echo esc_html( $stu->sessions_remaining ); ?> buổi
						</span>
					</div>

					<?php if ( $marked ) : ?>
						<div class="mc-done-badge" style="background:<?php
							echo 'present' === $marked_st ? '#d4edda' : ( 'excused' === $marked_st ? '#fff3cd' : '#fce4ec' );
						?>; color:<?php
							echo 'present' === $marked_st ? '#00a32a' : ( 'excused' === $marked_st ? '#856404' : '#d63638' );
						?>;">
							<?php
							echo 'present' === $marked_st ? '✅ Có mặt' : ( 'excused' === $marked_st ? '🟡 Xin phép' : '❌ Vắng' );
							if ( $marked_note ) echo ' — ' . esc_html( $marked_note );
							?>
						</div>
					<?php else : ?>
						<div class="mc-btns">
							<label class="mc-btn" id="btn-p-<?php echo esc_attr( $stu->enrollment_id ); ?>"
								onclick="selAtt(<?php echo esc_attr( $stu->enrollment_id ); ?>, 'present')">
								<input type="radio" name="attendance[<?php echo esc_attr( $stu->enrollment_id ); ?>]" value="present">
								✅ Có
							</label>
							<label class="mc-btn" id="btn-a-<?php echo esc_attr( $stu->enrollment_id ); ?>"
								onclick="selAtt(<?php echo esc_attr( $stu->enrollment_id ); ?>, 'absent')">
								<input type="radio" name="attendance[<?php echo esc_attr( $stu->enrollment_id ); ?>]" value="absent">
								❌ Vắng
							</label>
							<label class="mc-btn" id="btn-e-<?php echo esc_attr( $stu->enrollment_id ); ?>"
								onclick="selAtt(<?php echo esc_attr( $stu->enrollment_id ); ?>, 'excused')">
								<input type="radio" name="attendance[<?php echo esc_attr( $stu->enrollment_id ); ?>]" value="excused">
								🟡 Phép
							</label>
						</div>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>

				<?php
				$has_unmarked = false;
				foreach ( $students as $s ) {
					if ( ! isset( $already_marked[ $s->enrollment_id ] ) ) { $has_unmarked = true; break; }
				}
				if ( $has_unmarked ) :
				?>
				<button type="submit" class="mc-btn-submit">📋 Lưu điểm danh</button>
				<?php else : ?>
				<div style="text-align:center; padding:12px; color:#00a32a; font-weight:600; font-size:15px;">
					✅ Đã chấm xong buổi này!
				</div>
				<?php endif; ?>
			</form>
		<?php endif; ?>
	<?php endif; ?>
</div>

<script>
function selAtt(eid, status) {
	['present','absent','excused'].forEach(function(s) {
		var b = document.getElementById('btn-' + s.charAt(0) + '-' + eid);
		if (b) b.classList.remove('selected-present','selected-absent','selected-excused');
	});
	var btn = document.getElementById('btn-' + status.charAt(0) + '-' + eid);
	if (btn) {
		btn.classList.add('selected-' + status);
		btn.querySelector('input').checked = true;
	}
}
</script>
</div>
