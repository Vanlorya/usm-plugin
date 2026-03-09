<?php
/**
 * Students CRUD – Quản lý Học viên.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table  = $wpdb->prefix . 'usm_students';
$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// ── Handle form submissions ─────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_student_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_student_nonce'] ) ), 'usm_save_student' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}

	if ( ! current_user_can( 'manage_usm_students' ) ) {
		wp_die( esc_html__( 'Bạn không có quyền thực hiện hành động này.', 'usm' ) );
	}

	$full_name     = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
	$date_of_birth = sanitize_text_field( wp_unslash( $_POST['date_of_birth'] ?? '' ) );
	$phone         = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$email         = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$notes         = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
	$student_id    = absint( $_POST['student_id'] ?? 0 );

	if ( empty( $full_name ) ) {
		USM_Helpers::admin_notice( 'Vui lòng nhập họ và tên.', 'error' );
	} else {
		// Validate phone if provided.
		if ( ! empty( $phone ) && ! USM_Helpers::validate_phone( $phone ) ) {
			USM_Helpers::admin_notice( 'Số điện thoại không hợp lệ. Vui lòng nhập 10 hoặc 11 chữ số.', 'error' );
		} else {
			$phone = ! empty( $phone ) ? USM_Helpers::validate_phone( $phone ) : null;

			$data = array(
				'full_name'     => $full_name,
				'date_of_birth' => ! empty( $date_of_birth ) ? $date_of_birth : null,
				'phone'         => $phone,
				'email'         => $email ?: null,
				'notes'         => $notes ?: null,
			);

			if ( $student_id > 0 ) {
				$wpdb->update( $table, $data, array( 'id' => $student_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				USM_Helpers::admin_notice( 'Đã cập nhật học viên thành công.' );
			} else {
				$data['created_at'] = current_time( 'mysql' );
				$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				USM_Helpers::admin_notice( 'Đã thêm học viên thành công.' );
			}
			$action = 'list';
		}
	}
}

// ── Archive (soft delete) ───────────────────────
if ( 'archive' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$archive_id = absint( $_GET['id'] );
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_archive_student_' . $archive_id ) ) {
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$table} WHERE id = %d", $archive_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $table, array( 'is_active' => $current ? 0 : 1 ), array( 'id' => $archive_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		USM_Helpers::admin_notice( $current ? 'Đã lưu trữ học viên.' : 'Đã kích hoạt lại học viên.' );
	}
	$action = 'list';
}

// ── Edit: load record ───────────────────────────
$edit_record = null;
if ( 'edit' === $action && isset( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$edit_id     = absint( $_GET['id'] );
	$edit_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $edit_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// ── Filter ──────────────────────────────────────
$show_archived = isset( $_GET['show_archived'] ) && '1' === $_GET['show_archived']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="wrap usm-wrap">
	<div class="usm-header">
		<h1><?php esc_html_e( 'Quản lý Học viên', 'usm' ); ?></h1>
		<?php if ( 'list' === $action ) : ?>
			<div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-students&action=add' ) ); ?>" class="button button-primary">
					<?php esc_html_e( '+ Thêm học viên', 'usm' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-students&export=csv' ), 'usm_export_students_csv' ) ); ?>" class="button">
					<?php esc_html_e( '📥 Xuất CSV', 'usm' ); ?>
				</a>
				<?php if ( ! $show_archived ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-students&show_archived=1' ) ); ?>" class="button">
						<?php esc_html_e( 'Xem lưu trữ', 'usm' ); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-students' ) ); ?>" class="button">
						<?php esc_html_e( 'Xem hoạt động', 'usm' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( 'add' === $action || 'edit' === $action ) : ?>
		<?php
		$bc_label = $edit_record ? 'Sửa: ' . $edit_record->full_name : 'Thêm mới';
		USM_Helpers::render_breadcrumb( 'usm-students', 'Học viên', $bc_label );
		?>
		<div class="usm-form">
			<h2><?php echo $edit_record ? esc_html__( 'Sửa học viên', 'usm' ) : esc_html__( 'Thêm học viên mới', 'usm' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'usm_save_student', 'usm_student_nonce' ); ?>
				<input type="hidden" name="student_id" value="<?php echo esc_attr( $edit_record->id ?? 0 ); ?>">

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Họ và tên', 'usm' ); ?></label>
					<input type="text" name="full_name" value="<?php echo esc_attr( $edit_record->full_name ?? '' ); ?>" required maxlength="255">
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Ngày sinh', 'usm' ); ?></label>
					<input type="date" name="date_of_birth" value="<?php echo esc_attr( $edit_record->date_of_birth ?? '' ); ?>">
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Số điện thoại', 'usm' ); ?></label>
					<input type="text" name="phone" value="<?php echo esc_attr( $edit_record->phone ?? '' ); ?>" maxlength="10" placeholder="09xxxxxxxx">
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Email', 'usm' ); ?></label>
					<input type="email" name="email" value="<?php echo esc_attr( $edit_record->email ?? '' ); ?>">
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Ghi chú', 'usm' ); ?></label>
					<textarea name="notes"><?php echo esc_textarea( $edit_record->notes ?? '' ); ?></textarea>
				</div>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu', 'usm' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-students' ) ); ?>" class="button"><?php esc_html_e( 'Huỷ', 'usm' ); ?></a>
			</form>
		</div>

	<?php else : ?>
		<?php
		$per_page     = 20;
		$current_page = USM_Helpers::get_current_page();
		$offset       = USM_Helpers::get_offset( $current_page, $per_page );
		$where        = $show_archived ? 'WHERE is_active = 0' : 'WHERE is_active = 1';

		// Server-side search.
		$search_q = isset( $_GET['sq'] ) ? sanitize_text_field( wp_unslash( $_GET['sq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $search_q ) {
			$like   = '%' . $wpdb->esc_like( $search_q ) . '%';
			$where .= $wpdb->prepare( ' AND (full_name LIKE %s OR phone LIKE %s)', $like, $like );
		}

		$total        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items        = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY full_name ASC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Count active enrollments for each student.
		$enrollment_counts = array();
		if ( ! empty( $items ) ) {
			$student_ids = wp_list_pluck( $items, 'id' );
			$ids_str     = implode( ',', array_map( 'absint', $student_ids ) );
			$counts_raw  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT student_id, COUNT(*) as cnt FROM {$wpdb->prefix}usm_enrollments WHERE status = %s AND student_id IN ({$ids_str}) GROUP BY student_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'active'
				)
			);
			foreach ( $counts_raw as $c ) {
				$enrollment_counts[ $c->student_id ] = (int) $c->cnt;
			}
		}
		?>

		<div class="usm-search-bar" style="display:flex; gap:6px; align-items:center;">
			<form method="get" style="display:flex; gap:6px;">
				<input type="hidden" name="page" value="usm-students">
				<?php if ( $show_archived ) : ?><input type="hidden" name="show_archived" value="1"><?php endif; ?>
				<input type="text" name="sq" value="<?php echo esc_attr( $search_q ); ?>" placeholder="🔍 Tìm tên, SĐT..." style="min-width:250px;">
				<button type="submit" class="button"><?php esc_html_e( 'Tìm', 'usm' ); ?></button>
				<?php if ( $search_q ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-students' . ( $show_archived ? '&show_archived=1' : '' ) ) ); ?>" class="button">✖</a>
					<span style="font-size:12px; color:#666;"><?php echo esc_html( sprintf( 'Tìm thấy %d kết quả cho "%s"', $total, $search_q ) ); ?></span>
				<?php endif; ?>
			</form>
		</div>

		<table class="usm-table">
			<thead>
				<tr>
					<th class="column-id">ID</th>
					<th><?php esc_html_e( 'Họ và tên', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Ngày sinh', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Số điện thoại', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Số đăng ký', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Trạng thái', 'usm' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Thao tác', 'usm' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Chưa có dữ liệu.', 'usm' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->id ); ?></td>
							<td>
								<strong>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-students&action=edit&id=' . $row->id ) ); ?>">
										<?php echo esc_html( $row->full_name ); ?>
									</a>
								</strong>
							</td>
							<td>
								<?php
								echo $row->date_of_birth
									? esc_html( wp_date( 'd/m/Y', strtotime( $row->date_of_birth ) ) )
									: '—';
								?>
							</td>
							<td><?php echo esc_html( $row->phone ?: '—' ); ?></td>
							<td><?php echo esc_html( $enrollment_counts[ $row->id ] ?? 0 ); ?></td>
							<td>
								<?php
								echo $row->is_active
									? wp_kses_post( USM_Helpers::status_badge( 'active', 'Hoạt động' ) )
									: wp_kses_post( USM_Helpers::status_badge( 'inactive', 'Lưu trữ' ) );
								?>
							</td>
							<td class="usm-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-students&action=edit&id=' . $row->id ) ); ?>">
									<?php esc_html_e( 'Sửa', 'usm' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-students&action=archive&id=' . $row->id ), 'usm_archive_student_' . $row->id ) ); ?>">
									<?php echo $row->is_active ? esc_html__( 'Lưu trữ', 'usm' ) : esc_html__( 'Kích hoạt', 'usm' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php
		$pagination_url = admin_url( 'admin.php?page=usm-students' );
		if ( $show_archived ) {
			$pagination_url = add_query_arg( 'show_archived', '1', $pagination_url );
		}
		USM_Helpers::render_pagination( $total, $per_page, $current_page, $pagination_url );
		?>
	<?php endif; ?>
</div>
