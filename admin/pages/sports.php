<?php
/**
 * Sports CRUD – Quản lý Bộ môn.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table  = $wpdb->prefix . 'usm_sports';
$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// ── Handle form submissions ─────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_sport_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_sport_nonce'] ) ), 'usm_save_sport' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}

	if ( ! current_user_can( 'manage_usm_students' ) ) {
		wp_die( esc_html__( 'Bạn không có quyền thực hiện hành động này.', 'usm' ) );
	}

	$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
	$sport_id    = absint( $_POST['sport_id'] ?? 0 );

	if ( empty( $name ) ) {
		USM_Helpers::admin_notice( 'Vui lòng nhập tên bộ môn.', 'error' );
	} else {
		$data = array(
			'name'        => $name,
			'description' => $description,
		);

		if ( $sport_id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $sport_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã cập nhật bộ môn thành công.' );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã thêm bộ môn thành công.' );
		}
		$action = 'list';
	}
}

// ── Toggle active ───────────────────────────────
if ( 'toggle' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$toggle_id = absint( $_GET['id'] );
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_toggle_sport_' . $toggle_id ) ) {
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$table} WHERE id = %d", $toggle_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $table, array( 'is_active' => $current ? 0 : 1 ), array( 'id' => $toggle_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		USM_Helpers::admin_notice( 'Đã cập nhật trạng thái.' );
	}
	$action = 'list';
}

// ── Delete sport ───────────────────────────────
if ( 'delete' === $action && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
	$del_id = absint( $_GET['id'] );
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_delete_sport_' . $del_id ) ) {
		$has_courses = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}usm_courses WHERE sport_id = %d", $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $has_courses > 0 ) {
			USM_Helpers::admin_notice( 'Không thể xoá bộ môn còn khóa học liên quan.', 'error' );
		} else {
			$wpdb->delete( $table, array( 'id' => $del_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			USM_Helpers::admin_notice( 'Đã xoá bộ môn.' );
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
		<h1><?php esc_html_e( 'Quản lý Bộ môn', 'usm' ); ?></h1>
		<?php if ( 'list' === $action ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-sports&action=add' ) ); ?>" class="button button-primary">
				<?php esc_html_e( '+ Thêm bộ môn', 'usm' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( 'add' === $action || 'edit' === $action ) : ?>
		<!-- ── FORM ──────────────────────────────── -->
		<div class="usm-form">
			<h2><?php echo $edit_record ? esc_html__( 'Sửa bộ môn', 'usm' ) : esc_html__( 'Thêm bộ môn mới', 'usm' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'usm_save_sport', 'usm_sport_nonce' ); ?>
				<input type="hidden" name="sport_id" value="<?php echo esc_attr( $edit_record->id ?? 0 ); ?>">

				<div class="usm-form-row">
					<label class="required"><?php esc_html_e( 'Tên bộ môn', 'usm' ); ?></label>
					<input type="text" name="name" value="<?php echo esc_attr( $edit_record->name ?? '' ); ?>" required maxlength="255">
				</div>

				<div class="usm-form-row">
					<label><?php esc_html_e( 'Mô tả', 'usm' ); ?></label>
					<textarea name="description"><?php echo esc_textarea( $edit_record->description ?? '' ); ?></textarea>
				</div>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu', 'usm' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-sports' ) ); ?>" class="button"><?php esc_html_e( 'Huỷ', 'usm' ); ?></a>
			</form>
		</div>

	<?php else : ?>
		<!-- ── LIST TABLE ────────────────────────── -->
		<?php
		$per_page = 20;
		$current  = USM_Helpers::get_current_page();
		$offset   = USM_Helpers::get_offset( $current, $per_page );
		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$items    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY name ASC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		?>

		<div class="usm-search-bar">
			<input type="text" id="usm-search-input" placeholder="🔍 <?php esc_attr_e( 'Tìm kiếm bộ môn...', 'usm' ); ?>">
		</div>

		<table class="usm-table">
			<thead>
				<tr>
					<th class="column-id">ID</th>
					<th><?php esc_html_e( 'Tên bộ môn', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Mô tả', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Trạng thái', 'usm' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Thao tác', 'usm' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Chưa có dữ liệu.', 'usm' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->id ); ?></td>
							<td><strong><?php echo esc_html( $row->name ); ?></strong></td>
							<td><?php echo esc_html( wp_trim_words( $row->description, 10, '…' ) ); ?></td>
							<td>
								<?php
								echo $row->is_active
									? wp_kses_post( USM_Helpers::status_badge( 'active', 'Hoạt động' ) )
									: wp_kses_post( USM_Helpers::status_badge( 'inactive', 'Ngưng' ) );
								?>
							</td>
							<td class="usm-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=usm-sports&action=edit&id=' . $row->id ) ); ?>">
									<?php esc_html_e( 'Sửa', 'usm' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-sports&action=toggle&id=' . $row->id ), 'usm_toggle_sport_' . $row->id ) ); ?>">
									<?php echo $row->is_active ? esc_html__( 'Ngưng', 'usm' ) : esc_html__( 'Kích hoạt', 'usm' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-sports&action=delete&id=' . $row->id ), 'usm_delete_sport_' . $row->id ) ); ?>" class="usm-confirm-delete" style="color:#d63638;">
									<?php esc_html_e( 'Xoá', 'usm' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php USM_Helpers::render_pagination( $total, $per_page, $current, admin_url( 'admin.php?page=usm-sports' ) ); ?>
	<?php endif; ?>
</div>
