<?php
/**
 * Setup – Thiết lập (Khóa học + Gói học + Cơ sở) – Tab navigation hub.
 *
 * Consolidates 3 setup pages under one menu item with tabs.
 * Each tab links to the individual page which works independently.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Determine active tab.
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'courses'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$tabs = array(
	'courses'    => array( 'label' => '📚 Khóa học',  'page' => 'usm-courses' ),
	'packages'   => array( 'label' => '📦 Gói học',   'page' => 'usm-packages' ),
	'facilities' => array( 'label' => '🏟️ Cơ sở',   'page' => 'usm-facilities' ),
);

// Redirect to the actual page with tab context.
$target = $tabs[ $active_tab ] ?? $tabs['courses'];

// If we're on ?page=usm-setup, redirect to the actual page.
wp_safe_redirect( admin_url( 'admin.php?page=' . $target['page'] ) );
exit;
