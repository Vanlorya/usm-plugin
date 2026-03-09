<?php
/**
 * Coaches CRUD – Quản lý Huấn luyện viên.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table  = $wpdb->prefix . 'usm_coaches';
$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// ── Handle form submissions ─────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_coach_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_coach_nonce'] ) ), 'usm_save_coach' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}

	if ( ! current_user_can( 'manage_usm_students' ) ) {
		wp_die( esc_html__( 'Bạn không có quyền thực hiện hành động này.', 'usm' ) );
	}

	$full_name      = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
	$phone          = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$email          = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$specialization = sanitize_text_field( wp_unslash( $_POST['specialization'] ?? '' ) );
	$certifications = sanitize_textarea_field( wp_unslash( $_POST['certifications'] ?? '' ) );
	$hourly_rate    = floatval( $_POST['hourly_rate'] ?? 0 );
	$commission_pct = floatval( $_POST['commission_pct'] ?? 0 );
	$coach_id       = absint( $_POST['coach_id'] ?? 0 );

	$errors = array();

	if ( empty( $full_name ) ) {
		$errors[] = 'Vui lòng nhập họ và tên.';
	}

	if ( empty( $phone ) ) {
		$errors[] = 'Vui lòng nhập số điện thoại.';
	} elseif ( ! USM_Helpers::validate_phone( $phone ) ) {
		$errors[] = 'Số điện thoại không hợp lệ. Vui lòng nhập 10 hoặc 11 chữ số.';
	} else {
		$phone = USM_Helpers::validate_phone( $phone );
	}

	if ( $commission_pct < 0 || $commission_pct > 100 ) {
		$errors[] = 'Hoa hồng phải từ 0 đến 100%.';
	}

	if ( ! empty( $errors ) ) {
		foreach ( $errors as $err ) {
			USM_Helpers::admin_notice( $err, 'error' );
		}
	} else {
		$data = array(
			'full_name'      => $full_name,
			'phone'          => $phone,
			'email'          => $email ?: null,
			'specialization' => $specialization ?: null,
			'certifications' => $certifications ?: null,
			'hourly_rate'    => $hourly_rate > 0 ? $hourly_rate : null,
			'commission_pct' => $commission_pct,
		);

		if ( $coach_id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $coach_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã cập nhật HLV thành công.' );
		} else {
			$data['wp_user_id'] = 0; // Will be linked when WP user account is created.
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã thêm HLV thành công.' );
		}
		$action = 'list';
	}
}

// ── Toggle active ───────────────────────────────
if ( 'toggle' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$toggle_id = absint( $_GET['id'] );
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_toggle_coach_' . $toggle_id ) ) {
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$table} WHERE id = %d", $toggle_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $table, array( 'is_active' => $current ? 0 : 1 ), array( 'id' => $toggle_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		USM_Helpers::admin_notice( 'Đã cập nhật trạng thái.' );
	}
	$action = 'list';
}

// ── Delete coach ───────────────────────────────
if ( 'delete' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$del_id = absint( $_GET['id'] );
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_delete_coach_' . $del_id ) ) {
		$has_courses = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}usm_courses WHERE coach_id = %d", $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $has_courses > 0 ) {
			USM_Helpers::admin_notice( 'Không thể xoá HLV đang phụ trách khóa học.', 'error' );
		} else {
			$wpdb->delete( $table, array( 'id' => $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã xoá HLV.' );
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
?>

<div class="wrap usm-wrap">
	<div class="usm-header">
		<h1><?php esc_html_e( 'Quản lý Huấn luyện viên', 'usm' ); ?></h1>
		<?php if ( 'list' === $action ) : ?>
			<div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-coaches&action=add' ) ); ?>" class="button button-primary">
					<?php esc_html_e( '+ Thêm HLV', 'usm' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-coaches&export=csv' ), 'usm_export_coaches_csv' ) ); ?>" class="button">
					<?php esc_html_e( '📥 Xuất CSV', 'usm' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( 'add' === $action || 'edit' === $action ) : ?>
		<div class="usm-form">
			<h2><?php echo $edit_record ? esc_html__( 'Sửa HLV', 'usm' ) : esc_html__( 'Thêm HLV mới', 'usm' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'usm_save_coach', 'usm_coach_nonce' ); ?>
				<input type="hidden" name="coach_id" value="<?php echo esc_attr( $edit_record->id ?? 0 ); ?>">

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Họ và tên', 'usm' ); ?></label>
					<input type="text" name="full_name" value="<?php echo esc_attr( $edit_record->full_name ?? '' ); ?>" required maxlength="255">
				</div>

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Số điện thoại', 'usm' ); ?></label>
					<input type="text" name="phone" value="<?php echo esc_attr( $edit_record->phone ?? '' ); ?>" required maxlength="11" placeholder="09xxxxxxxx">
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Email', 'usm' ); ?></label>
					<input type="email" name="email" value="<?php echo esc_attr( $edit_record->email ?? '' ); ?>">
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Chuyên môn', 'usm' ); ?></label>
					<input type="text" name="specialization" value="<?php echo esc_attr( $edit_record->specialization ?? '' ); ?>">
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Chứng chỉ', 'usm' ); ?></label>
					<textarea name="certifications"><?php echo esc_textarea( $edit_record->certifications ?? '' ); ?></textarea>
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Lương/buổi (VNĐ)', 'usm' ); ?></label>
					<input type="number" name="hourly_rate" value="<?php echo esc_attr( $edit_record->hourly_rate ?? '' ); ?>" min="0" step="1000">
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Hoa hồng %', 'usm' ); ?></label>
					<input type="number" name="commission_pct" value="<?php echo esc_attr( $edit_record->commission_pct ?? 0 ); ?>" min="0" max="100" step="0.5">
				</div>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu', 'usm' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-coaches' ) ); ?>" class="button"><?php esc_html_e( 'Huỷ', 'usm' ); ?></a>
			</form>
		</div>

	<?php else : ?>
		<?php
		$per_page = 20;
		$current  = USM_Helpers::get_current_page();
		$offset   = USM_Helpers::get_offset( $current, $per_page );
		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$items    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY full_name ASC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		?>

		<div class="usm-search-bar">
			<input type="text" id="usm-search-input" placeholder="🔍 <?php esc_attr_e( 'Tìm kiếm HLV...', 'usm' ); ?>">
		</div>

		<table class="usm-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Họ và tên', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Số điện thoại', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Chuyên môn', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Lương/buổi', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Hoa hồng %', 'usm' ); ?></th>
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
							<td><strong><?php echo esc_html( $row->full_name ); ?></strong></td>
							<td><?php echo esc_html( $row->phone ); ?></td>
							<td><?php echo esc_html( $row->specialization ?: '—' ); ?></td>
							<td><?php echo $row->hourly_rate ? esc_html( USM_Helpers::format_vnd( $row->hourly_rate ) ) : '—'; ?></td>
							<td><?php echo esc_html( $row->commission_pct ); ?>%</td>
							<td>
								<?php
								echo $row->is_active
									? wp_kses_post( USM_Helpers::status_badge( 'active', 'Hoạt động' ) )
									: wp_kses_post( USM_Helpers::status_badge( 'inactive', 'Khoá' ) );
								?>
							</td>
							<td class="usm-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-coaches&action=edit&id=' . $row->id ) ); ?>">
									<?php esc_html_e( 'Sửa', 'usm' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-coaches&action=toggle&id=' . $row->id ), 'usm_toggle_coach_' . $row->id ) ); ?>">
									<?php echo $row->is_active ? esc_html__( 'Khoá', 'usm' ) : esc_html__( 'Mở', 'usm' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-coaches&action=delete&id=' . $row->id ), 'usm_delete_coach_' . $row->id ) ); ?>" class="usm-confirm-delete" style="color:#d63638;">
									<?php esc_html_e( 'Xoá', 'usm' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php USM_Helpers::render_pagination( $total, $per_page, $current, admin_url( 'admin.php?page=usm-coaches' ) ); ?>
	<?php endif; ?>
</div>
