<?php
/**
 * Enrollments CRUD – Đăng ký học + Pause/Resume.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table  = $wpdb->prefix . 'usm_enrollments';
$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// ── Print receipt (standalone page) ─────────────
if ( 'print' === $action && isset( $_GET['id'] ) ) {
	require __DIR__ . '/print-receipt.php';
}

// ── On-page-load expiry check (Layer 1 from FLOW 8) ──
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"UPDATE {$table} SET status = %s, updated_at = %s WHERE status = %s AND end_date IS NOT NULL AND end_date < %s",
		'expired',
		current_time( 'mysql' ),
		'active',
		current_time( 'Y-m-d' )
	)
);

// ── Handle new enrollment ───────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_enrollment_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_enrollment_nonce'] ) ), 'usm_save_enrollment' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}

	if ( ! current_user_can( 'manage_usm_enrollments' ) ) {
		wp_die( esc_html__( 'Bạn không có quyền thực hiện hành động này.', 'usm' ) );
	}

	$student_id     = absint( $_POST['student_id'] ?? 0 );
	$package_id     = absint( $_POST['package_id'] ?? 0 );
	$amount_paid    = floatval( $_POST['amount_paid'] ?? 0 );
	$payment_status = sanitize_text_field( wp_unslash( $_POST['payment_status'] ?? 'unpaid' ) );
	$notes          = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

	$errors = array();
	if ( $student_id <= 0 ) {
		$errors[] = 'Vui lòng chọn học viên.';
	}
	if ( $package_id <= 0 ) {
		$errors[] = 'Vui lòng chọn gói học.';
	}

	if ( ! empty( $errors ) ) {
		foreach ( $errors as $err ) {
			USM_Helpers::admin_notice( $err, 'error' );
		}
	} else {
		// Fetch package for snapshot.
		$package = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}usm_packages WHERE id = %d AND is_active = 1",
			$package_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $package ) {
			USM_Helpers::admin_notice( 'Gói học không tồn tại hoặc đã ngưng.', 'error' );
		} else {
			$enroll_date = current_time( 'Y-m-d' );
			$end_date    = null;

			// Snapshot sessions.
			$sessions_total     = $package->sessions ? (int) $package->sessions : 0;
			$sessions_remaining = $sessions_total;

			// Calculate end_date for time-type.
			if ( 'time' === $package->type && $package->duration_days > 0 ) {
				$end_date = wp_date( 'Y-m-d', strtotime( "+{$package->duration_days} days" ) );
			}

			$data = array(
				'student_id'         => $student_id,
				'package_id'         => $package_id,
				'enroll_date'        => $enroll_date,
				'start_date'         => $enroll_date,
				'end_date'           => $end_date,
				'sessions_total'     => $sessions_total,
				'sessions_remaining' => $sessions_remaining,
				'amount_paid'        => $amount_paid,
				'payment_status'     => $payment_status,
				'status'             => 'active',
				'notes'              => $notes ?: null,
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			);

			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$new_id = $wpdb->insert_id;

			// Insert revenue record if amount > 0.
			if ( $amount_paid > 0 ) {
				$wpdb->insert( $wpdb->prefix . 'usm_revenue', array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					'enrollment_id' => $new_id,
					'amount'        => $amount_paid,
					'payment_type'  => 'paid' === $payment_status ? 'full' : 'deposit',
					'collected_by'  => get_current_user_id(),
					'collected_at'  => current_time( 'mysql' ),
					'notes'         => 'Tự động tạo khi đăng ký.',
				) );
			}

			// Check if course has scheduled sessions already.
			$has_sessions = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}usm_sessions WHERE course_id = %d AND session_date >= %s",
				$package->course_id,
				current_time( 'Y-m-d' )
			) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$msg = 'Đã đăng ký học thành công.';
			if ( 0 === $has_sessions ) {
				$batch_url = admin_url( 'admin.php?page=usm-schedule&action=batch&course_id=' . $package->course_id );
				$msg .= ' <a href="' . esc_url( $batch_url ) . '" style="font-weight:600;">📅 Tạo lịch học cho khóa này →</a>';
			}
			USM_Helpers::admin_notice( $msg );

			// Send welcome notification.
			USM_Notifications::on_new_enrollment( $new_id );

			$action = 'list';
		}
	}
}

// ── Pause enrollment ────────────────────────────
if ( 'pause' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$pause_id = absint( $_GET['id'] );

	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_pause_enrollment_' . $pause_id ) ) {
		$enrollment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $pause_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $enrollment ) {
			USM_Helpers::admin_notice( 'Không tìm thấy đăng ký.', 'error' );
		} elseif ( 'active' !== $enrollment->status ) {
			USM_Helpers::admin_notice( 'Chỉ có thể tạm hoãn đăng ký đang hoạt động.', 'error' );
		} elseif ( (int) $enrollment->pause_used >= (int) USM_Helpers::get_setting( 'max_pause_count', 1 ) ) {
			USM_Helpers::admin_notice( sprintf( 'Đăng ký này đã được tạm hoãn %d lần. Không thể tạm hoãn thêm.', (int) USM_Helpers::get_setting( 'max_pause_count', 1 ) ), 'error' );
		} else {
			$wpdb->update( $table, array(
				'status'     => 'paused',
				'pause_used' => 1,
				'updated_at' => current_time( 'mysql' ),
			), array( 'id' => $pause_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$wpdb->insert( $wpdb->prefix . 'usm_pause_logs', array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				'enrollment_id' => $pause_id,
				'pause_date'    => current_time( 'Y-m-d' ),
				'created_at'    => current_time( 'mysql' ),
			) );

			USM_Helpers::admin_notice( 'Đã tạm hoãn đăng ký.' );
		}
	}
	$action = 'list';
}

// ── Resume enrollment ───────────────────────────
if ( 'resume' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$resume_id = absint( $_GET['id'] );

	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_resume_enrollment_' . $resume_id ) ) {
		$enrollment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $resume_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $enrollment ) {
			USM_Helpers::admin_notice( 'Không tìm thấy đăng ký.', 'error' );
		} elseif ( 'paused' !== $enrollment->status ) {
			USM_Helpers::admin_notice( 'Chỉ có thể kích hoạt lại đăng ký đang tạm hoãn.', 'error' );
		} else {
			// Calculate days paused and extend end_date.
			$pause_log = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}usm_pause_logs WHERE enrollment_id = %d AND resume_date IS NULL ORDER BY id DESC LIMIT 1",
				$resume_id
			) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$days_paused = 0;
			if ( $pause_log ) {
				$days_paused = max( 0, (int) ( ( strtotime( current_time( 'Y-m-d' ) ) - strtotime( $pause_log->pause_date ) ) / DAY_IN_SECONDS ) );

				$wpdb->update( $wpdb->prefix . 'usm_pause_logs', array(
					'resume_date' => current_time( 'Y-m-d' ),
					'days_paused' => $days_paused,
				), array( 'id' => $pause_log->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}

			$new_end_date = $enrollment->end_date;
			if ( $enrollment->end_date && $days_paused > 0 ) {
				$new_end_date = wp_date( 'Y-m-d', strtotime( $enrollment->end_date . " +{$days_paused} days" ) );
			}

			$wpdb->update( $table, array(
				'status'     => 'active',
				'end_date'   => $new_end_date,
				'updated_at' => current_time( 'mysql' ),
			), array( 'id' => $resume_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$msg = 'Đã kích hoạt lại đăng ký.';
			if ( $days_paused > 0 && $new_end_date !== $enrollment->end_date ) {
				$msg .= " Hạn mới: {$new_end_date} (gia hạn {$days_paused} ngày).";
			}
			USM_Helpers::admin_notice( $msg );
		}
	}
	$action = 'list';
}

// ── Delete enrollment ────────────────────────
if ( 'delete' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$del_id = absint( $_GET['id'] );
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_delete_enrollment_' . $del_id ) ) {
		// Delete related attendance records first.
		$session_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}usm_sessions WHERE course_id IN (SELECT c.id FROM {$wpdb->prefix}usm_courses c JOIN {$wpdb->prefix}usm_packages p ON p.course_id = c.id JOIN {$wpdb->prefix}usm_enrollments e ON e.package_id = p.id WHERE e.id = %d)",
			$del_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// Delete attendance for this student.
		$enrollment = $wpdb->get_row( $wpdb->prepare( "SELECT student_id FROM {$table} WHERE id = %d", $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $enrollment && ! empty( $session_ids ) ) {
			foreach ( $session_ids as $sid ) {
				$wpdb->delete( $wpdb->prefix . 'usm_attendance', array( 'session_id' => $sid, 'student_id' => $enrollment->student_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		// Delete pause logs.
		$wpdb->delete( $wpdb->prefix . 'usm_pause_logs', array( 'enrollment_id' => $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// Delete enrollment.
		$wpdb->delete( $table, array( 'id' => $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		USM_Helpers::admin_notice( 'Đã xoá đăng ký học.' );
	}
	$action = 'list';
}

// ── Record payment ──────────────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_payment_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_payment_nonce'] ) ), 'usm_record_payment' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}
	if ( ! current_user_can( 'manage_usm_data' ) ) {
		wp_die( esc_html__( 'Bạn không có quyền.', 'usm' ) );
	}

	$pay_enrollment_id = absint( $_POST['enrollment_id'] ?? 0 );
	$pay_amount        = floatval( $_POST['pay_amount'] ?? 0 );
	$pay_method        = sanitize_text_field( wp_unslash( $_POST['pay_method'] ?? 'cash' ) );
	$pay_note          = sanitize_textarea_field( wp_unslash( $_POST['pay_note'] ?? '' ) );

	if ( $pay_enrollment_id > 0 && $pay_amount > 0 ) {
		// Insert revenue record.
		$wpdb->insert( $wpdb->prefix . 'usm_revenue', array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'enrollment_id' => $pay_enrollment_id,
			'amount'        => $pay_amount,
			'payment_type'  => $pay_method,
			'collected_by'  => get_current_user_id(),
			'collected_at'  => current_time( 'mysql' ),
			'notes'         => $pay_note ?: null,
		) );

		// Recalculate total paid.
		$total_paid = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(amount) FROM {$wpdb->prefix}usm_revenue WHERE enrollment_id = %d",
			$pay_enrollment_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$enrollment_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT e.amount_paid, p.price AS package_price FROM {$table} e LEFT JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id WHERE e.id = %d", $pay_enrollment_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$new_pay_status = 'unpaid';
		if ( $total_paid >= (float) $enrollment_row->package_price ) {
			$new_pay_status = 'paid';
		} elseif ( $total_paid > 0 ) {
			$new_pay_status = 'partial';
		}

		$wpdb->update( $table, array(
			'amount_paid'     => $total_paid,
			'payment_status'  => $new_pay_status,
			'updated_at'      => current_time( 'mysql' ),
		), array( 'id' => $pay_enrollment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		USM_Helpers::admin_notice( sprintf( 'Đã ghi nhận thanh toán %s. Tổng đã thu: %s.', USM_Helpers::format_vnd( $pay_amount ), USM_Helpers::format_vnd( $total_paid ) ) );
		$action = 'payment';
		$_GET['id'] = $pay_enrollment_id;
	} else {
		USM_Helpers::admin_notice( 'Vui lòng nhập số tiền hợp lệ.', 'error' );
	}
}

// ── Dropdown data ───────────────────────────────
$students = $wpdb->get_results( "SELECT id, full_name FROM {$wpdb->prefix}usm_students WHERE is_active = 1 ORDER BY full_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$courses_dd = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}usm_courses WHERE is_active = 1 ORDER BY name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$packages = $wpdb->get_results(
	"SELECT p.id, p.name, p.type, p.sessions, p.duration_days, p.price, p.course_id, c.name AS course_name
	 FROM {$wpdb->prefix}usm_packages p
	 LEFT JOIN {$wpdb->prefix}usm_courses c ON p.course_id = c.id
	 WHERE p.is_active = 1
	 ORDER BY c.name, p.name"
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
?>

<div class="wrap usm-wrap">
	<div class="usm-header">
		<h1><?php esc_html_e( 'Quản lý Đăng ký học', 'usm' ); ?></h1>
		<?php if ( 'list' === $action ) : ?>
			<div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments&action=add' ) ); ?>" class="button button-primary">
					<?php esc_html_e( '+ Đăng ký mới', 'usm' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-enrollments&export=csv' ), 'usm_export_enrollments_csv' ) ); ?>" class="button">
					<?php esc_html_e( '📥 Xuất CSV', 'usm' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( 'add' === $action ) :
		$renew_student = absint( $_GET['renew_student'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$renew_package = absint( $_GET['renew_package'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
		<?php USM_Helpers::render_breadcrumb( 'usm-enrollments', 'Đăng ký học', $renew_student ? 'Gia hạn' : 'Đăng ký mới' ); ?>
		<div class="usm-form">
			<h2><?php echo $renew_student ? esc_html__( '🔄 Gia hạn đăng ký', 'usm' ) : esc_html__( 'Đăng ký học mới', 'usm' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'usm_save_enrollment', 'usm_enrollment_nonce' ); ?>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Học viên', 'usm' ); ?></label>
					<select name="student_id" required>
						<option value=""><?php esc_html_e( '— Chọn học viên —', 'usm' ); ?></option>
						<?php foreach ( $students as $s ) : ?>
							<option value="<?php echo esc_attr( $s->id ); ?>" <?php selected( $renew_student, $s->id ); ?>><?php echo esc_html( $s->full_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Khóa học', 'usm' ); ?></label>
					<select id="usm-enrollment-course" required>
						<option value=""><?php esc_html_e( '— Chọn khóa học trước —', 'usm' ); ?></option>
						<?php foreach ( $courses_dd as $c ) : ?>
							<option value="<?php echo esc_attr( $c->id ); ?>"><?php echo esc_html( $c->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Gói học', 'usm' ); ?></label>
					<select name="package_id" id="usm-enrollment-package" required>
						<option value=""><?php esc_html_e( '— Chọn khóa trước —', 'usm' ); ?></option>
						<?php foreach ( $packages as $p ) : ?>
							<option value="<?php echo esc_attr( $p->id ); ?>"
								data-course="<?php echo esc_attr( $p->course_id ); ?>"
								data-type="<?php echo esc_attr( $p->type ); ?>"
								data-sessions="<?php echo esc_attr( $p->sessions ); ?>"
								data-duration="<?php echo esc_attr( $p->duration_days ); ?>"
								data-price="<?php echo esc_attr( $p->price ); ?>"
								<?php selected( $renew_package, $p->id ); ?>>
								<?php echo esc_html( $p->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<div id="usm-package-preview" style="margin-top:8px; color:#555; font-size:13px;"></div>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Số tiền đã đóng (VNĐ)', 'usm' ); ?></label>
					<input type="number" name="amount_paid" value="0" min="0" step="1000">
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Trạng thái thanh toán', 'usm' ); ?></label>
					<select name="payment_status">
						<option value="paid"><?php esc_html_e( 'Đã đóng', 'usm' ); ?></option>
						<option value="deposit"><?php esc_html_e( 'Đặt cọc', 'usm' ); ?></option>
						<option value="unpaid" selected><?php esc_html_e( 'Chưa đóng', 'usm' ); ?></option>
					</select>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Ghi chú', 'usm' ); ?></label>
					<textarea name="notes"></textarea>
				</div>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Đăng ký', 'usm' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments' ) ); ?>" class="button"><?php esc_html_e( 'Huỷ', 'usm' ); ?></a>
			</form>
		</div>

		<script>
		(function() {
			var courseSel = document.getElementById('usm-enrollment-course');
			var pkgSel = document.getElementById('usm-enrollment-package');
			var preview = document.getElementById('usm-package-preview');
			var allOptions = Array.from(pkgSel.querySelectorAll('option[data-course]'));
			var defaultOpt = pkgSel.querySelector('option[value=""]');

			// Filter packages by course.
			courseSel.addEventListener('change', function() {
				var cid = courseSel.value;
				pkgSel.innerHTML = '';
				pkgSel.appendChild(defaultOpt);
				defaultOpt.textContent = cid ? '— Chọn gói học —' : '— Chọn khóa trước —';

				allOptions.forEach(function(opt) {
					if (opt.getAttribute('data-course') === cid) {
						pkgSel.appendChild(opt);
					}
				});
				pkgSel.value = '';
				preview.textContent = '';
			});

			// Show package details.
			pkgSel.addEventListener('change', function() {
				var opt = pkgSel.options[pkgSel.selectedIndex];
				if (!opt.value) { preview.textContent = ''; return; }
				var type = opt.getAttribute('data-type');
				var sessions = opt.getAttribute('data-sessions');
				var duration = opt.getAttribute('data-duration');
				var price = parseInt(opt.getAttribute('data-price')).toLocaleString('vi-VN');

				if (type === 'session') {
					preview.textContent = 'Số buổi: ' + sessions + ' | Học phí: ' + price + ' ₫';
				} else {
					preview.textContent = 'Thời hạn: ' + duration + ' ngày | Học phí: ' + price + ' ₫';
				}
			});
		})();
		</script>

	<?php elseif ( 'payment' === $action && isset( $_GET['id'] ) ) : ?>
		<?php
		$pay_id   = absint( $_GET['id'] );
		$pay_enr  = $wpdb->get_row( $wpdb->prepare(
			"SELECT e.*, s.full_name AS student_name, p.name AS package_name, p.price AS package_price
			 FROM {$table} e
			 LEFT JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
			 LEFT JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
			 WHERE e.id = %d", $pay_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$pay_logs = $wpdb->get_results( $wpdb->prepare(
			"SELECT r.*, u.display_name AS collector_name
			 FROM {$wpdb->prefix}usm_revenue r
			 LEFT JOIN {$wpdb->prefix}users u ON r.collected_by = u.ID
			 WHERE r.enrollment_id = %d ORDER BY r.collected_at DESC", $pay_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$total_collected = 0;
		foreach ( $pay_logs as $pl ) { $total_collected += (float) $pl->amount; }
		$still_owed = max( 0, (float) $pay_enr->package_price - $total_collected );
		?>

		<div class="usm-form" style="max-width:700px;">
			<h2>💰 <?php esc_html_e( 'Thanh toán', 'usm' ); ?> — <?php echo esc_html( $pay_enr->student_name ); ?></h2>
			<div style="background:#f6f7f7; padding:12px; border-radius:6px; margin-bottom:16px; font-size:13px;">
				<strong><?php echo esc_html( $pay_enr->package_name ); ?></strong><br>
				<?php esc_html_e( 'Học phí:', 'usm' ); ?> <strong><?php echo esc_html( USM_Helpers::format_vnd( $pay_enr->package_price ) ); ?></strong> |
				<?php esc_html_e( 'Đã thu:', 'usm' ); ?> <strong style="color:#00a32a;"><?php echo esc_html( USM_Helpers::format_vnd( $total_collected ) ); ?></strong> |
				<?php esc_html_e( 'Còn nợ:', 'usm' ); ?> <strong style="color:<?php echo $still_owed > 0 ? '#d63638' : '#00a32a'; ?>;"><?php echo esc_html( USM_Helpers::format_vnd( $still_owed ) ); ?></strong>
			</div>

			<?php if ( $still_owed > 0 ) : ?>
			<form method="post" style="margin-bottom:20px;">
				<?php wp_nonce_field( 'usm_record_payment', 'usm_payment_nonce' ); ?>
				<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( $pay_id ); ?>">

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Số tiền thu (VNĐ)', 'usm' ); ?></label>
					<input type="number" name="pay_amount" value="<?php echo esc_attr( (int) $still_owed ); ?>" min="1000" step="1000" required>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Phương thức', 'usm' ); ?></label>
					<select name="pay_method">
						<option value="cash"><?php esc_html_e( '💵 Tiền mặt', 'usm' ); ?></option>
						<option value="transfer"><?php esc_html_e( '🏦 Chuyển khoản', 'usm' ); ?></option>
						<option value="qr"><?php esc_html_e( '📱 VietQR', 'usm' ); ?></option>
					</select>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Ghi chú', 'usm' ); ?></label>
					<input type="text" name="pay_note" placeholder="VD: Đóng đợt 2">
				</div>

				<button type="submit" class="button button-primary">💰 <?php esc_html_e( 'Ghi nhận thanh toán', 'usm' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments' ) ); ?>" class="button"><?php esc_html_e( 'Quay lại', 'usm' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments&action=print&id=' . $pay_id ) ); ?>" target="_blank" class="button">🖨️ <?php esc_html_e( 'In phiếu thu', 'usm' ); ?></a>
			</form>
			<?php else : ?>
			<p style="color:#00a32a; font-weight:600;">✅ <?php esc_html_e( 'Đã thanh toán đủ.', 'usm' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments' ) ); ?>" class="button"><?php esc_html_e( 'Quay lại', 'usm' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments&action=print&id=' . $pay_id ) ); ?>" target="_blank" class="button">🖨️ <?php esc_html_e( 'In phiếu thu', 'usm' ); ?></a>
			<?php endif; ?>

			<?php if ( ! empty( $pay_logs ) ) : ?>
			<h3 style="margin-top:20px;">📋 <?php esc_html_e( 'Lịch sử thanh toán', 'usm' ); ?></h3>
			<table class="usm-table" style="font-size:13px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Ngày', 'usm' ); ?></th>
						<th><?php esc_html_e( 'Số tiền', 'usm' ); ?></th>
						<th><?php esc_html_e( 'Phương thức', 'usm' ); ?></th>
						<th><?php esc_html_e( 'Người thu', 'usm' ); ?></th>
						<th><?php esc_html_e( 'Ghi chú', 'usm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$method_labels = array( 'cash' => '💵 Tiền mặt', 'transfer' => '🏦 CK', 'qr' => '📱 QR', 'full' => '💰 Đầy đủ', 'deposit' => '💵 Đặt cọc' );
					foreach ( $pay_logs as $pl ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $pl->collected_at ) ) ); ?></td>
						<td style="font-weight:600; color:#00a32a;"><?php echo esc_html( USM_Helpers::format_vnd( $pl->amount ) ); ?></td>
						<td><?php echo esc_html( $method_labels[ $pl->payment_type ] ?? $pl->payment_type ); ?></td>
						<td><?php echo esc_html( $pl->collector_name ?? '—' ); ?></td>
						<td><?php echo esc_html( $pl->notes ?: '—' ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>

	<?php else : ?>
		<?php
		$per_page = 20;
		$current  = USM_Helpers::get_current_page();
		$offset   = USM_Helpers::get_offset( $current, $per_page );

		// Filter by status.
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where_status  = '';
		if ( $status_filter && in_array( $status_filter, array( 'active', 'paused', 'completed', 'expired' ), true ) ) {
			$where_status = $wpdb->prepare( 'AND e.status = %s', $status_filter );
		}

		// Filter by payment status.
		$pay_filter = isset( $_GET['pay'] ) ? sanitize_text_field( wp_unslash( $_GET['pay'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where_pay  = '';
		if ( 'paid' === $pay_filter ) {
			$where_pay = "AND e.payment_status = 'paid'";
		} elseif ( 'unpaid' === $pay_filter ) {
			$where_pay = "AND e.payment_status = 'unpaid'";
		} elseif ( 'partial' === $pay_filter ) {
			$where_pay = "AND e.payment_status = 'partial'";
		}

		// Server-side search by student name.
		$search_q    = isset( $_GET['sq'] ) ? sanitize_text_field( wp_unslash( $_GET['sq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where_search = '';
		if ( $search_q ) {
			$where_search = $wpdb->prepare( 'AND s.full_name LIKE %s', '%' . $wpdb->esc_like( $search_q ) . '%' );
		}

		// Filter by course.
		$course_filter = isset( $_GET['course'] ) ? absint( $_GET['course'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where_course  = '';
		if ( $course_filter ) {
			$where_course = $wpdb->prepare( 'AND p.course_id = %d', $course_filter );
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} e LEFT JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id LEFT JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id WHERE 1=1 {$where_status} {$where_pay} {$where_search} {$where_course}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT e.*, s.full_name AS student_name, p.name AS package_name
			 FROM {$table} e
			 LEFT JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
			 LEFT JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
			 WHERE 1=1 {$where_status} {$where_pay} {$where_search} {$where_course}
			 ORDER BY e.created_at DESC
			 LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		// Summary stats.
		$total_fees = 0;
		$low_count  = 0;
		$low_alert  = (int) USM_Helpers::get_setting( 'low_session_alert', 2 );
		foreach ( $items as $item ) {
			$total_fees += (float) $item->amount_paid;
			if ( (int) $item->sessions_remaining <= $low_alert && (int) $item->sessions_remaining > 0 ) {
				++$low_count;
			}
		}
		?>

		<!-- Status filter tabs -->
		<div style="margin-bottom: 12px;">
			<?php
			$statuses = array( '' => 'Tất cả', 'active' => 'Đang học', 'paused' => 'Tạm dừng', 'completed' => 'Hoàn thành', 'expired' => 'Hết hạn' );
			foreach ( $statuses as $key => $label ) :
				$url   = admin_url( 'admin.php?page=usm-enrollments' . ( $key ? '&status=' . $key : '' ) . ( $pay_filter ? '&pay=' . $pay_filter : '' ) . ( $search_q ? '&sq=' . rawurlencode( $search_q ) : '' ) );
				$class = $status_filter === $key ? 'button-primary' : '';
			?>
				<a href="<?php echo esc_url( $url ); ?>" class="button <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>

		<!-- Payment filter + Search -->
		<div style="margin-bottom: 12px; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
			<?php
			$pay_statuses = array( '' => '💰 Tất cả TT', 'paid' => '✅ Đã đóng', 'unpaid' => '❌ Chưa đóng', 'partial' => '⚠️ Đóng 1 phần' );
			foreach ( $pay_statuses as $pkey => $plabel ) :
				$purl  = admin_url( 'admin.php?page=usm-enrollments' . ( $status_filter ? '&status=' . $status_filter : '' ) . ( $pkey ? '&pay=' . $pkey : '' ) . ( $search_q ? '&sq=' . rawurlencode( $search_q ) : '' ) );
				$pclass = $pay_filter === $pkey ? 'button-primary' : '';
			?>
				<a href="<?php echo esc_url( $purl ); ?>" class="button <?php echo esc_attr( $pclass ); ?>" style="font-size:12px;"><?php echo esc_html( $plabel ); ?></a>
			<?php endforeach; ?>

			<form method="get" style="display:flex; gap:6px; margin-left:auto; flex-wrap:wrap;">
				<input type="hidden" name="page" value="usm-enrollments">
				<?php if ( $status_filter ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>"><?php endif; ?>
				<?php if ( $pay_filter ) : ?><input type="hidden" name="pay" value="<?php echo esc_attr( $pay_filter ); ?>"><?php endif; ?>
				<select name="course" onchange="this.form.submit()" style="min-width:140px;">
					<option value="">📚 Tất cả khóa</option>
					<?php foreach ( $courses_dd as $cdd ) : ?>
						<option value="<?php echo esc_attr( $cdd->id ); ?>" <?php selected( $course_filter, $cdd->id ); ?>><?php echo esc_html( $cdd->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="sq" value="<?php echo esc_attr( $search_q ); ?>" placeholder="🔍 Tìm tên học viên..." style="min-width:200px;">
				<button type="submit" class="button"><?php esc_html_e( 'Tìm', 'usm' ); ?></button>
				<?php if ( $search_q || $course_filter ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments' . ( $status_filter ? '&status=' . $status_filter : '' ) . ( $pay_filter ? '&pay=' . $pay_filter : '' ) ) ); ?>" class="button">✖</a>
				<?php endif; ?>
			</form>
		</div>

		<table class="usm-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Học viên', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Gói học', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Ngày ĐK', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Hết hạn', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Buổi còn lại', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Học phí', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Thanh toán', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Trạng thái', 'usm' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Thao tác', 'usm' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'Chưa có dữ liệu.', 'usm' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $row ) : ?>
						<tr>
							<td><div class="usm-name-cell"><?php echo wp_kses_post( USM_Helpers::render_avatar( $row->student_name ) ); ?><strong><?php echo esc_html( $row->student_name ); ?></strong></div></td>
							<td><?php echo esc_html( $row->package_name ); ?></td>
							<td><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $row->enroll_date ) ) ); ?></td>
							<td><?php echo $row->end_date ? esc_html( wp_date( 'd/m/Y', strtotime( $row->end_date ) ) ) : esc_html__( 'Không giới hạn', 'usm' ); ?></td>
							<td>
								<?php
								$low_alert = (int) USM_Helpers::get_setting( 'low_session_alert', 2 );
								$remaining_class = (int) $row->sessions_remaining <= $low_alert && (int) $row->sessions_remaining > 0 ? 'color:#d63638;font-weight:600;' : '';
								?>
								<span style="<?php echo esc_attr( $remaining_class ); ?>"><?php echo esc_html( $row->sessions_remaining ); ?></span>
							</td>
							<td><?php echo esc_html( USM_Helpers::format_vnd( $row->amount_paid ) ); ?></td>
							<td><?php echo wp_kses_post( USM_Helpers::status_badge( $row->payment_status, USM_Helpers::payment_status_label( $row->payment_status ) ) ); ?></td>
							<td><?php echo wp_kses_post( USM_Helpers::status_badge( $row->status, USM_Helpers::enrollment_status_label( $row->status ) ) ); ?></td>
							<td class="usm-actions">
								<?php if ( 'active' === $row->status ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-enrollments&action=pause&id=' . $row->id ), 'usm_pause_enrollment_' . $row->id ) ); ?>" title="Tạm hoãn" class="usm-icon-btn">⏸</a>
								<?php endif; ?>
								<?php if ( 'paused' === $row->status ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-enrollments&action=resume&id=' . $row->id ), 'usm_resume_enrollment_' . $row->id ) ); ?>" title="Kích hoạt lại" class="usm-icon-btn" style="color:#00a32a;">▶</a>
								<?php endif; ?>
								<?php if ( in_array( $row->status, array( 'completed', 'expired' ), true ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments&action=add&renew_student=' . $row->student_id . '&renew_package=' . $row->package_id ) ); ?>" title="Gia hạn" class="usm-icon-btn" style="color:#2271b1;">🔄</a>
								<?php endif; ?>
								<?php if ( 'paid' !== $row->payment_status ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments&action=payment&id=' . $row->id ) ); ?>" title="Thu tiền" class="usm-icon-btn" style="color:#00a32a;">💰</a>
								<?php endif; ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments&action=print&id=' . $row->id ) ); ?>" target="_blank" title="In phiếu thu" class="usm-icon-btn">🖨️</a>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-enrollments&action=payment&id=' . $row->id ) ); ?>" title="Lịch sử thanh toán" class="usm-icon-btn">📋</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-enrollments&action=delete&id=' . $row->id ), 'usm_delete_enrollment_' . $row->id ) ); ?>" class="usm-confirm-delete usm-icon-btn" title="Xoá" style="color:#d63638;">🗑</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $items ) ) : ?>
		<div style="display:flex; gap:16px; margin:8px 0 4px; font-size:13px; color:#50575e;">
			<span>💰 Đã thu: <strong style="color:#00a32a;"><?php echo esc_html( USM_Helpers::format_vnd( $total_fees ) ); ?></strong></span>
			<?php if ( $low_count > 0 ) : ?>
				<span>⚠️ Sắp hết buổi: <strong style="color:#d63638;"><?php echo esc_html( $low_count ); ?></strong></span>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php
		$pagination_url = admin_url( 'admin.php?page=usm-enrollments' );
		if ( $status_filter ) {
			$pagination_url = add_query_arg( 'status', $status_filter, $pagination_url );
		}
		if ( $course_filter ) {
			$pagination_url = add_query_arg( 'course', $course_filter, $pagination_url );
		}
		USM_Helpers::render_pagination( $total, $per_page, $current, $pagination_url );
		?>
	<?php endif; ?>
</div>
