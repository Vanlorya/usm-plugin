<?php
/**
 * USM Notifications – Zalo ZNS + Email fallback.
 *
 * @package USM
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USM_Notifications {

	/**
	 * Send a notification to a phone number (Zalo ZNS first, fallback email).
	 *
	 * @param string $phone   Phone number (e.g. 0901234567).
	 * @param string $email   Fallback email address.
	 * @param string $type    Notification type key.
	 * @param array  $data    Template data (student_name, message, etc).
	 */
	public static function send( $phone, $email, $type, $data = array() ) {
		$settings = get_option( 'usm_settings', array() );
		$enabled  = $settings['notify_enabled'] ?? '0';

		if ( '1' !== $enabled ) {
			return;
		}

		$zalo_sent = false;

		// Try Zalo ZNS first.
		$zalo_token = $settings['zalo_access_token'] ?? '';
		$template   = $settings[ 'zalo_template_' . $type ] ?? '';

		if ( ! empty( $zalo_token ) && ! empty( $template ) && ! empty( $phone ) ) {
			$zalo_sent = self::send_zalo_zns( $phone, $template, $data, $zalo_token );
		}

		// Fallback to email.
		if ( ! $zalo_sent && ! empty( $email ) && ( '1' === ( $settings['notify_email_fallback'] ?? '1' ) ) ) {
			self::send_email( $email, $type, $data );
		}

		// Log.
		self::log( $phone, $email, $type, $zalo_sent ? 'zalo' : 'email', $data );
	}

	/**
	 * Send via Zalo ZNS API.
	 *
	 * @param string $phone      Phone number.
	 * @param string $template   ZNS template ID.
	 * @param array  $data       Template data.
	 * @param string $token      Zalo OA access token.
	 * @return bool
	 */
	private static function send_zalo_zns( $phone, $template, $data, $token ) {
		// Normalize phone: 0xxx → +84xxx.
		$phone_intl = $phone;
		if ( str_starts_with( $phone, '0' ) ) {
			$phone_intl = '84' . substr( $phone, 1 );
		}

		$body = array(
			'phone'       => $phone_intl,
			'template_id' => $template,
			'template_data' => $data,
		);

		$response = wp_remote_post( 'https://business.openapi.zalo.me/message/template', array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'access_token' => $token,
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $result['error'] ) && 0 === (int) $result['error'];
	}

	/**
	 * Fallback: send via WordPress email.
	 *
	 * @param string $email Email address.
	 * @param string $type  Notification type.
	 * @param array  $data  Template data.
	 */
	private static function send_email( $email, $type, $data ) {
		$center = USM_Helpers::get_setting( 'center_name', 'USM' );
		$labels = array(
			'reminder'       => '📅 Nhắc lịch học ngày mai',
			'low_session'    => '⚠️ Sắp hết buổi học',
			'expiry'         => '🔔 Gói học hết hạn',
			'enrollment_new' => '✅ Xác nhận đăng ký học',
		);

		$subject = ( $labels[ $type ] ?? 'Thông báo' ) . ' — ' . $center;

		$message  = '<div style="font-family:sans-serif; max-width:600px; margin:auto; padding:20px;">';
		$message .= '<h2 style="color:#2271b1;">' . esc_html( $center ) . '</h2>';
		$message .= '<p>Xin chào <strong>' . esc_html( $data['student_name'] ?? '' ) . '</strong>,</p>';
		$message .= '<p>' . wp_kses_post( $data['message'] ?? '' ) . '</p>';
		$message .= '<hr style="border:none; border-top:1px solid #eee; margin:20px 0;">';
		$message .= '<p style="color:#999; font-size:12px;">Gửi từ ' . esc_html( $center ) . '</p>';
		$message .= '</div>';

		wp_mail( $email, $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * Log notification (simple WP option for now).
	 */
	private static function log( $phone, $email, $type, $channel, $data ) {
		$logs = get_option( 'usm_notification_logs', array() );
		$logs[] = array(
			'time'    => current_time( 'mysql' ),
			'phone'   => $phone,
			'email'   => $email,
			'type'    => $type,
			'channel' => $channel,
			'student' => $data['student_name'] ?? '',
		);
		// Keep last 200 logs.
		if ( count( $logs ) > 200 ) {
			$logs = array_slice( $logs, -200 );
		}
		update_option( 'usm_notification_logs', $logs );
	}

	// ──────────────────────────────────────────────
	// CRON HANDLERS
	// ──────────────────────────────────────────────

	/**
	 * Daily cron: remind about tomorrow's sessions.
	 */
	public static function cron_remind_tomorrow() {
		global $wpdb;
		$tomorrow = wp_date( 'Y-m-d', strtotime( '+1 day' ) );

		$sessions = $wpdb->get_results( $wpdb->prepare(
			"SELECT sess.*, c.name AS course_name, co.full_name AS coach_name
			 FROM {$wpdb->prefix}usm_sessions sess
			 JOIN {$wpdb->prefix}usm_courses c ON sess.course_id = c.id
			 LEFT JOIN {$wpdb->prefix}usm_coaches co ON c.coach_id = co.id
			 WHERE sess.session_date = %s",
			$tomorrow
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ( $sessions as $sess ) {
			// Find enrolled students for this course.
			$students = $wpdb->get_results( $wpdb->prepare(
				"SELECT s.full_name, s.phone, s.email
				 FROM {$wpdb->prefix}usm_enrollments e
				 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
				 WHERE e.package_id IN (SELECT p.id FROM {$wpdb->prefix}usm_packages p WHERE p.course_id = %d)
				 AND e.status = 'active'",
				$sess->course_id
			) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			foreach ( $students as $stu ) {
				self::send( $stu->phone, $stu->email, 'reminder', array(
					'student_name' => $stu->full_name,
					'course_name'  => $sess->course_name,
					'coach_name'   => $sess->coach_name ?: '',
					'session_date' => wp_date( 'd/m/Y', strtotime( $sess->session_date ) ),
					'start_time'   => substr( $sess->start_time, 0, 5 ),
					'end_time'     => substr( $sess->end_time, 0, 5 ),
					'message'      => sprintf(
						'Bạn có lịch học <strong>%s</strong> ngày mai %s, từ %s – %s. HLV: %s.',
						$sess->course_name,
						wp_date( 'd/m/Y', strtotime( $sess->session_date ) ),
						substr( $sess->start_time, 0, 5 ),
						substr( $sess->end_time, 0, 5 ),
						$sess->coach_name ?: '—'
					),
				) );
			}
		}
	}

	/**
	 * Daily cron: alert expiring enrollments + low sessions.
	 */
	public static function cron_check_expiry() {
		global $wpdb;
		$today     = current_time( 'Y-m-d' );
		$threshold = (int) USM_Helpers::get_setting( 'low_session_alert', 2 );

		// Enrollments expiring in 3 days.
		$expiring = $wpdb->get_results( $wpdb->prepare(
			"SELECT e.*, s.full_name, s.phone, s.email, p.name AS package_name
			 FROM {$wpdb->prefix}usm_enrollments e
			 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
			 JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
			 WHERE e.status = 'active' AND e.expiry_date = %s",
			wp_date( 'Y-m-d', strtotime( '+3 days' ) )
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ( $expiring as $row ) {
			self::send( $row->phone, $row->email, 'expiry', array(
				'student_name' => $row->full_name,
				'package_name' => $row->package_name,
				'expiry_date'  => wp_date( 'd/m/Y', strtotime( $row->expiry_date ) ),
				'message'      => sprintf(
					'Gói học <strong>%s</strong> sẽ hết hạn vào ngày <strong>%s</strong>. Vui lòng liên hệ để gia hạn.',
					$row->package_name,
					wp_date( 'd/m/Y', strtotime( $row->expiry_date ) )
				),
			) );
		}

		// Low sessions (≤ threshold).
		$low = $wpdb->get_results( $wpdb->prepare(
			"SELECT e.*, s.full_name, s.phone, s.email, p.name AS package_name
			 FROM {$wpdb->prefix}usm_enrollments e
			 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
			 JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
			 WHERE e.status = 'active' AND e.sessions_remaining > 0 AND e.sessions_remaining <= %d",
			$threshold
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ( $low as $row ) {
			self::send( $row->phone, $row->email, 'low_session', array(
				'student_name'       => $row->full_name,
				'package_name'       => $row->package_name,
				'sessions_remaining' => $row->sessions_remaining,
				'message'            => sprintf(
					'Gói học <strong>%s</strong> chỉ còn <strong>%d buổi</strong>. Vui lòng gia hạn sớm!',
					$row->package_name,
					$row->sessions_remaining
				),
			) );
		}
	}

	/**
	 * Trigger: send welcome notification after enrollment.
	 *
	 * @param int $enrollment_id The enrollment ID.
	 */
	public static function on_new_enrollment( $enrollment_id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT e.*, s.full_name, s.phone, s.email, p.name AS package_name
			 FROM {$wpdb->prefix}usm_enrollments e
			 JOIN {$wpdb->prefix}usm_students s ON e.student_id = s.id
			 JOIN {$wpdb->prefix}usm_packages p ON e.package_id = p.id
			 WHERE e.id = %d",
			$enrollment_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $row ) {
			return;
		}

		$center = USM_Helpers::get_setting( 'center_name', 'USM' );

		self::send( $row->phone, $row->email, 'enrollment_new', array(
			'student_name' => $row->full_name,
			'package_name' => $row->package_name,
			'center_name'  => $center,
			'enroll_date'  => wp_date( 'd/m/Y', strtotime( $row->enroll_date ) ),
			'message'      => sprintf(
				'Chào mừng bạn đã đăng ký gói <strong>%s</strong> tại <strong>%s</strong>! Ngày bắt đầu: %s.',
				$row->package_name,
				$center,
				wp_date( 'd/m/Y', strtotime( $row->enroll_date ) )
			),
		) );
	}
}
