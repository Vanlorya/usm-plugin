<?php
/**
 * Users – Tạo tài khoản WP (Coach/Parent) + Parent-Student linking.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$generated_password = '';

// ── Handle user creation ────────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_user_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_user_nonce'] ) ), 'usm_create_user' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}

	if ( ! current_user_can( 'manage_usm_users' ) ) {
		wp_die( esc_html__( 'Bạn không có quyền thực hiện hành động này.', 'usm' ) );
	}

	$role      = sanitize_text_field( wp_unslash( $_POST['role'] ?? '' ) );
	$full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
	$phone     = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

	$errors = array();

	if ( ! in_array( $role, array( 'usm_coach', 'usm_parent' ), true ) ) {
		$errors[] = 'Vui lòng chọn vai trò hợp lệ.';
	}
	if ( empty( $full_name ) ) {
		$errors[] = 'Vui lòng nhập họ và tên.';
	}
	if ( empty( $phone ) ) {
		$errors[] = 'Vui lòng nhập số điện thoại.';
	} elseif ( ! USM_Helpers::validate_phone( $phone ) ) {
		$errors[] = 'Số điện thoại không hợp lệ.';
	} else {
		$phone = USM_Helpers::validate_phone( $phone );
		if ( username_exists( $phone ) ) {
			$errors[] = 'Số điện thoại đã được sử dụng.';
		}
	}

	if ( ! empty( $errors ) ) {
		foreach ( $errors as $err ) {
			USM_Helpers::admin_notice( $err, 'error' );
		}
	} else {
		$password = wp_generate_password( 12, false );
		$user_id  = wp_create_user( $phone, $password, $email ?: '' );

		if ( is_wp_error( $user_id ) ) {
			USM_Helpers::admin_notice( 'Lỗi tạo tài khoản: ' . $user_id->get_error_message(), 'error' );
		} else {
			$user = new WP_User( $user_id );
			$user->set_role( $role );
			wp_update_user( array(
				'ID'           => $user_id,
				'display_name' => $full_name,
				'first_name'   => $full_name,
			) );

			if ( 'usm_coach' === $role ) {
				$existing_coach = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}usm_coaches WHERE phone = %s",
					$phone
				) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

				if ( $existing_coach ) {
					$wpdb->update( $wpdb->prefix . 'usm_coaches', array( 'wp_user_id' => $user_id ), array( 'id' => $existing_coach ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				} else {
					$wpdb->insert( $wpdb->prefix . 'usm_coaches', array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						'wp_user_id' => $user_id,
						'full_name'  => $full_name,
						'phone'      => $phone,
						'email'      => $email ?: null,
						'created_at' => current_time( 'mysql' ),
					) );
				}
			} elseif ( 'usm_parent' === $role ) {
				$wpdb->insert( $wpdb->prefix . 'usm_parents', array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					'wp_user_id' => $user_id,
					'full_name'  => $full_name,
					'phone'      => $phone,
					'created_at' => current_time( 'mysql' ),
				) );
			}

			$generated_password = $password;
			USM_Helpers::admin_notice( 'Đã tạo tài khoản thành công.' );
		}
	}
}

// ── Handle password reset ───────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_reset_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_reset_nonce'] ) ), 'usm_reset_password' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}

	$reset_user_id = absint( $_POST['reset_user_id'] ?? 0 );
	if ( $reset_user_id > 0 ) {
		$new_pass = wp_generate_password( 12, false );
		wp_set_password( $new_pass, $reset_user_id );
		$generated_password = $new_pass;
		$reset_user         = get_userdata( $reset_user_id );
		USM_Helpers::admin_notice( "Đã đặt lại mật khẩu cho: {$reset_user->display_name}" );
	}
}

// ── Handle link parent-student ──────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_link_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_link_nonce'] ) ), 'usm_link_parent_student' ) ) {
		wp_die( esc_html__( 'Yêu cầu không hợp lệ.', 'usm' ) );
	}

	$link_parent_id  = absint( $_POST['link_parent_id'] ?? 0 );
	$link_student_id = absint( $_POST['link_student_id'] ?? 0 );

	if ( $link_parent_id > 0 && $link_student_id > 0 ) {
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}usm_parent_students WHERE parent_id = %d AND student_id = %d",
			$link_parent_id,
			$link_student_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( $exists > 0 ) {
			USM_Helpers::admin_notice( 'Liên kết này đã tồn tại.', 'warning' );
		} else {
			$wpdb->insert( $wpdb->prefix . 'usm_parent_students', array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				'parent_id'  => $link_parent_id,
				'student_id' => $link_student_id,
				'created_at' => current_time( 'mysql' ),
			) );
			USM_Helpers::admin_notice( 'Đã liên kết phụ huynh với học viên.' );
		}
	} else {
		USM_Helpers::admin_notice( 'Vui lòng chọn cả phụ huynh và học viên.', 'error' );
	}
}

// ── Handle unlink ───────────────────────────────
if ( isset( $_GET['action'], $_GET['link_id'], $_GET['_wpnonce'] ) && 'unlink' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$unlink_id = absint( $_GET['link_id'] );
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'usm_unlink_' . $unlink_id ) ) {
		$wpdb->delete( $wpdb->prefix . 'usm_parent_students', array( 'id' => $unlink_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		USM_Helpers::admin_notice( 'Đã huỷ liên kết.' );
	}
}

// ── Data ────────────────────────────────────────
$usm_users = get_users( array(
	'role__in' => array( 'usm_admin', 'usm_coach', 'usm_parent' ),
	'orderby'  => 'display_name',
) );

$role_labels = array(
	'usm_admin'  => 'Admin',
	'usm_coach'  => 'HLV',
	'usm_parent' => 'Phụ huynh',
);

$parents  = $wpdb->get_results( "SELECT id, full_name, phone FROM {$wpdb->prefix}usm_parents ORDER BY full_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$all_students = $wpdb->get_results( "SELECT id, full_name FROM {$wpdb->prefix}usm_students WHERE is_active = 1 ORDER BY full_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$links = $wpdb->get_results(
	"SELECT ps.id, p.full_name AS parent_name, p.phone AS parent_phone, s.full_name AS student_name
	 FROM {$wpdb->prefix}usm_parent_students ps
	 JOIN {$wpdb->prefix}usm_parents p ON ps.parent_id = p.id
	 JOIN {$wpdb->prefix}usm_students s ON ps.student_id = s.id
	 ORDER BY p.full_name, s.full_name"
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
?>

<div class="wrap usm-wrap">
	<h1><?php esc_html_e( '👤 Quản lý Tài khoản', 'usm' ); ?></h1>

	<?php if ( ! empty( $generated_password ) ) : ?>
		<div class="notice notice-warning" style="padding:12px;">
			<strong>⚠️ <?php esc_html_e( 'Mật khẩu:', 'usm' ); ?></strong>
			<code style="font-size:16px; padding:4px 8px; background:#fff3cd;"><?php echo esc_html( $generated_password ); ?></code>
			<br><small><?php esc_html_e( 'Vui lòng thông báo cho người dùng ngay!', 'usm' ); ?></small>
		</div>
	<?php endif; ?>

	<!-- Create user form -->
	<div class="usm-form">
		<h2><?php esc_html_e( 'Tạo tài khoản mới', 'usm' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'usm_create_user', 'usm_user_nonce' ); ?>

			<div class="usm-form-row">
				<label class="required"><?php esc_html_e( 'Vai trò', 'usm' ); ?></label>
				<label style="margin-right:20px;"><input type="radio" name="role" value="usm_coach" required> HLV</label>
				<label><input type="radio" name="role" value="usm_parent"> Phụ huynh</label>
			</div>

			<div class="usm-form-row">
				<label class="required"><?php esc_html_e( 'Họ và tên', 'usm' ); ?></label>
				<input type="text" name="full_name" required maxlength="255">
			</div>

			<div class="usm-form-row">
				<label class="required"><?php esc_html_e( 'SĐT (tài khoản đăng nhập)', 'usm' ); ?></label>
				<input type="text" name="phone" required maxlength="10" placeholder="09xxxxxxxx">
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Email', 'usm' ); ?></label>
				<input type="email" name="email">
			</div>

			<button type="submit" class="button button-primary"><?php esc_html_e( 'Tạo tài khoản', 'usm' ); ?></button>
		</form>
	</div>

	<!-- Users list -->
	<h2><?php esc_html_e( 'Danh sách tài khoản USM', 'usm' ); ?></h2>
	<table class="usm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Họ và tên', 'usm' ); ?></th>
				<th><?php esc_html_e( 'Tài khoản', 'usm' ); ?></th>
				<th><?php esc_html_e( 'Vai trò', 'usm' ); ?></th>
				<th class="column-actions"><?php esc_html_e( 'Thao tác', 'usm' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $usm_users ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'Chưa có tài khoản USM nào.', 'usm' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $usm_users as $u ) :
					$user_roles = array_intersect( $u->roles, array_keys( $role_labels ) );
					$role_text  = ! empty( $user_roles ) ? $role_labels[ reset( $user_roles ) ] : '—';
				?>
					<tr>
						<td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
						<td><?php echo esc_html( $u->user_login ); ?></td>
						<td><?php echo esc_html( $role_text ); ?></td>
						<td class="usm-actions">
							<form method="post" style="display:inline;">
								<?php wp_nonce_field( 'usm_reset_password', 'usm_reset_nonce' ); ?>
								<input type="hidden" name="reset_user_id" value="<?php echo esc_attr( $u->ID ); ?>">
								<button type="submit" class="button button-small" onclick="return confirm('Đặt lại mật khẩu?');">
									<?php esc_html_e( 'Đặt lại MK', 'usm' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- ────── Parent-Student Linking ────── -->
	<hr style="margin:30px 0;">
	<h2><?php esc_html_e( '👨‍👧 Liên kết Phụ huynh ↔ Học viên', 'usm' ); ?></h2>

	<?php if ( ! empty( $parents ) && ! empty( $all_students ) ) : ?>
		<div class="usm-form" style="max-width:600px;">
			<form method="post" style="display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap;">
				<?php wp_nonce_field( 'usm_link_parent_student', 'usm_link_nonce' ); ?>
				<div>
					<label><strong><?php esc_html_e( 'Phụ huynh', 'usm' ); ?></strong></label><br>
					<select name="link_parent_id" required style="min-width:200px;">
						<option value=""><?php esc_html_e( '— Chọn —', 'usm' ); ?></option>
						<?php foreach ( $parents as $p ) : ?>
							<option value="<?php echo esc_attr( $p->id ); ?>">
								<?php echo esc_html( $p->full_name . ' (' . $p->phone . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label><strong><?php esc_html_e( 'Học viên', 'usm' ); ?></strong></label><br>
					<select name="link_student_id" required style="min-width:200px;">
						<option value=""><?php esc_html_e( '— Chọn —', 'usm' ); ?></option>
						<?php foreach ( $all_students as $s ) : ?>
							<option value="<?php echo esc_attr( $s->id ); ?>">
								<?php echo esc_html( $s->full_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="submit" class="button button-primary"><?php esc_html_e( '🔗 Liên kết', 'usm' ); ?></button>
			</form>
		</div>
	<?php else : ?>
		<p style="color:#666;"><?php esc_html_e( 'Cần tạo tài khoản phụ huynh và thêm học viên trước khi liên kết.', 'usm' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $links ) ) : ?>
		<table class="usm-table" style="max-width:600px; margin-top:16px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Phụ huynh', 'usm' ); ?></th>
					<th><?php esc_html_e( 'SĐT', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Học viên', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Thao tác', 'usm' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $links as $lk ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $lk->parent_name ); ?></strong></td>
						<td><?php echo esc_html( $lk->parent_phone ); ?></td>
						<td><?php echo esc_html( $lk->student_name ); ?></td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=usm-users&action=unlink&link_id=' . $lk->id ), 'usm_unlink_' . $lk->id ) ); ?>" style="color:#d63638;" onclick="return confirm('Huỷ liên kết?');">
								<?php esc_html_e( 'Huỷ', 'usm' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
