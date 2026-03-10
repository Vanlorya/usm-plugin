<?php
/**
 * UI Polish – Role-based menus, admin bar, custom CSS, redirects.
 *
 * Makes USM feel like a standalone app rather than a WP plugin.
 *
 * @package USM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USM_UI_Polish {

	/**
	 * Constructor – register all hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'cleanup_menus' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'customize_admin_bar' ), 999 );
		add_action( 'admin_head', array( $this, 'inject_custom_css' ) );
		add_filter( 'admin_footer_text', array( $this, 'custom_footer' ) );
		add_filter( 'update_footer', array( $this, 'custom_footer_right' ), 999 );
		add_filter( 'login_redirect', array( $this, 'role_based_redirect' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'redirect_dashboard' ) );
		add_filter( 'show_admin_bar', array( $this, 'maybe_hide_admin_bar' ) );
		add_action( 'admin_notices', array( $this, 'suppress_notices_for_non_admins' ), 1 );
		add_action( 'login_enqueue_scripts', array( $this, 'custom_login_css' ) );
		add_filter( 'login_headerurl', array( $this, 'login_logo_url' ) );
		add_filter( 'login_headertext', array( $this, 'login_logo_text' ) );
	}

	/**
	 * Check if current user is a USM-only user (coach or parent).
	 */
	private function is_usm_restricted_user() {
		$user = wp_get_current_user();
		return in_array( 'usm_coach', (array) $user->roles, true )
			|| in_array( 'usm_parent', (array) $user->roles, true )
			|| in_array( 'usm_admin', (array) $user->roles, true );
	}

	private function is_coach() {
		return in_array( 'usm_coach', (array) wp_get_current_user()->roles, true );
	}

	private function is_parent() {
		return in_array( 'usm_parent', (array) wp_get_current_user()->roles, true );
	}

	private function is_wp_admin() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Remove WP menu items for non-admin USM users.
	 */
	public function cleanup_menus() {
		if ( $this->is_wp_admin() ) {
			return; // Admins see everything.
		}

		if ( ! $this->is_usm_restricted_user() ) {
			return;
		}

		// Remove all WP core menus.
		$wp_menus = array(
			'index.php',           // Dashboard.
			'edit.php',            // Posts.
			'upload.php',          // Media.
			'edit.php?post_type=page', // Pages.
			'edit-comments.php',   // Comments.
			'themes.php',          // Appearance.
			'plugins.php',         // Plugins.
			'users.php',           // Users.
			'tools.php',           // Tools.
			'options-general.php', // Settings.
			'profile.php',        // Profile.
		);

		foreach ( $wp_menus as $menu ) {
			remove_menu_page( $menu );
		}

		// Also remove separators.
		global $menu;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $key => $item ) {
				if ( isset( $item[4] ) && strpos( $item[4], 'wp-menu-separator' ) !== false ) {
					unset( $menu[ $key ] );
				}
			}
		}
	}

	/**
	 * Customize admin bar – remove WP items, add USM branding.
	 */
	public function customize_admin_bar( $wp_admin_bar ) {
		if ( $this->is_wp_admin() ) {
			// Admin: just clean up a bit.
			$wp_admin_bar->remove_node( 'comments' );
			$wp_admin_bar->remove_node( 'new-content' );
			$wp_admin_bar->remove_node( 'wp-logo' );

			// Add USM logo.
			$wp_admin_bar->add_node( array(
				'id'    => 'usm-brand',
				'title' => '🏋️ USM',
				'href'  => admin_url( 'admin.php?page=usm-dashboard' ),
				'meta'  => array( 'class' => 'usm-admin-bar-brand' ),
			) );
			return;
		}

		if ( ! $this->is_usm_restricted_user() ) {
			return;
		}

		// Remove ALL top-level bar items for coaches/parents.
		$nodes_to_remove = array(
			'wp-logo', 'site-name', 'comments', 'new-content',
			'updates', 'search', 'customize', 'my-sites',
		);
		foreach ( $nodes_to_remove as $node ) {
			$wp_admin_bar->remove_node( $node );
		}

		// Add USM branding.
		$wp_admin_bar->add_node( array(
			'id'    => 'usm-brand',
			'title' => '🏋️ USM',
			'href'  => admin_url( 'admin.php?page=usm-dashboard' ),
			'meta'  => array( 'class' => 'usm-admin-bar-brand' ),
		) );
	}

	/**
	 * Inject custom CSS for a cleaner admin look.
	 */
	public function inject_custom_css() {
		$is_usm_page = isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'usm-' ) === 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<style>
		/* ── USM Admin Bar Branding ── */
		#wpadminbar #wp-admin-bar-usm-brand > .ab-item {
			font-weight: 700 !important;
			font-size: 14px !important;
			letter-spacing: 0.5px;
		}

		/* ── Custom footer ── */
		#wpfooter { border-top: 1px solid #e0e0e0; }
		#wpfooter p { color: #999; }

		<?php if ( $this->is_usm_restricted_user() && ! $this->is_wp_admin() ) : ?>
		/* ── Hide WP clutter for USM roles ── */
		#wpadminbar #wp-admin-bar-wp-logo,
		#wpadminbar #wp-admin-bar-site-name,
		#wpadminbar #wp-admin-bar-comments,
		#wpadminbar #wp-admin-bar-new-content,
		#wpadminbar #wp-admin-bar-updates { display: none !important; }

		/* Cleaner admin bar */
		#wpadminbar { background: #1d2327 !important; }

		/* Hide WP update nag */
		.update-nag, .notice:not(.usm-notice) { display: none !important; }

		/* Hide screen options & help */
		#screen-meta-links { display: none !important; }
		<?php endif; ?>

		<?php if ( $is_usm_page ) : ?>
		/* ── USM Page Typography ── */
		@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
		.usm-wrap, .usm-wrap * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
		.usm-wrap h1 { font-weight: 700; letter-spacing: -0.3px; }
		.usm-wrap h2 { font-weight: 600; }

		/* Smoother tables */
		.usm-table { border-radius: 8px; overflow: hidden; }
		.usm-table th { text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
		<?php endif; ?>
		</style>
		<?php
	}

	/**
	 * Custom footer text.
	 */
	public function custom_footer( $text ) {
		$settings = get_option( 'usm_settings', array() );
		$center = $settings['center_name'] ?? 'USM';
		return '<span style="color:#999;">⚡ Powered by <strong>USM</strong> — ' . esc_html( $center ) . '</span>';
	}

	/**
	 * Custom right footer (version info).
	 */
	public function custom_footer_right( $text ) {
		if ( $this->is_usm_restricted_user() && ! $this->is_wp_admin() ) {
			return '<span style="color:#bbb;">USM v' . esc_html( USM_VERSION ) . '</span>';
		}
		return $text;
	}

	/**
	 * Redirect on login based on role.
	 */
	public function role_based_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( is_wp_error( $user ) || ! is_object( $user ) || ! isset( $user->roles ) ) {
			return $redirect_to;
		}

		if ( in_array( 'usm_coach', (array) $user->roles, true ) ) {
			return admin_url( 'admin.php?page=usm-checkin' );
		}

		if ( in_array( 'usm_parent', (array) $user->roles, true ) ) {
			return admin_url( 'admin.php?page=usm-parent-dashboard' );
		}

		if ( in_array( 'usm_admin', (array) $user->roles, true ) ) {
			return admin_url( 'admin.php?page=usm-dashboard' );
		}

		// WP Administrators with USM.
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return admin_url( 'admin.php?page=usm-dashboard' );
		}

		return $redirect_to;
	}

	/**
	 * Redirect /wp-admin/ (index.php) to USM dashboard for USM users.
	 */
	public function redirect_dashboard() {
		global $pagenow;

		if ( 'index.php' !== $pagenow ) {
			return;
		}

		// Don't redirect if explicitly requesting index.php with action.
		if ( isset( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$user = wp_get_current_user();

		if ( in_array( 'usm_coach', (array) $user->roles, true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=usm-checkin' ) );
			exit;
		}

		if ( in_array( 'usm_parent', (array) $user->roles, true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=usm-parent-dashboard' ) );
			exit;
		}
	}

	/**
	 * Hide admin bar on frontend for parents.
	 */
	public function maybe_hide_admin_bar( $show ) {
		if ( $this->is_parent() ) {
			return false;
		}
		return $show;
	}

	/**
	 * Suppress all non-USM admin notices for restricted users.
	 */
	public function suppress_notices_for_non_admins() {
		if ( ! $this->is_wp_admin() && $this->is_usm_restricted_user() ) {
			remove_all_actions( 'admin_notices' );
			// Re-add USM notices.
			add_action( 'admin_notices', array( 'USM_Helpers', 'render_admin_notices' ) );
		}
	}

	/**
	 * Custom login page CSS.
	 */
	public function custom_login_css() {
		$settings = get_option( 'usm_settings', array() );
		$center = $settings['center_name'] ?? 'USM Sports Manager';
		?>
		<style>
		body.login {
			background: linear-gradient(135deg, #1d2327 0%, #2271b1 100%) !important;
		}
		#login h1 a {
			background-image: none !important;
			font-size: 28px !important;
			font-weight: 700 !important;
			color: #fff !important;
			width: auto !important;
			height: auto !important;
			text-indent: 0 !important;
			font-family: 'Inter', -apple-system, sans-serif;
		}
		#login h1 a::after {
			content: '🏋️';
			font-size: 40px;
			display: block;
			margin-bottom: 8px;
		}
		#loginform {
			border-radius: 12px !important;
			border: none !important;
			box-shadow: 0 4px 24px rgba(0,0,0,0.15) !important;
		}
		#loginform .button-primary {
			background: #2271b1 !important;
			border-color: #135e96 !important;
			border-radius: 6px !important;
			font-weight: 600 !important;
		}
		.login #backtoblog, .login #nav { text-align: center; }
		.login #backtoblog a, .login #nav a { color: rgba(255,255,255,0.7) !important; }
		.login #backtoblog a:hover, .login #nav a:hover { color: #fff !important; }
		#login { padding-top: 8% !important; }
		</style>
		<?php
	}

	/**
	 * Login logo URL.
	 */
	public function login_logo_url() {
		return home_url();
	}

	/**
	 * Login logo text.
	 */
	public function login_logo_text() {
		$settings = get_option( 'usm_settings', array() );
		return $settings['center_name'] ?? 'USM Sports Manager';
	}
}
