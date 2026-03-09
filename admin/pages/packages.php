<?php
/**
 * Packages CRUD – Quản lý Gói học.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table  = $wpdb->prefix . 'usm_packages';
$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// ── Handle form submissions ─────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_package_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_package_nonce'] ) ), 'usm_save_package' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}

	if ( ! current_user_can( 'manage_usm_packages' ) ) {
		wp_die( esc_html__( 'Bạn không có quyền thực hiện hành động này.', 'usm' ) );
	}

	$name          = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$course_id     = absint( $_POST['course_id'] ?? 0 );
	$type          = sanitize_text_field( wp_unslash( $_POST['type'] ?? 'session' ) );
	$sessions      = absint( $_POST['sessions'] ?? 0 );
	$duration_days = absint( $_POST['duration_days'] ?? 0 );
	$price         = floatval( $_POST['price'] ?? 0 );
	$package_id    = absint( $_POST['package_id'] ?? 0 );

	$errors = array();
	if ( empty( $name ) ) {
		$errors[] = 'Vui lòng nhập tên gói học.';
	}
	if ( $course_id <= 0 ) {
		$errors[] = 'Vui lòng chọn khóa học.';
	}
	if ( ! in_array( $type, array( 'session', 'time' ), true ) ) {
		$errors[] = 'Loại gói không hợp lệ.';
	}
	if ( 'session' === $type && $sessions <= 0 ) {
		$errors[] = 'Gói theo buổi phải có số buổi > 0.';
	}
	if ( 'time' === $type && $duration_days <= 0 ) {
		$errors[] = 'Gói theo thời hạn phải có số ngày > 0.';
	}
	if ( $price < 0 ) {
		$errors[] = 'Học phí không được âm.';
	}

	if ( ! empty( $errors ) ) {
		foreach ( $errors as $err ) {
			USM_Helpers::admin_notice( $err, 'error' );
		}
	} else {
		$data = array(
			'name'          => $name,
			'course_id'     => $course_id,
			'type'          => $type,
			'sessions'      => 'session' === $type ? $sessions : null,
			'duration_days' => 'time' === $type ? $duration_days : null,
			'price'         => $price,
		);

		if ( $package_id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $package_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã cập nhật gói học thành công.' );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã thêm gói học thành công.' );
		}
		$action = 'list';
	}
}

// ── Toggle active ───────────────────────────────
if ( 'toggle' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$toggle_id = absint( $_GET['id'] );
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_toggle_package_' . $toggle_id ) ) {
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$table} WHERE id = %d", $toggle_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $table, array( 'is_active' => $current ? 0 : 1 ), array( 'id' => $toggle_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		USM_Helpers::admin_notice( 'Đã cập nhật trạng thái.' );
	}
	$action = 'list';
}

// ── Delete package ─────────────────────────────
if ( 'delete' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$del_id = absint( $_GET['id'] );
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_delete_package_' . $del_id ) ) {
		$has_enrollments = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}usm_enrollments WHERE package_id = %d", $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $has_enrollments > 0 ) {
			USM_Helpers::admin_notice( 'Không thể xoá gói học còn đăng ký liên quan.', 'error' );
		} else {
			$wpdb->delete( $table, array( 'id' => $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã xoá gói học.' );
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
$courses = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}usm_courses WHERE is_active = 1 ORDER BY name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
?>

<div class="wrap usm-wrap">
	<?php USM_Helpers::render_setup_tabs( 'packages' ); ?>
	<div class="usm-header">
		<h1><?php esc_html_e( 'Quản lý Gói học', 'usm' ); ?></h1>
		<?php if ( 'list' === $action ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-packages&action=add' ) ); ?>" class="button button-primary">
				<?php esc_html_e( '+ Thêm gói học', 'usm' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( 'add' === $action || 'edit' === $action ) : ?>
		<div class="usm-form">
			<h2><?php echo $edit_record ? esc_html__( 'Sửa gói học', 'usm' ) : esc_html__( 'Thêm gói học mới', 'usm' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'usm_save_package', 'usm_package_nonce' ); ?>
				<input type="hidden" name="package_id" value="<?php echo esc_attr( $edit_record->id ?? 0 ); ?>">

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Tên gói', 'usm' ); ?></label>
					<input type="text" name="name" value="<?php echo esc_attr( $edit_record->name ?? '' ); ?>" required maxlength="255">
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Khóa học', 'usm' ); ?></label>
					<select name="course_id" required>
						<option value=""><?php esc_html_e( '— Chọn khóa học —', 'usm' ); ?></option>
						<?php foreach ( $courses as $course ) : ?>
							<option value="<?php echo esc_attr( $course->id ); ?>" <?php selected( $edit_record->course_id ?? '', $course->id ); ?>>
								<?php echo esc_html( $course->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Loại gói', 'usm' ); ?></label>
					<select name="type" id="usm-package-type">
						<option value="session" <?php selected( $edit_record->type ?? 'session', 'session' ); ?>><?php esc_html_e( 'Theo buổi', 'usm' ); ?></option>
						<option value="time" <?php selected( $edit_record->type ?? '', 'time' ); ?>><?php esc_html_e( 'Theo thời hạn', 'usm' ); ?></option>
					</select>
				</div>

				<div class="usm-form-row" id="usm-sessions-row">
					<label><?php esc_html_e( 'Số buổi', 'usm' ); ?></label>
					<input type="number" name="sessions" value="<?php echo esc_attr( $edit_record->sessions ?? '' ); ?>" min="1">
				</div>

				<div class="usm-form-row" id="usm-duration-row" style="display:none;">
					<label><?php esc_html_e( 'Thời hạn (ngày)', 'usm' ); ?></label>
					<input type="number" name="duration_days" value="<?php echo esc_attr( $edit_record->duration_days ?? '' ); ?>" min="1">
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Học phí (VNĐ)', 'usm' ); ?></label>
					<input type="number" name="price" value="<?php echo esc_attr( $edit_record->price ?? '' ); ?>" min="0" step="1000" required>
				</div>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu', 'usm' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-packages' ) ); ?>" class="button"><?php esc_html_e( 'Huỷ', 'usm' ); ?></a>
			</form>
		</div>

		<script>
		(function() {
			var typeSelect = document.getElementById('usm-package-type');
			var sessionsRow = document.getElementById('usm-sessions-row');
			var durationRow = document.getElementById('usm-duration-row');

			function toggleFields() {
				if (typeSelect.value === 'session') {
					sessionsRow.style.display = '';
					durationRow.style.display = 'none';
				} else {
					sessionsRow.style.display = 'none';
					durationRow.style.display = '';
				}
			}

			typeSelect.addEventListener('change', toggleFields);
			toggleFields();
		})();
		</script>

	<?php else : ?>
		<?php
		$per_page = 20;
		$current  = USM_Helpers::get_current_page();
		$offset   = USM_Helpers::get_offset( $current, $per_page );
		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.*, c.name AS course_name
			 FROM {$table} p
			 LEFT JOIN {$wpdb->prefix}usm_courses c ON p.course_id = c.id
			 ORDER BY p.name ASC
			 LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$type_labels = array(
			'session' => 'Theo buổi',
			'time'    => 'Thời hạn',
		);
		?>

		<table class="usm-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Tên gói', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Khóa học', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Loại', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Số buổi', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Thời hạn (ngày)', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Học phí', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Trạng thái', 'usm' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Thao tác', 'usm' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'Chưa có dữ liệu.', 'usm' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $row->name ); ?></strong></td>
							<td><?php echo esc_html( $row->course_name ); ?></td>
							<td><?php echo esc_html( $type_labels[ $row->type ] ?? $row->type ); ?></td>
							<td><?php echo $row->sessions ? esc_html( $row->sessions ) : '—'; ?></td>
							<td><?php echo $row->duration_days ? esc_html( $row->duration_days ) : '—'; ?></td>
							<td><?php echo esc_html( USM_Helpers::format_vnd( $row->price ) ); ?></td>
							<td>
								<?php
								echo $row->is_active
									? wp_kses_post( USM_Helpers::status_badge( 'active', 'Hoạt động' ) )
									: wp_kses_post( USM_Helpers::status_badge( 'inactive', 'Ngưng' ) );
								?>
							</td>
							<td class="usm-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-packages&action=edit&id=' . $row->id ) ); ?>">
									<?php esc_html_e( 'Sửa', 'usm' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-packages&action=toggle&id=' . $row->id ), 'usm_toggle_package_' . $row->id ) ); ?>">
									<?php echo $row->is_active ? esc_html__( 'Ngưng', 'usm' ) : esc_html__( 'Kích hoạt', 'usm' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-packages&action=delete&id=' . $row->id ), 'usm_delete_package_' . $row->id ) ); ?>" class="usm-confirm-delete" style="color:#d63638;">
									<?php esc_html_e( 'Xoá', 'usm' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php USM_Helpers::render_pagination( $total, $per_page, $current, admin_url( 'admin.php?page=usm-packages' ) ); ?>
	<?php endif; ?>
</div>
