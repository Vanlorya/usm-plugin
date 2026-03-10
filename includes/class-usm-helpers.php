<?php
/**
 * Shared helper utilities.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USM_Helpers {

	/**
	 * Validate phone number (10 or 11 digits).
	 *
	 * @param string $phone Raw phone input.
	 * @return string|false Sanitized phone or false.
	 */
	public static function validate_phone( $phone ) {
		$phone = preg_replace( '/\D/', '', sanitize_text_field( $phone ) );
		if ( preg_match( '/^\d{10,11}$/', $phone ) ) {
			return $phone;
		}
		return false;
	}

	/**
	 * Format number as VND currency.
	 *
	 * @param float $amount Amount in VND.
	 * @return string Formatted string.
	 */
	public static function format_vnd( $amount ) {
		return number_format( (float) $amount, 0, ',', '.' ) . ' ₫';
	}

	/**
	 * Get current page number from query string.
	 *
	 * @return int Current page (1-indexed).
	 */
	public static function get_current_page() {
		return max( 1, absint( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Calculate pagination offset.
	 *
	 * @param int $current_page Current page number.
	 * @param int $per_page     Items per page.
	 * @return int Offset for SQL LIMIT.
	 */
	public static function get_offset( $current_page, $per_page = 20 ) {
		return ( $current_page - 1 ) * $per_page;
	}

	/**
	 * Render pagination links.
	 *
	 * @param int    $total_items Total number of items.
	 * @param int    $per_page    Items per page.
	 * @param int    $current     Current page.
	 * @param string $base_url    URL for pagination links.
	 */
	public static function render_pagination( $total_items, $per_page, $current, $base_url ) {
		$total_pages = ceil( $total_items / $per_page );
		if ( $total_pages <= 1 ) {
			return;
		}

		echo '<div class="usm-pagination">';
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			$url   = esc_url( add_query_arg( 'paged', $i, $base_url ) );
			$class = ( $i === $current ) ? 'current' : '';
			printf( '<a href="%s" class="button %s">%d</a> ', $url, esc_attr( $class ), $i );
		}
		echo '</div>';
	}

	/**
	 * Display admin notice.
	 *
	 * @param string $message Notice text.
	 * @param string $type    'success', 'error', 'warning', 'info'.
	 */
	public static function admin_notice( $message, $type = 'success' ) {
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			wp_kses_post( $message )
		);
	}

	/**
	 * Get enrollment status label in Vietnamese.
	 *
	 * @param string $status Status key.
	 * @return string Vietnamese label.
	 */
	public static function enrollment_status_label( $status ) {
		$labels = array(
			'active'    => 'Đang học',
			'paused'    => 'Tạm dừng',
			'completed' => 'Hoàn thành',
			'expired'   => 'Hết hạn',
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Get payment status label in Vietnamese.
	 *
	 * @param string $status Status key.
	 * @return string Vietnamese label.
	 */
	public static function payment_status_label( $status ) {
		$labels = array(
			'paid'    => 'Đã đóng',
			'deposit' => 'Đặt cọc',
			'unpaid'  => 'Chưa đóng',
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Render a status badge HTML.
	 *
	 * @param string $status Raw status key.
	 * @param string $label  Display label.
	 * @return string HTML span with badge class.
	 */
	public static function status_badge( $status, $label ) {
		return sprintf(
			'<span class="usm-badge usm-badge--%s">%s</span>',
			esc_attr( $status ),
			esc_html( $label )
		);
	}

	/**
	 * Get a USM setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not set.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		static $settings = null;
		if ( null === $settings ) {
			$settings = wp_parse_args( get_option( 'usm_settings', array() ), array(
				'center_name'         => '',
				'center_phone'        => '',
				'center_address'      => '',
				'default_coach_rate'  => 300000,
				'max_pause_count'     => 1,
				'low_session_alert'   => 2,
				'currency_symbol'     => '₫',
			) );
		}
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Render setup tab navigation for Khóa / Gói / Cơ sở pages.
	 *
	 * @param string $active Current active tab slug.
	 */
	public static function render_setup_tabs( $active = 'courses' ) {
		$tabs = array(
			'courses'    => array( 'label' => '📚 Khóa học',  'page' => 'usm-courses' ),
			'packages'   => array( 'label' => '📦 Gói học',   'page' => 'usm-packages' ),
			'facilities' => array( 'label' => '🏟️ Cơ sở',   'page' => 'usm-facilities' ),
		);
		echo '<nav class="nav-tab-wrapper" style="margin-bottom:16px;">';
		foreach ( $tabs as $slug => $tab ) {
			$class = ( $active === $slug ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			$url   = admin_url( 'admin.php?page=' . $tab['page'] );
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $tab['label'] ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Render a breadcrumb navigation.
	 *
	 * @param string $page_slug  Admin page slug (e.g. 'usm-students').
	 * @param string $list_label Label for the list page (e.g. 'Học viên').
	 * @param string $current    Current page label (e.g. 'Sửa: Trần Minh Khôi').
	 */
	public static function render_breadcrumb( $page_slug, $list_label, $current = '' ) {
		echo '<div class="usm-breadcrumb">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $page_slug ) ) . '">' . esc_html( $list_label ) . '</a>';
		if ( ! empty( $current ) ) {
			echo '<span class="sep">›</span>';
			echo '<span class="current">' . esc_html( $current ) . '</span>';
		}
		echo '</div>';
	}

	/**
	 * Render avatar initials circle.
	 *
	 * @param string $name Full name.
	 * @return string HTML for the avatar.
	 */
	public static function render_avatar( $name ) {
		$initial = mb_strtoupper( mb_substr( trim( $name ), 0, 1, 'UTF-8' ), 'UTF-8' );
		$colors  = array( '#e74c3c', '#3498db', '#2ecc71', '#9b59b6', '#e67e22', '#1abc9c', '#e84393', '#0984e3', '#6c5ce7', '#00b894' );
		$index   = crc32( $name ) % count( $colors );
		$color   = $colors[ abs( $index ) ];
		return sprintf( '<span class="usm-avatar" style="background:%s;">%s</span>', esc_attr( $color ), esc_html( $initial ) );
	}
}
