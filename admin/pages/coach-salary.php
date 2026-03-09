<?php
/**
 * Coach Salary – Tính lương HLV (view-only report).
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// ── Month filter ────────────────────────────────
$month = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : current_time( 'Y-m' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$year  = (int) substr( $month, 0, 4 );
$mon   = (int) substr( $month, 5, 2 );

$month_start = "{$month}-01";
$month_end   = wp_date( 'Y-m-t', strtotime( $month_start ) );

// ── Query: sessions coached + attendance counts ─
$results = $wpdb->get_results( $wpdb->prepare(
	"SELECT co.id, co.full_name, co.hourly_rate, co.commission_pct,
	        COUNT(DISTINCT sess.id) AS total_sessions,
	        SUM(CASE WHEN att.status = 'present' THEN 1 ELSE 0 END) AS total_present
	 FROM {$wpdb->prefix}usm_coaches co
	 LEFT JOIN {$wpdb->prefix}usm_sessions sess
	   ON COALESCE(sess.coach_id, (SELECT c.coach_id FROM {$wpdb->prefix}usm_courses c WHERE c.id = sess.course_id)) = co.id
	   AND sess.session_date BETWEEN %s AND %s
	 LEFT JOIN {$wpdb->prefix}usm_attendance att
	   ON att.session_id = sess.id
	 WHERE co.is_active = 1
	 GROUP BY co.id
	 ORDER BY co.full_name",
	$month_start,
	$month_end
) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
?>

<div class="wrap usm-wrap">
	<div class="usm-header">
		<h1><?php esc_html_e( '💰 Tính lương HLV', 'usm' ); ?></h1>
		<button type="button" class="button" onclick="window.print()">
			<?php esc_html_e( '🖨️ In báo cáo', 'usm' ); ?>
		</button>
	</div>

	<div style="margin-bottom:16px; display:flex; align-items:center; gap:8px;">
		<form method="get" style="display:flex; align-items:center; gap:8px;">
			<input type="hidden" name="page" value="usm-coach-salary">
			<input type="month" name="month" value="<?php echo esc_attr( $month ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'Xem', 'usm' ); ?></button>
		</form>
	</div>

	<table class="usm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'HLV', 'usm' ); ?></th>
				<th><?php esc_html_e( 'Số buổi dạy', 'usm' ); ?></th>
				<th><?php esc_html_e( 'Lương/buổi', 'usm' ); ?></th>
				<th><?php esc_html_e( 'Tổng lương cơ bản', 'usm' ); ?></th>
				<th><?php esc_html_e( 'HV có mặt', 'usm' ); ?></th>
				<th><?php esc_html_e( 'Hoa hồng %', 'usm' ); ?></th>
				<th><?php esc_html_e( 'Ước tính tổng', 'usm' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $results ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'Không có dữ liệu.', 'usm' ); ?></td></tr>
			<?php else : ?>
				<?php
				$grand_total = 0;
				foreach ( $results as $row ) :
					$base_salary   = (float) $row->hourly_rate * (int) $row->total_sessions;
					$grand_total  += $base_salary;
				?>
					<tr>
						<td><strong><?php echo esc_html( $row->full_name ); ?></strong></td>
						<td><?php echo esc_html( $row->total_sessions ); ?></td>
						<td><?php echo $row->hourly_rate ? esc_html( USM_Helpers::format_vnd( $row->hourly_rate ) ) : '—'; ?></td>
						<td><?php echo esc_html( USM_Helpers::format_vnd( $base_salary ) ); ?></td>
						<td><?php echo esc_html( $row->total_present ); ?></td>
						<td><?php echo esc_html( $row->commission_pct ); ?>%</td>
						<td><strong><?php echo esc_html( USM_Helpers::format_vnd( $base_salary ) ); ?></strong></td>
					</tr>
				<?php endforeach; ?>
				<tr style="background:#f6f7f7;">
					<td colspan="6" style="text-align:right;"><strong><?php esc_html_e( 'Tổng cộng:', 'usm' ); ?></strong></td>
					<td><strong><?php echo esc_html( USM_Helpers::format_vnd( $grand_total ) ); ?></strong></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<p style="color:#666; font-size:12px;">
		<?php esc_html_e( '* Đây là báo cáo tham khảo. Plugin không lưu trữ thông tin thanh toán lương.', 'usm' ); ?>
	</p>
</div>
