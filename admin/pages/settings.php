<?php
/**
 * Settings – Cài đặt USM.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Handle save ────────────────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['usm_settings_nonce'] ) ) {
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_settings_nonce'] ) ), 'usm_save_settings' ) && current_user_can( 'manage_options' ) ) {

		$settings = array(
			'center_name'         => sanitize_text_field( wp_unslash( $_POST['center_name'] ?? '' ) ),
			'center_phone'        => sanitize_text_field( wp_unslash( $_POST['center_phone'] ?? '' ) ),
			'center_address'      => sanitize_text_field( wp_unslash( $_POST['center_address'] ?? '' ) ),
			'default_coach_rate'  => absint( $_POST['default_coach_rate'] ?? 300000 ),
			'max_pause_count'     => absint( $_POST['max_pause_count'] ?? 1 ),
			'low_session_alert'   => absint( $_POST['low_session_alert'] ?? 2 ),
			'currency_symbol'     => sanitize_text_field( wp_unslash( $_POST['currency_symbol'] ?? '₫' ) ),
			// Notification settings.
			'notify_enabled'        => isset( $_POST['notify_enabled'] ) ? '1' : '0',
			'notify_email_fallback' => isset( $_POST['notify_email_fallback'] ) ? '1' : '0',
			'zalo_oa_id'            => sanitize_text_field( wp_unslash( $_POST['zalo_oa_id'] ?? '' ) ),
			'zalo_access_token'     => sanitize_text_field( wp_unslash( $_POST['zalo_access_token'] ?? '' ) ),
			'zalo_template_reminder'       => sanitize_text_field( wp_unslash( $_POST['zalo_template_reminder'] ?? '' ) ),
			'zalo_template_low_session'    => sanitize_text_field( wp_unslash( $_POST['zalo_template_low_session'] ?? '' ) ),
			'zalo_template_expiry'         => sanitize_text_field( wp_unslash( $_POST['zalo_template_expiry'] ?? '' ) ),
			'zalo_template_enrollment_new' => sanitize_text_field( wp_unslash( $_POST['zalo_template_enrollment_new'] ?? '' ) ),
			// VietQR bank settings.
			'bank_bin'     => sanitize_text_field( wp_unslash( $_POST['bank_bin'] ?? '' ) ),
			'bank_account' => sanitize_text_field( wp_unslash( $_POST['bank_account'] ?? '' ) ),
			'bank_name'    => sanitize_text_field( wp_unslash( $_POST['bank_name'] ?? '' ) ),
			'bank_holder'  => sanitize_text_field( wp_unslash( $_POST['bank_holder'] ?? '' ) ),
		);

		update_option( 'usm_settings', $settings );
		USM_Helpers::admin_notice( 'Đã lưu cài đặt thành công.' );
	}
}

// ── Load current settings ──────────────────────
$defaults = array(
	'center_name'         => '',
	'center_phone'        => '',
	'center_address'      => '',
	'default_coach_rate'  => 300000,
	'max_pause_count'     => 1,
	'low_session_alert'   => 2,
	'currency_symbol'     => '₫',
	'notify_enabled'        => '0',
	'notify_email_fallback' => '1',
	'zalo_oa_id'            => '',
	'zalo_access_token'     => '',
	'zalo_template_reminder'       => '',
	'zalo_template_low_session'    => '',
	'zalo_template_expiry'         => '',
	'zalo_template_enrollment_new' => '',
	// VietQR bank settings.
	'bank_bin'     => '',
	'bank_account' => '',
	'bank_name'    => '',
	'bank_holder'  => '',
);
$settings = wp_parse_args( get_option( 'usm_settings', array() ), $defaults );
?>

<div class="wrap usm-wrap">
	<div class="usm-header">
		<h1><?php esc_html_e( '⚙️ Cài đặt USM', 'usm' ); ?></h1>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'usm_save_settings', 'usm_settings_nonce' ); ?>

		<!-- Center Info -->
		<div class="usm-form" style="max-width:700px;">
			<h2><?php esc_html_e( '🏢 Thông tin trung tâm', 'usm' ); ?></h2>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Tên trung tâm', 'usm' ); ?></label>
				<input type="text" name="center_name" value="<?php echo esc_attr( $settings['center_name'] ); ?>" placeholder="VD: Trung tâm Thể thao ABC">
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Số điện thoại', 'usm' ); ?></label>
				<input type="text" name="center_phone" value="<?php echo esc_attr( $settings['center_phone'] ); ?>" placeholder="09xxxxxxxx">
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Địa chỉ', 'usm' ); ?></label>
				<input type="text" name="center_address" value="<?php echo esc_attr( $settings['center_address'] ); ?>" style="max-width:100%;" placeholder="VD: 123 Nguyễn Văn A, Quận 1, TP.HCM">
			</div>
		</div>

		<!-- Business Rules -->
		<div class="usm-form" style="max-width:700px;">
			<h2><?php esc_html_e( '📋 Quy tắc nghiệp vụ', 'usm' ); ?></h2>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Mức lương mặc định HLV (VNĐ/buổi)', 'usm' ); ?></label>
				<input type="number" name="default_coach_rate" value="<?php echo esc_attr( $settings['default_coach_rate'] ); ?>" min="0" step="10000">
				<p class="description"><?php esc_html_e( 'Áp dụng cho HLV mới khi chưa thiết lập mức lương riêng.', 'usm' ); ?></p>
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Số lần tạm hoãn tối đa', 'usm' ); ?></label>
				<input type="number" name="max_pause_count" value="<?php echo esc_attr( $settings['max_pause_count'] ); ?>" min="0" max="10">
				<p class="description"><?php esc_html_e( 'Mỗi đăng ký học được phép tạm hoãn tối đa bao nhiêu lần.', 'usm' ); ?></p>
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Cảnh báo buổi còn lại (≤)', 'usm' ); ?></label>
				<input type="number" name="low_session_alert" value="<?php echo esc_attr( $settings['low_session_alert'] ); ?>" min="1" max="20">
				<p class="description"><?php esc_html_e( 'Hiển thị cảnh báo khi số buổi còn lại nhỏ hơn hoặc bằng giá trị này.', 'usm' ); ?></p>
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Ký hiệu tiền tệ', 'usm' ); ?></label>
				<input type="text" name="currency_symbol" value="<?php echo esc_attr( $settings['currency_symbol'] ); ?>" style="max-width:100px;">
			</div>
		</div>

		<!-- VietQR Bank Settings -->
		<div class="usm-form" style="max-width:700px;">
			<h2><?php esc_html_e( '🏦 Thanh toán VietQR', 'usm' ); ?></h2>
			<p class="description" style="margin-bottom:15px;">
				<?php esc_html_e( 'Cấu hình tài khoản ngân hàng để tạo mã QR thanh toán cho phụ huynh.', 'usm' ); ?>
			</p>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Ngân hàng', 'usm' ); ?></label>
				<select name="bank_bin">
					<option value=""><?php esc_html_e( '— Chọn ngân hàng —', 'usm' ); ?></option>
					<?php
					$banks = array(
						'970436' => 'Vietcombank',
						'970415' => 'VietinBank',
						'970418' => 'BIDV',
						'970422' => 'MB Bank',
						'970416' => 'ACB',
						'970407' => 'Techcombank',
						'970423' => 'TPBank',
						'970432' => 'VPBank',
						'970448' => 'OCB',
						'970405' => 'Agribank',
						'970403' => 'Sacombank',
						'970441' => 'VIB',
						'970443' => 'SHB',
						'970437' => 'HDBank',
						'970433' => 'MSB',
						'970449' => 'LienVietPostBank',
						'970431' => 'Eximbank',
						'970454' => 'VietA Bank',
						'970429' => 'SCB',
						'970426' => 'SeABank',
					);
					foreach ( $banks as $bin => $name ) :
					?>
						<option value="<?php echo esc_attr( $bin ); ?>" <?php selected( $settings['bank_bin'], $bin ); ?>>
							<?php echo esc_html( $name . ' (' . $bin . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Số tài khoản', 'usm' ); ?></label>
				<input type="text" name="bank_account" value="<?php echo esc_attr( $settings['bank_account'] ); ?>" placeholder="VD: 1234567890">
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Tên ngân hàng (hiển thị)', 'usm' ); ?></label>
				<input type="text" name="bank_name" value="<?php echo esc_attr( $settings['bank_name'] ); ?>" placeholder="VD: Vietcombank">
				<p class="description"><?php esc_html_e( 'Tên sẽ hiện trên QR cho phụ huynh biết chuyển đến ngân hàng nào.', 'usm' ); ?></p>
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Tên chủ tài khoản', 'usm' ); ?></label>
				<input type="text" name="bank_holder" value="<?php echo esc_attr( $settings['bank_holder'] ); ?>" placeholder="VD: NGUYEN VAN A" style="text-transform:uppercase;">
				<p class="description"><?php esc_html_e( 'Viết hoa, không dấu (đúng như trên tài khoản ngân hàng).', 'usm' ); ?></p>
			</div>

			<?php if ( $settings['bank_bin'] && $settings['bank_account'] ) : ?>
			<div style="margin-top:10px; padding:12px; background:#f0f6fc; border-radius:6px;">
				<strong><?php esc_html_e( 'Xem trước QR:', 'usm' ); ?></strong><br>
				<img src="https://img.vietqr.io/image/<?php echo esc_attr( $settings['bank_bin'] ); ?>-<?php echo esc_attr( $settings['bank_account'] ); ?>-compact.png?amount=100000&addInfo=USM%20Test&accountName=<?php echo esc_attr( rawurlencode( $settings['bank_holder'] ) ); ?>"
					alt="VietQR Preview" style="max-width:250px; margin-top:8px; border-radius:6px;">
			</div>
			<?php endif; ?>
		</div>

		<!-- Zalo Notifications -->
		<div class="usm-form" style="max-width:700px;">
			<h2><?php esc_html_e( '🔔 Thông báo Zalo', 'usm' ); ?></h2>

			<div class="usm-form-row">
				<label>
					<input type="checkbox" name="notify_enabled" value="1" <?php checked( $settings['notify_enabled'], '1' ); ?>>
					<?php esc_html_e( 'Bật thông báo tự động', 'usm' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Gửi nhắc lịch, cảnh báo hết buổi, hết hạn qua Zalo/Email.', 'usm' ); ?></p>
			</div>

			<div class="usm-form-row">
				<label>
					<input type="checkbox" name="notify_email_fallback" value="1" <?php checked( $settings['notify_email_fallback'], '1' ); ?>>
					<?php esc_html_e( 'Gửi Email khi không có Zalo', 'usm' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Nếu chưa cấu hình Zalo hoặc gửi Zalo thất bại, sẽ gửi email thay thế.', 'usm' ); ?></p>
			</div>

			<hr style="margin:15px 0">
			<h3><?php esc_html_e( 'Cấu hình Zalo OA', 'usm' ); ?></h3>
			<p class="description" style="margin-bottom:15px;">
				<?php esc_html_e( 'Cần có Zalo Official Account và đăng ký ZNS. Để trống nếu chỉ dùng Email.', 'usm' ); ?>
				<a href="https://oa.zalo.me" target="_blank"><?php esc_html_e( 'Tạo Zalo OA →', 'usm' ); ?></a>
			</p>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Zalo OA ID', 'usm' ); ?></label>
				<input type="text" name="zalo_oa_id" value="<?php echo esc_attr( $settings['zalo_oa_id'] ); ?>" placeholder="VD: 1234567890">
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( 'Access Token', 'usm' ); ?></label>
				<input type="password" name="zalo_access_token" value="<?php echo esc_attr( $settings['zalo_access_token'] ); ?>" placeholder="Dán access token từ Zalo">
				<p class="description"><?php esc_html_e( 'Lấy từ Zalo for Developers → App → Access Token.', 'usm' ); ?></p>
			</div>

			<hr style="margin:15px 0">
			<h3><?php esc_html_e( 'ZNS Template ID', 'usm' ); ?></h3>
			<p class="description" style="margin-bottom:15px;">
				<?php esc_html_e( 'Tạo template ZNS tại Zalo OA → ZNS → Tạo mẫu. Điền ID template tương ứng.', 'usm' ); ?>
			</p>

			<div class="usm-form-row">
				<label><?php esc_html_e( '📅 Nhắc lịch học', 'usm' ); ?></label>
				<input type="text" name="zalo_template_reminder" value="<?php echo esc_attr( $settings['zalo_template_reminder'] ); ?>" placeholder="Template ID">
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( '⚠️ Sắp hết buổi', 'usm' ); ?></label>
				<input type="text" name="zalo_template_low_session" value="<?php echo esc_attr( $settings['zalo_template_low_session'] ); ?>" placeholder="Template ID">
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( '🔔 Hết hạn gói', 'usm' ); ?></label>
				<input type="text" name="zalo_template_expiry" value="<?php echo esc_attr( $settings['zalo_template_expiry'] ); ?>" placeholder="Template ID">
			</div>

			<div class="usm-form-row">
				<label><?php esc_html_e( '✅ Xác nhận đăng ký', 'usm' ); ?></label>
				<input type="text" name="zalo_template_enrollment_new" value="<?php echo esc_attr( $settings['zalo_template_enrollment_new'] ); ?>" placeholder="Template ID">
			</div>
		</div>

		<button type="submit" class="button button-primary button-hero"><?php esc_html_e( '💾 Lưu cài đặt', 'usm' ); ?></button>
	</form>

	<!-- Notification Log -->
	<?php
	$logs = get_option( 'usm_notification_logs', array() );
	$logs = array_reverse( array_slice( $logs, -20 ) );
	?>
	<?php if ( ! empty( $logs ) ) : ?>
	<div class="usm-form" style="max-width:700px; margin-top:20px;">
		<h2><?php esc_html_e( '📋 Lịch sử thông báo (20 gần nhất)', 'usm' ); ?></h2>
		<table class="usm-table" style="font-size:13px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Thời gian', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Học viên', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Loại', 'usm' ); ?></th>
					<th><?php esc_html_e( 'Kênh', 'usm' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
				<tr>
					<td><?php echo esc_html( $log['time'] ); ?></td>
					<td><?php echo esc_html( $log['student'] ); ?></td>
					<td>
						<?php
						$type_labels = array(
							'reminder'       => '📅 Nhắc lịch',
							'low_session'    => '⚠️ Hết buổi',
							'expiry'         => '🔔 Hết hạn',
							'enrollment_new' => '✅ Đăng ký',
						);
						echo esc_html( $type_labels[ $log['type'] ] ?? $log['type'] );
						?>
					</td>
					<td>
						<?php echo 'zalo' === $log['channel'] ? '<span style="color:#0068ff; font-weight:600;">Zalo</span>' : '<span style="color:#888;">Email</span>'; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- Version Info -->
	<div class="usm-version-info">
		<span class="usm-ver-badge">v<?php echo esc_html( USM_VERSION ); ?></span>
		<span>
			Universal Sports Manager —
			<a href="https://github.com/Vanlorya/usm-plugin" target="_blank">GitHub</a> |
			<a href="<?php echo esc_url( admin_url( 'plugins.php?puc_check_for_updates=1&puc_slug=usm-plugin' ) ); ?>">Kiểm tra cập nhật</a>
		</span>
	</div>
</div>
