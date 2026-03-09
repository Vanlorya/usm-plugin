<?php
/**
 * Courses CRUD – Quản lý Khóa học.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table  = $wpdb->prefix . 'usm_courses';
$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// ── Handle form submissions ─────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_course_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_course_nonce'] ) ), 'usm_save_course' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}

	if ( ! current_user_can( 'manage_usm_students' ) ) {
		wp_die( esc_html__( 'Bạn không có quyền thực hiện hành động này.', 'usm' ) );
	}

	$name         = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$sport_name   = sanitize_text_field( wp_unslash( $_POST['sport_name'] ?? '' ) );
	$facility_id  = absint( $_POST['facility_id'] ?? 0 );
	$coach_id     = absint( $_POST['coach_id'] ?? 0 );
	$description  = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
	$max_students = absint( $_POST['max_students'] ?? 0 );
	$start_date   = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
	$end_date     = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
	$course_id    = absint( $_POST['course_id'] ?? 0 );

	$errors = array();
	if ( empty( $name ) ) {
		$errors[] = 'Vui lòng nhập tên khóa học.';
	}

	if ( ! empty( $errors ) ) {
		foreach ( $errors as $err ) {
			USM_Helpers::admin_notice( $err, 'error' );
		}
	} else {
		$data = array(
			'name'         => $name,
			'sport_name'   => $sport_name ?: null,
			'facility_id'  => $facility_id > 0 ? $facility_id : null,
			'coach_id'     => $coach_id > 0 ? $coach_id : null,
			'description'  => $description ?: null,
			'max_students' => $max_students > 0 ? $max_students : null,
			'start_date'   => ! empty( $start_date ) ? $start_date : null,
			'end_date'     => ! empty( $end_date ) ? $end_date : null,
		);

		if ( $course_id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $course_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã cập nhật khóa học thành công.' );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã thêm khóa học thành công.' );
		}
		$action = 'list';
	}
}

// ── Toggle active ───────────────────────────────
if ( 'toggle' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$toggle_id = absint( $_GET['id'] );
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_toggle_course_' . $toggle_id ) ) {
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$table} WHERE id = %d", $toggle_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $table, array( 'is_active' => $current ? 0 : 1 ), array( 'id' => $toggle_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		USM_Helpers::admin_notice( 'Đã cập nhật trạng thái.' );
	}
	$action = 'list';
}

// ── Delete course ───────────────────────────────
if ( 'delete' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$del_id = absint( $_GET['id'] );
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_delete_course_' . $del_id ) ) {
		$has_packages = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}usm_packages WHERE course_id = %d", $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$has_sessions = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}usm_sessions WHERE course_id = %d", $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $has_packages > 0 || $has_sessions > 0 ) {
			USM_Helpers::admin_notice( 'Không thể xoá khóa học còn gói học hoặc lịch dạy liên quan.', 'error' );
		} else {
			$wpdb->delete( $table, array( 'id' => $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã xoá khóa học.' );
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
$sport_names = $wpdb->get_col( "SELECT DISTINCT sport_name FROM {$table} WHERE sport_name IS NOT NULL AND sport_name != '' ORDER BY sport_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$facilities  = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}usm_facilities WHERE is_active = 1 ORDER BY name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$coaches     = $wpdb->get_results( "SELECT id, full_name FROM {$wpdb->prefix}usm_coaches WHERE is_active = 1 ORDER BY full_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
?>

<div class="wrap usm-wrap">
	<?php USM_Helpers::render_setup_tabs( 'courses' ); ?>
	<div class="usm-header">
		<h1><?php esc_html_e( 'Quản lý Khóa học', 'usm' ); ?></h1>
		<?php if ( 'list' === $action ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-courses&action=add' ) ); ?>" class="button button-primary">
				<?php esc_html_e( '+ Thêm khóa học', 'usm' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( 'add' === $action || 'edit' === $action ) : ?>
		<div class="usm-form">
			<h2><?php echo $edit_record ? esc_html__( 'Sửa khóa học', 'usm' ) : esc_html__( 'Thêm khóa học mới', 'usm' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'usm_save_course', 'usm_course_nonce' ); ?>
				<input type="hidden" name="course_id" value="<?php echo esc_attr( $edit_record->id ?? 0 ); ?>">

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Tên khóa học', 'usm' ); ?></label>
					<input type="text" name="name" value="<?php echo esc_attr( $edit_record->name ?? '' ); ?>" required maxlength="255">
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Bộ môn', 'usm' ); ?></label>
					<input type="text" name="sport_name" value="<?php echo esc_attr( $edit_record->sport_name ?? '' ); ?>" list="usm-sport-list" placeholder="<?php esc_attr_e( 'Ví dụ: Bơi lội, Pickleball, Tennis...', 'usm' ); ?>" maxlength="100">
					<datalist id="usm-sport-list">
						<?php foreach ( $sport_names as $sn ) : ?>
							<option value="<?php echo esc_attr( $sn ); ?>">
						<?php endforeach; ?>
					</datalist>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Cơ sở', 'usm' ); ?></label>
					<select name="facility_id">
						<option value="0"><?php esc_html_e( '— Không chỉ định —', 'usm' ); ?></option>
						<?php foreach ( $facilities as $fac ) : ?>
							<option value="<?php echo esc_attr( $fac->id ); ?>" <?php selected( $edit_record->facility_id ?? '', $fac->id ); ?>>
								<?php echo esc_html( $fac->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'HLV phụ trách', 'usm' ); ?></label>
					<select name="coach_id">
						<option value="0"><?php esc_html_e( '— Không chỉ định —', 'usm' ); ?></option>
						<?php foreach ( $coaches as $coach ) : ?>
							<option value="<?php echo esc_attr( $coach->id ); ?>" <?php selected( $edit_record->coach_id ?? '', $coach->id ); ?>>
								<?php echo esc_html( $coach->full_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Mô tả', 'usm' ); ?></label>
					<textarea name="description"><?php echo esc_textarea( $edit_record->description ?? '' ); ?></textarea>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Số học viên tối đa', 'usm' ); ?></label>
					<input type="number" name="max_students" value="<?php echo esc_attr( $edit_record->max_students ?? '' ); ?>" min="0">
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Ngày bắt đầu', 'usm' ); ?></label>
					<input type="date" name="start_date" value="<?php echo esc_attr( $edit_record->start_date ?? '' ); ?>">
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Ngày kết thúc', 'usm' ); ?></label>
					<input type="date" name="end_date" value="<?php echo esc_attr( $edit_record->end_date ?? '' ); ?>">
				</div>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu', 'usm' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-courses' ) ); ?>" class="button"><?php esc_html_e( 'Huỷ', 'usm' ); ?></a>
			</form>
		</div>

	<?php else : ?>
		<?php
		$per_page = 20;
		$current  = USM_Helpers::get_current_page();
		$offset   = USM_Helpers::get_offset( $current, $per_page );
		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.*, f.name AS facility_name, co.full_name AS coach_name
			 FROM {$table} c
			 LEFT JOIN {$wpdb->prefix}usm_facilities f ON c.facility_id = f.id
			 LEFT JOIN {$wpdb->prefix}usm_coaches co ON c.coach_id = co.id
			 ORDER BY c.name ASC
			 LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		?>

		<div class="usm-search-bar">
			<input type="text" id="usm-search-input" placeholder="🔍 <?php esc_attr_e( 'Tìm kiếm khóa học...', 'usm' ); ?>">
		</div>

		<table class="usm-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Tên khóa học', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Bộ môn', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Cơ sở', 'usm' ); ?></th>
					<th><?php esc_html_e( 'HLV', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Trạng thái', 'usm' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Thao tác', 'usm' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Chưa có dữ liệu.', 'usm' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $row->name ); ?></strong></td>
							<td><?php echo esc_html( $row->sport_name ); ?></td>
							<td><?php echo esc_html( $row->facility_name ?: '—' ); ?></td>
							<td><?php echo esc_html( $row->coach_name ?: '—' ); ?></td>
							<td>
								<?php
								echo $row->is_active
									? wp_kses_post( USM_Helpers::status_badge( 'active', 'Hoạt động' ) )
									: wp_kses_post( USM_Helpers::status_badge( 'inactive', 'Ngưng' ) );
								?>
							</td>
							<td class="usm-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-courses&action=edit&id=' . $row->id ) ); ?>">
									<?php esc_html_e( 'Sửa', 'usm' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-courses&action=toggle&id=' . $row->id ), 'usm_toggle_course_' . $row->id ) ); ?>">
									<?php echo $row->is_active ? esc_html__( 'Ngưng', 'usm' ) : esc_html__( 'Kích hoạt', 'usm' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-courses&action=delete&id=' . $row->id ), 'usm_delete_course_' . $row->id ) ); ?>" class="usm-confirm-delete" style="color:#d63638;">
									<?php esc_html_e( 'Xoá', 'usm' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php USM_Helpers::render_pagination( $total, $per_page, $current, admin_url( 'admin.php?page=usm-courses' ) ); ?>
	<?php endif; ?>
</div>
