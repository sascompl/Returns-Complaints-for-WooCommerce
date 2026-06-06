<?php
/**
 * Plugin Name:       Returns & Complaints for WooCommerce
 * Plugin URI:        https://sascom.pl/
 * Description:       Lightweight WooCommerce plugin that adds a public return, withdrawal and complaint request form, including support for guest customers.
 * Version:           1.0.0
 * Author:            Sascom - Bartosz Sudół
 * Author URI:        https://sascom.pl/
 * Text Domain:       returns-complaints-for-woocommerce
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.0
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Copyright (c) 2026 Sascom - Bartosz Sudół
 *
 * @package Sascom_RC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Brak bezpośredniego dostępu.
}

define( 'SASCOM_RC_VERSION', '1.0.0' );
define( 'SASCOM_RC_FILE', __FILE__ );
define( 'SASCOM_RC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SASCOM_RC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Deklaracja zgodności z HPOS (High-Performance Order Storage) WooCommerce.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				SASCOM_RC_FILE,
				true
			);
		}
	}
);

/**
 * Bootstrap wtyczki – uruchamiany po załadowaniu wszystkich wtyczek.
 */
add_action(
	'plugins_loaded',
	function () {
		// Wtyczka wymaga aktywnego WooCommerce.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Wtyczka „Returns & Complaints for WooCommerce” wymaga aktywnej wtyczki WooCommerce.', 'returns-complaints-for-woocommerce' );
					echo '</p></div>';
				}
			);
			return;
		}

		require_once SASCOM_RC_PATH . 'includes/class-sascom-rc-cpt.php';
		require_once SASCOM_RC_PATH . 'includes/class-sascom-rc-rate-limiter.php';
		require_once SASCOM_RC_PATH . 'includes/class-sascom-rc-emails.php';
		require_once SASCOM_RC_PATH . 'includes/class-sascom-rc-form.php';
		require_once SASCOM_RC_PATH . 'includes/class-sascom-rc-admin.php';
		require_once SASCOM_RC_PATH . 'includes/class-sascom-rc-settings.php';
		require_once SASCOM_RC_PATH . 'includes/class-sascom-rc.php';

		Sascom_RC::instance()->init();
	}
);

/**
 * Aktywacja – rejestracja CPT i odświeżenie reguł przepisywania.
 */
register_activation_hook(
	__FILE__,
	function () {
		require_once SASCOM_RC_PATH . 'includes/class-sascom-rc-cpt.php';
		Sascom_RC_CPT::register_post_type();
		flush_rewrite_rules();
	}
);

/**
 * Dezaktywacja – odświeżenie reguł przepisywania.
 */
register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);
