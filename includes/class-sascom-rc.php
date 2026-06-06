<?php
/**
 * Główna klasa wtyczki – spina komponenty w całość.
 *
 * @package Sascom_RC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasa główna (singleton).
 */
final class Sascom_RC {

	/**
	 * Instancja singletona.
	 *
	 * @var Sascom_RC|null
	 */
	private static $instance = null;

	/**
	 * Pobranie instancji.
	 *
	 * @return Sascom_RC
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Inicjalizacja komponentów.
	 */
	public function init() {
		( new Sascom_RC_CPT() )->hooks();
		( new Sascom_RC_Form() )->hooks();
		( new Sascom_RC_Admin() )->hooks();
	}
}
