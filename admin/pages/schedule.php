<?php
/**
 * Schedule – Lịch dạy (Session scheduling).
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table  = $wpdb->prefix . 'usm_sessions';
$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// ── Handle form submissions ─────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_session_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_session_nonce'] ) ), 'usm_save_session' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}

	if ( ! current_user_can( 'manage_usm_classes' ) ) {
		wp_die( esc_html__( 'Bạn không có quyền thực hiện hành động này.', 'usm' ) );
	}

	$course_id    = absint( $_POST['course_id'] ?? 0 );
	$session_date = sanitize_text_field( wp_unslash( $_POST['session_date'] ?? '' ) );
	$start_time   = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) );
	$end_time     = sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '' ) );
	$coach_id     = absint( $_POST['coach_id'] ?? 0 );
	$facility_id  = absint( $_POST['facility_id'] ?? 0 );
	$notes        = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
	$session_id   = absint( $_POST['session_id'] ?? 0 );

	$errors = array();
	if ( $course_id <= 0 ) {
		$errors[] = 'Vui lòng chọn khóa học.';
	}
	if ( empty( $session_date ) ) {
		$errors[] = 'Vui lòng chọn ngày.';
	}
	if ( empty( $start_time ) || empty( $end_time ) ) {
		$errors[] = 'Vui lòng nhập giờ bắt đầu và kết thúc.';
	}
	if ( ! empty( $start_time ) && ! empty( $end_time ) && $start_time >= $end_time ) {
		$errors[] = 'Giờ kết thúc phải sau giờ bắt đầu.';
	}

	if ( ! empty( $errors ) ) {
		foreach ( $errors as $err ) {
			USM_Helpers::admin_notice( $err, 'error' );
		}
	} else {
		$data = array(
			'course_id'    => $course_id,
			'session_date' => $session_date,
			'start_time'   => $start_time,
			'end_time'     => $end_time,
			'coach_id'     => $coach_id > 0 ? $coach_id : null,
			'facility_id'  => $facility_id > 0 ? $facility_id : null,
			'notes'        => $notes ?: null,
		);

		if ( $session_id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $session_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã cập nhật buổi học.' );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã thêm buổi học thành công.' );
		}
		$action = 'list';
	}
}

// ── Batch session generator ────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_batch_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_batch_nonce'] ) ), 'usm_batch_sessions' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}
	if ( ! current_user_can( 'manage_usm_classes' ) ) {
		wp_die( esc_html__( 'Bạn không có quyền.', 'usm' ) );
	}

	$batch_course   = absint( $_POST['batch_course_id'] ?? 0 );
	$batch_start    = sanitize_text_field( wp_unslash( $_POST['batch_start_date'] ?? '' ) );
	$batch_time_s   = sanitize_text_field( wp_unslash( $_POST['batch_start_time'] ?? '' ) );
	$batch_time_e   = sanitize_text_field( wp_unslash( $_POST['batch_end_time'] ?? '' ) );
	$batch_count    = absint( $_POST['batch_count'] ?? 0 );
	$batch_coach    = absint( $_POST['batch_coach_id'] ?? 0 );
	$batch_facility = absint( $_POST['batch_facility_id'] ?? 0 );
	$batch_days     = isset( $_POST['batch_days'] ) && is_array( $_POST['batch_days'] ) ? array_map( 'absint', $_POST['batch_days'] ) : array();

	$batch_errors = array();
	if ( $batch_course <= 0 ) { $batch_errors[] = 'Chọn khóa học.'; }
	if ( empty( $batch_start ) ) { $batch_errors[] = 'Chọn ngày bắt đầu.'; }
	if ( empty( $batch_time_s ) || empty( $batch_time_e ) ) { $batch_errors[] = 'Nhập giờ.'; }
	if ( $batch_count <= 0 || $batch_count > 100 ) { $batch_errors[] = 'Số buổi 1-100.'; }
	if ( empty( $batch_days ) ) { $batch_errors[] = 'Chọn ít nhất 1 ngày trong tuần.'; }

	if ( ! empty( $batch_errors ) ) {
		foreach ( $batch_errors as $err ) {
			USM_Helpers::admin_notice( $err, 'error' );
		}
	} else {
		// PHP: 1=Mon...7=Sun.
		$created = 0;
		$current = strtotime( $batch_start );
		$max_iterations = $batch_count * 30; // safety limit
		$iteration = 0;

		while ( $created < $batch_count && $iteration < $max_iterations ) {
			$iteration++;
			$dow = (int) date( 'N', $current ); // 1=Mon...7=Sun
			if ( in_array( $dow, $batch_days, true ) ) {
				$wpdb->insert( $table, array(
					'course_id'    => $batch_course,
					'session_date' => date( 'Y-m-d', $current ),
					'start_time'   => $batch_time_s,
					'end_time'     => $batch_time_e,
					'coach_id'     => $batch_coach > 0 ? $batch_coach : null,
					'facility_id'  => $batch_facility > 0 ? $batch_facility : null,
					'created_at'   => current_time( 'mysql' ),
				) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$created++;
			}
			$current = strtotime( '+1 day', $current );
		}

		USM_Helpers::admin_notice( sprintf( '⚡ Đã tạo %d buổi học thành công!', $created ) );
		$action = 'list';
	}
}

// ── Delete session ──────────────────────────────
if ( 'delete' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$del_id = absint( $_GET['id'] );
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_delete_session_' . $del_id ) ) {
		// Check no attendance records exist.
		$has_attendance = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}usm_attendance WHERE session_id = %d",
			$del_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( $has_attendance > 0 ) {
			USM_Helpers::admin_notice( 'Không thể xoá buổi học đã có điểm danh.', 'error' );
		} else {
			$wpdb->delete( $table, array( 'id' => $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã xoá buổi học.' );
		}
	}
	$action = 'list';
}

// ── Edit: load record ───────────────────────────
$edit_record = null;
if ( 'edit' === $action && isset( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$edit_id     = absint( $_GET['id'] );
	$edit_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $edit_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// ── Dropdown data ───────────────────────────────
$courses    = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}usm_courses WHERE is_active = 1 ORDER BY name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$coaches    = $wpdb->get_results( "SELECT id, full_name FROM {$wpdb->prefix}usm_coaches WHERE is_active = 1 ORDER BY full_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$facilities = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}usm_facilities WHERE is_active = 1 ORDER BY name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// ── Week navigation ─────────────────────────────
$week_offset = absint( $_GET['week'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$direction   = isset( $_GET['dir'] ) ? sanitize_text_field( wp_unslash( $_GET['dir'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( 'prev' === $direction ) {
	$week_offset = absint( $_GET['week'] ?? 1 );
}

$week_start_ts = strtotime( "monday this week" . ( $week_offset > 0 ? ( 'prev' === $direction ? " -{$week_offset} weeks" : " +{$week_offset} weeks" ) : '' ) );
$week_start    = wp_date( 'Y-m-d', $week_start_ts );
$week_end      = wp_date( 'Y-m-d', strtotime( $week_start . ' +6 days' ) );
?>

<div class="wrap usm-wrap">
	<div class="usm-header">
		<h1><?php esc_html_e( 'Lịch dạy', 'usm' ); ?></h1>
		<div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule&action=add' ) ); ?>" class="button button-primary">
				<?php esc_html_e( '+ Thêm buổi học', 'usm' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule&action=batch' ) ); ?>" class="button" style="background:#2271b1; color:#fff; border-color:#2271b1;">
				<?php esc_html_e( '⚡ Tạo lịch hàng loạt', 'usm' ); ?>
			</a>
		</div>
	</div>

	<?php if ( 'batch' === $action ) : ?>
		<div class="usm-form">
			<h2><?php esc_html_e( '⚡ Tạo lịch hàng loạt', 'usm' ); ?></h2>
			<p style="color:#646970;"><?php esc_html_e( 'Tạo nhiều buổi học cùng lúc. VD: Pickleball 12 buổi, thứ 2-4-6, 8h-9h30.', 'usm' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'usm_batch_sessions', 'usm_batch_nonce' ); ?>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Khóa học', 'usm' ); ?></label>
					<?php $pre_course_id = absint( $_GET['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<select name="batch_course_id" required>
						<option value=""><?php esc_html_e( '— Chọn khóa học —', 'usm' ); ?></option>
						<?php foreach ( $courses as $c ) : ?>
							<option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $pre_course_id, $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Ngày trong tuần', 'usm' ); ?></label>
					<div style="display:flex; gap:12px; flex-wrap:wrap;">
						<?php
						$day_labels = array( 1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN' );
						foreach ( $day_labels as $val => $label ) : ?>
							<label style="cursor:pointer; padding:6px 12px; background:#f6f7f7; border-radius:4px; border:1px solid #c3c4c7;">
								<input type="checkbox" name="batch_days[]" value="<?php echo esc_attr( $val ); ?>"> <?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Giờ bắt đầu', 'usm' ); ?></label>
					<input type="time" name="batch_start_time" required>
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Giờ kết thúc', 'usm' ); ?></label>
					<input type="time" name="batch_end_time" required>
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Ngày bắt đầu', 'usm' ); ?></label>
					<input type="date" name="batch_start_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Số buổi cần tạo', 'usm' ); ?></label>
					<input type="number" name="batch_count" value="12" min="1" max="100" required>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'HLV', 'usm' ); ?></label>
					<select name="batch_coach_id">
						<option value="0"><?php esc_html_e( '— Mặc định theo khóa —', 'usm' ); ?></option>
						<?php foreach ( $coaches as $coach ) : ?>
							<option value="<?php echo esc_attr( $coach->id ); ?>"><?php echo esc_html( $coach->full_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Cơ sở', 'usm' ); ?></label>
					<select name="batch_facility_id">
						<option value="0"><?php esc_html_e( '— Mặc định theo khóa —', 'usm' ); ?></option>
						<?php foreach ( $facilities as $fac ) : ?>
							<option value="<?php echo esc_attr( $fac->id ); ?>"><?php echo esc_html( $fac->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( '⚡ Tạo lịch', 'usm' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule' ) ); ?>" class="button"><?php esc_html_e( 'Huỷ', 'usm' ); ?></a>
			</form>
		</div>

	<?php elseif ( 'add' === $action || 'edit' === $action ) : ?>
		<div class="usm-form">
			<h2><?php echo $edit_record ? esc_html__( 'Sửa buổi học', 'usm' ) : esc_html__( 'Thêm buổi học mới', 'usm' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'usm_save_session', 'usm_session_nonce' ); ?>
				<input type="hidden" name="session_id" value="<?php echo esc_attr( $edit_record->id ?? 0 ); ?>">

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Khóa học', 'usm' ); ?></label>
					<select name="course_id" required>
						<option value=""><?php esc_html_e( '— Chọn khóa học —', 'usm' ); ?></option>
						<?php foreach ( $courses as $c ) : ?>
							<option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $edit_record->course_id ?? '', $c->id ); ?>>
								<?php echo esc_html( $c->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Ngày', 'usm' ); ?></label>
					<input type="date" name="session_date" value="<?php echo esc_attr( $edit_record->session_date ?? current_time( 'Y-m-d' ) ); ?>" required>
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Giờ bắt đầu', 'usm' ); ?></label>
					<input type="time" name="start_time" value="<?php echo esc_attr( $edit_record->start_time ?? '' ); ?>" required>
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Giờ kết thúc', 'usm' ); ?></label>
					<input type="time" name="end_time" value="<?php echo esc_attr( $edit_record->end_time ?? '' ); ?>" required>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'HLV (ghi đè)', 'usm' ); ?></label>
					<select name="coach_id">
						<option value="0"><?php esc_html_e( '— Mặc định theo khóa học —', 'usm' ); ?></option>
						<?php foreach ( $coaches as $coach ) : ?>
							<option value="<?php echo esc_attr( $coach->id ); ?>" <?php selected( $edit_record->coach_id ?? '', $coach->id ); ?>>
								<?php echo esc_html( $coach->full_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Cơ sở (ghi đè)', 'usm' ); ?></label>
					<select name="facility_id">
						<option value="0"><?php esc_html_e( '— Mặc định theo khóa học —', 'usm' ); ?></option>
						<?php foreach ( $facilities as $fac ) : ?>
							<option value="<?php echo esc_attr( $fac->id ); ?>" <?php selected( $edit_record->facility_id ?? '', $fac->id ); ?>>
								<?php echo esc_html( $fac->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Ghi chú', 'usm' ); ?></label>
					<textarea name="notes"><?php echo esc_textarea( $edit_record->notes ?? '' ); ?></textarea>
				</div>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu', 'usm' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule' ) ); ?>" class="button"><?php esc_html_e( 'Huỷ', 'usm' ); ?></a>
			</form>
		</div>

	<?php else : ?>
		<?php
		$view_mode = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : 'week'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>

		<!-- View toggle -->
		<div style="margin-bottom:12px; display:flex; gap:6px; align-items:center;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule&view=week' ) ); ?>" class="button <?php echo 'week' === $view_mode ? 'button-primary' : ''; ?>">
				📅 <?php esc_html_e( 'Tuần', 'usm' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule&view=month' ) ); ?>" class="button <?php echo 'month' === $view_mode ? 'button-primary' : ''; ?>">
				🗓️ <?php esc_html_e( 'Tháng', 'usm' ); ?>
			</a>
		</div>

		<?php if ( 'month' === $view_mode ) : ?>
		<?php
		// ── Month calendar ──────────────────────────────
		$cal_month = isset( $_GET['cal_month'] ) ? absint( $_GET['cal_month'] ) : (int) current_time( 'm' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cal_year  = isset( $_GET['cal_year'] ) ? absint( $_GET['cal_year'] ) : (int) current_time( 'Y' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Sanitize month bounds.
		if ( $cal_month < 1 ) { $cal_month = 12; $cal_year--; }
		if ( $cal_month > 12 ) { $cal_month = 1; $cal_year++; }

		$prev_m = $cal_month - 1;
		$prev_y = $cal_year;
		if ( $prev_m < 1 ) { $prev_m = 12; $prev_y--; }
		$next_m = $cal_month + 1;
		$next_y = $cal_year;
		if ( $next_m > 12 ) { $next_m = 1; $next_y++; }

		$first_day   = sprintf( '%04d-%02d-01', $cal_year, $cal_month );
		$days_in     = (int) wp_date( 't', strtotime( $first_day ) );
		$last_day    = sprintf( '%04d-%02d-%02d', $cal_year, $cal_month, $days_in );
		$first_dow   = (int) wp_date( 'N', strtotime( $first_day ) ); // 1=Mon .. 7=Sun.

		$month_names = array( '', 'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12' );

		// Fetch sessions.
		$month_sessions = $wpdb->get_results( $wpdb->prepare(
			"SELECT s.*, c.name AS course_name, co.full_name AS coach_name, f.name AS facility_name
			 FROM {$table} s
			 LEFT JOIN {$wpdb->prefix}usm_courses c ON s.course_id = c.id
			 LEFT JOIN {$wpdb->prefix}usm_coaches co ON COALESCE(s.coach_id, c.coach_id) = co.id
			 LEFT JOIN {$wpdb->prefix}usm_facilities f ON COALESCE(s.facility_id, c.facility_id) = f.id
			 WHERE s.session_date BETWEEN %s AND %s
			 ORDER BY s.session_date, s.start_time",
			$first_day,
			$last_day
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$m_grouped = array();
		foreach ( $month_sessions as $sess ) {
			$m_grouped[ $sess->session_date ][] = $sess;
		}

		// Color palette for courses.
		$course_colors = array( '#2271b1', '#d63638', '#00a32a', '#dba617', '#8c5e9b', '#e36d00', '#0073aa', '#bb77ae' );
		$course_cmap   = array();
		$ci = 0;
		foreach ( $month_sessions as $s ) {
			if ( ! isset( $course_cmap[ $s->course_id ] ) ) {
				$course_cmap[ $s->course_id ] = $course_colors[ $ci % count( $course_colors ) ];
				$ci++;
			}
		}

		$today_str = current_time( 'Y-m-d' );
		?>

		<style>
		.usm-cal-nav { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
		.usm-cal-nav h2 { margin:0; font-size:18px; min-width:180px; text-align:center; }
		.usm-cal { border-collapse:collapse; width:100%; table-layout:fixed; background:#fff; }
		.usm-cal th { background:#f0f0f1; padding:8px 4px; text-align:center; font-size:13px; font-weight:600; border:1px solid #ddd; }
		.usm-cal td { border:1px solid #e0e0e0; vertical-align:top; padding:4px; min-height:80px; height:90px; font-size:12px; }
		.usm-cal td.today { background:#e7f5ff; }
		.usm-cal td.other-month { background:#f9f9f9; color:#bbb; }
		.usm-cal .day-num { font-weight:600; font-size:13px; color:#1d2327; margin-bottom:2px; }
		.usm-cal td.today .day-num { color:#2271b1; }
		.usm-cal .sess-pill { display:block; padding:2px 5px; margin:1px 0; border-radius:3px; color:#fff; font-size:11px; line-height:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; cursor:default; }
		@media print { .usm-cal-nav a, .usm-header, .usm-cal .sess-pill a { display:none; } .usm-cal { font-size:10px; } }
		</style>

		<!-- Month navigation -->
		<div class="usm-cal-nav">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule&view=month&cal_month=' . $prev_m . '&cal_year=' . $prev_y ) ); ?>" class="button">← <?php esc_html_e( 'Trước', 'usm' ); ?></a>
			<h2><?php echo esc_html( $month_names[ $cal_month ] . ' ' . $cal_year ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule&view=month&cal_month=' . $next_m . '&cal_year=' . $next_y ) ); ?>" class="button"><?php esc_html_e( 'Sau', 'usm' ); ?> →</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule&view=month' ) ); ?>" class="button"><?php esc_html_e( 'Tháng này', 'usm' ); ?></a>
			<button onclick="window.print()" class="button">🖨️ <?php esc_html_e( 'In', 'usm' ); ?></button>
		</div>

		<!-- Calendar grid -->
		<table class="usm-cal">
			<thead>
				<tr>
					<th><?php esc_html_e( 'T2', 'usm' ); ?></th>
					<th><?php esc_html_e( 'T3', 'usm' ); ?></th>
					<th><?php esc_html_e( 'T4', 'usm' ); ?></th>
					<th><?php esc_html_e( 'T5', 'usm' ); ?></th>
					<th><?php esc_html_e( 'T6', 'usm' ); ?></th>
					<th><?php esc_html_e( 'T7', 'usm' ); ?></th>
					<th><?php esc_html_e( 'CN', 'usm' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			$cell = 1;
			$day  = 1;

			for ( $row = 0; $row < 6; $row++ ) :
				if ( $day > $days_in ) { break; }
				echo '<tr>';
				for ( $col = 1; $col <= 7; $col++ ) :
					if ( ( $row === 0 && $col < $first_dow ) || $day > $days_in ) {
						echo '<td class="other-month"></td>';
					} else {
						$date_str    = sprintf( '%04d-%02d-%02d', $cal_year, $cal_month, $day );
						$is_today    = ( $date_str === $today_str );
						$day_s       = $m_grouped[ $date_str ] ?? array();
						$td_class    = $is_today ? 'today' : '';
						echo '<td class="' . esc_attr( $td_class ) . '">';
						echo '<div class="day-num">' . esc_html( $day ) . '</div>';
						foreach ( $day_s as $s ) {
							$bg    = $course_cmap[ $s->course_id ] ?? '#2271b1';
							$tip   = esc_attr( $s->course_name . ' ' . substr( $s->start_time, 0, 5 ) . '-' . substr( $s->end_time, 0, 5 ) );
							if ( $s->coach_name ) { $tip .= ' | HLV: ' . $s->coach_name; }
							if ( $s->facility_name ) { $tip .= ' | ' . $s->facility_name; }
							echo '<span class="sess-pill" style="background:' . esc_attr( $bg ) . '" title="' . $tip . '">';
							echo esc_html( substr( $s->start_time, 0, 5 ) . ' ' . $s->course_name );
							echo '</span>';
						}
						echo '</td>';
						$day++;
					}
				endfor;
				echo '</tr>';
			endfor;
			?>
			</tbody>
		</table>

		<!-- Legend -->
		<?php if ( ! empty( $course_cmap ) ) : ?>
		<div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:10px; font-size:12px;">
			<?php foreach ( $course_cmap as $cid => $color ) :
				$cname = '';
				foreach ( $month_sessions as $ss ) { if ( $ss->course_id == $cid ) { $cname = $ss->course_name; break; } }
			?>
			<span><span style="display:inline-block; width:12px; height:12px; border-radius:2px; background:<?php echo esc_attr( $color ); ?>; vertical-align:middle;"></span> <?php echo esc_html( $cname ); ?></span>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php else : ?>
		<!-- Weekly view (existing) -->
		<div style="margin-bottom: 16px; display:flex; align-items:center; gap:8px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule&view=week&week=' . ( $week_offset + 1 ) . '&dir=prev' ) ); ?>" class="button">
				<?php esc_html_e( '← Tuần trước', 'usm' ); ?>
			</a>
			<strong>
				<?php echo esc_html( wp_date( 'd/m', $week_start_ts ) . ' – ' . wp_date( 'd/m/Y', strtotime( $week_end ) ) ); ?>
			</strong>
			<?php if ( $week_offset > 0 ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule&view=week&week=' . max( 0, $week_offset - 1 ) . '&dir=next' ) ); ?>" class="button">
					<?php esc_html_e( 'Tuần sau →', 'usm' ); ?>
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule&view=week' ) ); ?>" class="button"><?php esc_html_e( 'Hôm nay', 'usm' ); ?></a>
		</div>

		<?php
		// Fetch sessions for this week.
		$week_sessions = $wpdb->get_results( $wpdb->prepare(
			"SELECT s.*, c.name AS course_name, co.full_name AS coach_name, f.name AS facility_name
			 FROM {$table} s
			 LEFT JOIN {$wpdb->prefix}usm_courses c ON s.course_id = c.id
			 LEFT JOIN {$wpdb->prefix}usm_coaches co ON COALESCE(s.coach_id, c.coach_id) = co.id
			 LEFT JOIN {$wpdb->prefix}usm_facilities f ON COALESCE(s.facility_id, c.facility_id) = f.id
			 WHERE s.session_date BETWEEN %s AND %s
			 ORDER BY s.session_date, s.start_time",
			$week_start,
			$week_end
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Group by day.
		$grouped = array();
		foreach ( $week_sessions as $sess ) {
			$grouped[ $sess->session_date ][] = $sess;
		}

		$day_names = array( 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN' );
		?>

		<table class="usm-table" style="table-layout:fixed;">
			<thead>
				<tr>
					<?php for ( $d = 0; $d < 7; $d++ ) :
						$day_date = wp_date( 'Y-m-d', strtotime( $week_start . " +{$d} days" ) );
						$is_today = $day_date === current_time( 'Y-m-d' );
					?>
						<th style="<?php echo $is_today ? 'background:#e7f5fe;' : ''; ?> text-align:center; width:14.28%;">
							<?php echo esc_html( $day_names[ $d ] ); ?><br>
							<small><?php echo esc_html( wp_date( 'd/m', strtotime( $day_date ) ) ); ?></small>
						</th>
					<?php endfor; ?>
				</tr>
			</thead>
			<tbody>
				<tr>
					<?php for ( $d = 0; $d < 7; $d++ ) :
						$day_date = wp_date( 'Y-m-d', strtotime( $week_start . " +{$d} days" ) );
						$day_sessions = $grouped[ $day_date ] ?? array();
					?>
						<td style="vertical-align:top; padding:8px;">
							<?php foreach ( $day_sessions as $sess ) : ?>
								<div style="background:#f6f7f7; border-left:3px solid #2271b1; padding:6px 8px; margin-bottom:6px; border-radius:3px; font-size:12px;">
									<strong><?php echo esc_html( $sess->course_name ); ?></strong><br>
									<?php echo esc_html( substr( $sess->start_time, 0, 5 ) . ' – ' . substr( $sess->end_time, 0, 5 ) ); ?><br>
									<?php if ( $sess->coach_name ) : ?>
										<span style="color:#555;">🏃 <?php echo esc_html( $sess->coach_name ); ?></span><br>
									<?php endif; ?>
									<?php if ( $sess->facility_name ) : ?>
										<span style="color:#555;">📍 <?php echo esc_html( $sess->facility_name ); ?></span><br>
									<?php endif; ?>
									<div style="margin-top:4px;">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-attendance&session_id=' . $sess->id ) ); ?>" style="font-size:11px;">📋</a>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-schedule&action=edit&id=' . $sess->id ) ); ?>" style="font-size:11px;">Sửa</a>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-schedule&action=delete&id=' . $sess->id ), 'usm_delete_session_' . $sess->id ) ); ?>" class="usm-confirm-delete" style="font-size:11px; color:#d63638;">Xoá</a>
										<a href="#" onclick="usmShowQR('<?php echo esc_url( admin_url( 'admin.php?page=usm-attendance&session_id=' . $sess->id ) ); ?>', '<?php echo esc_js( $sess->course_name . ' – ' . substr( $sess->start_time, 0, 5 ) ); ?>'); return false;" style="font-size:11px; color:#2271b1; font-weight:bold;">📱QR</a>
									</div>
								</div>
							<?php endforeach; ?>
							<?php if ( empty( $day_sessions ) ) : ?>
								<span style="color:#999; font-size:12px;">—</span>
							<?php endif; ?>
						</td>
					<?php endfor; ?>
				</tr>
			</tbody>
		</table>

		<!-- QR Modal -->
		<div id="usm-qr-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); z-index:999999; justify-content:center; align-items:center; flex-direction:column;">
			<div style="background:#fff; border-radius:16px; padding:32px; text-align:center; max-width:400px; width:90%;">
				<h2 id="usm-qr-title" style="margin-top:0; font-size:18px;"></h2>
				<div id="usm-qr-code" style="margin:20px auto;"></div>
				<p style="color:#646970; font-size:13px;"><?php esc_html_e( 'Quét mã QR để mở trang điểm danh', 'usm' ); ?></p>
				<button onclick="document.getElementById('usm-qr-modal').style.display='none';" class="button" style="margin-top:12px;"><?php esc_html_e( 'Đóng', 'usm' ); ?></button>
			</div>
		</div>

		<?php endif; ?>

	<?php endif; ?>
</div>
