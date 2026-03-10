<?php
/**
 * Attendance – Điểm danh + Session Deduction (Transaction-safe).
 *
 * CRITICAL: This is the most important page in the plugin.
 * Uses DB transactions to prevent race conditions on sessions_remaining.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// ── Handle attendance submission ────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_attendance_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_attendance_nonce'] ) ), 'usm_submit_attendance' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}

	if ( ! current_user_can( 'manage_usm_attendance' ) ) {
		wp_die( esc_html__( 'Bạn không có quyền thực hiện hành động này.', 'usm' ) );
	}

	$session_id  = absint( $_POST['session_id'] ?? 0 );
	$statuses    = isset( $_POST['attendance'] ) && is_array( $_POST['attendance'] ) ? $_POST['attendance'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$att_notes   = isset( $_POST['att_notes'] ) && is_array( $_POST['att_notes'] ) ? $_POST['att_notes'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$checked_by  = get_current_user_id();
	$checked_at  = current_time( 'mysql' );

	$count_present = 0;
	$count_absent  = 0;
	$count_excused = 0;
	$errors        = array();

	foreach ( $statuses as $enrollment_id => $status_raw ) {
		$enrollment_id = absint( $enrollment_id );
		$status        = sanitize_text_field( $status_raw );

		if ( ! in_array( $status, array( 'present', 'absent', 'excused' ), true ) ) {
			continue;
		}

		// Get enrollment + student.
		$enrollment = $wpdb->get_row( $wpdb->prepare(
			"SELECT e.*, s.full_name AS student_name, s.id AS sid
			 FROM {$wpdb->prefix}usm_enrollments e
			 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
			 WHERE e.id = %d",
			$enrollment_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $enrollment ) {
			continue;
		}

		// Pre-checks (FLOW 5).
		if ( 'active' !== $enrollment->status ) {
			$errors[] = "{$enrollment->student_name}: Đăng ký không ở trạng thái hoạt động.";
			continue;
		}

		if ( 'present' === $status && (int) $enrollment->sessions_remaining <= 0 ) {
			$errors[] = "{$enrollment->student_name}: Đã hết buổi học. Không thể điểm danh có mặt.";
			continue;
		}

		// Check duplicate.
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}usm_attendance WHERE session_id = %d AND enrollment_id = %d",
			$session_id,
			$enrollment_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( $existing > 0 ) {
			$errors[] = "{$enrollment->student_name}: Đã điểm danh buổi này rồi.";
			continue;
		}

		// ── TRANSACTION for present status (CRITICAL) ──
		$note_text = isset( $att_notes[ $enrollment_id ] ) ? sanitize_textarea_field( wp_unslash( $att_notes[ $enrollment_id ] ) ) : '';
		if ( 'present' === $status ) {
			$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			// Re-check inside transaction.
			$current_remaining = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT sessions_remaining FROM {$wpdb->prefix}usm_enrollments WHERE id = %d FOR UPDATE",
				$enrollment_id
			) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			if ( $current_remaining <= 0 ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$errors[] = "{$enrollment->student_name}: Đã hết buổi (concurrent check).";
				continue;
			}

			// Insert attendance.
			$inserted = $wpdb->insert( $wpdb->prefix . 'usm_attendance', array(
				'session_id'    => $session_id,
				'enrollment_id' => $enrollment_id,
				'student_id'    => $enrollment->sid,
				'status'        => 'present',
				'checked_by'    => $checked_by,
				'checked_at'    => $checked_at,
				'notes'         => $note_text ?: null,
			) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$errors[] = "{$enrollment->student_name}: Lỗi ghi điểm danh.";
				continue;
			}

			// Decrement sessions_remaining.
			$new_remaining = $current_remaining - 1;
			$update_data   = array(
				'sessions_remaining' => $new_remaining,
				'updated_at'         => current_time( 'mysql' ),
			);

			// Auto-complete if 0 (FLOW 8 Auto-Complete).
			if ( 0 === $new_remaining ) {
				$update_data['status'] = 'completed';
			}

			$wpdb->update(
				$wpdb->prefix . 'usm_enrollments',
				$update_data,
				array( 'id' => $enrollment_id )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$count_present++;

		} else {
			// Absent / Excused – no session deduction.
			$wpdb->insert( $wpdb->prefix . 'usm_attendance', array(
				'session_id'    => $session_id,
				'enrollment_id' => $enrollment_id,
				'student_id'    => $enrollment->sid,
				'status'        => $status,
				'checked_by'    => $checked_by,
				'checked_at'    => $checked_at,
				'notes'         => $note_text ?: null,
			) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			if ( 'absent' === $status ) {
				$count_absent++;
			} else {
				$count_excused++;
			}
		}
	}

	// Show results.
	if ( ! empty( $errors ) ) {
		foreach ( $errors as $err ) {
			USM_Helpers::admin_notice( $err, 'error' );
		}
	}
	if ( $count_present > 0 || $count_absent > 0 || $count_excused > 0 ) {
		$summary = sprintf(
			'Kết quả: %d có mặt, %d vắng, %d xin phép.',
			$count_present,
			$count_absent,
			$count_excused
		);
		USM_Helpers::admin_notice( $summary );
	}
}

// ── Get today's sessions for dropdown ───────────
$selected_session = absint( $_GET['session_id'] ?? ( $_POST['session_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
$today            = current_time( 'Y-m-d' );

$todays_sessions = $wpdb->get_results( $wpdb->prepare(
	"SELECT s.id, s.start_time, s.end_time, c.name AS course_name
	 FROM {$wpdb->prefix}usm_sessions s
	 LEFT JOIN {$wpdb->prefix}usm_courses c ON s.course_id = c.id
	 WHERE s.session_date = %s
	 ORDER BY s.start_time",
	$today
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// If no session selected and today has sessions, default to first.
if ( 0 === $selected_session && ! empty( $todays_sessions ) ) {
	$selected_session = (int) $todays_sessions[0]->id;
}
?>

<div class="wrap usm-wrap">
	<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
		<h1 style="margin:0;"><?php esc_html_e( '📋 Điểm danh', 'usm' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-checkin' ) ); ?>" class="button" style="font-size:13px;">
			📱 <?php esc_html_e( 'Check-in Mobile', 'usm' ); ?>
		</a>
	</div>

	<!-- Step 1: Select session -->
	<div style="margin-bottom:20px; display:flex; align-items:center; gap:10px;">
		<form method="get" style="display:flex; align-items:center; gap:8px;">
			<input type="hidden" name="page" value="usm-attendance">
			<select name="session_id" style="min-width:300px;">
				<?php if ( empty( $todays_sessions ) ) : ?>
					<option value=""><?php esc_html_e( 'Hôm nay không có buổi học nào', 'usm' ); ?></option>
				<?php else : ?>
					<?php foreach ( $todays_sessions as $sess ) : ?>
						<option value="<?php echo esc_attr( $sess->id ); ?>" <?php selected( $selected_session, $sess->id ); ?>>
							<?php echo esc_html( $sess->course_name . ' — ' . substr( $sess->start_time, 0, 5 ) . '–' . substr( $sess->end_time, 0, 5 ) ); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Mở điểm danh', 'usm' ); ?></button>
		</form>
	</div>

	<?php if ( $selected_session > 0 ) : ?>
		<?php
		// Get session details.
		$session = $wpdb->get_row( $wpdb->prepare(
			"SELECT s.*, c.name AS course_name, c.id AS cid
			 FROM {$wpdb->prefix}usm_sessions s
			 LEFT JOIN {$wpdb->prefix}usm_courses c ON s.course_id = c.id
			 WHERE s.id = %d",
			$selected_session
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $session ) :
			USM_Helpers::admin_notice( 'Không tìm thấy buổi học.', 'error' );
		else :
			// Get enrolled students for this course (active enrollments).
			$enrolled = $wpdb->get_results( $wpdb->prepare(
				"SELECT e.id AS enrollment_id, e.sessions_remaining, e.status AS enrollment_status,
				        s.full_name, s.id AS student_id
				 FROM {$wpdb->prefix}usm_enrollments e
				 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
				 JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
				 WHERE p.course_id = %d AND e.status = %s
				 ORDER BY s.full_name",
				$session->cid,
				'active'
			) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			// Check which already have attendance.
			$already_marked = array();
			$already_notes  = array();
			if ( ! empty( $enrolled ) ) {
				$enrollment_ids = wp_list_pluck( $enrolled, 'enrollment_id' );
				$ids_str        = implode( ',', array_map( 'absint', $enrollment_ids ) );
				$marked_raw     = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					"SELECT enrollment_id, status, notes FROM {$wpdb->prefix}usm_attendance WHERE session_id = %d AND enrollment_id IN ({$ids_str})", // phpcs:ignore WordPress.DB.PreparedSQL
					$selected_session
				) );
				foreach ( $marked_raw as $m ) {
					$already_marked[ $m->enrollment_id ] = $m->status;
					$already_notes[ $m->enrollment_id ]  = $m->notes;
				}
			}
		?>

			<div class="usm-form" style="max-width:100%;">
				<h2><?php echo esc_html( $session->course_name ); ?> — <?php echo esc_html( wp_date( 'd/m/Y', strtotime( $session->session_date ) ) . ' ' . substr( $session->start_time, 0, 5 ) . '–' . substr( $session->end_time, 0, 5 ) ); ?></h2>
				<p style="margin:0; color:#646970;">
					<?php printf( esc_html__( 'Tổng: %d học viên | Đã điểm danh: %d/%d', 'usm' ), count( $enrolled ), count( $already_marked ), count( $enrolled ) ); ?>
					<?php if ( count( $already_marked ) >= count( $enrolled ) ) : ?>
						<span style="color:#00a32a; font-weight:600;"> ✓ Hoàn tất</span>
					<?php endif; ?>
				</p>
			</div>

			<?php if ( empty( $enrolled ) ) : ?>
				<p><?php esc_html_e( 'Không có học viên đăng ký hoạt động cho khóa này.', 'usm' ); ?></p>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'usm_submit_attendance', 'usm_attendance_nonce' ); ?>
					<input type="hidden" name="session_id" value="<?php echo esc_attr( $selected_session ); ?>">

					<?php if ( count( $already_marked ) < count( $enrolled ) ) : ?>
						<div style="margin-bottom:12px; display:flex; gap:8px;">
							<button type="button" class="button" onclick="document.querySelectorAll('input[value=present]').forEach(r=>r.checked=true)">
								<?php esc_html_e( '✅ Tất cả có mặt', 'usm' ); ?>
							</button>
							<button type="button" class="button" onclick="document.querySelectorAll('input[value=absent]').forEach(r=>r.checked=true)">
								<?php esc_html_e( '❌ Tất cả vắng', 'usm' ); ?>
							</button>
						</div>
					<?php endif; ?>

					<table class="usm-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Học viên', 'usm' ); ?></th>
								<th style="width:100px;"><?php esc_html_e( 'Buổi còn lại', 'usm' ); ?></th>
								<th style="width:280px;"><?php esc_html_e( 'Trạng thái', 'usm' ); ?></th>
								<th style="width:200px;"><?php esc_html_e( '📝 Ghi chú', 'usm' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $enrolled as $row ) :
								$is_marked  = isset( $already_marked[ $row->enrollment_id ] );
								$marked_val = $is_marked ? $already_marked[ $row->enrollment_id ] : '';
								$low_alert = (int) USM_Helpers::get_setting( 'low_session_alert', 2 );
								$remaining_style = (int) $row->sessions_remaining <= $low_alert ? 'color:#d63638;font-weight:600;' : '';
							?>
								<tr>
									<td><strong><?php echo esc_html( $row->full_name ); ?></strong></td>
									<td><span style="<?php echo esc_attr( $remaining_style ); ?>"><?php echo esc_html( $row->sessions_remaining ); ?></span></td>
									<td>
										<?php if ( $is_marked ) : ?>
											<?php echo wp_kses_post( USM_Helpers::status_badge( $marked_val, $marked_val === 'present' ? 'Có mặt ✓' : ( $marked_val === 'absent' ? 'Vắng ✗' : 'Xin phép' ) ) ); ?>
										<?php else : ?>
											<label style="margin-right:16px; cursor:pointer;">
												<input type="radio" name="attendance[<?php echo esc_attr( $row->enrollment_id ); ?>]" value="present"> Có mặt
											</label>
											<label style="margin-right:16px; cursor:pointer;">
												<input type="radio" name="attendance[<?php echo esc_attr( $row->enrollment_id ); ?>]" value="absent"> Vắng
											</label>
											<label style="cursor:pointer;">
												<input type="radio" name="attendance[<?php echo esc_attr( $row->enrollment_id ); ?>]" value="excused"> Xin phép
											</label>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $is_marked ) : ?>
											<?php if ( ! empty( $already_notes[ $row->enrollment_id ] ) ) : ?>
												<span style="font-size:12px; color:#555;"><?php echo esc_html( $already_notes[ $row->enrollment_id ] ); ?></span>
											<?php else : ?>
												<span style="color:#bbb;">—</span>
											<?php endif; ?>
										<?php else : ?>
											<input type="text" name="att_notes[<?php echo esc_attr( $row->enrollment_id ); ?>]" placeholder="Nhận xét..." style="width:100%; font-size:12px; padding:4px 6px;">
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( count( $already_marked ) < count( $enrolled ) ) : ?>
						<div style="margin-top:16px;">
							<button type="submit" class="button button-primary button-hero"><?php esc_html_e( '💾 Lưu điểm danh', 'usm' ); ?></button>
						</div>
					<?php else : ?>
						<p style="margin-top:12px; color:#00a32a; font-weight:600;"><?php esc_html_e( '✓ Đã điểm danh đầy đủ cho buổi này.', 'usm' ); ?></p>
					<?php endif; ?>
				</form>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
