<?php
/**
 * Printable Receipt — Phiếu thu / Biên lai (A5 print-friendly).
 *
 * Usage: admin.php?page=usm-enrollments&action=print&id=X
 * Opens clean page, auto-print dialog.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$enrollment_id = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! $enrollment_id ) {
	wp_die( 'ID không hợp lệ.' );
}

$enr = $wpdb->get_row( $wpdb->prepare(
	"SELECT e.*, s.full_name AS student_name, s.phone AS student_phone,
	        p.name AS package_name, p.price AS package_price, c.name AS course_name
	 FROM {$wpdb->prefix}usm_enrollments e
	 LEFT JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
	 LEFT JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
	 LEFT JOIN {$wpdb->prefix}usm_courses c ON p.course_id = c.id
	 WHERE e.id = %d",
	$enrollment_id
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

if ( ! $enr ) {
	wp_die( 'Không tìm thấy đăng ký.' );
}

// Payment history.
$payments = $wpdb->get_results( $wpdb->prepare(
	"SELECT r.*, u.display_name AS collector_name
	 FROM {$wpdb->prefix}usm_revenue r
	 LEFT JOIN {$wpdb->prefix}users u ON r.collected_by = u.ID
	 WHERE r.enrollment_id = %d ORDER BY r.collected_at ASC",
	$enrollment_id
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$total_paid = 0;
foreach ( $payments as $p ) {
	$total_paid += (float) $p->amount;
}
$still_owed = max( 0, (float) $enr->package_price - $total_paid );

$settings = get_option( 'usm_settings', array() );
$center_name = $settings['center_name'] ?? 'Trung tâm Thể thao';
$method_labels = array( 'cash' => 'Tiền mặt', 'transfer' => 'Chuyển khoản', 'qr' => 'VietQR', 'full' => 'Đầy đủ', 'deposit' => 'Đặt cọc' );
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Phiếu thu #<?php echo esc_html( $enrollment_id ); ?> — <?php echo esc_html( $enr->student_name ); ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #1d2327; padding: 20px; max-width: 600px; margin: 0 auto; }
h1 { font-size: 20px; text-align: center; margin-bottom: 4px; }
.subtitle { text-align: center; color: #666; font-size: 12px; margin-bottom: 16px; }
.receipt-no { text-align: right; color: #888; font-size: 11px; margin-bottom: 12px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #e0e0e0; }
th { background: #f6f7f7; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #555; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; margin-bottom: 16px; padding: 10px; background: #fafafa; border-radius: 6px; border: 1px solid #e0e0e0; }
.info-grid dt { font-weight: 600; color: #555; font-size: 11px; text-transform: uppercase; }
.info-grid dd { margin: 0 0 4px; font-size: 13px; }
.total-row td { font-weight: 700; font-size: 14px; border-top: 2px solid #1d2327; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.badge-paid { background: #d4edda; color: #00a32a; }
.badge-unpaid { background: #f8d7da; color: #d63638; }
.badge-partial { background: #fff3cd; color: #856404; }
.footer { margin-top: 24px; display: flex; justify-content: space-between; font-size: 12px; color: #666; }
.sign-area { text-align: center; margin-top: 40px; }
.sign-area .line { border-top: 1px solid #999; width: 150px; margin: 0 auto; }
@media print {
	body { padding: 0; }
	.no-print { display: none !important; }
}
</style>
</head>
<body>
<div class="no-print" style="text-align:center; margin-bottom:16px;">
	<button onclick="window.print()" style="padding:8px 24px; font-size:14px; cursor:pointer; background:#2271b1; color:#fff; border:none; border-radius:4px;">🖨️ In phiếu thu</button>
	<button onclick="window.close()" style="padding:8px 16px; font-size:14px; cursor:pointer; border:1px solid #ccc; border-radius:4px; margin-left:8px;">Đóng</button>
</div>

<h1><?php echo esc_html( $center_name ); ?></h1>
<p class="subtitle">PHIẾU THU HỌC PHÍ</p>
<p class="receipt-no">Mã: #<?php echo esc_html( $enrollment_id ); ?> | Ngày: <?php echo esc_html( wp_date( 'd/m/Y' ) ); ?></p>

<dl class="info-grid">
	<dt>Học viên</dt>
	<dd><strong><?php echo esc_html( $enr->student_name ); ?></strong></dd>
	<dt>SĐT</dt>
	<dd><?php echo esc_html( $enr->student_phone ?: '—' ); ?></dd>
	<dt>Khóa học</dt>
	<dd><?php echo esc_html( $enr->course_name ); ?></dd>
	<dt>Gói học</dt>
	<dd><?php echo esc_html( $enr->package_name ); ?></dd>
	<dt>Ngày đăng ký</dt>
	<dd><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $enr->enroll_date ) ) ); ?></dd>
	<dt>Hết hạn</dt>
	<dd><?php echo $enr->end_date ? esc_html( wp_date( 'd/m/Y', strtotime( $enr->end_date ) ) ) : 'Không giới hạn'; ?></dd>
	<dt>Số buổi</dt>
	<dd><?php echo esc_html( $enr->sessions_total ); ?> (còn <?php echo esc_html( $enr->sessions_remaining ); ?>)</dd>
	<dt>Trạng thái TT</dt>
	<dd>
		<?php if ( 'paid' === $enr->payment_status ) : ?>
			<span class="badge badge-paid">Đã đóng đủ</span>
		<?php elseif ( 'partial' === $enr->payment_status ) : ?>
			<span class="badge badge-partial">Đóng 1 phần</span>
		<?php else : ?>
			<span class="badge badge-unpaid">Chưa đóng</span>
		<?php endif; ?>
	</dd>
</dl>

<?php if ( ! empty( $payments ) ) : ?>
<h3 style="font-size:14px; margin-bottom:6px;">Lịch sử thanh toán</h3>
<table>
	<thead>
		<tr>
			<th>Ngày</th>
			<th>Số tiền</th>
			<th>Phương thức</th>
			<th>Người thu</th>
			<th>Ghi chú</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $payments as $p ) : ?>
		<tr>
			<td><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $p->collected_at ) ) ); ?></td>
			<td style="font-weight:600;"><?php echo esc_html( USM_Helpers::format_vnd( $p->amount ) ); ?></td>
			<td><?php echo esc_html( $method_labels[ $p->payment_type ] ?? $p->payment_type ); ?></td>
			<td><?php echo esc_html( $p->collector_name ?? '—' ); ?></td>
			<td><?php echo esc_html( $p->notes ?: '—' ); ?></td>
		</tr>
		<?php endforeach; ?>
		<tr class="total-row">
			<td>Tổng đã thu</td>
			<td colspan="4" style="color:#00a32a;"><?php echo esc_html( USM_Helpers::format_vnd( $total_paid ) ); ?></td>
		</tr>
		<?php if ( $still_owed > 0 ) : ?>
		<tr>
			<td style="font-weight:600;">Còn nợ</td>
			<td colspan="4" style="color:#d63638; font-weight:600;"><?php echo esc_html( USM_Helpers::format_vnd( $still_owed ) ); ?></td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>
<?php else : ?>
<table>
	<tr class="total-row">
		<td>Học phí</td>
		<td style="text-align:right;"><?php echo esc_html( USM_Helpers::format_vnd( $enr->package_price ) ); ?></td>
	</tr>
	<tr>
		<td>Đã thu</td>
		<td style="text-align:right; color:#00a32a; font-weight:600;"><?php echo esc_html( USM_Helpers::format_vnd( $total_paid ) ); ?></td>
	</tr>
</table>
<?php endif; ?>

<div class="footer">
	<div>
		<div class="sign-area">
			<p>Người nộp</p>
			<br><br><br>
			<div class="line"></div>
		</div>
	</div>
	<div>
		<div class="sign-area">
			<p>Người thu</p>
			<br><br><br>
			<div class="line"></div>
		</div>
	</div>
</div>

<script>window.onload = function() { window.print(); };</script>
</body>
</html>
<?php exit; ?>
